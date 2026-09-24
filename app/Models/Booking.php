<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Booking extends Model
{
    use HasFactory;

    /**
     * Characters used in a booking reference. I, O, 0 and 1 are omitted because
     * these codes get read aloud over the phone and written down by hand.
     */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * How many characters of randomness a reference carries.
     */
    private const REFERENCE_LENGTH = 8;

    protected $fillable = [
        'booking_reference',
        'user_id',
        'travel_package_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'travel_date',
        'number_of_passengers',
        'special_requests',
        'amount_due',
        'currency',
        'status',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'number_of_passengers' => 'integer',
            'amount_due' => 'decimal:2',
        ];
    }

    /**
     * The amount owed, rendered for display.
     */
    public function getFormattedAmountAttribute(): ?string
    {
        if ($this->amount_due === null) {
            return null;
        }

        return TravelPackage::renderPrice((float) $this->amount_due, (string) ($this->currency ?: 'PHP'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class);
    }

    /**
     * Generate a unique AMEGA booking reference code.
     *
     * The previous version took the tail of uniqid(), which yielded roughly a
     * million values correlated to the clock. Because GET /bookings/{reference}
     * is public and renders the customer's name, email and phone, that made the
     * confirmation pages enumerable. This draws from a cryptographic source and
     * retries on the rare collision.
     */
    public static function generateReference(): string
    {
        $year = date('Y');
        $max = strlen(self::REFERENCE_ALPHABET) - 1;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';

            for ($i = 0; $i < self::REFERENCE_LENGTH; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, $max)];
            }

            $reference = "AMG-{$year}-{$code}";

            if (! static::where('booking_reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique booking reference after 10 attempts.');
    }
}
