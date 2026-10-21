<?php

namespace App\Models;

use App\Models\Concerns\HasPaymentOption;
use App\Models\Concerns\HasRequirementFiles;
use App\Models\Concerns\NeedsAdminApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A petition-style case on a client sheet, one of six kinds:
 *  - spouse:    Conversion to Non-Quota Immigrant Visa by Marriage (Probationary), BI Form V-I-002
 *  - permanent: Amendment to Permanent Non-Quota Immigrant Visa by Marriage, BI Form V-I-005
 *  - renewal:   Renewal of the ACR I-Card of a permanent 13(A) resident, BI Form 2014-08-008
 *  - dual:      Retention/Re-acquisition of Philippine Citizenship under R.A. 9225, BSI03.QF.001
 *  - downgrade: Downgrading of Visa, BI checklist IRD01.QF.001, filed with a letter request
 *  - ecc:       Emigration Clearance Certificate, BI application form ARD04.QF.003
 * The first two file on the Consolidated General Application Form for Immigrant Visa
 * (BOC04.QF.001); the renewal on the ACR I-Card renewal form (BI Form 2015-08-002); dual
 * citizenship on the sworn petition (BI Form 2014-01-005); a downgrade on a letter request
 * to the Commissioner; an ECC on its application form. What each form asks beyond the
 * client sheet is kept in `details`.
 */
class ImmigrationPetition extends Model
{
    use HasPaymentOption;
    use HasRequirementFiles;
    use NeedsAdminApproval;

    /** Immigration service key => case type. */
    public const SERVICES = [
        '13a_spouse' => 'spouse',
        '13a_permanent' => 'permanent',
        'permanent_13a_series' => 'renewal',
        'dual_citizenship' => 'dual',
        'visa_downgrading' => 'downgrade',
        'ecc' => 'ecc',
    ];

    public const TITLES = [
        'spouse' => 'Conversion to Non-Quota Immigrant Visa by Marriage (Probationary)',
        'permanent' => 'Amendment to Permanent Non-Quota Immigrant Visa by Marriage',
        'renewal' => 'Renewal of the ACR I-Card',
        'dual' => 'Retention/Re-acquisition of Philippine Citizenship under R.A. 9225',
        'downgrade' => 'Downgrading of Visa',
        'ecc' => 'Emigration Clearance Certificate (ECC)',
    ];

    /** What the folder's main print button is called for each type. */
    public const FORM_LABELS = [
        'spouse' => 'Immigrant visa CGAF',
        'permanent' => 'Immigrant visa CGAF',
        'renewal' => 'ACR I-Card renewal form',
        'dual' => 'R.A. 9225 petition',
        'downgrade' => 'Letter request',
        'ecc' => 'ECC application form',
    ];

    public const CHECKLISTS = [
        'spouse' => [
            'joint_letter' => 'Joint letter request addressed to the Commissioner from the applicant and the petitioning Filipino spouse',
            'cgaf' => 'Duly accomplished CGAF (BI Form CGAF-001-Rev 2)',
            'marriage_certificate' => 'Marriage Certificate or Marriage Contract',
            'spouse_birth_certificate' => 'Birth Certificate or certified true copy of BI-issued Identification Certificate as Filipino citizen of the Filipino spouse',
            'passport_copy' => 'Photocopy of passport bio-page and latest admission with valid authorized stay',
            'nbi_clearance' => 'Valid National Bureau of Investigation (NBI) Clearance, if application is filed six (6) months or more from the date of first arrival in the Philippines',
            'bi_clearance' => 'BI Clearance Certificate',
            'quarantine_clearance' => 'Original or certified true copy of Bureau of Quarantine Medical Clearance, if applicant is a national of any of the countries listed under Annex "A" of Immigration Operations Order No. SBM-14-059-A who arrived in the Philippines on or after June 2014',
            'spa' => 'If filed by a representative: BI Accreditation ID or original SPA, with a photocopy of a valid government-issued ID',
            'dep_cgaf' => 'Each dependent: duly accomplished CGAF (BI Form CGAF-001-Rev 2)',
            'dep_passport' => 'Each dependent: photocopy of passport bio-page and latest admission with valid authorized stay',
            'dep_filiation' => 'Each dependent: proof of filiation with the petitioner',
            'dep_nbi' => 'Each dependent 15 years of age or above: valid NBI Clearance, if application is filed six (6) months or more from the date of first arrival in the Philippines',
            'dep_bi_clearance' => 'Each dependent: BI Clearance Certificate',
            'dep_quarantine' => 'Each dependent: original or certified true copy of Bureau of Quarantine Medical Clearance, if a national of a country listed under Annex "A" of Immigration Operations Order No. SBM-14-059-A who arrived in the Philippines on or after June 2014',
        ],
        'permanent' => [
            'joint_letter' => 'Joint letter request addressed to the Commissioner from the applicant and the petitioning Filipino spouse',
            'cgaf' => 'Duly accomplished CGAF (BI Form CGAF-001-Rev 1)',
            'cohabitation_affidavit' => 'Joint affidavit of continuous cohabitation of applicant and the petitioning spouse',
            'passport_copy' => 'Photocopy of passport bio-page, visa implementation page and latest admission with valid authorized stay',
            'nbi_clearance' => 'Valid National Bureau of Investigation (NBI) Clearance',
            'bi_clearance' => 'BI Clearance Certificate',
            'quarantine_clearance' => 'Original or certified true copy of Bureau of Quarantine Medical Clearance, if applicant is a national of any of the countries listed under Annex "A" of Immigration Operations Order No. SBM-14-059-A who arrived in the Philippines on or after June 2014',
            'spa' => 'If filed by a representative: BI Accreditation ID or original SPA, with a photocopy of a valid government-issued ID',
            'dep_cgaf' => 'Each dependent: duly accomplished CGAF (BI Form CGAF-001-Rev 1)',
            'dep_passport' => 'Each dependent: photocopy of passport bio-page, visa implementation page and latest admission with valid authorized stay',
            'dep_filiation' => 'Each dependent: proof of filiation with the petitioner',
            'dep_nbi' => 'Each dependent 15 years of age or above: valid NBI Clearance',
            'dep_bi_clearance' => 'Each dependent: BI Clearance Certificate',
            'dep_quarantine' => 'Each dependent: original or certified true copy of Bureau of Quarantine Medical Clearance, if a national of a country listed under Annex "A" of Immigration Operations Order No. SBM-14-059-A who arrived in the Philippines on or after June 2014',
        ],
        'renewal' => [
            'application_form' => 'Accomplished application form (BI Form 2014-08-010 Rev 0)',
            'acr_card' => 'Original ACR I-Card (to be surrendered)',
            'passport_copy' => 'Photocopy of passport bio-page, visa implementation page and latest admission with valid authorized stay',
            'boc_order' => 'Immigrant visa: photocopy of the Board of Commissioners (BOC)\'s Order of Approval',
            'ren_doj' => 'Sec. 47(a)2: photocopy of endorsement from the Department of Justice (DOJ)',
            'ren_zone' => 'Special Subic/Clark Investors or Working Visa, Cagayan Economic Zone Investor/Working Visa: photocopy of endorsement from the Subic Bay Metropolitan Authority (SBMA) / Clark Development Corporation (CDC)',
            'ren_student' => 'Student Visa: photocopy of the Student Desk-approved Order granting a Student Visa extension',
            'ren_dep_boc' => 'Re-registration of a dependent: certified true copy of the Board of Commissioners (BOC)\'s Order, for a non-immigrant applicant',
            'ren_dep_parent' => 'Re-registration of a dependent: photocopy of parent\'s passport bio-page and passport page bearing the visa implementation, and the ACR I-Card (front and back)',
            'ren_dep_photos' => 'Re-registration of a dependent: two (2) pieces of 2x2 colored photograph with white background, taken within the last three months',
            'ren_ph_birth' => 'Philippine born (permanent resident): Birth Certificate',
            'ren_ph_acr' => 'Philippine born (permanent resident): certified true copy of ACR and Native-Born Certificate of Residence (NBCR), if applicable (issued by the Records Section for internal verification)',
            'ren_ph_parent' => 'Philippine born (permanent resident): photocopy of parent\'s ACR (front and back) and Immigrant Certificate of Residence (ICR)',
            'spa' => 'If filed by a representative: BI Accreditation ID or original SPA, with a photocopy of a valid government-issued ID',
        ],
        'dual' => [
            'petition_form' => 'Duly signed and notarized petition/application form (BI Form 2014-01-005 Rev 1)',
            'photos' => 'Two (2) pieces 2x2 photograph with white background, without any facial obstruction, taken within the last three months',
            'birth_psa' => 'Proof of natural-born citizenship: original PSA Birth Certificate',
            'birth_lcr' => 'Proof of natural-born citizenship: PSA Certificate of No Record of Birth AND the Certification of Birth from the Local Civil Registrar where the petitioner was born',
            'birth_other' => 'Proof of natural-born citizenship: PSA and LCR Certificates of No Record of Birth, AND any two of: PSA Marriage Certificate, cancelled Philippine passport, Voter\'s ID, Baptismal Certificate, or another Philippine document',
            'foreign_passport' => 'Original and photocopy of valid foreign passport',
            'naturalization' => 'Photocopy of Certificate of Naturalization (with English translation), or an affidavit on how and when foreign citizenship was acquired',
            'acr_surrender' => 'BI-registered alien: original ACR I-Card and ICR/CRTV (or an Affidavit of Loss)',
            'name_variance' => 'Name differs between documents: Affidavit of One and the Same Person, and proof of the varied name (marriage certificate, annotation, court decree, etc.)',
            'spa' => 'If filed by a representative: BI Accreditation ID or original SPA, with a photocopy of a valid government-issued ID',
            'dep_supplement' => 'Each dependent child: duly signed and notarized Supplement for Dependent Form (BI Form 2014-01-006 Rev 1)',
            'dep_photos' => 'Each dependent child: two (2) pieces 2x2 photograph with white background, without any facial obstruction (e.g. eyeglasses, face mask, etc.), taken within the last three months',
            'dep_birth_certificate' => 'Each dependent child: photocopy of Birth Certificate of the Dependent Child',
            'dep_passport' => 'Each dependent child: photocopy of valid foreign passport bio-page of the Dependent Child',
            'dep_acr' => 'Dependent child who is a BI-registered alien: original ACR I-Card or Alien Certificate of Registration, and original ICR or CRTV (Affidavit of Loss executed by the Principal if lost)',
        ],
        'downgrade' => [
            'letter_request' => 'Letter request addressed to the Commissioner stating the reason(s) for downgrading, with the address and contact numbers of whoever files it',
            'passport_copy' => 'Photocopy of passport bio-page, front and back of the ACR I-Card (if applicable), visa implementation and latest admission with valid authorized stay',
            'employment_cert' => 'Certificate of Employment / Certification of Missionary Work from the petitioning company or congregation',
            'marriage_docs' => 'Request from the applicant and/or spouse with their residential address and contact numbers, and a photocopy of the marriage certificate or contract',
            'doj_indorsement' => 'Copy of the DOJ Indorsement granting the 47(A)2 visa, and proof of notice of downgrading from the DOJ',
            'cancelation_order' => 'Cancelation Order from the Special Economic Zone authorities',
            'embassy_docs' => 'Certificate of Employment from the Embassy/Consulate/International Organization, DFA Endorsement, and the Embassy\'s Note Verbale if applicable',
            'spa' => 'If filed by a representative: BI Accreditation ID or original SPA, with a photocopy of a valid government-issued ID',
        ],
        'ecc' => [
            'ecc_form' => 'Accomplished ECC application form (ARD04.QF.003)',
            'photos' => 'Four (4) pieces of 2x2 ID photos with white background (no eyeglasses or hats)',
            'passport_copy' => 'Photocopy of passport bio-page',
            'arrival_stamp' => 'Photocopy of latest arrival stamp',
            'extension_receipt' => 'Photocopy of latest visa extension receipt',
            'extension_or' => 'Photocopy of official receipt for last extension payment',
            'acr_card' => 'Photocopy of ACR I-Card (front and back)',
            'departure_ticket' => 'Photocopy of departure ticket',
        ],
    ];

    /** The visa being downgraded: each category adds its own requirement to the BI checklist. */
    public const DOWNGRADE_CATEGORIES = [
        'employment' => 'Pre-Arranged Employee Commercial Visa (9G)',
        'missionary' => 'Non-Commercial Visa (Missionary)',
        'marriage' => 'Non-Quota Immigrant Visa by Marriage (13A)',
        'trv' => 'Temporary Resident Visa (TRV)',
        '47a2' => '47(A)2 Visa',
        'special' => 'Special Visa (CWV, SCWV, SCIV, FWV, etc.)',
        '9e' => '9(E) Visa (Accredited Foreign Government Official)',
        'other' => 'Other visa',
    ];

    /** Why the visa is downgraded, as the BI checklist lists the usual reasons. */
    public const DOWNGRADE_REASONS = [
        'resignation' => 'Resignation',
        'termination' => 'Termination of employment',
        'late_filing' => 'Late filing of extension of visa',
        'dissolution' => 'Dissolution of the company',
        'other' => 'Other reason',
    ];

    /** The "Application Information" boxes on the ECC form. */
    public const ECC_KINDS = [
        'ecc' => 'Emigration Clearance Certificate (ECC)',
        'exemption' => 'Certificate of Exemption',
        'renewal' => 'ECC/CE Renewal',
        'paper' => 'Paper-Based ECC and SRC (Sec. 47(a)2 exempt visa holder)',
        'order_to_leave' => 'Order to Leave',
    ];

    /** How a dual citizenship petitioner proves natural-born citizenship (checklist item 3 a, b or c). */
    public const BIRTH_PROOFS = [
        'psa' => 'PSA Birth Certificate',
        'lcr' => 'No PSA record: LCR Certification of Birth',
        'other' => 'No PSA or LCR record: two other Philippine documents',
    ];

    /** How a parent held Philippine (or other) citizenship at the petitioner's birth. */
    public const ACQUIRED_BY = ['birth' => 'Birth', 'election' => 'Election', 'marriage' => 'Marriage', 'naturalization' => 'Naturalization'];

    /** The transactions on the ACR I-Card renewal form, in its order. */
    public const TRANSACTIONS = [
        'renewal' => 'ACR I-Card Renewal',
        'expired' => 'Expired ACR I-Card',
        'reregistration' => 'Re-registration (14 yrs. old)',
        'replacement' => 'ACR Replacement',
        'change_status' => 'Change of Visa Status',
        'lost' => 'Lost Card',
        'rejected' => 'Rejected Card',
        'damaged' => 'Damaged Card',
        'new_validity' => 'To effect new card validity',
        'amendments' => 'Amendments (Name/Nationality/Civil Status/Address/*Others)',
        'rp_src' => 'RP/SRC Issuance/Extension/Re-issuance',
        'annual_report' => 'Annual Report with Fine',
    ];

    /** The "Type of Visa Application" boxes on the immigrant visa CGAF. */
    public const APPLICATION_KINDS = ['conversion' => 'Conversion', 'amendment' => 'Amendment', 'inclusion' => 'Inclusion'];

    /** Details kept as dates, for the form's date boxes. */
    public const DATE_DETAILS = [
        'arrival_date', 'last_day_of_stay', 'passport_issued_on', 'passport_expires_on',
        'acr_issued_on', 'acr_expires_on', 'child_1_dob', 'child_2_dob', 'valid_stay',
        'lost_on', 'oath_date', 'dep_1_dob', 'dep_2_dob', 'dep_3_dob',
        'visa_issued_on', 'previous_acr_issued_on', 'rp_src_issued_on', 'rp_src_expires_on',
        'departure_date',
    ];

    protected $fillable = [
        'immigration_client_id',
        'created_by',
        'type',
        'details',
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
            'details' => 'array',
            'checklist' => 'array',
            'documents' => 'array',
            'filed_on' => 'date',
            'fee' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'approval_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'workflow_status_at' => 'datetime',
            'released_on' => 'date',
        ];
    }

    public static function typeFor(string $serviceKey): ?string
    {
        return self::SERVICES[$serviceKey] ?? null;
    }

    /** Every item any petition checklist can hold. */
    public static function allItems(): array
    {
        return array_merge(...array_values(self::CHECKLISTS));
    }

    /** Reference prefix per type: probationary, permanent, ACR I-Card renewal. */
    public const PREFIXES = ['spouse' => '13AP', 'permanent' => '13AM', 'renewal' => 'ACR', 'dual' => 'DC', 'downgrade' => 'DG', 'ecc' => 'ECC'];

    public function referencePrefix(): string
    {
        return self::PREFIXES[$this->type] ?? '13A';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ImmigrationClient::class, 'immigration_client_id');
    }

    /** One detail from the form, or null. */
    public function detail(string $key): mixed
    {
        $value = ($this->details ?? [])[$key] ?? null;

        return $value === '' ? null : $value;
    }

    /** A date detail, parsed. */
    public function dateDetail(string $key): ?Carbon
    {
        $value = $this->detail($key);

        return filled($value) ? Carbon::parse($value) : null;
    }

    public function flag(string $key): bool
    {
        return (bool) $this->detail($key);
    }

    public function isRenewal(): bool
    {
        return $this->type === 'renewal';
    }

    public function isDualCitizenship(): bool
    {
        return $this->type === 'dual';
    }

    /**
     * What an ACR I-Card renewal is for, which sets the extra documents the BI asks for. An
     * immigrant or resident visa (the Board of Commissioners' order) unless said otherwise.
     */
    public const RENEWAL_CATEGORIES = [
        'immigrant' => 'Immigrant visa, probationary to permanent, Temporary Resident, Treaty Trader or Pre-Arranged Employee Visa (Commercial/Missionary)',
        '47a2' => 'Sec. 47(a)2',
        'zone' => 'Special Subic/Clark Investors or Working Visa, Cagayan Economic Zone Investor/Working Visa',
        'student' => 'Student Visa',
        'dependent' => 'Re-registration of a dependent',
        'ph_born' => 'Philippine born (permanent resident)',
    ];

    public function renewalCategory(): string
    {
        return array_key_exists((string) $this->detail('renewal_category'), self::RENEWAL_CATEGORIES) ? $this->detail('renewal_category') : 'immigrant';
    }

    /** The proof of natural-born citizenship chosen, a PSA birth certificate unless said otherwise. */
    public function birthProof(): string
    {
        return array_key_exists((string) $this->detail('birth_proof'), self::BIRTH_PROOFS) ? $this->detail('birth_proof') : 'psa';
    }

    /** The service this case belongs to. */
    public function serviceKey(): string
    {
        return array_search($this->type, self::SERVICES, true) ?: '13a_spouse';
    }

    /**
     * The checklist items this case needs: the NBI clearance for a probationary
     * applicant only when filed six months or more after first arrival, the quarantine
     * clearance only for Annex "A" nationals, the SPA only for a representative. For dual
     * citizenship, the one proof of birth chosen, the ACR only for a BI-registered alien,
     * and the name affidavit only when the names differ. For a downgrade, the one extra
     * document its visa category needs; for an ECC, the ACR I-Card only for a registered alien.
     *
     * @return array<string, string>
     */
    public function requiredItems(): array
    {
        return collect(self::CHECKLISTS[$this->type] ?? [])->filter(fn (string $label, string $key): bool => match ($key) {
            'nbi_clearance' => $this->type !== 'spouse' || $this->flag('nbi_needed'),
            'quarantine_clearance' => $this->flag('annex_a'),
            'spa' => $this->flag('by_representative'),
            'birth_psa', 'birth_lcr', 'birth_other' => $key === 'birth_'.$this->birthProof(),
            'acr_surrender' => $this->flag('registered_alien'),
            'name_variance' => $this->flag('name_variance'),
            'employment_cert' => in_array($this->detail('visa_category'), ['employment', 'missionary'], true),
            'marriage_docs' => in_array($this->detail('visa_category'), ['marriage', 'trv'], true),
            'doj_indorsement' => $this->detail('visa_category') === '47a2',
            'cancelation_order' => $this->detail('visa_category') === 'special',
            'embassy_docs' => $this->detail('visa_category') === '9e',
            'acr_card' => $this->type !== 'ecc' || $this->flag('registered_alien'),
            // Each dependent has a checklist of their own, only when the applicant is joined by one.
            'dep_cgaf', 'dep_passport', 'dep_filiation', 'dep_bi_clearance', 'dep_supplement', 'dep_photos', 'dep_birth_certificate' => $this->flag('with_dependents'),
            'dep_nbi' => $this->flag('with_dependents') && ($this->type !== 'spouse' || $this->flag('nbi_needed')),
            'dep_quarantine' => $this->flag('with_dependents') && $this->flag('annex_a'),
            'dep_acr' => $this->flag('with_dependents') && $this->flag('dependent_alien'),
            'boc_order' => $this->renewalCategory() === 'immigrant',
            'ren_doj' => $this->renewalCategory() === '47a2',
            'ren_zone' => $this->renewalCategory() === 'zone',
            'ren_student' => $this->renewalCategory() === 'student',
            'ren_dep_boc', 'ren_dep_parent', 'ren_dep_photos' => $this->renewalCategory() === 'dependent',
            'ren_ph_birth', 'ren_ph_acr', 'ren_ph_parent' => $this->renewalCategory() === 'ph_born',
            default => true,
        })->all();
    }

    /**
     * @return array<string, string>
     */
    public function missingItems(): array
    {
        return collect($this->requiredItems())->reject(fn (string $label, string $key): bool => $this->hasChecked($key))->all();
    }
}
