<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The annual SRRV renewal. Due once a year for as long as the retiree stays.
 * The fee turns on the visa class and nothing else, and at most two years can
 * be paid ahead.
 */
#[ScopedBy([OwnFilesScope::class])]
class SrrvRenewal extends Model
{
    use HasFactory;

    /** Renewal fee by visa class. */
    public const CLASS_FEES = [
        'classic' => 360.00,
        'courtesy' => 10.00,
    ];

    /** The ceiling on how far ahead a retiree can pay. */
    public const MAX_YEARS_PREPAID = 2;

    public const STATUSES = [
        'pending', 'documented', 'email_sent', 'processing',
        'ready_for_collection', 'collected', 'cancelled',
    ];

    /** The renewal pipeline, in flowchart order; cancelling takes a file off it. */
    public const STAGES = [
        'pending', 'documented', 'email_sent', 'processing', 'ready_for_collection', 'collected',
    ];

    /** What each renewal stage is called at the desk, in flowchart order. */
    public const STAGE_LABELS = [
        'pending' => 'Renewal Documents',
        'documented' => 'Fee Calculated',
        'email_sent' => 'Submitted to PRA',
        'processing' => 'Processing',
        'ready_for_collection' => 'Ready at PRA Office',
        'collected' => 'Collected',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'reference',
        'srrv_application_id',
        'created_by',
        'status',
        'visa_class',
        'retiree_name',
        'retiree_email',
        'srrv_card_number',
        'years_paid',
        'fee_amount',
        'amount_paid',
        'currency',
        'id_and_photocopy_received',
        'form_filled_online',
        'signature_thumbmark_at',
        'email_sent_at',
        'processed_at',
        'ready_at_pra_at',
        'client_notified_at',
        'collected_at',
        'collected_by_name',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'srrv_application_id' => 'integer',
            'created_by' => 'integer',
            'years_paid' => 'integer',
            'fee_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'id_and_photocopy_received' => 'boolean',
            'form_filled_online' => 'boolean',
            'signature_thumbmark_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'processed_at' => 'datetime',
            'ready_at_pra_at' => 'datetime',
            'client_notified_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SrrvApplication::class, 'srrv_application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stageLabel(string $stage): string
    {
        return self::STAGE_LABELS[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->stageLabel((string) $this->status);
    }

    public function outstandingBalance(): float
    {
        return max(0, (float) $this->fee_amount - (float) $this->amount_paid);
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->fee_amount > 0 && $this->outstandingBalance() <= 0;
    }

    /**
     * What the renewal still needs before it can leave its current stage, or
     * null when it may advance. Collection itself waits for full payment.
     */
    public function stageBlocker(): ?string
    {
        return match ($this->status) {
            'pending' => ($missing = collect([
                'SRRV ID and photocopy' => $this->id_and_photocopy_received,
                'online form' => $this->form_filled_online,
                'signature and thumb mark' => (bool) $this->signature_thumbmark_at,
            ])->reject()->keys())->isNotEmpty()
                ? 'Still needed: '.$missing->implode(', ').'.'
                : null,
            'documented' => (float) $this->fee_amount > 0 ? null : 'The renewal fee has not been calculated.',
            'ready_for_collection' => $this->isFullyPaid()
                ? null
                : 'The renewal fee must be fully paid before the client collects. Balance: '.$this->currency.' '.number_format($this->outstandingBalance(), 2).'.',
            default => null,
        };
    }

    /**
     * The annual fee for a visa class. Falls back to the classic rate.
     */
    public static function feeForClass(string $visaClass): float
    {
        return self::CLASS_FEES[$visaClass] ?? self::CLASS_FEES['classic'];
    }

    /**
     * Whether the requested prepayment is within the two-year ceiling.
     */
    public static function withinPrepayCeiling(int $years): bool
    {
        return $years >= 1 && $years <= self::MAX_YEARS_PREPAID;
    }

    /**
     * Generate a unique SRRV renewal reference code.
     */
    public static function generateReference(): string
    {
        return 'SRV-REN-'.date('Y').'-'.strtoupper(substr(uniqid(), -5));
    }
}
