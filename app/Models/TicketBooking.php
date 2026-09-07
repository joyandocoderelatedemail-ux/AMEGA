<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'estimated_fare',
        'taxes_amount',
        'visa_assistance_fee',
        'insurance_fee',
        'other_charges',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'total_passengers' => 'integer',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'infants_count' => 'integer',
            'travel_tax_included' => 'boolean',
            'has_insurance' => 'boolean',
            'multi_city_segments' => 'array',
            'selected_services' => 'array',
            'special_requests_list' => 'array',
            'total_amount' => 'decimal:2',
            'estimated_fare' => 'decimal:2',
            'taxes_amount' => 'decimal:2',
            'visa_assistance_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'other_charges' => 'decimal:2',
        ];
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

    public function bookingAgreement(): HasOne
    {
        return $this->hasOne(BookingAgreement::class, 'ticket_booking_id');
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
