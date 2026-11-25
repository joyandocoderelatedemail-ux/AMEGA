<?php

namespace App\Models;

use App\Models\Concerns\HasFormDetails;
use App\Models\Concerns\NeedsAdminApproval;
use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The annual SRRV renewal. Due once a year for as long as the retiree stays.
 * The fee turns on the visa class and nothing else, and at most two years can
 * be paid ahead.
 */
#[ScopedBy([OwnFilesScope::class])]
class SrrvRenewal extends Model
{
    /** The desk whose "view_all" permission lifts OwnFilesScope. */
    public static function ownFilesDesk(): string
    {
        return 'srrv';
    }

    use HasFactory, HasFormDetails, NeedsAdminApproval;

    /** Renewal fee by visa class. */
    public const CLASS_FEES = [
        'classic' => 360.00,
        'courtesy' => 10.00,
    ];

    /**
     * What each dependent beyond the two the annual fee covers adds a year: US$100 on a Classic SRRV
     * (PRA ID Services, "Schedule of annual fees according to SRRV scheme").
     */
    public const EXTRA_DEPENDENT_FEES = ['classic' => 100.00];

    public const DEPENDENTS_COVERED = 2;

    /** How the agent may discount the service fee (the admin approves it). */
    public const DISCOUNT_TYPES = ['percent' => 'Percent (%)', 'amount' => 'Amount'];

    /**
     * The PRA ID renewal fee is always billed in USD; AMEGA's service fee (and
     * its discount) always in PHP. No conversion, no exchange rate.
     */
    public const FEE_CURRENCY = 'USD';

    public const SERVICE_CURRENCY = 'PHP';

    /** What the cashier collects, in SQL: the PRA fee plus the service fee less its discount (see totalDue). */
    public const TOTAL_SQL = 'fee_amount + service_fee - discount_amount';

    /** The ceiling on how far ahead a retiree can pay. */
    public const MAX_YEARS_PREPAID = 2;

    /** Once here the file is done: its status is locked. */
    public const FINISHED_STATUSES = ['released_to_client', 'closed', 'cancelled'];

    public const STATUSES = [
        'pending', 'ready_for_submission', 'submitted_to_pra', 'payment_confirmed',
        'new_id_ready', 'released_to_client', 'closed', 'cancelled',
    ];

    /** The renewal pipeline, in flowchart order; cancelling takes a file off it. */
    public const STAGES = [
        'pending', 'ready_for_submission', 'submitted_to_pra', 'payment_confirmed',
        'new_id_ready', 'released_to_client', 'closed',
    ];

    /** What each renewal stage is called at the desk, in flowchart order. */
    public const STAGE_LABELS = [
        'pending' => 'Waiting for Admin Approval',
        'ready_for_submission' => 'Ready for Submission',
        'submitted_to_pra' => 'Submitted to PRA',
        'payment_confirmed' => 'Payment Confirmed',
        'new_id_ready' => 'New ID Ready',
        'released_to_client' => 'Released to Client',
        'closed' => 'Closed',
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
        'retiree_phone',
        'nationality',
        'date_of_birth',
        'srrv_card_number',
        'years_paid',
        'dependents',
        'fee_amount',
        'fee_paid',
        'service_fee',
        'service_currency',
        'discount_type',
        'discount_value',
        'discount_amount',
        'amount_paid',
        'currency',
        'exchange_rate',
        'id_and_photocopy_received',
        'form_filled_online',
        'signature_thumbmark_at',
        'submitted_at',
        'submitted_remarks',
        'card_expiry_date',
        'closed_at',
        'email_sent_at',
        'processed_at',
        'ready_at_pra_at',
        'client_notified_at',
        'collected_at',
        'collected_by_name',
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
            'srrv_application_id' => 'integer',
            'created_by' => 'integer',
            'years_paid' => 'integer',
            'dependents' => 'integer',
            'date_of_birth' => 'date',
            'fee_amount' => 'decimal:2',
            'fee_paid' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_paid' => 'decimal:2',
            'id_and_photocopy_received' => 'boolean',
            'form_filled_online' => 'boolean',
            'signature_thumbmark_at' => 'datetime',
            'submitted_at' => 'date',
            'card_expiry_date' => 'date',
            'closed_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'processed_at' => 'datetime',
            'ready_at_pra_at' => 'datetime',
            'client_notified_at' => 'datetime',
            'collected_at' => 'datetime',
            'form_details' => 'array',
            'approval_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'acknowledgement_signed_at' => 'datetime',
            'agreement_signed_at' => 'datetime',
            'card_printed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SrrvApplication::class, 'srrv_application_id');
    }

    /**
     * The form details, with the passport from the retiree's client record where this renewal has none (renewals
     * saved before the Passport section, or opened without a client): matched by SRRV number, then email.
     *
     * @return array<string, mixed>
     */
    public function detailsWithClient(): array
    {
        $details = $this->form_details ?? [];
        if (filled($details['passport_no'] ?? null)) {
            return $details;
        }

        $client = User::where('role', 'client')
            ->where(fn ($q) => $q->where('srrv_number', $this->srrv_card_number ?: '-')->orWhere('email', $this->retiree_email ?: '-'))
            ->orderByRaw('srrv_number = ? desc', [(string) $this->srrv_card_number])
            ->first();

        return array_filter([
            'passport_no' => $client?->passport_number,
            'passport_expires_on' => $client?->passport_expiry?->toDateString(),
        ]) + $details;
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
        // The first stage is named for the admin's approval; once approved, it is no longer waiting on them.
        if ($this->status === 'pending' && $this->approval_status === self::APPROVAL_APPROVED) {
            return 'Approved by Admin';
        }

        return $this->stageLabel((string) $this->status);
    }

    /** The cashier takes these, once the admin has approved the renewal. */
    public function payments(): HasMany
    {
        return $this->hasMany(SrrvPayment::class, 'srrv_renewal_id')->oldest('received_at')->oldest('id');
    }

    /** The scans filed on Step 3 Documentation. */
    public function documents(): HasMany
    {
        return $this->hasMany(SrrvRenewalDocument::class, 'srrv_renewal_id');
    }

    /** The running notes log, newest first: what happened, when, and who wrote it. */
    public function notes(): HasMany
    {
        return $this->hasMany(SrrvRenewalNote::class, 'srrv_renewal_id')->latest('id');
    }

    /** The discount on a service fee: a percentage of it, or a fixed amount. */
    public static function discountFor(?string $type, float $value, float $serviceFee): float
    {
        return match ($type) {
            'percent' => round($serviceFee * $value / 100, 2),
            'amount' => round($value, 2),
            default => 0.0,
        };
    }

    /** What the cashier collects: the PRA fee in USD, plus AMEGA's service fee in PHP less its discount. */
    public function totalDue(): float
    {
        return round((float) $this->fee_amount + (float) $this->service_fee - (float) $this->discount_amount, 2);
    }

    /** The PRA fee still to collect, in USD. */
    public function feeBalance(): float
    {
        return max(0, round((float) $this->fee_amount - (float) $this->fee_paid, 2));
    }

    /** AMEGA's service fee less the discount, in PHP. */
    public function serviceDue(): float
    {
        return round((float) $this->service_fee - (float) $this->discount_amount, 2);
    }

    /** The service fee still to collect, in PHP. */
    public function serviceBalance(): float
    {
        return max(0, round($this->serviceDue() - (float) $this->amount_paid, 2));
    }

    public function outstandingBalance(): float
    {
        return max(0, round($this->totalDue() - (float) $this->amount_paid - (float) $this->fee_paid, 2));
    }

    public function isFullyPaid(): bool
    {
        return ($this->fee_amount > 0 || $this->serviceDue() > 0) && $this->feeBalance() <= 0 && $this->serviceBalance() <= 0;
    }

    /** Approved (or saved before approvals existed, as isCleared reads it), with money still to collect: the cashier's list. */
    public function scopeAwaitingPayment(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('approval_status')->orWhere('approval_status', self::APPROVAL_APPROVED))
            ->where('status', '!=', 'cancelled')
            ->whereRaw('(fee_amount + service_fee - discount_amount) > 0')
            ->whereRaw('(fee_paid < fee_amount OR amount_paid < service_fee - discount_amount)');
    }

    /**
     * What the renewal still needs before it can leave its current stage, or
     * null when it may advance. Release waits for full payment.
     */
    public function stageBlocker(): ?string
    {
        // Nothing moves on until the admin has approved the file.
        return $this->approvalBlocker('advanced or paid at the cashier') ?? match ($this->status) {
            'pending' => ($missing = collect([
                'SRRV ID and photocopy' => $this->id_and_photocopy_received,
                'online form' => $this->form_filled_online,
                'signature and thumb mark' => (bool) $this->signature_thumbmark_at,
            ])->reject()->keys())->isNotEmpty()
                ? 'Still needed: '.$missing->implode(', ').'.'
                // Approved, documents in: the cashier takes both dues before the file goes on to the PRA.
                : ($this->isFullyPaid() ? null : 'Waiting for the cashier: both dues must be paid before the file moves on. PRA fee balance: USD '.number_format($this->feeBalance(), 2).'; service fee balance: '.($this->service_currency ?: 'PHP').' '.number_format($this->serviceBalance(), 2).'.'),
            'submitted_to_pra' => $this->submitted_at === null || trim((string) $this->submitted_remarks) === ''
                ? 'Record when it was submitted to the PRA and what was said, then move it on.'
                : null,
            'payment_confirmed' => $this->isFullyPaid()
                ? null
                : 'Both dues must be fully paid before the file moves on. PRA fee balance: USD '.number_format($this->feeBalance(), 2).'; service fee balance: '.($this->service_currency ?: 'PHP').' '.number_format($this->serviceBalance(), 2).'.',
            'new_id_ready' => $this->isFullyPaid()
                ? null
                : 'Both dues must be fully paid before the new ID is released. PRA fee balance: USD '.number_format($this->feeBalance(), 2).'; service fee balance: '.($this->service_currency ?: 'PHP').' '.number_format($this->serviceBalance(), 2).'.',
            'released_to_client' => $this->collected_by_name
                ? null
                : 'Record who the new ID was released to.',
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
     * A year's fee: the class rate, plus each dependent beyond the two it covers (Classic).
     */
    public static function annualFee(string $visaClass, int $dependents = 0): float
    {
        return self::feeForClass($visaClass)
            + max(0, $dependents - self::DEPENDENTS_COVERED) * (self::EXTRA_DEPENDENT_FEES[$visaClass] ?? 0);
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
