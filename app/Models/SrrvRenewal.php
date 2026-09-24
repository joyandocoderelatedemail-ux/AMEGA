<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The annual SRRV renewal. Due once a year for as long as the retiree stays.
 * The fee turns on the visa class and nothing else, and at most two years can
 * be paid ahead.
 */
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
