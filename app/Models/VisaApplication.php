<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A visa assistance counter file. Covers any of the three services the counter
 * runs: visit visa, e-Visa, and passporting.
 */
class VisaApplication extends Model
{
    use HasFactory;

    /** The three services at one counter. */
    public const SERVICE_TYPES = ['visit_visa', 'e_visa', 'passporting'];

    /** Visit visa only: the purpose decides the requirement list. */
    public const PURPOSES = ['tourist', 'business', 'family'];

    /** Visit visa only: rush adds a fee and shortens the embassy turnaround. */
    public const PROCESSING_SPEEDS = ['regular', 'rush'];

    /** Passporting only: two different jobs from here on. */
    public const PASSPORT_TYPES = ['foreign', 'local'];

    /** e-Visa only: the steps are identical, only the file size differs. */
    public const APPLICANT_TYPES = ['individual', 'group'];

    public const PAYMENT_TYPES = ['deposit', 'full'];

    /** Destinations where no embassy appearance is needed. */
    public const NO_APPEARANCE_COUNTRIES = ['Australia', 'New Zealand'];

    /** Countries the foreign passporting route covers. */
    public const FOREIGN_PASSPORT_COUNTRIES = ['USA', 'Canada', 'Australia', 'United Kingdom'];

    public const STATUSES = [
        'pending', 'requirements', 'lodged', 'approved', 'appointment',
        'agreement', 'insurance', 'etravel', 'payment', 'acknowledged',
        'released', 'cancelled',
    ];

    /**
     * The stages each service actually walks through, in flowchart order.
     *
     * @var array<string, list<string>>
     */
    public const SERVICE_STAGES = [
        'visit_visa' => [
            'pending', 'requirements', 'lodged', 'agreement',
            'insurance', 'etravel', 'payment', 'acknowledged', 'released',
        ],
        'e_visa' => [
            'pending', 'requirements', 'lodged', 'agreement', 'payment', 'released',
        ],
        'passporting' => [
            'pending', 'requirements', 'appointment', 'payment', 'released',
        ],
    ];

    protected $fillable = [
        'reference',
        'created_by',
        'service_type',
        'status',
        'applicant_type',
        'client_name',
        'client_email',
        'client_phone',
        'destination_country',
        'purpose',
        'requires_appearance',
        'processing_speed',
        'passport_type',
        'embassy_country',
        'appointment_at',
        'agreement_signed_at',
        'acknowledgement_signed_at',
        'insurance_included',
        'etravel_reference',
        'service_fee',
        'rush_fee',
        'insurance_fee',
        'etravel_fee',
        'total_amount',
        'amount_paid',
        'currency',
        'payment_type',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'requires_appearance' => 'boolean',
            'insurance_included' => 'boolean',
            'appointment_at' => 'datetime',
            'agreement_signed_at' => 'datetime',
            'acknowledgement_signed_at' => 'datetime',
            'service_fee' => 'decimal:2',
            'rush_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'etravel_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(VisaApplicant::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VisaApplicationDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The stages this particular file should walk through.
     *
     * @return list<string>
     */
    public function stages(): array
    {
        return self::SERVICE_STAGES[$this->service_type] ?? self::STATUSES;
    }

    public function isRush(): bool
    {
        return $this->processing_speed === 'rush';
    }

    /**
     * e-Visa files covering several pax are group packages.
     */
    public function isGroupPackage(): bool
    {
        return $this->applicant_type === 'group';
    }

    /**
     * A visit visa to Australia or New Zealand needs no embassy appearance.
     */
    public function needsAppearance(): bool
    {
        return ! in_array($this->destination_country, self::NO_APPEARANCE_COUNTRIES, true);
    }

    public function outstandingBalance(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->amount_paid);
    }

    /**
     * Generate a unique visa assistance reference code.
     */
    public static function generateReference(): string
    {
        return 'VSA-'.date('Y').'-'.strtoupper(substr(uniqid(), -5));
    }
}
