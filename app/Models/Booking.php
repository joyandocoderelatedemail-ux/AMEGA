<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

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
        'total_amount',
        'status',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'number_of_passengers' => 'integer',
        ];
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
     */
    public static function generateReference(): string
    {
        return 'AMG-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
    }
}
