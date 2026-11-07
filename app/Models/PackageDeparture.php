<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageDeparture extends Model
{
    protected $fillable = ['travel_package_id', 'departure_date', 'return_date', 'slots'];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
            'slots' => 'integer',
        ];
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class);
    }

    /** Passengers already booked (not cancelled) on this departure. */
    public function bookedSlots(): int
    {
        return (int) Booking::where('travel_package_id', $this->travel_package_id)
            ->whereDate('travel_date', $this->departure_date)
            ->where('status', '!=', 'cancelled')
            ->sum('number_of_passengers');
    }

    public function slotsLeft(): int
    {
        return max(0, $this->slots - $this->bookedSlots());
    }
}
