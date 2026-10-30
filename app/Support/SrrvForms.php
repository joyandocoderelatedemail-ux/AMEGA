<?php

namespace App\Support;

use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;

/**
 * The PRA forms the SRRV desk prints: the Application Form for Principal Retiree
 * (PRA-CR-FORM-0004), the Checklist of Requirements (PRA-CR-FORM-0001), the Medical
 * Examination (PRA-CR-FORM-0012) and the Retiree Request Form (PRA-SD-FORM-0006).
 *
 * What they ask beyond the desk's own columns is kept in the file's `form_details`,
 * described here once: the edit form draws its fields from sections(), and the
 * controllers validate and save them through rules() and clean().
 */
class SrrvForms
{
    public const FORMS = ['application', 'dependent', 'checklist', 'medical', 'request'];

    public const LABELS = [
        'application' => 'Application form',
        'dependent' => 'Dependent application form',
        'checklist' => 'Checklist',
        'medical' => 'Medical certificate',
        'request' => 'Retiree request form',
    ];

    /** The SRRV Application wizard's steps after the overview (step 0, new files only). */
    public const WIZARD_STEPS = [
        1 => 'Service & Class',
        2 => 'Retiree',
        3 => 'Requirements',
        4 => 'Fees',
        5 => 'PRA Form Details',
        6 => 'Remarks & Save',
    ];

    public const SRRV_OPTIONS = ['smile' => 'SMILE', 'classic' => 'Classic', 'human_touch' => 'Human Touch', 'courtesy' => 'Courtesy'];

    public const PRIOR_VISAS = ['tourist' => 'Tourist Visa', 'working' => 'Working Visa', 'investment' => 'Investment Visa', 'missionary' => 'Missionary Visa', 'student' => 'Student Visa', 'others' => 'Others'];

    public const PLANS = ['tourism' => 'Tourism/Travel', 'investment' => 'Investment', 'employment' => 'Employment', 'others' => 'Others'];

    public const TRANSACTIONS = [
        'id_renewal' => 'ID Renewal', 'id_replacement' => 'ID Replacement', 'restamping' => 'Restamping',
        'certificate_membership' => 'Certificate of Membership', 'certificate_deposit' => 'Certificate of Visa Deposit', 'others' => 'Others',
    ];

    public const OFFICES = ['head_office' => 'Head Office', 'satellite' => 'Satellite', 'online' => 'Online'];

    public const INCOME = ['pension' => 'Pension', 'business' => 'Business', 'employment' => 'Employment', 'investments' => 'Investments', 'others' => 'Others'];

    public const BUSINESS = [
        'government' => 'Government & Public Administration', 'entertainment' => 'Entertainment & Media', 'food' => 'Food Services',
        'technology' => 'Technology & Communication', 'retail' => 'Retail & Consumer Goods', 'manufacturing' => 'Manufacturing',
        'transportation' => 'Transportation', 'construction' => 'Construction & Real Estate', 'education' => 'Education',
        'tourism' => 'Tourism & Hospitality', 'financial' => 'Financial Services', 'healthcare' => 'Healthcare',
        'agriculture' => 'Agriculture', 'religious' => 'Religious & Social Works',
    ];

    public const SKILLS = ['executive' => 'Executive / Managerial', 'admin_support' => 'Administrative Support', 'technical' => 'Technical / Skilled', 'manual' => 'Manual Labor', 'others' => 'Others'];

    public const CIVIL_STATUSES = ['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed'];

    /**
     * The edit form's sections: title => list of [key, label, type, options?]. Types are
     * text, date, select (options), checks (options, several may apply) and yesno.
     *
     * @return array<string, list<array{0: string, 1: string, 2: string, 3?: array<string, string>}>>
     */
    public static function sections(bool $renewal = false): array
    {
        $request = [
            'Retiree Request Form' => [
                ['request_office', 'Filed at', 'select', self::OFFICES],
                ['transactions', 'Transaction/s', 'checks', self::TRANSACTIONS],
                ['transaction_note', 'SRRV nos., years or purpose', 'text'],
                ['address_local', 'Address (local)', 'text'],
                ['address_abroad', 'Address (abroad)', 'text'],
                ['other_contacts', 'Other contact nos. (Viber, WhatsApp, ...)', 'text'],
                ['alternate_email', 'Alternate email', 'text'],
                ['income', 'Source(s) of income', 'checks', self::INCOME],
                ['income_other', 'Other source of income', 'text'],
                ['income_company', 'Company / employer / pension provider', 'text'],
                ['income_address', 'Its address', 'text'],
                ['income_contact', 'Its contact information', 'text'],
                ['business', 'Nature of business or expertise', 'checks', self::BUSINESS],
                ['skill', 'Professional / skill level', 'checks', self::SKILLS],
            ],
        ];

        if ($renewal) {
            return $request;
        }

        $family = [['spouse_name', 'Spouse name', 'text'], ['spouse_dob', 'Spouse date of birth', 'date'], ['spouse_id', 'Spouse ID no.', 'text'], ['spouse_included', 'Spouse included?', 'yesno']];
        foreach (range(1, 4) as $i) {
            array_push($family, ["child_{$i}_name", "Child {$i} name", 'text'], ["child_{$i}_dob", "Child {$i} date of birth", 'date'], ["child_{$i}_id", "Child {$i} ID no.", 'text'], ["child_{$i}_included", "Child {$i} included?", 'yesno']);
        }

        $history = [
            ['arrival_date', 'Date of arrival in the Philippines', 'date'],
            ['visa_expires_on', 'Expiry of tourist visa / others', 'date'],
            ['entry_visa', 'Entry visa to the Philippines', 'text'],
            ['visited_before', 'Visited the Philippines before this travel?', 'yesno'],
            ['prior_visa', 'If yes, entry visa then', 'select', self::PRIOR_VISAS],
            ['prior_visa_other', 'Other visa (specify)', 'text'],
        ];
        foreach (range(1, 4) as $i) {
            array_push($history, ["residence_{$i}_period", "Residence {$i}: period (mm/yyyy - mm/yyyy)", 'text'], ["residence_{$i}_address", "Residence {$i}: address", 'text']);
        }

        $work = [];
        foreach (range(1, 3) as $i) {
            array_push($work, ["education_{$i}_level", "Education {$i}: attainment", 'text'], ["education_{$i}_school", "Education {$i}: school and location", 'text'], ["education_{$i}_period", "Education {$i}: from/to", 'text']);
        }
        array_push($work, ['company', 'Present company / business, and address', 'text'], ['company_contact', 'Its contact no.', 'text'],
            ['plans', 'Other plans in the Philippines', 'checks', self::PLANS], ['plans_other', 'Other plans (specify)', 'text']);
        foreach (range(1, 3) as $i) {
            array_push($work, ["employment_{$i}_company", "Employment {$i}: company and address", 'text'], ["employment_{$i}_title", "Employment {$i}: job title", 'text'], ["employment_{$i}_period", "Employment {$i}: from/to", 'text']);
        }

        return [
            'Personal & passport' => [
                ['srrv_option', 'SRRV option', 'select', self::SRRV_OPTIONS],
                ['last_name', 'Last name', 'text'], ['first_name', 'First name', 'text'], ['middle_name', 'Middle name', 'text'],
                ['alias', 'Alias (AKA)', 'text'], ['religion', 'Religion', 'text'],
                ['gender', 'Gender', 'select', ['M' => 'Male', 'F' => 'Female']],
                ['place_of_birth', 'Place of birth', 'text'], ['id_no', 'ID no.', 'text'],
                ['civil_status', 'Civil status', 'select', self::CIVIL_STATUSES],
                ['height', 'Height', 'text'], ['weight', 'Weight', 'text'],
                ['passport_no', 'Passport no.', 'text'], ['passport_place', 'Passport place of issue', 'text'],
                ['passport_issued_on', 'Passport date of issue', 'date'], ['passport_expires_on', 'Passport valid until', 'date'],
            ],
            'Addresses & contact' => [
                ['home_address', 'Home country address', 'text'],
                ['home_phone', 'Home telephone', 'text'], ['home_fax', 'Home fax', 'text'], ['home_mobile', 'Home mobile', 'text'], ['home_email', 'Home email', 'text'],
                ['primary_address', 'Primary address in the Philippines', 'text'],
                ['secondary_address', 'Secondary address in the Philippines', 'text'],
                ['ph_phone', 'Philippine telephone', 'text'], ['ph_fax', 'Philippine fax', 'text'], ['ph_mobile', 'Philippine mobile', 'text'], ['ph_email', 'Philippine email', 'text'],
            ],
            'Family' => $family,
            'Parents & emergency contact' => [
                ['father_name', 'Name of father', 'text'], ['father_age', 'Father\'s age', 'text'],
                ['mother_name', 'Name of mother', 'text'], ['mother_age', 'Mother\'s age', 'text'],
                ['emergency_name', 'Emergency contact person', 'text'], ['emergency_contact', 'Their contact no.', 'text'],
                ['emergency_nationality', 'Their nationality', 'text'], ['emergency_relationship', 'Relationship', 'text'],
                ['emergency_address', 'Their address', 'text'],
            ],
            'Travel & residence history' => $history,
            'Education & work' => $work,
            'Marketer & deposit' => [
                ['marketer', 'Registered name of marketer', 'text'], ['accreditation_no', 'PRA accreditation no.', 'text'],
                ['representative', 'Name of representative', 'text'], ['representative_phone', 'Representative telephone', 'text'],
                ['bank', 'Depository bank', 'text'], ['bank_reference', 'Bank reference no.', 'text'], ['actual_deposit', 'Actual deposit', 'text'],
            ],
        ] + $request;
    }

    /** @return array<string, list<string>> */
    public static function rules(bool $renewal = false): array
    {
        return FormSpec::rules(self::sections($renewal));
    }

    /**
     * The validated form fields worth keeping: blanks dropped.
     *
     * @return array<string, mixed>
     */
    public static function clean(?array $form): array
    {
        return FormSpec::clean($form);
    }

    /**
     * Last, first and middle name: the form fields when given, otherwise split from the
     * single name the desk keeps ("Smith, John" or "John Smith").
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    /**
     * The dependents named on the file's Family section, in the form's order: the spouse, then
     * each child. Each prints on their own Application Form for Dependent Retiree.
     *
     * @return array<string, string> key (spouse, child_1, ...) => name
     */
    public static function dependents(?SrrvApplication $application): array
    {
        if (! $application) {
            return [];
        }

        return collect(['spouse', 'child_1', 'child_2', 'child_3', 'child_4'])
            ->mapWithKeys(fn (string $key) => [$key => trim((string) $application->detail("{$key}_name"))])
            ->filter()
            ->all();
    }

    public static function nameParts(SrrvApplication|SrrvRenewal|SrrvServiceRequest|null $file, ?SrrvApplication $application = null): array
    {
        if (! $file) {
            return [null, null, null];
        }

        $detail = fn (string $key) => $file->detail($key) ?? $application?->detail($key);

        if ($detail('last_name') || $detail('first_name')) {
            return [$detail('last_name'), $detail('first_name'), $detail('middle_name')];
        }

        $name = trim((string) $file->retiree_name);

        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));

            return [$last, $first, null];
        }

        $words = preg_split('/\s+/', $name) ?: [];
        $last = count($words) > 1 ? array_pop($words) : null;

        return [$last, implode(' ', $words) ?: null, null];
    }
}
