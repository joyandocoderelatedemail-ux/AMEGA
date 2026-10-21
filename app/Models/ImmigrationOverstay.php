<?php

namespace App\Models;

use App\Models\Concerns\HasPaymentOption;
use App\Models\Concerns\HasRequirementFiles;
use App\Models\Concerns\NeedsAdminApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Visa Overstaying case on a client sheet: the reasons and dates the BI's Request
 * for Reconsideration on the Updating and Extension of Authorized Stay (BI Form
 * 2014-01-011) asks for, the documentary checklist, and its filing and release.
 * The missed extensions themselves are paid on the client sheet's ledger.
 */
class ImmigrationOverstay extends Model
{
    use HasPaymentOption;
    use HasRequirementFiles;
    use NeedsAdminApproval;

    /**
     * What the BI asks for: its checklist for extension and updating of stay
     * (IRD04.QF.007), plus the request letter and what the letter itself says to attach.
     */
    public const CHECKLIST = [
        'reconsideration' => 'Request for Reconsideration on the Updating and Extension of Authorized Stay (BI Form 2014-01-011), signed',
        'cgaf' => 'Duly accomplished Consolidated General Application Form (CGAF) for Extension of Temporary Visitor’s Visa',
        'passport' => 'Original Passport/Travel Document of the applicant',
        'downgrading_order' => 'Photocopy of Downgrading Order',
        'overstay_letter' => 'Notarized letter of explanation for overstaying with attached original or Certified True Copy (CTC) of supporting documents',
        'departure_ticket' => 'Confirmed departure ticket and an original affidavit that the applicant will be leaving the Philippines',
        'spa' => 'Special Power of Attorney (SPA) and a photocopy of the representative’s valid government-issued ID',
    ];

    /** The items that also appear on the BI's printed checklist, which ticks them from this case. */
    public const BI_CHECKLIST_ITEMS = ['cgaf', 'passport', 'downgrading_order', 'overstay_letter'];

    /** An overstay longer than this many months also needs the notarized letter of explanation. */
    public const LETTER_AFTER_MONTHS = 6;

    protected $fillable = [
        'immigration_client_id',
        'created_by',
        'expired_on',
        'reason_failed',
        'reason_stay',
        'departure_date',
        'destination',
        'by_representative',
        'representative_name',
        'representative_accreditation_no',
        'representative_relationship',
        'checklist',
        'documents',
        'filed_on',
        'fee',
        'payment_type',
        'amount_paid',
        'terms_count',
        'approval_status',
        'approval_requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'acknowledged_by',
        'acknowledged_at',
        'workflow_status',
        'workflow_status_at',
        'released_on',
    ];

    protected function casts(): array
    {
        return [
            'expired_on' => 'date',
            'departure_date' => 'date',
            'filed_on' => 'date',
            'fee' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'approval_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'workflow_status_at' => 'datetime',
            'released_on' => 'date',
            'by_representative' => 'boolean',
            'checklist' => 'array',
            'documents' => 'array',
        ];
    }

    public function referencePrefix(): string
    {
        return 'OS';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ImmigrationClient::class, 'immigration_client_id');
    }

    /** The day the overstay is counted to: the filing date once filed, today until then. */
    private function countedTo(): Carbon
    {
        return ($this->filed_on ?? now())->copy()->startOfDay();
    }

    /** Whole days past the authorized stay, or null without an expiry date. */
    public function daysOverstayed(): ?int
    {
        if ($this->expired_on === null) {
            return null;
        }

        return max(0, (int) $this->expired_on->copy()->startOfDay()->diffInDays($this->countedTo(), false));
    }

    /** "4 months, 12 days" past the authorized stay. */
    public function overstayLength(): ?string
    {
        if ($this->expired_on === null) {
            return null;
        }

        $diff = $this->expired_on->copy()->startOfDay()->diff($this->countedTo());

        if ($diff->invert || $diff->days === 0) {
            return 'Not overstayed yet';
        }

        $months = $diff->y * 12 + $diff->m;

        return collect([
            $months ? $months.' '.str('month')->plural($months) : null,
            $diff->d ? $diff->d.' '.str('day')->plural($diff->d) : null,
        ])->filter()->implode(', ');
    }

    /** Past six months, the BI also wants the notarized letter of explanation. */
    public function needsExplanationLetter(): bool
    {
        return $this->expired_on !== null
            && $this->countedTo()->gt($this->expired_on->copy()->addMonthsNoOverflow(self::LETTER_AFTER_MONTHS));
    }

    /**
     * The items this case needs, in checklist order: the letter of explanation past six
     * months, the ticket when the client is leaving soon, the SPA for a representative.
     * The downgrading order is listed but optional.
     *
     * @return array<string, string>
     */
    public function requiredItems(): array
    {
        return collect(self::CHECKLIST)->filter(fn (string $label, string $key): bool => match ($key) {
            'overstay_letter' => $this->needsExplanationLetter(),
            'departure_ticket' => $this->departure_date !== null,
            'spa' => (bool) $this->by_representative,
            default => true,
        })->all();
    }

    /**
     * What is still missing; the downgrading order only applies to some clients.
     *
     * @return array<string, string>
     */
    public function missingItems(): array
    {
        return collect($this->requiredItems())
            ->reject(fn (string $label, string $key): bool => $key === 'downgrading_order' || $this->hasChecked($key))
            ->all();
    }
}
