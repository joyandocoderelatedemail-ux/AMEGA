<?php

namespace App\Models;

use App\Models\Concerns\HasRequirementFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A petition-style case on a client sheet, one of four kinds:
 *  - spouse:    Conversion to Non-Quota Immigrant Visa by Marriage (Probationary), BI Form V-I-002
 *  - permanent: Amendment to Permanent Non-Quota Immigrant Visa by Marriage, BI Form V-I-005
 *  - renewal:   Renewal of the ACR I-Card of a permanent 13(A) resident, BI Form 2014-08-008
 *  - dual:      Retention/Re-acquisition of Philippine Citizenship under R.A. 9225, BSI03.QF.001
 * The first two file on the Consolidated General Application Form for Immigrant Visa
 * (BOC04.QF.001); the renewal on the ACR I-Card renewal form (BI Form 2014-08-010); dual
 * citizenship on the sworn petition (BI Form 2014-01-005). What each form asks beyond the
 * client sheet is kept in `details`.
 */
class ImmigrationPetition extends Model
{
    use HasRequirementFiles;

    /** Immigration service key => case type. */
    public const SERVICES = [
        '13a_spouse' => 'spouse',
        '13a_permanent' => 'permanent',
        'permanent_13a_series' => 'renewal',
        'dual_citizenship' => 'dual',
    ];

    public const TITLES = [
        'spouse' => 'Conversion to Non-Quota Immigrant Visa by Marriage (Probationary)',
        'permanent' => 'Amendment to Permanent Non-Quota Immigrant Visa by Marriage',
        'renewal' => 'Renewal of the ACR I-Card',
        'dual' => 'Retention/Re-acquisition of Philippine Citizenship under R.A. 9225',
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
        ],
        'renewal' => [
            'application_form' => 'Accomplished application form (BI Form 2014-08-010 Rev 0)',
            'acr_card' => 'Original ACR I-Card (to be surrendered)',
            'passport_copy' => 'Photocopy of passport bio-page, visa implementation page and latest admission with valid authorized stay',
            'boc_order' => 'Immigrant visa: photocopy of the Board of Commissioners (BOC)\'s Order of Approval',
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
        ],
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
    ];

    protected $fillable = [
        'immigration_client_id',
        'created_by',
        'type',
        'details',
        'checklist',
        'documents',
        'filed_on',
        'released_on',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checklist' => 'array',
            'documents' => 'array',
            'filed_on' => 'date',
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
    public const PREFIXES = ['spouse' => '13AP', 'permanent' => '13AM', 'renewal' => 'ACR', 'dual' => 'DC'];

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
     * and the name affidavit only when the names differ.
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
