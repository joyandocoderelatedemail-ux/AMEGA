<?php

namespace App\Support;

/**
 * AMEGA's country worksheets for the Non-Immigrant (visit visa) desk: the one-page intake
 * forms in the office's own style that collect what each embassy's application asks.
 * One per applicant, filled from the applicant's record and their `form_details`.
 *
 * The name, birth date, sex, nationality and passport come from the applicant record;
 * sections() lists only what the worksheet asks beyond that.
 */
class VisaWorksheets
{
    /** Destination on the file => worksheet. */
    public const COUNTRIES = [
        'Schengen (Europe)' => 'schengen',
        'Korea' => 'korea',
    ];

    public const TITLES = [
        'schengen' => 'Schengen visa worksheet',
        'korea' => 'Korea visa worksheet',
    ];

    public const CIVIL_STATUSES = ['single' => 'Single', 'married' => 'Married', 'registered_partnership' => 'Registered partnership', 'separated' => 'Separated', 'divorced' => 'Divorced', 'widowed' => 'Widow(er)', 'other' => 'Other'];

    public const SCHENGEN_PURPOSES = [
        'tourism' => 'Tourism', 'business' => 'Business', 'family' => 'Visiting family or friends', 'cultural' => 'Cultural',
        'sports' => 'Sports', 'official' => 'Official visit', 'medical' => 'Medical reasons', 'study' => 'Study',
        'transit' => 'Airport transit', 'other' => 'Other',
    ];

    public const KOREA_PURPOSES = ['tourism' => 'Tourism', 'family' => 'Visiting family or friends', 'business' => 'Business', 'medical' => 'Medical treatment', 'other' => 'Other'];

    public const SCHENGEN_ENTRIES = ['single' => 'Single entry', 'two' => 'Two entries', 'multiple' => 'Multiple entries'];

    public const TRAVEL_DOCUMENTS = ['ordinary' => 'Ordinary passport', 'diplomatic' => 'Diplomatic passport', 'service' => 'Service passport', 'official' => 'Official passport', 'special' => 'Special passport', 'other' => 'Other travel document'];

    public const COST_COVERED = ['self' => 'By the applicant himself/herself', 'sponsor' => 'By a sponsor (host, company, organisation)'];

    /** The worksheet for a file's destination, or null when there is none yet. */
    public static function forCountry(?string $country): ?string
    {
        return self::COUNTRIES[(string) $country] ?? null;
    }

    /**
     * The worksheet's fields beyond the applicant record, as FormSpec sections.
     *
     * @return array<string, list<array{0: string, 1: string, 2: string, 3?: array<string, string>}>>
     */
    public static function sections(string $worksheet): array
    {
        return match ($worksheet) {
            'schengen' => [
                'Personal information' => [
                    ['surname_at_birth', 'Surname at birth (former family name, if married)', 'text'],
                    ['place_of_birth', 'Place of birth', 'text'], ['country_of_birth', 'Country of birth', 'text'],
                    ['civil_status', 'Civil status', 'select', self::CIVIL_STATUSES],
                    ['phone', 'Phone number', 'text'], ['email', 'E-mail address', 'text'],
                    ['home_address', "Applicant's home address", 'text'], ['occupation', 'Occupation', 'text'],
                    ['national_id', 'National ID number (if applicable)', 'text'],
                ],
                'Travel document' => [
                    ['travel_document', 'Type of travel document', 'select', self::TRAVEL_DOCUMENTS],
                    ['passport_issued_on', 'Date of issue', 'date'], ['passport_issued_by', 'Issued by (country)', 'text'],
                ],
                'The journey' => [
                    ['purpose', 'Main purpose of the journey', 'select', self::SCHENGEN_PURPOSES],
                    ['purpose_info', 'Additional information on purpose of stay', 'text'],
                    ['main_destination', 'Member state(s) of main destination', 'text'], ['first_entry', 'Member state of first entry', 'text'],
                    ['entries', 'Number of entries requested', 'select', self::SCHENGEN_ENTRIES],
                    ['arrival_date', 'Intended date of arrival', 'date'], ['departure_date', 'Intended date of departure', 'date'],
                ],
                'For minors: parental authority / legal guardian' => [
                    ['guardian_surname', 'Surname', 'text'], ['guardian_first_name', 'First name', 'text'],
                    ['guardian_address', 'Address', 'text'], ['guardian_phone', 'Phone number', 'text'],
                    ['guardian_email', 'E-mail address', 'text'], ['guardian_nationality', 'Nationality', 'text'],
                ],
                'Family member who is an EU, EEA or CH citizen' => [
                    ['eu_surname', 'Surname', 'text'], ['eu_first_name', 'First name', 'text'],
                    ['eu_dob', 'Date of birth', 'date'], ['eu_nationality', 'Nationality', 'text'],
                    ['eu_document', 'Travel document or ID card number', 'text'], ['eu_relationship', 'Family relationship', 'text'],
                ],
                'Inviting person and accommodation' => [
                    ['host_surname', "Inviting person's surname", 'text'], ['host_first_name', "Inviting person's first name", 'text'],
                    ['hotel_name', 'Hotel or temporary accommodation', 'text'], ['host_phone', 'Phone number', 'text'],
                    ['host_address', "Inviting person's address", 'text'], ['hotel_address', 'Hotel / accommodation address', 'text'],
                    ['cost_covered_by', 'Cost of travelling and living is covered', 'select', self::COST_COVERED],
                ],
            ],
            'korea' => [
                'Personal information' => [
                    ['surname_at_birth', 'Surname at birth (former family name, if married)', 'text'],
                    ['place_of_birth', 'Place of birth', 'text'], ['country_of_birth', 'Country of birth', 'text'],
                    ['civil_status', 'Civil status', 'select', self::CIVIL_STATUSES],
                    ['phone', 'Phone number', 'text'], ['email', 'E-mail address', 'text'],
                    ['home_address', "Applicant's home address", 'text'],
                    ['purpose', 'Main purpose of the journey', 'select', self::KOREA_PURPOSES],
                    ['arrival_date', 'Intended date of arrival', 'date'],
                ],
                'Work information' => [
                    ['work_street', 'Street address', 'text'], ['work_city', 'City', 'text'], ['work_province', 'Province', 'text'],
                    ['job_title', 'Job title', 'text'], ['work_start_date', 'Start date', 'date'], ['work_start_from', 'Start from', 'text'],
                    ['work_phone', 'Contact number', 'text'], ['work_email', 'E-mail address', 'text'],
                ],
                'Education information' => [
                    ['school_name', 'Name of institution', 'text'], ['school_address', 'Address', 'text'],
                    ['course', 'Course of study', 'text'], ['school_from', 'Date of attendance from', 'text'], ['school_to', 'Date of attendance to', 'text'],
                ],
                'Family information' => [
                    ['spouse_surname', "Spouse's surname", 'text'], ['spouse_first_name', "Spouse's first name", 'text'],
                    ['spouse_address', "Spouse's address", 'text'], ['spouse_phone', "Spouse's phone number", 'text'],
                    ['spouse_email', "Spouse's e-mail address", 'text'], ['spouse_nationality', "Spouse's nationality", 'text'],
                    ['has_children', 'Do you have any children?', 'yesno'], ['children_count', 'Number of children', 'text'],
                ],
                'Invitation' => [
                    ['invited', 'Is anyone inviting the applicant?', 'yesno'],
                    ['host_name', 'Name of inviting person', 'text'], ['host_relationship', 'Relationship to applicant', 'text'],
                    ['host_birthday', 'Birthday', 'date'], ['host_phone', 'Phone number', 'text'], ['host_email', 'E-mail address', 'text'],
                    ['host_address', 'Address', 'text'],
                ],
            ],
            default => [],
        };
    }

    /** @return array<string, list<string>> */
    public static function rules(string $worksheet): array
    {
        return FormSpec::rules(self::sections($worksheet));
    }
}
