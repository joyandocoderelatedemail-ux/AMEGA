<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPassenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_booking_id',
        'passenger_number',
        'passenger_type',
        'nationality_type',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'gender',
        'date_of_birth',
        'passport_number',
        'passport_expiry_date',
        'passport_country',
        'government_id_type',
        'government_id_number',
        'visa_type',
        'stay_duration_months',
        'requires_exit_clearance',
        'travel_tax_included',
        // Phase 2 International fields
        'visa_status',
        'visa_assistance_type',
        'intended_stay_days',
        'purpose_of_travel',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_expiry_date' => 'date',
            'passenger_number' => 'integer',
            'stay_duration_months' => 'integer',
            'intended_stay_days' => 'integer',
            'requires_exit_clearance' => 'boolean',
            'travel_tax_included' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TicketBooking::class, 'ticket_booking_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TicketPassengerDocument::class, 'ticket_passenger_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])));
    }

    public function isFilipino(): bool
    {
        return $this->nationality_type === 'filipino';
    }

    public function isForeignNational(): bool
    {
        return $this->nationality_type === 'foreign_national';
    }
}
