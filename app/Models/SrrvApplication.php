<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An SRRV retiree file worked through the Philippine Retirement Authority.
 * Classic or courtesy is decided by who the retiree is, not by preference:
 * courtesy covers government employees and military personnel aged 50 and over.
 */
class SrrvApplication extends Model
{
    use HasFactory;

    public const SERVICE_TYPES = ['renewal_application', 'restamping'];

    public const VISA_CLASSES = ['classic', 'courtesy'];

    public const STATUSES = [
        'pending', 'requirements', 'lodged', 'paid',
        'processing', 'awaiting_release', 'released', 'cancelled',
    ];

    /**
     * The stages a renewal application walks through, in flowchart order.
     *
     * @var array<string, list<string>>
     */
    public const SERVICE_STAGES = [
        'renewal_application' => [
            'pending', 'requirements', 'lodged', 'paid',
            'processing', 'awaiting_release', 'released',
        ],
        // Re-stamping has not been specified yet, so it shares the base pipeline.
        'restamping' => [
            'pending', 'requirements', 'lodged', 'paid', 'released',
        ],
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
