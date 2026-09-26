<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A visa assistance counter file. Covers any of the three services the counter
 * runs: visit visa, e-Visa, and passporting.
 */
#[ScopedBy([OwnFilesScope::class])]
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

    /** How an embassy or processing office answered. */
    public const RESULTS = ['approved', 'denied', 'withdrawn'];

    /** Destinations where no embassy appearance is needed. */
    public const NO_APPEARANCE_COUNTRIES = ['Australia', 'New Zealand'];

    /** Countries the foreign passporting route covers. */
    public const FOREIGN_PASSPORT_COUNTRIES = ['USA', 'Canada', 'Australia', 'United Kingdom'];

    public const STATUSES = [
        'pending', 'requirements', 'agreement', 'insurance', 'etravel', 'payment',
        'acknowledged', 'appointment', 'lodged', 'released', 'cancelled',
    ];

    /**
     * The stages each service walks through, in the counter flowchart's order.
     * Staff record each stage's work on the file before it can advance.
     *
     * @var array<string, list<string>>
     */
    public const SERVICE_STAGES = [
        'visit_visa' => [
            'pending', 'requirements', 'agreement', 'insurance', 'etravel',
            'payment', 'acknowledged', 'lodged', 'released',
        ],
        'e_visa' => [
            'pending', 'requirements', 'payment', 'agreement', 'lodged', 'released',
        ],
        'passporting' => [
            'pending', 'requirements', 'appointment', 'payment', 'lodged', 'released',
        ],
    ];

    /**
     * Passporting splits on the passport: the Philippine passport goes through
     * a DFA appointment, the foreign one through the embassy.
     *
     * @var array<string, list<string>>
     */
    public const PASSPORT_STAGES = [
        'local' => ['pending', 'requirements', 'appointment', 'payment', 'lodged', 'released'],
        'foreign' => ['pending', 'requirements', 'payment', 'lodged', 'released'],
    ];

    /** What each stage is called on the counter. */
    public const STAGE_LABELS = [
        'pending' => 'File Opened',
        'requirements' => 'Requirements',
        'agreement' => 'Agreement',
        'insurance' => 'Travel Insurance',
        'etravel' => 'E-Travel',
        'payment' => 'Payment',
        'acknowledged' => 'Acknowledgment',
        'appointment' => 'DFA Appointment',
        'lodged' => 'Processing',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
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
        'lodged_at',
        'embassy_reference',
        'result',
        'result_at',
        'result_reference',
        'result_validity',
        'insurance_included',
        'insurance_provider',
        'insurance_policy_number',
        'insurance_declined',
        'etravel_reference',
        'service_fee',
        'visa_fee',
        'rush_fee',
        'insurance_fee',
        'etravel_fee',
        'total_amount',
        'amount_paid',
        'currency',
        'payment_type',
        'remarks',
        'stage_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'requires_appearance' => 'boolean',
            'insurance_included' => 'boolean',
            'insurance_declined' => 'boolean',
            'stage_notified_at' => 'array',
            'appointment_at' => 'datetime',
            'agreement_signed_at' => 'datetime',
            'acknowledgement_signed_at' => 'datetime',
            'lodged_at' => 'date',
            'result_at' => 'date',
            'service_fee' => 'decimal:2',
            'visa_fee' => 'decimal:2',
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
        if ($this->service_type === 'passporting' && isset(self::PASSPORT_STAGES[$this->passport_type])) {
            return self::PASSPORT_STAGES[$this->passport_type];
        }

        return self::SERVICE_STAGES[$this->service_type] ?? self::STATUSES;
    }

    /**
     * A stage's name on this file: the embassy stages read differently per service.
     */
    public function stageLabel(string $stage): string
    {
        return match (true) {
            $stage === 'lodged' && $this->service_type === 'visit_visa' => 'Embassy Processing',
            $stage === 'released' && $this->service_type === 'visit_visa' => 'Application Result',
            $stage === 'released' && $this->service_type === 'e_visa' => 'e-Visa Released',
            $stage === 'released' && $this->service_type === 'passporting' => 'Passport Released',
            $stage === 'agreement' && $this->service_type === 'e_visa' => 'Booking Agreement',
            default => self::STAGE_LABELS[$stage] ?? ucfirst(str_replace('_', ' ', $stage)),
        };
    }

    /**
     * The stages this file has finished: every one before its current stage,
     * and the last one once the file reaches it. A cancelled file has none.
     *
     * @return list<string>
     */
    public function completedStages(): array
    {
        $stages = $this->stages();
        $current = array_search($this->status, $stages, true);

        if ($current === false) {
            return [];
        }

        return array_slice($stages, 0, $current === count($stages) - 1 ? $current + 1 : $current);
    }

    /**
     * When staff last emailed the client that this stage was done.
     */
    public function stageNotifiedAt(string $stage): ?Carbon
    {
        $sentAt = $this->stage_notified_at[$stage] ?? null;

        return $sentAt ? Carbon::parse($sentAt) : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->stageLabel((string) $this->status);
    }

    /**
     * The documents the Requirements stage waits for, from the counter
     * flowcharts. Per-applicant documents are needed once for every applicant.
     *
     * @return list<array{type: string, label: string, per_applicant: bool}>
     */
    public function requiredDocuments(): array
    {
        return match (true) {
            $this->service_type === 'passporting' && $this->passport_type === 'local' => [
                ['type' => 'valid_id', 'label' => 'Valid ID', 'per_applicant' => true],
                ['type' => 'birth_certificate', 'label' => 'PSA Birth Certificate', 'per_applicant' => true],
            ],
            $this->service_type === 'passporting' => [
                ['type' => 'photo', 'label' => 'Photo (embassy specification)', 'per_applicant' => true],
            ],
            default => [
                ['type' => 'passport_scan', 'label' => 'Passport copy', 'per_applicant' => true],
            ],
        };
    }

    /**
     * Required documents not filed yet, as "Passport copy — Juan Dela Cruz".
     *
     * @return list<string>
     */
    public function missingDocuments(): array
    {
        $documents = $this->documents;
        $missing = [];

        foreach ($this->requiredDocuments() as $required) {
            if (! $required['per_applicant']) {
                if (! $documents->contains('document_type', $required['type'])) {
                    $missing[] = $required['label'];
                }

                continue;
            }

            foreach ($this->applicants as $applicant) {
                $filed = $documents->contains(fn ($document) => $document->document_type === $required['type']
                    && $document->visa_applicant_id === $applicant->id);

                if (! $filed) {
                    $missing[] = "{$required['label']} — {$applicant->full_name}";
                }
            }
        }

        return $missing;
    }

    /**
     * Why the file cannot leave its current stage yet, or null when that
     * stage's work is recorded and it may advance.
     */
    public function stageBlocker(): ?string
    {
        return match ($this->status) {
            'pending' => $this->applicants->isEmpty()
                ? 'Add at least one applicant to the file.'
                : null,
            'requirements' => ($missing = $this->missingDocuments()) !== []
                ? 'Still missing: '.implode('; ', $missing).'.'
                : null,
            'agreement' => $this->agreement_signed_at ? null : 'Record that the client signed the agreement.',
            'insurance' => ($this->insurance_declined || filled($this->insurance_policy_number))
                ? null
                : 'Record the insurance policy, or that the client declined insurance.',
            'etravel' => filled($this->etravel_reference) ? null : 'Enter the e-Travel reference.',
            'payment' => $this->isFullyPaid()
                ? null
                : 'The file must be fully paid. Balance: '.$this->currency.' '.number_format($this->outstandingBalance(), 2).'.',
            'acknowledged' => $this->acknowledgement_signed_at ? null : 'Record that the client signed the Acknowledgment of Documents.',
            'appointment' => $this->appointment_at ? null : 'Enter the DFA appointment date.',
            'lodged' => filled($this->result) ? null : 'Record the application result.',
            default => null,
        };
    }

    /**
     * Paid in full: something is billed and nothing is outstanding.
     */
    public function isFullyPaid(): bool
    {
        return (float) $this->total_amount > 0 && $this->outstandingBalance() <= 0;
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
