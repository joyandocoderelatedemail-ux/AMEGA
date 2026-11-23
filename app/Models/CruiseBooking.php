<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cruise booked for a client at the Cruise Services desk, worked step by step (STAGES). The agent
 * can only continue past a step once everything it needs is in (stageBlocker): Check Availability
 * needs the slot confirmed before the quotation is prepared.
 */
#[ScopedBy([OwnFilesScope::class])]
class CruiseBooking extends Model
{
    /** The steps, in order. More are added as the cruise flow is built. */
    public const STAGES = [
        'availability' => 'Check Availability',
        'quotation' => 'Prepare the Quotation',
    ];

    /** The quotation's cost lines, besides the add-ons. */
    public const COST_LINES = [
        'base_fare' => 'Base fare',
        'tipping' => 'Tipping',
        'insurance' => 'Insurance',
        'travel_taxes' => 'Travel taxes',
    ];

    /** Currency => the sign printed before an amount. Cruises are often priced in US dollars. */
    public const CURRENCIES = [
        'PHP' => '₱',
        'USD' => 'US$',
    ];

    /**
     * The Destination dropdown: the cruise routes AMEGA sells (from the cruise packages on the
     * website). A destination not listed is typed in under "Other".
     */
    public const DESTINATIONS = [
        'Hong Kong, Japan and Taiwan',
        'Manila to Miyakojima, Japan',
        'Singapore',
        'Singapore, Penang and Phuket',
        'Taiwan and Japan',
        'Taiwan, Korea and Japan',
    ];

    /**
     * The cruise lines for the Preferred cruise line dropdown, each with the ships AMEGA sells on it
     * (from the cruise packages on the website). A line or ship not listed is typed in under "Other".
     */
    public const LINES = [
        'Adora Cruises' => ['Adora Mediterranea'],
        'Costa Cruises' => [],
        'Disney Cruise Line' => ['Disney Adventure'],
        'MSC Cruises' => ['MSC Bellissima'],
        'Norwegian Cruise Line' => [],
        'Royal Caribbean International' => ['Navigator of the Seas', 'Spectrum of the Seas'],
        'Star Cruises' => ['Star Navigator'],
    ];

    protected $fillable = [
        'reference', 'created_by', 'user_id', 'status', 'client_name', 'contact_number',
        'destination', 'departure_date', 'return_date', 'adults', 'children', 'infants', 'cruise_line', 'ship',
        'currency', 'base_fare', 'tipping', 'insurance', 'travel_taxes', 'add_ons', 'itinerary',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'infants' => 'integer',
            'slot_confirmed_at' => 'datetime',
            'base_fare' => 'decimal:2',
            'tipping' => 'decimal:2',
            'insurance' => 'decimal:2',
            'travel_taxes' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'add_ons' => 'array',
            'itinerary' => 'array',
            'quotation_at' => 'datetime',
        ];
    }

    /** The desk whose "view_all" permission lifts OwnFilesScope. */
    public static function ownFilesDesk(): string
    {
        return 'cruise_services';
    }

    public static function generateReference(): string
    {
        return 'CR-'.date('Y').'-'.strtoupper(substr(uniqid(), -5));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function slotConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'slot_confirmed_by');
    }

    public function stageIndex(): int
    {
        $index = array_search($this->status, array_keys(self::STAGES), true);

        return $index === false ? 0 : $index;
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function nextStage(): ?string
    {
        return array_keys(self::STAGES)[$this->stageIndex() + 1] ?? null;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** "2 adults, 1 child, 1 infant", or null before the adults are entered. */
    public function guestsLabel(): ?string
    {
        if ((int) $this->adults < 1) {
            return null;
        }

        $count = fn (int $n, string $one, string $many) => $n > 0 ? $n.' '.($n === 1 ? $one : $many) : null;

        return collect([
            $count((int) $this->adults, 'adult', 'adults'),
            $count((int) $this->children, 'child', 'children'),
            $count((int) $this->infants, 'infant', 'infants'),
        ])->filter()->implode(', ');
    }

    public function quotationBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quotation_by');
    }

    /** Whether the booking has reached this step (or gone past it). */
    public function hasReached(string $stage): bool
    {
        $index = array_search($stage, array_keys(self::STAGES), true);

        return $index !== false && $this->stageIndex() >= $index;
    }

    public function money(mixed $amount): string
    {
        return (self::CURRENCIES[$this->currency] ?? '₱').number_format((float) $amount, 2);
    }

    /** The cost lines and add-ons with an amount, label => amount, as the quotation prints them. */
    public function costLines(): array
    {
        $lines = collect(self::COST_LINES)
            ->mapWithKeys(fn (string $label, string $field) => [$label => (float) $this->{$field}])
            ->filter(fn (float $amount) => $amount > 0);

        foreach ($this->add_ons ?? [] as $addOn) {
            $lines->put('Add-on: '.$addOn['description'], (float) $addOn['amount']);
        }

        return $lines->all();
    }

    /** The total package cost: the cost lines plus the add-ons. */
    public function computedTotal(): float
    {
        return round(
            collect(array_keys(self::COST_LINES))->sum(fn (string $field) => (float) $this->{$field})
            + collect($this->add_ons ?? [])->sum(fn (array $addOn) => (float) $addOn['amount']),
            2,
        );
    }

    /**
     * The day-by-day itinerary for the form: what is saved, or else one empty day for each day of
     * the sailing (from the sailing date to the return date).
     *
     * @return list<array{day: int, date: ?string, description: string}>
     */
    public function itineraryRows(): array
    {
        if (! empty($this->itinerary)) {
            return array_values($this->itinerary);
        }

        if (! $this->departure_date || ! $this->return_date || $this->return_date->lt($this->departure_date)) {
            return [['day' => 1, 'date' => $this->departure_date?->toDateString(), 'description' => '']];
        }

        $days = min(60, (int) $this->departure_date->diffInDays($this->return_date) + 1);

        return collect(range(0, $days - 1))->map(fn (int $i) => [
            'day' => $i + 1,
            'date' => $this->departure_date->copy()->addDays($i)->toDateString(),
            'description' => '',
        ])->all();
    }

    public function hasQuotation(): bool
    {
        return $this->quotation_at !== null;
    }

    public function isSlotConfirmed(): bool
    {
        return $this->slot_confirmed_at !== null;
    }

    /** "Royal Caribbean · Navigator of the Seas", or whichever of them is known. */
    public function cruiseLabel(): ?string
    {
        return collect([$this->cruise_line, $this->ship])->filter()->implode(' · ') ?: null;
    }

    public function sailingLabel(): ?string
    {
        if (! $this->departure_date) {
            return null;
        }

        return $this->departure_date->format('M j, Y').($this->return_date ? ' – '.$this->return_date->format('M j, Y') : '');
    }

    /** Why the booking cannot continue past its current step, or null when it can. */
    public function stageBlocker(): ?string
    {
        return $this->blockerFor((string) $this->status);
    }

    /** Why the booking cannot continue past this step, or null when it can. */
    public function blockerFor(string $stage): ?string
    {
        return match ($stage) {
            'availability' => match (true) {
                blank($this->destination) => 'Enter the destination.',
                $this->departure_date === null || $this->return_date === null => 'Enter the sailing and return dates.',
                (int) $this->adults < 1 => 'Enter the number of adults.',
                ! $this->isSlotConfirmed() => 'Confirm the slot before continuing.',
                default => null,
            },
            'quotation' => (float) $this->base_fare <= 0 ? 'Enter the base fare.' : null,
            default => null,
        };
    }
}
