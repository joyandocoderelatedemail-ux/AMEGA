<?php

namespace App\Models;

use App\Models\Concerns\NeedsAdminApproval;
use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A PRA service request at the SRRV desk, from the PRA's own checklists:
 *
 *  - Termination of Membership (PRA-SD-FORM-0008, July 2025): cancelling the SRRV, with or without
 *    downgrading to a Temporary Visitor's Visa, and withdrawing the visa deposit.
 *  - Conversion of the Visa Deposit into an Active Investment through the sublease of a residential
 *    property (PRA-SD-FORM-0010, October 2022): SRRV Classic only, at least US$50,000 for at least
 *    25 years, no sooner than 30 days after the SRRV was issued; an ocular inspection follows.
 *  - Re-stamping of the SRRV (PRA Citizen's Charter 2025, Servicing Division): the SRRV sticker moved
 *    to the retiree's new passport. Filed on the Retiree Request Form; the PRA endorses it to the
 *    Bureau of Immigration, which runs a derogatory check and implements the new sticker.
 *
 * Which requirements apply depends on the details (reason, downgrading, deposit withdrawal,
 * representative...). The checklist keeps what was received; a file moves on only when everything
 * that applies is in.
 */
#[ScopedBy([OwnFilesScope::class])]
class SrrvServiceRequest extends Model
{
    use NeedsAdminApproval;

    /** The desk whose "view_all" permission lifts OwnFilesScope. */
    public static function ownFilesDesk(): string
    {
        return 'srrv';
    }

    public const TYPES = [
        'termination' => 'SRRV Termination of Membership',
        'conversion' => 'SRRV Conversion into Active Investment',
        'restamping' => 'SRRV Re-stamping',
    ];

    /** The pipeline of each request, in order; cancelling takes a file off it. */
    public const STAGES = [
        'termination' => [
            'documents' => 'Requirements',
            'fees_paid' => 'Fees Paid',
            'submitted' => 'Submitted to PRA',
            'processing' => 'Processing at PRA & BI',
            'completed' => 'Completed',
        ],
        'conversion' => [
            'documents' => 'Requirements',
            'submitted' => 'Submitted to PRA Investment Unit',
            'ocular_inspection' => 'Ocular Inspection',
            'completed' => 'Converted',
        ],
        'restamping' => [
            'documents' => 'Requirements',
            'fees_paid' => 'Fees Paid',
            'endorsed' => 'Endorsed to the BI',
            'implemented' => 'Sticker Implemented',
            'completed' => 'Passport Released',
        ],
    ];

    /** Re-stamping: what happened to the old passport with the SRRV sticker. */
    public const OLD_PASSPORT = [
        'surrendered' => 'Surrendered with the new passport',
        'lost' => 'Lost',
        'not_submitted' => 'Kept, or cannot be submitted',
    ];

    /** Re-stamping fees, per retiree-member (Citizen's Charter 2025). */
    public const RESTAMPING_FEE = ['usd' => 10.00, 'php' => 1010.00];

    /** Termination: the reasons on the form (subrogation only for the reasons the PRA allows). */
    public const REASONS = [
        'voluntary' => 'Voluntary (the retiree is leaving the program)',
        'death' => 'Death of the principal or spouse',
        'subrogation' => 'Subrogation by the spouse (permanent disability, separation or incapacity of the principal)',
    ];

    /** Termination fees, per member (PRA-SD-FORM-0008 page 2). */
    public const TERMINATION_FEES = [
        'cancellation' => ['usd' => 10.00, 'php' => 1000.00],
        'downgrading' => ['usd' => 20.00, 'php' => 5520.00],
    ];

    /** Conversion rules (PRA-SD-FORM-0010 reminders). */
    public const CONVERSION_MIN_AMOUNT = 50000;

    public const CONVERSION_MIN_YEARS = 25;

    public const CONVERSION_DAYS_AFTER_SRRV = 30;

    protected $fillable = [
        'reference', 'type', 'srrv_application_id', 'created_by', 'status',
        'retiree_name', 'retiree_email', 'retiree_phone', 'srrv_card_number', 'visa_class',
        'details', 'checklist', 'fee_usd', 'fee_php', 'paid_usd', 'paid_php', 'remarks',
        'submitted_at', 'inspected_at', 'completed_at',
        'approval_status', 'approval_requested_at', 'reviewed_by', 'reviewed_at', 'review_note', 'acknowledged_by', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checklist' => 'array',
            'fee_usd' => 'decimal:2',
            'fee_php' => 'decimal:2',
            'paid_usd' => 'decimal:2',
            'paid_php' => 'decimal:2',
            'submitted_at' => 'datetime',
            'inspected_at' => 'datetime',
            'completed_at' => 'datetime',
            'approval_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
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

    /** The scans filed on the request, by requirement (or "other"). */
    public function documents(): HasMany
    {
        return $this->hasMany(SrrvServiceRequestDocument::class)->latest('id');
    }

    public static function generateReference(string $type): string
    {
        $code = ['conversion' => 'CNV', 'restamping' => 'RST'][$type] ?? 'TRM';

        return 'SRV-'.$code.'-'.date('Y').'-'.strtoupper(substr(uniqid(), -5));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'SRRV Service Request';
    }

    /** @return array<string, string> */
    public function stages(): array
    {
        return self::STAGES[$this->type] ?? [];
    }

    public function stageLabel(string $stage): string
    {
        return $stage === 'cancelled' ? 'Cancelled' : ($this->stages()[$stage] ?? ucfirst(str_replace('_', ' ', $stage)));
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->stageLabel((string) $this->status);
    }

    public function nextStage(): ?string
    {
        $keys = array_keys($this->stages());
        $at = array_search($this->status, $keys, true);

        return $at === false ? null : ($keys[$at + 1] ?? null);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function detail(string $key, mixed $default = null): mixed
    {
        return ($this->details ?? [])[$key] ?? $default;
    }

    /**
     * Every requirement on the PRA checklist, grouped as the form groups them, with whether it applies
     * to this request. Key => [label, section, applies].
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function requirementsFor(string $type, array $details): array
    {
        $d = fn (string $key) => $details[$key] ?? null;
        $rep = (bool) $d('via_representative');

        if ($type === 'restamping') {
            $basic = 'Basic requirements';
            $old = $d('old_passport') ?? 'surrendered';
            $change = 'Change of name or nationality';
            $agent = 'Authorized representative';

            return [
                'retiree_request_form' => ['Duly accomplished Retiree Request Form, signed by or on behalf of the principal retiree', $basic, true],
                'official_receipt' => ['Official Receipt (PRA Cashier)', $basic, true],
                'old_passport' => ['Old passport with the SRRV sticker', $basic, $old === 'surrendered'],
                'new_passport' => ['New passport', $basic, true],
                'affidavit_of_loss' => ['Notarized Affidavit of Loss of the passport with the SRRV sticker, or a police report', $basic, $old === 'lost'],
                'affidavit_of_explanation' => ['Notarized Affidavit of Explanation for not submitting the old passport with the SRRV sticker', $basic, $old === 'not_submitted'],
                'change_of_name' => ['Certificate of Change of Name (publication or family register)', $change, (bool) $d('name_changed')],
                'change_of_nationality' => ['Certificate of Change of Nationality (subject to the BI Commissioner\'s approval)', $change, (bool) $d('nationality_changed')],
                'not_same_person' => ['Certificate of Not the Same Person (not cleared on the BI derogatory check)', 'Bureau of Immigration', (bool) $d('derogatory_hit')],
                'spa' => ['Notarized Special Power of Attorney (apostilled or authenticated if executed abroad)', $agent, $rep],
                'pra_id_copy' => ['Photocopy of the PRA ID card of the SRRV holder represented', $agent, $rep],
                'representative_id' => ["Representative's government-issued ID (original and photocopy)", $agent, $rep],
            ];
        }

        if ($type === 'conversion') {
            $section = 'Conversion through Sublease of Residential Property';

            return [
                'investment_request_form' => ['Investment Request Form', $section, true],
                'retiree_deed' => ["Duly notarized Retiree's Deed of Undertaking", $section, true],
                'sublessor_deed' => ["Duly notarized Sublessor's Deed of Undertaking", $section, true],
                'sublease_agreement' => ['Duly notarized Sublease Agreement between the sublessor and the retiree, with the PRA annotation', $section, true],
                'lessor_certification' => ['Certification from CDC, SBMA or the government entity authorizing the sublease that the agreement with PRA restriction is registered', $section, true],
                'registration_tax_exemption' => ['Copy of the Certificate of Registration and Tax Exemption', $section, true],
                'lease_agreement' => ['Duly notarized Lease Agreement between the lessor and the sublessor', $section, true],
                'spa' => ['Notarized Special Power of Attorney', 'Authorized representative', $rep],
                'representative_id' => ["Representative's valid ID (complete name, signature and photo)", 'Authorized representative', $rep],
            ];
        }

        $downgrading = (bool) $d('with_downgrading');
        $withdraw = (bool) $d('withdraw_deposit');
        $reason = $d('reason') ?? 'voluntary';
        $death = $reason === 'death';
        $estate = $d('estate') ?? 'testate';
        $cancel = 'Cancellation of the SRRV';
        $deposit = 'Withdrawal of the visa deposit';

        return [
            'service_request_form' => ['Accomplished Service Request Form', $cancel, true],
            'passport_with_srrv' => ['Passport with SRRV (or a notarized Affidavit of Loss / Explanation if it cannot be surrendered)', $cancel, true],
            'pra_id_cards' => ['Valid PRA ID cards, to be surrendered', $cancel, true],
            'valid_passport' => ['Valid (unexpired) passport, for the downgrading of the visa', $cancel, $downgrading],
            'proof_of_departure' => ['Proof of planned departure (e.g. flight ticket)', $cancel, ! $downgrading],
            'certificate_of_payment' => ['Certificate of Payment of duties and taxes (Bureau of Customs) or Bill of Lading, for a tax-free importation within 3 years of the SRRV', $cancel, (bool) $d('tax_free_importation')],
            'death_certificate' => ['Death Certificate', $cancel, $death],
            'proof_of_incapacity' => ['Proof of Incapacity of the principal', $cancel, $reason === 'subrogation'],
            'letter_of_intent_bank' => ["Service Request Form / Letter of Intent with the principal's complete bank details", $deposit, $withdraw],
            'claimant_ids' => ['Valid proof of identification of the claimants', $deposit, $withdraw && $death],
            'estate_tax' => ['Settlement of Estate Tax: BIR Form 1904 (within a year of death) or BIR Form 2313 / eCAR (after a year)', $deposit, $withdraw && $death],
            'letters_testamentary' => ['Letters Testamentary / Administration or Court Order (the principal left a will)', $deposit, $withdraw && $death && $estate === 'testate'],
            'proof_of_relationship' => ['Proof of relationship', $deposit, $withdraw && $death && $estate === 'intestate'],
            'self_adjudication' => ['Affidavit of Self-Adjudication or Extra Judicial Settlement of the Estate', $deposit, $withdraw && $death && $estate === 'intestate'],
            'affidavit_of_publication' => ['Affidavit of Publication (of the Self-Adjudication or Extra Judicial Settlement)', $deposit, $withdraw && $death && $estate === 'intestate'],
            'quitclaim' => ['Notarized Affidavit of Quitclaim and Release', $deposit, $withdraw],
            'spa' => ['Notarized Special Power of Attorney (for a BDO deposit, in the format BDO prescribes)', 'Authorized representative, agent or marketer', $rep],
            'representative_id' => ['Valid proof of identification of the representative', 'Authorized representative, agent or marketer', $rep],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public function requirements(): array
    {
        return self::requirementsFor((string) $this->type, $this->details ?? []);
    }

    /** The requirements that apply and have not been received yet (labels). */
    public function missingRequirements(): array
    {
        $received = $this->checklist ?? [];

        return collect($this->requirements())
            ->filter(fn (array $item, string $key) => $item[2] && ! in_array($key, $received, true))
            ->map(fn (array $item) => $item[0])
            ->values()->all();
    }

    /**
     * Termination fees for these details: per member, US dollars to the PRA and pesos to the BI.
     *
     * @return array{usd: float, php: float}
     */
    public static function terminationFees(array $details): array
    {
        $rate = self::TERMINATION_FEES[! empty($details['with_downgrading']) ? 'downgrading' : 'cancellation'];
        $members = max(1, (int) ($details['members'] ?? 1));

        return ['usd' => $rate['usd'] * $members, 'php' => $rate['php'] * $members];
    }

    /**
     * The fees of a request with these details, US dollars to the PRA and pesos to the BI (a
     * conversion has none).
     *
     * @return array{usd: float, php: float}|null
     */
    public static function feesFor(string $type, array $details): ?array
    {
        return match ($type) {
            'termination' => self::terminationFees($details),
            'restamping' => [
                'usd' => self::RESTAMPING_FEE['usd'] * max(1, (int) ($details['members'] ?? 1)),
                'php' => self::RESTAMPING_FEE['php'] * max(1, (int) ($details['members'] ?? 1)),
            ],
            default => null,
        };
    }

    /** Whether this kind of request has fees to record. */
    public function hasFees(): bool
    {
        return in_array($this->type, ['termination', 'restamping'], true);
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->paid_usd >= (float) $this->fee_usd && (float) $this->paid_php >= (float) $this->fee_php;
    }

    /** What the conversion still breaks of the PRA's rules, or an empty list. */
    public static function conversionProblems(array $details, ?string $visaClass): array
    {
        $problems = [];

        if ($visaClass !== null && $visaClass !== 'classic') {
            $problems[] = 'Only an SRRV Classic visa deposit can be converted into a sublease.';
        }
        if ((float) ($details['sublease_amount'] ?? 0) < self::CONVERSION_MIN_AMOUNT) {
            $problems[] = 'The sublease must be at least US$'.number_format(self::CONVERSION_MIN_AMOUNT).' (in Philippine peso equivalent).';
        }
        if ((int) ($details['sublease_years'] ?? 0) < self::CONVERSION_MIN_YEARS) {
            $problems[] = 'The sublease must be for at least '.self::CONVERSION_MIN_YEARS.' years.';
        }
        $issued = $details['srrv_issued_on'] ?? null;
        if (! $issued) {
            $problems[] = 'Enter the date the SRRV was issued.';
        } elseif (Carbon::parse($issued)->addDays(self::CONVERSION_DAYS_AFTER_SRRV)->isFuture()) {
            $problems[] = 'A conversion is allowed only '.self::CONVERSION_DAYS_AFTER_SRRV.' days after the SRRV was issued (from '.Carbon::parse($issued)->addDays(self::CONVERSION_DAYS_AFTER_SRRV)->format('M j, Y').').';
        }

        return $problems;
    }

    /** What the request still needs before it can leave its current stage, or null when it may advance. */
    public function stageBlocker(): ?string
    {
        if ($blocker = $this->approvalBlocker('advanced')) {
            return $blocker;
        }

        if ($this->status === 'documents') {
            if ($missing = $this->missingRequirements()) {
                return 'Still needed: '.implode('; ', $missing).'.';
            }
            if ($this->type === 'conversion' && $problems = self::conversionProblems($this->details ?? [], $this->visa_class)) {
                return implode(' ', $problems);
            }
        }

        // A hit on the BI derogatory check needs the Certificate of Not the Same Person before the sticker.
        if ($this->type === 'restamping' && $this->status === 'endorsed' && $this->detail('derogatory_hit')
            && ! in_array('not_same_person', $this->checklist ?? [], true)) {
            return 'The retiree was not cleared on the BI derogatory check: file the Certificate of Not the Same Person first.';
        }

        return match (true) {
            $this->hasFees() && $this->status === 'documents' && ! $this->isFullyPaid() => 'The fees must be fully paid: US$'.number_format(max(0, (float) $this->fee_usd - (float) $this->paid_usd), 2).' and ₱'.number_format(max(0, (float) $this->fee_php - (float) $this->paid_php), 2).' still due.',
            $this->type === 'conversion' && $this->status === 'ocular_inspection' && ! $this->detail('ocular_inspection_on') => 'Record the date of the ocular inspection first.',
            default => null,
        };
    }
}
