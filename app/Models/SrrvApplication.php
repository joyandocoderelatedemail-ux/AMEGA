<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An SRRV retiree file worked through the Philippine Retirement Authority.
 * Classic or courtesy is decided by who the retiree is, not by preference:
 * courtesy covers government employees and military personnel aged 50 and over.
 */
#[ScopedBy([OwnFilesScope::class])]
class SrrvApplication extends Model
{
    use HasFactory;

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

    /** Courtesy is for government employees and military personnel aged 50 and above. */
    public const COURTESY_MIN_AGE = 50;

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
        'renewal_application' => [
            'pending', 'requirements', 'investment', 'supporting',
            'documentation', 'oath', 'awaiting_release', 'released',
        ],
        // Re-stamping has not been specified yet, so it shares the base pipeline.
        'restamping' => [
            'pending', 'requirements', 'lodged', 'paid', 'released',
        ],
    ];

    /** What each stage is called at the desk. */
    public const STAGE_LABELS = [
        'pending' => 'Category',
        'requirements' => 'Requirements',
        'investment' => 'Investment Amount',
        'supporting' => 'Supporting Documents',
        'documentation' => 'Documentation',
        'oath' => 'Oath Taking',
        'lodged' => 'Lodged',
        'paid' => 'Paid',
        'processing' => 'Processing',
        'awaiting_release' => 'Waiting for Release',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Documents the Requirements stage waits for: the standard PRA checklist,
     * then the class-specific proofs.
     *
     * @var array<string, array<string, string>>
     */
    public const REQUIRED_DOCUMENTS = [
        'classic' => [
            'pra_checklist' => 'Standard PRA checklist',
            'police_clearance' => 'Police clearance',
            'pension_proof' => 'Proof of pension',
        ],
        'courtesy' => [
            'pra_checklist' => 'Standard PRA checklist',
            'military_service_proof' => 'Proof of military service',
        ],
    ];

    /** A class proof also counts as received when its box is ticked at the desk. */
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
            'copies_submitted' => 'integer',
            'email_sent_at' => 'datetime',
            'lodged_at' => 'datetime',
            'supporting_completed_at' => 'datetime',
            'payment_in_full_at' => 'datetime',
            'oath_at' => 'datetime',
            'released_at' => 'datetime',
            'service_fee' => 'decimal:2',
            'amount_paid' => 'decimal:2',
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
     * Required documents with whether each has been received: filed on the
     * file, or (for a class proof) ticked at the desk.
     *
     * @return list<array{type: string, label: string, received: bool}>
     */
    public function documentChecklist(): array
    {
        $filed = $this->documents->pluck('document_type');

        return collect(self::REQUIRED_DOCUMENTS[$this->visa_class] ?? self::REQUIRED_DOCUMENTS['classic'])
            ->map(fn (string $label, string $type) => [
                'type' => $type,
                'label' => $label,
                'received' => $filed->contains($type)
                    || (isset(self::PROOF_FLAGS[$type]) && $this->{self::PROOF_FLAGS[$type]}),
            ])
            ->values()
            ->all();
    }

    /**
     * Why the file cannot leave its current stage yet, or null when that
     * stage's work is recorded and it may advance.
     */
    public function stageBlocker(): ?string
    {
        return match ($this->status) {
            'pending' => $this->categoryProblem(),
            'requirements' => ($missing = collect($this->documentChecklist())->reject(fn ($document) => $document['received'])->pluck('label'))->isNotEmpty()
                ? 'Still missing: '.$missing->implode('; ').'.'
                : null,
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
     * Courtesy is only for government employees and military personnel aged
     * 50 and above, so the birth date must bear that out.
     */
    public function categoryProblem(): ?string
    {
        if (! in_array($this->visa_class, self::VISA_CLASSES, true)) {
            return 'Choose SRRV Classic or SRRV Courtesy.';
        }

        if (! $this->isCourtesy()) {
            return null;
        }

        $age = $this->age();

        return match (true) {
            $age === null => 'Courtesy needs the retiree\'s birth date to confirm they are '.self::COURTESY_MIN_AGE.' or above.',
            $age < self::COURTESY_MIN_AGE => 'Courtesy is for retirees aged '.self::COURTESY_MIN_AGE." and above; this retiree is {$age}. Use SRRV Classic.",
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

    /**
     * Courtesy requires proof of military service; classic requires police
     * clearance and proof of pension on top of the standard checklist.
     *
     * @return list<string>
     */
    public function requiredProofs(): array
    {
        return $this->isCourtesy()
            ? ['military_service_proof_received']
            : ['police_clearance_received', 'pension_proof_received'];
    }

    /**
     * Whether every class-specific proof has been collected.
     */
    public function hasAllProofs(): bool
    {
        foreach ($this->requiredProofs() as $proof) {
            if (! $this->{$proof}) {
                return false;
            }
        }

        return true;
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
