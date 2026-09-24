<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPackage extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_featured' => 'boolean',
            'price_amount' => 'decimal:2',
            'has_breakfast' => 'boolean',
            'pet_friendly' => 'boolean',
            'has_transportation' => 'boolean',
            'number_of_pax' => 'integer',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
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
