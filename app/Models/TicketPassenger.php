<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPassenger extends Model
{
    use HasFactory;

    /**
     * Fare categories by age on the travel date: infants are under 2,
     * children 2 to 11, adults 12 and over.
     */
    public const CHILD_MIN_AGE = 2;

    public const ADULT_MIN_AGE = 12;

    /**
     * The fare category a traveller born on $dateOfBirth falls into on $travelDate.
     */
    public static function typeForAge(Carbon $dateOfBirth, Carbon $travelDate): string
    {
        $age = (int) $dateOfBirth->diffInYears($travelDate);

        return match (true) {
            $age >= self::ADULT_MIN_AGE => 'adult',
            $age >= self::CHILD_MIN_AGE => 'child',
            default => 'infant',
        };
    }

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

    /**
     * The documents this passenger still has to hand in before the ticket can
     * be issued, as stored document type => what to ask for.
     *
     * What is required follows the trip and the traveller: a passport for
     * international travel and foreign nationals, a government ID for Filipino
     * adults on domestic flights, a birth certificate (or school ID) for
     * infants and children, and the visa papers the visa choice calls for.
     *
     * @return array<string, string>
     */
    public function missingDocuments(): array
    {
        $held = ($this->relationLoaded('documents') ? $this->documents : $this->documents()->get())
            ->pluck('document_type')
            ->all();
        $has = fn (string ...$types): bool => array_intersect($types, $held) !== [];

        $international = $this->booking?->travel_type === 'international';
        $missing = [];

        if (($international || $this->isForeignNational()) && ! $has('passport_scan')) {
            $missing['passport_scan'] = 'Passport scan';
        }

        if ($international) {
            if ($this->visa_status === 'already_has_visa' && ! $has('visa_scan')) {
                $missing['visa_scan'] = 'Visa copy';
            }

            if ($this->visa_status === 'needs_assistance') {
                if (! $has('passport_photo')) {
                    $missing['passport_photo'] = 'Passport photo (2x2)';
                }

                if (! $has('supporting_documents')) {
                    $missing['supporting_documents'] = 'Supporting documents (COE / bank certificate)';
                }
            }

            return $missing;
        }

        if ($this->isFilipino() && $this->passenger_type === 'adult' && ! $has('government_id')) {
            $missing['government_id'] = 'Government ID';
        }

        if ($this->passenger_type === 'infant' && ! $has('birth_certificate')) {
            $missing['birth_certificate'] = 'Birth certificate';
        }

        if ($this->passenger_type === 'child' && ! $has('school_id', 'birth_certificate')) {
            $missing['school_id'] = 'School ID or birth certificate';
        }

        if ($this->isForeignNational()) {
            if (in_array($this->visa_type, ['e_visa', 'regular_visa'], true) && ! $has('visa_scan')) {
                $missing['visa_scan'] = $this->visa_type === 'e_visa' ? 'e-Visa copy' : 'Regular visa (R-Visa) copy';
            }

            if ((int) $this->stay_duration_months > 6 && ! $has('exit_clearance')) {
                $missing['exit_clearance'] = 'Emigration Exit Clearance (ECC) certificate';
            }
        }

        return $missing;
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
