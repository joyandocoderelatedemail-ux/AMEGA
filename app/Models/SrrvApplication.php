<?php

namespace App\Models;

use App\Models\Concerns\HasFormDetails;
use App\Models\Concerns\NeedsAdminApproval;
use App\Models\Scopes\OwnFilesScope;
use App\Support\SrrvForms;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An SRRV retiree file worked through the Philippine Retirement Authority.
 * The options and requirements follow the PRA's Expanded SRRV Program (PRA-PD-LORQ-0010,
 * August 2025): principals are 40 or older; Classic for anyone, Courtesy for retired diplomats,
 * international-organisation officers, treaty military and high achievers, or for former Filipinos.
 */
#[ScopedBy([OwnFilesScope::class])]
class SrrvApplication extends Model
{
    use HasFactory, HasFormDetails, NeedsAdminApproval;

    /**
     * 'renewal_application' is the new SRRV application (the key predates the
     * name). Re-stamping stays for existing files but is not offered for new
     * ones until its flow is defined.
     */
    public const SERVICE_TYPES = ['renewal_application', 'restamping'];

    /** Services a new file can be opened for. */
    public const OPEN_SERVICE_TYPES = ['renewal_application'];

    public const SERVICE_LABELS = [
        'renewal_application' => 'New SRRV Application',
        'restamping' => 'Re-stamping',
    ];

    /** The PRA's minimum age for a principal retiree, every option. */
    public const MIN_AGE = 40;

    /** Who a Courtesy file is for. */
    public const COURTESY_TYPES = [
        'foreign_national' => 'Foreign national',
        'former_filipino' => 'Former Filipino',
    ];

    /**
     * Visa deposit in USD by option and pension: [aged 40 to 49, aged 50 and above].
     * Inclusive of the principal and up to two dependents.
     */
    public const DEPOSITS = [
        'classic' => ['pensioner' => [25000, 15000], 'non_pensioner' => [50000, 30000]],
        'foreign_national' => ['pensioner' => [3000, 1500], 'non_pensioner' => [6000, 1500]],
        'former_filipino' => ['pensioner' => [3000, 1500], 'non_pensioner' => [3000, 1500]],
    ];

    /** PRA annual fee in USD by option: [the fee, plus this for each dependent beyond two]. */
    public const ANNUAL_FEES = [
        'classic' => [360, 100],
        'foreign_national' => [100, 10],
        'former_filipino' => [50, 10],
    ];

    public const PROCESSING_FEE = 1500;

    public const DEPENDENT_PROCESSING_FEE = 300;

    /** Added to the deposit for each dependent beyond two. */
    public const EXTRA_DEPENDENT_DEPOSIT = 15000;

    public const VISA_CLASSES = ['classic', 'courtesy'];

    public const STATUSES = [
        'pending', 'requirements', 'investment', 'supporting', 'documentation',
        'oath', 'lodged', 'paid', 'processing', 'awaiting_release', 'released', 'cancelled',
    ];

    /**
     * The stages a renewal application walks through, in flowchart order.
     *
     * @var array<string, list<string>>
     */
    public const SERVICE_STAGES = [
        // The wizard (option, requirements, deposit) is the application itself; after it the file
        // only tracks the PRA's own processing.
        'renewal_application' => [
            'pending', 'documentation', 'oath', 'awaiting_release', 'released',
        ],
        // Re-stamping has not been specified yet, so it shares the base pipeline.
        'restamping' => [
            'pending', 'requirements', 'lodged', 'paid', 'released',
        ],
    ];

    /** What each stage is called at the desk. */
    public const STAGE_LABELS = [
        'pending' => 'Application',
        'requirements' => 'Requirements',
        'investment' => 'Investment Amount',
        'supporting' => 'Supporting Documents',
        'documentation' => 'Lodged with PRA',
        'oath' => 'Oath Taking',
        'lodged' => 'Lodged',
        'paid' => 'Paid',
        'processing' => 'Processing',
        'awaiting_release' => 'Waiting for Release',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Every requirement on the PRA list: type => [label, what the PRA asks for]. Which of them a file
     * needs depends on its option, pension, age and dependents (see requirements()).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const REQUIREMENTS = [
        'passport' => ['Passport', 'Original and 4 photocopies; tourist visa valid 30+ days and convertible'],
        'application_form' => ['PRA application form', '1 original and 4 photocopies, typed and signed'],
        'medical_certificate' => ['PRA medical certificate and results', 'Valid 6 months; apostilled if done abroad'],
        'police_clearance' => ['Police clearance', '1 original and 2 photocopies, valid 6 months, apostilled'],
        'bi_clearance' => ['BI Clearance Certificate (BICC)', '1 original, clear from the BI derogatory check'],
        'nbi_clearance' => ['NBI clearance', 'Only if they have stayed in the Philippines 90 days or more'],
        'visa_deposit' => ['Certificate of visa deposit', 'Inward remittance to a PRA-accredited bank'],
        'relationship_proof' => ['Proof of relationship', 'Marriage or birth certificate of each spouse or child, apostilled'],
        'id_photos' => ['ID photos', '8 pieces, 2x2, white background, no eyeglasses'],
        'pension_proof' => ['Proof of pension', 'Apostilled or authenticated'],
        'employment_certificate' => ['Certificate of employment', 'From their foreign service, organisation or service'],
        'military_service_proof' => ['Proof of military service', 'Retired military only'],
        'former_filipino_proof' => ['Proof of former Filipino citizenship', 'Philippine passport, certificate of naturalization or PSA birth certificate'],
    ];

    /** The old desk ticks, kept in step with the ticked requirements. */
    private const PROOF_FLAGS = [
        'police_clearance' => 'police_clearance_received',
        'pension_proof' => 'pension_proof_received',
        'military_service_proof' => 'military_service_proof_received',
    ];

    protected $fillable = [
        'reference',
        'created_by',
        'service_type',
        'status',
        'visa_class',
        'courtesy_type',
        'is_pensioner',
        'retiree_name',
        'retiree_email',
        'retiree_phone',
        'date_of_birth',
        'nationality',
        'srrv_card_number',
        'investment_amount',
        'police_clearance_received',
        'pension_proof_received',
        'military_service_proof_received',
        'requirements_received',
        'copies_submitted',
        'email_sent_at',
        'lodged_at',
        'pra_reference',
        'supporting_completed_at',
        'payment_in_full_at',
        'oath_at',
        'released_at',
        'service_fee',
        'amount_paid',
        'currency',
        'remarks',
        'form_details',
        'approval_status',
        'approval_requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'date_of_birth' => 'date',
            'investment_amount' => 'decimal:2',
            'police_clearance_received' => 'boolean',
            'pension_proof_received' => 'boolean',
            'military_service_proof_received' => 'boolean',
            'is_pensioner' => 'boolean',
            'requirements_received' => 'array',
            'copies_submitted' => 'integer',
            'email_sent_at' => 'datetime',
            'lodged_at' => 'datetime',
            'supporting_completed_at' => 'datetime',
            'payment_in_full_at' => 'datetime',
            'oath_at' => 'datetime',
            'released_at' => 'datetime',
            'service_fee' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'form_details' => 'array',
            'approval_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SrrvApplicationDocument::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(SrrvRenewal::class);
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

    public function stageLabel(string $stage): string
    {
        return self::STAGE_LABELS[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->stageLabel((string) $this->status);
    }

    public function getServiceLabelAttribute(): string
    {
        return self::SERVICE_LABELS[$this->service_type] ?? ucfirst(str_replace('_', ' ', (string) $this->service_type));
    }

    /**
     * The retiree's age today, or null without a birth date.
     */
    public function age(): ?int
    {
        return $this->date_of_birth ? (int) $this->date_of_birth->diffInYears(now()) : null;
    }

    /**
     * The PRA requirements this file needs, each with whether it is required (an optional one is
     * shown but does not hold the file back) and whether it has been received: filed on the file,
     * or ticked as seen at the desk.
     *
     * @return list<array{type: string, label: string, hint: string, required: bool, received: bool}>
     */
    public function documentChecklist(): array
    {
        $filed = $this->documents->pluck('document_type');
        $ticked = $this->ticked();

        return collect($this->requirements())
            ->map(fn (bool $required, string $type) => [
                'type' => $type,
                'label' => self::REQUIREMENTS[$type][0],
                'hint' => self::REQUIREMENTS[$type][1],
                'required' => $required,
                'received' => $filed->contains($type) || in_array($type, $ticked, true),
            ])
            ->values()
            ->all();
    }

    /**
     * Which requirements apply to this file: type => required. Basic documents for everyone, then
     * the option's own: pension proof for a Classic pensioner, or for a foreign-national Courtesy
     * retiree aged 40 to 49; certificate of employment (and military proof for retired military)
     * for foreign-national Courtesy; proof of former citizenship for former Filipinos.
     *
     * @return array<string, bool>
     */
    public function requirements(): array
    {
        $age = $this->age();
        $option = $this->optionKey();

        return array_filter([
            'passport' => true,
            'application_form' => true,
            'medical_certificate' => true,
            'police_clearance' => true,
            'bi_clearance' => true,
            'nbi_clearance' => false,
            'visa_deposit' => true,
            'relationship_proof' => $this->dependentCount() > 0 ? true : null,
            'id_photos' => true,
            'pension_proof' => match ($option) {
                'classic' => $this->is_pensioner ? true : null,
                'foreign_national' => $age !== null && $age < 50 ? true : null,
                default => null,
            },
            'employment_certificate' => $option === 'foreign_national' ? true : null,
            'military_service_proof' => $option === 'foreign_national' ? false : null,
            'former_filipino_proof' => $option === 'former_filipino' ? true : null,
        ], fn ($required) => $required !== null);
    }

    /**
     * Requirements ticked as seen, including the old desk ticks.
     *
     * @return list<string>
     */
    public function ticked(): array
    {
        $ticked = (array) ($this->requirements_received ?? []);

        foreach (self::PROOF_FLAGS as $type => $flag) {
            if ($this->{$flag}) {
                $ticked[] = $type;
            }
        }

        return array_values(array_unique($ticked));
    }

    /**
     * Ticked requirement types, cleaned, with the old desk ticks kept in step.
     *
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    public static function tickAttributes(array $types): array
    {
        $types = array_values(array_intersect(array_keys(self::REQUIREMENTS), $types));

        return ['requirements_received' => $types] + collect(self::PROOF_FLAGS)
            ->mapWithKeys(fn (string $flag, string $type) => [$flag => in_array($type, $types, true)])
            ->all();
    }

    /** classic, foreign_national or former_filipino: which row of the PRA tables applies. */
    public function optionKey(): string
    {
        return $this->isCourtesy() ? ($this->courtesy_type ?: 'foreign_national') : 'classic';
    }

    public function optionLabel(): string
    {
        return match ($this->optionKey()) {
            'classic' => 'SRRV Classic'.($this->is_pensioner ? ' · Pensioner' : ''),
            'former_filipino' => 'SRRV Courtesy · Former Filipino',
            default => 'SRRV Courtesy · Foreign national'.($this->is_pensioner ? ' · Pensioner' : ''),
        };
    }

    /** The spouse and children named on the file. */
    public function dependentCount(): int
    {
        return count(SrrvForms::dependents($this));
    }

    /**
     * What the PRA charges for this file, in USD: the visa deposit (null until the birth date is
     * known), the annual fee and the processing fee, each with the dependents beyond two added.
     *
     * @return array{deposit: ?float, annual_fee: float, processing_fee: float, dependents: int}
     */
    public function praFees(): array
    {
        $age = $this->age();
        $option = $this->optionKey();
        $dependents = $this->dependentCount();
        $extra = max(0, $dependents - 2);
        [$annual, $perExtra] = self::ANNUAL_FEES[$option];

        return [
            'deposit' => $age === null ? null
                : (float) self::DEPOSITS[$option][$this->is_pensioner ? 'pensioner' : 'non_pensioner'][$age >= 50 ? 1 : 0] + $extra * self::EXTRA_DEPENDENT_DEPOSIT,
            'annual_fee' => (float) $annual + $extra * $perExtra,
            'processing_fee' => (float) self::PROCESSING_FEE + $dependents * self::DEPENDENT_PROCESSING_FEE,
            'dependents' => $dependents,
        ];
    }

    /**
     * Why the file cannot leave its current stage yet, or null when that
     * stage's work is recorded and it may advance.
     */
    public function stageBlocker(): ?string
    {
        // Nothing moves on until the admin has approved the file.
        return $this->approvalBlocker('advanced') ?? match ($this->status) {
            // The application: the option is right, every PRA requirement is in, the deposit is recorded.
            'pending' => $this->categoryProblem() ?? ($this->service_type === 'renewal_application'
                ? ($this->missingRequirements() ?? ((float) $this->investment_amount > 0 ? null : 'Record the investment amount (the visa deposit).'))
                : null),
            'requirements' => $this->missingRequirements(),
            'investment' => (float) $this->investment_amount > 0 ? null : 'Record the investment amount.',
            'supporting' => $this->supporting_completed_at ? null : 'Confirm the additional supporting documents are complete.',
            'documentation' => $this->lodged_at ? null : 'Record when the file was lodged with PRA.',
            'oath' => $this->oath_at ? null : 'Record the oath taking date.',
            'awaiting_release' => $this->isFullyPaid()
                ? null
                : ((float) $this->service_fee <= 0
                    ? 'Set the service fee before release.'
                    : 'The file must be fully paid before release. Balance: '.$this->currency.' '.number_format($this->outstandingBalance(), 2).'.'),
            default => null,
        };
    }

    /**
     * The PRA's rules for the option: a principal is 40 or older, and a Courtesy file says whether
     * it is for a foreign national or a former Filipino.
     */
    public function categoryProblem(): ?string
    {
        if (! in_array($this->visa_class, self::VISA_CLASSES, true)) {
            return 'Choose SRRV Classic or SRRV Courtesy.';
        }

        if ($this->isCourtesy() && ! array_key_exists((string) $this->courtesy_type, self::COURTESY_TYPES)) {
            return 'Say whether this Courtesy file is for a foreign national or a former Filipino.';
        }

        $age = $this->age();

        return match (true) {
            $age === null => 'Enter the retiree\'s birth date: the PRA requires a principal to be '.self::MIN_AGE.' or older, and the deposit depends on age.',
            $age < self::MIN_AGE => 'The PRA requires a principal retiree to be '.self::MIN_AGE." or older; this retiree is {$age}.",
            default => null,
        };
    }

    /**
     * Paid in full: a fee is set and nothing is outstanding.
     */
    public function isFullyPaid(): bool
    {
        return (float) $this->service_fee > 0 && $this->outstandingBalance() <= 0;
    }

    public function isCourtesy(): bool
    {
        return $this->visa_class === 'courtesy';
    }

    /** "Still missing: ..." for the required PRA documents not yet in, or null when all are. */
    public function missingRequirements(): ?string
    {
        $missing = collect($this->documentChecklist())->reject(fn ($document) => $document['received'] || ! $document['required'])->pluck('label');

        return $missing->isNotEmpty() ? 'Still missing: '.$missing->implode('; ').'.' : null;
    }

    /**
     * Whether every required PRA document has been collected.
     */
    public function hasAllProofs(): bool
    {
        return collect($this->documentChecklist())->every(fn ($document) => $document['received'] || ! $document['required']);
    }

    public function outstandingBalance(): float
    {
        return max(0, (float) $this->service_fee - (float) $this->amount_paid);
    }

    /**
     * Generate a unique SRRV reference code.
     */
    public static function generateReference(): string
    {
        return 'SRV-'.date('Y').'-'.strtoupper(substr(uniqid(), -5));
    }
}
