<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[ScopedBy([OwnFilesScope::class])]
class TicketBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference',
        'created_by',
        'user_id',
        'travel_type',
        'package_type',
        'travel_package_id',
        'package_name',
        'origin',
        'destination',
        'trip_type',
        'departure_date',
        'return_date',
        'total_passengers',
        'adults_count',
        'children_count',
        'infants_count',
        'contact_name',
        'contact_email',
        'contact_phone',
        'travel_tax_included',
        'total_amount',
        'status',
        'special_requests',
        // Phase 2 International fields
        'destination_country',
        'destination_city',
        'arrival_airport',
        'preferred_airline',
        // The flight actually booked, copied from the airline's site
        'airline_id',
        'airline_pnr',
        'flight_number',
        'departure_time',
        'arrival_time',
        'return_flight_number',
        'return_departure_time',
        'return_arrival_time',
        'travel_class',
        'preferred_flight_time',
        'multi_city_segments',
        'has_insurance',
        'insurance_plan',
        'selected_services',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_email',
        'special_requests_list',
        'airline_restrictions',
        'estimated_fare',
        'taxes_amount',
        'visa_assistance_fee',
        'insurance_fee',
        'other_charges',
        // Payment and issuance
        'payment_status',
        'amount_paid',
        'paid_at',
        'issued_at',
        'issued_by',
        'consent_accepted_at',
        'consent_accepted_by',
        'is_quotation',
        'custom_package_specs',
    ];

    /** Money received against the booking. */
    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partially_paid';

    public const PAYMENT_FULL = 'fully_paid';

    /**
     * Mirror the database defaults so a freshly created model reports the same
     * values in memory as it would after a refresh — otherwise `payment_status`
     * reads as null until the row is re-fetched.
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'payment_status' => self::PAYMENT_UNPAID,
        'amount_paid' => 0,
        'is_quotation' => false,
    ];

    /** Booking lifecycle, independent of payment. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'paid_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'consent_accepted_at' => 'datetime',
            'is_quotation' => 'boolean',
            'amount_paid' => 'decimal:2',
            'total_passengers' => 'integer',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'infants_count' => 'integer',
            'travel_tax_included' => 'boolean',
            'has_insurance' => 'boolean',
            'multi_city_segments' => 'array',
            'selected_services' => 'array',
            'special_requests_list' => 'array',
            'airline_restrictions' => 'array',
            'custom_package_specs' => 'array',
            'total_amount' => 'decimal:2',
            'estimated_fare' => 'decimal:2',
            'taxes_amount' => 'decimal:2',
            'visa_assistance_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'other_charges' => 'decimal:2',
        ];
    }

    public function isCustomPackage(): bool
    {
        return $this->package_type === 'custom_package' || ! empty($this->custom_package_specs);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TicketPassenger::class)->orderBy('passenger_number');
    }

    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(TicketPassengerDocument::class, TicketPassenger::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class);
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    /**
     * Whether staff recorded any detail of the flight they booked.
     */
    public function hasBookedFlight(): bool
    {
        return $this->airline_id !== null
            || filled($this->airline_pnr)
            || filled($this->flight_number)
            || filled($this->return_flight_number);
    }

    /**
     * The flight lines for client emails: the booked airline and flights when
     * staff recorded them, otherwise the airline the client asked for.
     *
     * @return list<string>
     */
    public function flightEmailLines(): array
    {
        $airline = $this->airline?->label() ?? $this->preferred_airline;

        $leg = function (string $label, ?string $number, ?string $departs, ?string $arrives): ?string {
            if (blank($number)) {
                return null;
            }

            $times = collect([self::formatFlightTime($departs), self::formatFlightTime($arrives)])->filter()->implode(' – ');

            return "**{$label}:** {$number}".($times !== '' ? " ({$times})" : '');
        };

        return array_values(array_filter([
            filled($airline) ? "**Airline:** {$airline}" : null,
            filled($this->airline_pnr) ? "**Booking code:** {$this->airline_pnr}" : null,
            $leg('Flight', $this->flight_number, $this->departure_time, $this->arrival_time),
            $leg('Return flight', $this->return_flight_number, $this->return_departure_time, $this->return_arrival_time),
        ]));
    }

    /**
     * Each flight on the trip, in order: the outbound leg, then the return on a
     * round trip, or every segment of a multi-city trip.
     *
     * @return list<array{from: string, to: string, date: ?Carbon, flight: ?string, departs: ?string, arrives: ?string}>
     */
    public function itineraryLegs(): array
    {
        $segments = collect($this->multi_city_segments ?? [])
            ->filter(fn ($segment): bool => filled($segment['from'] ?? null) && filled($segment['to'] ?? null));

        if ($this->trip_type === 'multi_city' && $segments->isNotEmpty()) {
            return $segments->values()->map(fn (array $segment, int $index): array => [
                'from' => $segment['from'],
                'to' => $segment['to'],
                'date' => filled($segment['date'] ?? null) ? Carbon::parse($segment['date']) : null,
                // Only one flight number is recorded, and it belongs to the first leg.
                'flight' => $index === 0 ? $this->flight_number : null,
                'departs' => $index === 0 ? self::formatFlightTime($this->departure_time) : null,
                'arrives' => $index === 0 ? self::formatFlightTime($this->arrival_time) : null,
            ])->all();
        }

        $legs = [[
            'from' => (string) $this->origin,
            'to' => (string) $this->destination,
            'date' => $this->departure_date,
            'flight' => $this->flight_number,
            'departs' => self::formatFlightTime($this->departure_time),
            'arrives' => self::formatFlightTime($this->arrival_time),
        ]];

        if ($this->trip_type === 'round_trip' && $this->return_date) {
            $legs[] = [
                'from' => (string) $this->destination,
                'to' => (string) $this->origin,
                'date' => $this->return_date,
                'flight' => $this->return_flight_number,
                'departs' => self::formatFlightTime($this->return_departure_time),
                'arrives' => self::formatFlightTime($this->return_arrival_time),
            ];
        }

        return $legs;
    }

    /**
     * A stored flight time ("14:05:00") as the desk reads it ("2:05 PM").
     */
    public static function formatFlightTime(?string $time): ?string
    {
        return filled($time) ? Carbon::parse($time)->format('g:i A') : null;
    }

    public function bookingAgreement(): HasOne
    {
        return $this->hasOne(BookingAgreement::class, 'ticket_booking_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function consentAcceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_accepted_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Every payment received and refund given, oldest first.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TicketPayment::class)->orderBy('received_at')->orderBy('id');
    }

    /**
     * What is still owed on this booking.
     */
    public function balanceDue(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->amount_paid, 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_FULL;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * A quotation was saved without the document checks, so it is priced but
     * not yet a complete booking.
     */
    public function isQuotation(): bool
    {
        return (bool) $this->is_quotation;
    }

    /**
     * Full payment is necessary but not sufficient — issuance stays a separate,
     * deliberate step so a paid booking is never silently turned into a ticket.
     */
    public function canBeIssued(): bool
    {
        return $this->isFullyPaid()
            && ! $this->isIssued()
            && ! $this->isCancelled()
            // A quotation skipped the document rules, so it must be completed
            // as a full booking before it can become a ticket.
            && ! $this->isQuotation()
            // A booking can be taken through to payment before the documents
            // arrive, but the ticket waits for every one of them.
            && $this->hasAllRequiredDocuments();
    }

    /**
     * The passengers who still owe documents, each with what is missing.
     *
     * @return Collection<int, array{passenger: TicketPassenger, missing: array<string, string>}>
     */
    public function missingDocuments(): Collection
    {
        return $this->passengers()->with('documents')->get()
            ->map(function (TicketPassenger $passenger): array {
                $passenger->setRelation('booking', $this);

                return ['passenger' => $passenger, 'missing' => $passenger->missingDocuments()];
            })
            ->filter(fn (array $row): bool => $row['missing'] !== [])
            ->values();
    }

    public function hasAllRequiredDocuments(): bool
    {
        return $this->missingDocuments()->isEmpty();
    }

    /**
     * Derive the payment status from the amount recorded against the total.
     *
     * A booking with no total on it yet cannot be considered settled, otherwise
     * a zero total would make every new booking instantly issuable.
     */
    public function recordPayment(float $amountPaid): void
    {
        $total = (float) $this->total_amount;
        $amountPaid = max(0, round($amountPaid, 2));

        $this->amount_paid = $amountPaid;

        if ($total > 0 && $amountPaid >= $total) {
            $this->payment_status = self::PAYMENT_FULL;
            $this->paid_at = $this->paid_at ?? now();
        } elseif ($amountPaid > 0) {
            $this->payment_status = self::PAYMENT_PARTIAL;
            $this->paid_at = null;
        } else {
            $this->payment_status = self::PAYMENT_UNPAID;
            $this->paid_at = null;
        }

        // Taking money confirms the booking, but never issues it.
        if ($this->status === self::STATUS_PENDING && $amountPaid > 0) {
            $this->status = self::STATUS_CONFIRMED;
        }

        $this->save();
    }

    /**
     * Record money received, adding it to the total already paid.
     *
     * @throws \DomainException when it is more than the outstanding balance
     */
    public function receivePayment(float $amount, string $method, User $by, ?string $reference = null, ?\Carbon\Carbon $receivedAt = null, ?string $note = null): TicketPayment
    {
        return $this->addLedgerEntry(TicketPayment::TYPE_PAYMENT, $amount, $method, $by, $reference, $receivedAt, $note);
    }

    /**
     * Record money handed back to the client for a cancelled booking.
     *
     * @throws \DomainException when the booking is not cancelled or the amount is more than was received
     */
    public function refundPayment(float $amount, string $method, User $by, ?string $reference = null, ?\Carbon\Carbon $receivedAt = null, ?string $note = null): TicketPayment
    {
        return $this->addLedgerEntry(TicketPayment::TYPE_REFUND, $amount, $method, $by, $reference, $receivedAt, $note);
    }

    /**
     * Add one entry and move the running total with it.
     *
     * The booking row is locked and re-read first, so two agents recording at
     * the same moment add to each other's figure instead of overwriting it, and
     * the limits are checked against the true total rather than a stale page.
     */
    private function addLedgerEntry(string $type, float $amount, string $method, User $by, ?string $reference, ?\Carbon\Carbon $receivedAt, ?string $note): TicketPayment
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($type, $amount, $method, $by, $reference, $receivedAt, $note): TicketPayment {
            $this->newQueryWithoutScopes()->whereKey($this->getKey())->lockForUpdate()->first();
            $this->refresh();

            $paid = (float) $this->amount_paid;
            $isRefund = $type === TicketPayment::TYPE_REFUND;

            if ($isRefund && ! $this->isCancelled()) {
                throw new \DomainException('Only a cancelled booking can be refunded.');
            }

            if ($isRefund && $amount - $paid > 0.004) {
                throw new \DomainException('The refund is more than the ₱'.number_format($paid, 2).' received.');
            }

            if (! $isRefund && $amount - $this->balanceDue() > 0.004) {
                throw new \DomainException('The amount is more than the outstanding balance of ₱'.number_format($this->balanceDue(), 2).'.');
            }

            $entry = $this->payments()->create([
                'type' => $type,
                'amount' => $amount,
                'method' => $method,
                'reference' => filled($reference) ? trim($reference) : null,
                'note' => filled($note) ? trim($note) : null,
                'received_at' => $receivedAt ?? now(),
                'received_by' => $by->id,
            ]);

            $this->recordPayment($isRefund ? $paid - $amount : $paid + $amount);

            return $entry;
        });
    }

    /**
     * Cancel the booking, keeping who did it and why. Money already received
     * is untouched: it is returned through a refund entry.
     */
    public function cancel(User $by, string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'cancellation_reason' => trim($reason),
        ])->save();
    }

    /**
     * Issue the ticket, recording who consented and who issued it.
     */
    public function markAsIssued(User $staff): void
    {
        $this->forceFill([
            'status' => self::STATUS_ISSUED,
            'issued_at' => now(),
            'issued_by' => $staff->id,
            'consent_accepted_at' => $this->consent_accepted_at ?? now(),
            'consent_accepted_by' => $this->consent_accepted_by ?? $staff->id,
        ])->save();
    }

    /**
     * Generate unique reference code for tickets.
     */
    public static function generateReference(string $type = 'DOM'): string
    {
        $prefix = strtoupper($type) === 'DOM' ? 'TKT-DOM' : 'TKT-INT';
        $timestamp = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        return "{$prefix}-{$timestamp}-{$random}";
    }
}
