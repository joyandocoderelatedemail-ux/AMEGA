<?php

namespace App\Models;

use App\Models\Concerns\HasRequirementFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Re Stamping / Amendment case on a client sheet: the IRD Consolidated General
 * Application Form (BI Form IRD01.QF.006) for a transfer of admission status to a new
 * passport, or an amendment/correction of admission (including a failure to check,
 * encode or stamp at arrival), with the BI's documentary checklist for each.
 */
class ImmigrationRestamping extends Model
{
    use HasRequirementFiles;

    /** The boxes under "Application" on the form, in its order. */
    public const APPLICATIONS = [
        'transfer' => 'Transfer of Admission Status',
        'amendment' => 'Amendment/Correction of Admission Status',
        'failed_check' => 'Failed to Check',
        'failed_encode' => 'Failed to Encode',
        'failed_stamp' => 'Failed to Stamp',
    ];

    /** The applications that file under the amendment/correction checklist. */
    public const AMENDMENT_APPLICATIONS = ['amendment', 'failed_check', 'failed_encode', 'failed_stamp'];

    /**
     * Checklist of Documentary Requirements for Application for Transfer of Admission
     * Status from Old/Lost/Cancelled Passport to New Passport (BI Form IRD01.QF.002 Rev 3).
     */
    public const TRANSFER_CHECKLIST = [
        'cgaf' => 'Duly accomplished application form (IRD Consolidated General Application Form)',
        'certificate_of_travel' => 'Certificate of Travel duly issued by the Certificate and Clearance Section',
        'manifest' => 'Passenger Manifest or Airline Certification',
        'old_passport' => 'Original old passport (if available)',
        'new_passport' => 'Original new passport (only, for lost passport)',
    ];

    /**
     * Checklist of Documentary Requirements for Amendment/Correction of Admission
     * (BI Form IRD01.QF.003 Rev 1).
     */
    public const AMENDMENT_CHECKLIST = [
        'amendment_form' => 'Duly accomplished application form (BI Form 2016-10-004 Rev 1)',
        'passport' => 'Valid original passport',
        'visa_page' => 'Photocopy of the passport page with the valid visa implementation, and the ACR I-Card, if applicable',
        'birth_certificate' => 'Balikbayan: photocopy of the Birth Certificate',
        'marriage_contract' => 'Balikbayan travelling with a Filipino spouse: Marriage Contract or other proof of travelling together',
    ];

    /** Items marked "if applicable" or "if available": listed, but not chased. */
    public const OPTIONAL_ITEMS = ['old_passport', 'visa_page'];

    protected $fillable = [
        'immigration_client_id',
        'created_by',
        'applications',
        'middle_name',
        'old_passport_number',
        'landline',
        'arrival_date',
        'flight_number',
        'bibs_number',
        'admission_status',
        'reason',
        'passport_lost',
        'balikbayan',
        'with_filipino_spouse',
        'checklist',
        'documents',
        'filed_on',
        'released_on',
    ];

    protected function casts(): array
    {
        return [
            'applications' => 'array',
            'arrival_date' => 'date',
            'filed_on' => 'date',
            'released_on' => 'date',
            'passport_lost' => 'boolean',
            'balikbayan' => 'boolean',
            'with_filipino_spouse' => 'boolean',
            'checklist' => 'array',
            'documents' => 'array',
        ];
    }

    /** Every item either checklist can hold. */
    public static function allItems(): array
    {
        return self::TRANSFER_CHECKLIST + self::AMENDMENT_CHECKLIST;
    }

    public function referencePrefix(): string
    {
        return 'RS';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ImmigrationClient::class, 'immigration_client_id');
    }

    public function applies(string $application): bool
    {
        return in_array($application, $this->applications ?? [], true);
    }

    public function isTransfer(): bool
    {
        return $this->applies('transfer');
    }

    public function isAmendment(): bool
    {
        return array_intersect(self::AMENDMENT_APPLICATIONS, $this->applications ?? []) !== [];
    }

    /**
     * The items this case needs, by checklist: the new passport only when the old one
     * was lost, the birth certificate for a balikbayan, the marriage contract when
     * travelling with a Filipino spouse, the visa page for anyone else.
     *
     * @return array<string, string>
     */
    public function requiredItems(): array
    {
        $items = ($this->isTransfer() ? self::TRANSFER_CHECKLIST : [])
            + ($this->isAmendment() ? self::AMENDMENT_CHECKLIST : []);

        return collect($items)->filter(fn (string $label, string $key): bool => match ($key) {
            'new_passport' => (bool) $this->passport_lost,
            'visa_page' => ! $this->balikbayan,
            'birth_certificate' => (bool) $this->balikbayan,
            'marriage_contract' => $this->balikbayan && $this->with_filipino_spouse,
            default => true,
        })->all();
    }

    /**
     * @return array<string, string>
     */
    public function missingItems(): array
    {
        return collect($this->requiredItems())
            ->reject(fn (string $label, string $key): bool => in_array($key, self::OPTIONAL_ITEMS, true) || $this->hasChecked($key))
            ->all();
    }
}
