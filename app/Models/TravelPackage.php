<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TravelPackage extends Model
{
    /** Whether the package price covers the flight. */
    public const AIRFARE_OPTIONS = [
        'included' => 'Airfare included',
        'not_included' => 'Airfare not included',
        'optional' => 'Airfare as an optional add-on',
    ];

    /** Ready-made durations offered as a dropdown; anything else is typed in. */
    public const DURATIONS = [
        'Day Tour',
        '2 Days / 1 Night',
        '3 Days / 2 Nights',
        '4 Days / 3 Nights',
        '5 Days / 4 Nights',
        '6 Days / 5 Nights',
        '7 Days / 6 Nights',
        '8 Days / 7 Nights',
        '9 Days / 8 Nights',
        '10 Days / 9 Nights',
        '12 Days / 11 Nights',
        '14 Days / 13 Nights',
    ];

    /** Which destinations fit a package category. */
    public const CATEGORY_DESTINATION_TYPE = [
        'domestic' => 'domestic',
        'short_haul' => 'international',
        'long_haul' => 'international',
    ];

    /** The same values the ticket wizard's trip type uses. */
    public const TRIP_TYPES = [
        'round_trip' => 'Round Trip',
        'one_way' => 'One Way',
        'multi_city' => 'Multi-City',
    ];

    /** The same values the ticket wizard's preferred flight time uses. */
    public const FLIGHT_TIMES = [
        'anytime' => 'Anytime',
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
    ];

    /** The same values the ticket wizard's travel class uses. */
    public const CABIN_CLASSES = [
        'economy' => 'Economy',
        'premium_economy' => 'Premium Economy',
        'business' => 'Business',
        'first_class' => 'First Class',
    ];

    use HasFactory;

    protected $fillable = [
        'destination_id',
        'title',
        'duration',
        'price',
        'price_amount',
        'price_currency',
        'rating',
        'image',
        'description',
        'inclusions',
        'exclusions',
        'itinerary',
        'available_dates',
        'category',
        'status',
        'is_featured',
        'package_type',
        'hotel_name',
        'preferred_hotel',
        'has_breakfast',
        'bed_config',
        'check_in_date',
        'check_out_date',
        'smoking_preference',
        'pet_friendly',
        'has_transportation',
        'transportation_type',
        'number_of_pax',
        'special_requests',
        'airfare_inclusion',
        'airfare_amount',
        'remarks',
        'trip_type',
        'multi_city_segments',
        'preferred_flight_time',
        'flight_destination',
        'departure_date',
        'return_date',
        'airline_id',
        'origin_airport',
        'cabin_class',
        'baggage_allowance',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_featured' => 'boolean',
            'price_amount' => 'decimal:2',
            'airfare_amount' => 'decimal:2',
            'has_breakfast' => 'boolean',
            'pet_friendly' => 'boolean',
            'has_transportation' => 'boolean',
            'number_of_pax' => 'integer',
            'check_in_date' => 'date:Y-m-d',
            'check_out_date' => 'date:Y-m-d',
            'multi_city_segments' => 'array',
            // Plain Y-m-d in JSON, so the ticket wizard gets the same calendar day in any timezone.
            'departure_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Keep the numeric amount and the display string in step.
     *
     * `price` is the label rendered across the public site; `price_amount` is
     * what everything else computes from. Whichever side is written, the other
     * is rebuilt, so the two can never drift apart.
     */
    protected static function booted(): void
    {
        static::saving(function (self $package): void {
            $package->syncPriceColumns();
        });
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    /**
     * Validation for the flight terms, shared by every form that saves a package.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function flightRules(): array
    {
        return [
            'airfare_inclusion' => ['nullable', 'string', Rule::in(array_keys(self::AIRFARE_OPTIONS))],
            'airline_id' => ['nullable', 'integer', 'exists:airlines,id'],
            'origin_airport' => ['nullable', 'string', 'max:255'],
            'cabin_class' => ['nullable', 'string', Rule::in(array_keys(self::CABIN_CLASSES))],
            'baggage_allowance' => ['nullable', 'string', 'max:255'],
            'trip_type' => ['nullable', 'string', Rule::in(array_keys(self::TRIP_TYPES))],
            'multi_city_segments' => ['nullable', 'array', 'max:10'],
            'multi_city_segments.*.from' => ['nullable', 'string', 'max:100'],
            'multi_city_segments.*.to' => ['nullable', 'string', 'max:100'],
            'preferred_flight_time' => ['nullable', 'string', Rule::in(array_keys(self::FLIGHT_TIMES))],
            'flight_destination' => ['nullable', 'string', 'max:255'],
            'departure_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date', 'after:departure_date'],
        ];
    }

    /**
     * Tidy the validated flight terms before saving: route legs are kept only
     * for a multi-city package, blank legs are dropped, and a multi-city
     * package must keep at least two legs.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function normalizeFlightTerms(array $validated): array
    {
        if (! array_key_exists('trip_type', $validated) && ! array_key_exists('multi_city_segments', $validated)) {
            return $validated;
        }

        // Only a round trip comes back; a set trip type of another kind drops the return date.
        if (! empty($validated['trip_type']) && $validated['trip_type'] !== 'round_trip' && array_key_exists('return_date', $validated)) {
            $validated['return_date'] = null;
        }

        if (($validated['trip_type'] ?? null) !== 'multi_city') {
            $validated['multi_city_segments'] = null;

            return $validated;
        }

        $segments = collect($validated['multi_city_segments'] ?? [])
            ->map(fn ($segment) => [
                'from' => trim((string) ($segment['from'] ?? '')),
                'to' => trim((string) ($segment['to'] ?? '')),
            ])
            ->filter(fn ($segment) => $segment['from'] !== '' || $segment['to'] !== '')
            ->values();

        if ($segments->count() < 2) {
            throw ValidationException::withMessages([
                'multi_city_segments' => 'A multi-city package needs at least two route legs.',
            ]);
        }

        $validated['multi_city_segments'] = $segments->all();

        return $validated;
    }

    /**
     * The price as it should be shown to a customer.
     */
    public function getFormattedPriceAttribute(): ?string
    {
        if (filled($this->price_amount)) {
            return self::renderPrice((float) $this->price_amount, (string) ($this->price_currency ?: 'PHP'));
        }

        return $this->price;
    }

    /**
     * Whether this package has a price a booking can be billed from.
     */
    public function hasNumericPrice(): bool
    {
        return filled($this->price_amount) && (float) $this->price_amount > 0;
    }

    /**
     * Pull a numeric amount and a currency code out of a display string.
     *
     * @return array{0: float|null, 1: string}
     */
    public static function parsePrice(string $value): array
    {
        $upper = strtoupper($value);

        $currency = (str_contains($value, '$') || str_contains($upper, 'USD')) ? 'USD' : 'PHP';

        $digits = preg_replace('/[^0-9.]/', '', $value);

        if ($digits === null || $digits === '' || ! is_numeric($digits)) {
            return [null, $currency];
        }

        return [(float) $digits, $currency];
    }

    /**
     * Render a numeric amount back into a display string.
     */
    public static function renderPrice(float $amount, string $currency = 'PHP'): string
    {
        $symbol = $currency === 'USD' ? '$' : '₱';

        return $symbol.number_format($amount, 0);
    }

    /**
     * Reconcile the two price representations after an attribute is set.
     */
    protected function syncPriceColumns(): void
    {
        // A supplied numeric amount is the source of truth; rebuild the label.
        if ($this->isDirty('price_amount') && filled($this->price_amount)) {
            $this->attributes['price'] = self::renderPrice(
                (float) $this->price_amount,
                (string) ($this->price_currency ?: 'PHP')
            );

            return;
        }

        // A supplied display string is the legacy path; derive the amount.
        if ($this->isDirty('price') && filled($this->attributes['price'] ?? null)) {
            [$amount, $currency] = self::parsePrice((string) $this->attributes['price']);

            if ($amount !== null) {
                $this->attributes['price_amount'] = $amount;
                $this->attributes['price_currency'] = $currency;
            }

            return;
        }

        // Only the currency moved; re-render the label at the same amount.
        if ($this->isDirty('price_currency') && filled($this->price_amount)) {
            $this->attributes['price'] = self::renderPrice(
                (float) $this->price_amount,
                (string) $this->price_currency
            );
        }
    }
}
