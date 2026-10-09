<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change to a booked flight, kept with the schedule before and after so the
 * ticket carries its own history and the client can be told exactly what moved.
 */
class TicketFlightChange extends Model
{
    public const TYPE_DELAY = 'delay';

    public const TYPE_RESCHEDULE = 'reschedule';

    public const TYPE_AIRLINE_CANCELLED = 'airline_cancelled';

    public const TYPE_CLIENT_REQUEST = 'client_request';

    /** What happened, as staff pick it. */
    public const TYPES = [
        self::TYPE_DELAY => 'Flight delayed / time changed',
        self::TYPE_RESCHEDULE => 'Date or flight changed by the airline',
        self::TYPE_AIRLINE_CANCELLED => 'Flight cancelled by the airline',
        self::TYPE_CLIENT_REQUEST => 'Change requested by the client',
    ];

    /** The booking columns a flight change can move. */
    public const FIELDS = [
        'departure_date',
        'departure_time',
        'arrival_time',
        'flight_number',
        'return_date',
        'return_departure_time',
        'return_arrival_time',
        'return_flight_number',
    ];

    private const LABELS = [
        'departure_date' => 'Departure date',
        'departure_time' => 'Departure time',
        'arrival_time' => 'Arrival time',
        'flight_number' => 'Flight number',
        'return_date' => 'Return date',
        'return_departure_time' => 'Return departure time',
        'return_arrival_time' => 'Return arrival time',
        'return_flight_number' => 'Return flight number',
    ];

    protected $fillable = [
        'ticket_booking_id',
        'recorded_by',
        'type',
        'schedule_before',
        'schedule_after',
        'reason',
        'change_fee',
        'fee_added',
        'client_notified_at',
        'notified_via',
    ];

    protected function casts(): array
    {
        return [
            'schedule_before' => 'array',
            'schedule_after' => 'array',
            'change_fee' => 'decimal:2',
            'fee_added' => 'boolean',
            'client_notified_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TicketBooking::class, 'ticket_booking_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The flight fields of a booking as they stand now, as plain strings.
     *
     * @return array<string, ?string>
     */
    public static function snapshot(TicketBooking $ticket): array
    {
        $snapshot = [];

        foreach (self::FIELDS as $field) {
            $value = $ticket->{$field};
            $snapshot[$field] = match (true) {
                $value instanceof Carbon => $value->toDateString(),
                blank($value) => null,
                str_ends_with($field, '_time') => substr((string) $value, 0, 5),
                default => (string) $value,
            };
        }

        return $snapshot;
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function isAirlineCancellation(): bool
    {
        return $this->type === self::TYPE_AIRLINE_CANCELLED;
    }

    public function clientIsNotified(): bool
    {
        return $this->client_notified_at !== null;
    }

    /**
     * What moved, one row per field that differs: label, the old value and the new one.
     *
     * @return list<array{label: string, from: string, to: string}>
     */
    public function movements(): array
    {
        $rows = [];

        foreach (self::FIELDS as $field) {
            $from = $this->schedule_before[$field] ?? null;
            $to = $this->schedule_after[$field] ?? null;

            if ($from === $to) {
                continue;
            }

            $rows[] = [
                'label' => self::LABELS[$field],
                'from' => self::display($field, $from),
                'to' => self::display($field, $to),
            ];
        }

        return $rows;
    }

    /**
     * A stored value the way a client reads it: "Mon, Oct 12, 2026", "8:30 AM".
     */
    public static function display(string $field, ?string $value): string
    {
        if (blank($value)) {
            return 'not set';
        }

        return match (true) {
            str_ends_with($field, '_date') => Carbon::parse($value)->format('D, M j, Y'),
            str_ends_with($field, '_time') => Carbon::parse($value)->format('g:i A'),
            default => strtoupper($value),
        };
    }
}
