<?php

namespace App\Support;

/**
 * The questions on AMEGA's country application forms (storage/app/reference-forms/VISA FORMS): China, New Zealand,
 * Australia (tourist and business), Canada (visa and eTA), the UK and the US. Each is a list of sections of
 * [key, label, type, options?] (FormSpec), printed by the shared worksheet layout (worksheets/form.blade.php)
 * under the form's own heading and bar colour. The name, birth date, sex, nationality and passport number and
 * expiry come from the applicant record, so they are not asked again here. Keys repeat across forms on purpose
 * (phone, email, home_address, civil_status...), so a worksheet starts from what the file already knows.
 */
class VisaWorksheetSpecs
{
    /** Each form's title (the edit page and the buttons), its printed heading and its section bar colour. */
    public const META = [
        'china' => ['China visa application form', 'CHINA APPLICATION FORM', '#fff200'],
        'new_zealand' => ['New Zealand visa application form', 'NEW ZEALAND APPLICATION FORM', '#c5e0b3'],
        'australia_tourist' => ['Australia visa application form', 'AUSTRALIA APPLICATION FORM', '#b4d7e2'],
        'australia_business' => ['Australia business visa application form', 'AUSTRALIA BUSINESS VISA APPLICATION FORM', '#b4d7e2'],
        'canada' => ['Canada visa application form', 'CANADA APPLICATION FORM', '#d9c2d5'],
        'canada_eta' => ['Canada eTA application form', 'CANADA eTA APPLICATION FORM', '#d9c2d5'],
        'uk' => ['UK visa application form', 'UK VISA APPLICATION FORM', '#fbe3a6'],
        'usa' => ['U.S. visa application form', 'U.S APPLICATION FORM', '#b8cce4'],
    ];

    private const SEX = ['male' => 'Male', 'female' => 'Female'];

    private const APPLYING_FOR = ['tourist' => 'Tourist', 'family' => 'Visiting family or friends', 'business' => 'Business'];

    private const PAYER = ['self' => 'Self', 'other' => 'Other person'];

    /**
     * @return array<string, list<array{0: string, 1: string, 2: string, 3?: array<string, string>}>>
     */
    public static function sections(string $form): array
    {
        $civil = VisaWorksheets::CIVIL_STATUSES;

        return match ($form) {
            'china' => [
                'Purpose of stay' => [
                    ['applying_for', 'Applicant is applying for', 'select', self::APPLYING_FOR],
                    ['significant_dates', 'Significant dates the applicant needs to be in China', 'text'],
                ],
                'Passport details' => [
                    ['passport_country', 'Country of passport', 'text'],
                    ['passport_issued_on', 'Date of issue', 'date'], ['passport_issued_by', 'Issuing authority', 'text'],
                ],
                'Country of residence and contact details of applicant' => [
                    ['res_country', 'Country', 'text'], ['home_address', 'Address', 'text'],
                    ['suburb', 'Suburb / town', 'text'], ['province', 'State / province', 'text'], ['postal_code', 'Postal code', 'text'],
                    ['phone', 'Mobile phone', 'text'], ['email', 'Email address', 'text'],
                ],
                'Travelling companion' => [
                    ['companions', 'Any other persons travelling with the applicant to China?', 'yesno'],
                    ...self::people('companion', 3, ['family' => 'Family name', 'given' => 'Given name', 'dob' => 'Date of birth', 'relationship' => 'Relationship to you']),
                ],
                'Work experience: current work' => [
                    ['work_company', 'Company name', 'text'], ['work_address', 'Address', 'text'],
                    ['work_start_date', 'Start date', 'date'], ['work_position', 'Position', 'text'], ['work_duty', 'Duty', 'text'],
                    ['work_supervisor', "Supervisor's full name", 'text'],
                ],
                'Previous work (last 5 years)' => [
                    ['prev_company', 'Company name', 'text'], ['prev_address', 'Address', 'text'],
                    ['prev_start', 'Start date', 'date'], ['prev_end', 'Date end', 'date'], ['prev_position', 'Position', 'text'],
                    ['prev_supervisor', "Supervisor's full name", 'text'],
                ],
                'Education' => [
                    ['school_name', 'Name of institution', 'text'], ['school_major', 'Major', 'text'], ['school_diploma', 'Diploma', 'text'],
                ],
                'Family information' => [
                    ...self::relative('father', 'Father'), ...self::relative('mother', 'Mother'), ...self::relative('spouse', 'Spouse'),
                ],
                'Children' => self::people('child', 2, ['name' => 'Full name', 'birthday' => 'Birthday', 'relationship' => 'Relationship to you', 'address' => 'Address', 'email' => 'Email address', 'phone' => 'Contact number']),
                'Emergency contact information' => [
                    ['emergency_name', 'Full name', 'text'], ['emergency_address', 'Address', 'text'], ['emergency_email', 'Email address', 'text'],
                ],
                'Contact in China' => [
                    ['cn_family', 'Family name', 'text'], ['cn_given', 'Given name', 'text'], ['cn_dob', 'Date of birth', 'date'],
                    ['cn_sex', 'Sex', 'select', self::SEX], ['cn_relationship', 'Relationship to you', 'text'],
                ],
                'Travel itinerary' => [
                    ['arrival_date', 'Date of arrival', 'date'], ['departure_date', 'Date of departure', 'date'], ['arrival_city', 'City of arrival', 'text'],
                ],
                'Previous travel to China' => [
                    ['had_china_visa', 'Have you been issued a Chinese visa?', 'yesno'], ['cn_visa_type', 'Type of visa', 'text'],
                    ['cn_visa_number', 'Number of visa', 'text'], ['cn_visa_place', 'Place of issue', 'text'], ['cn_visa_issued', 'Date of issue', 'date'],
                ],
                'Valid visas and previous travel' => [
                    ['other_visas', 'Do you currently hold any valid visas issued by other countries?', 'yesno'],
                    ['other_visas_list', 'If yes, which', 'text'],
                    ['countries_visited', 'Countries visited in the last 10 years', 'text'],
                ],
            ],

            'new_zealand' => [
                'Personal information of applicant' => [
                    ['place_of_birth', 'Place of birth', 'text'],
                    ['other_nationality', 'Do you hold or have you held any other nationality?', 'yesno'], ['national_id', 'National identification number', 'text'],
                ],
                'Contact person' => self::address('contact', true),
                'Person / entity paying for your trip' => [
                    ['payer', 'Paid by', 'select', self::PAYER], ['payer_name', 'Other person', 'text'],
                ],
                'Travel companion information' => self::people('companion', 2, ['surname' => 'Surname', 'given' => 'Given name', 'relationship' => 'Relationship to you']),
                'Address and phone information' => [
                    ['home_address', 'Street address', 'text'], ['city', 'City', 'text'], ['province', 'State / province', 'text'],
                    ['postal_code', 'Postal zone / ZIP code', 'text'], ['res_country', 'Country / region', 'text'],
                    ['mailing_same', 'Is your mailing address the same as your home address?', 'yesno'],
                    ['phone', 'Primary phone number', 'text'], ['work_phone', 'Work phone number', 'text'], ['email', 'Email address', 'text'],
                ],
                'Passport information' => self::passport(),
                'Contact information in New Zealand' => self::address('nz', true),
                'Family information' => [
                    ['father_surname', "Father's surname", 'text'], ['father_given', "Father's given names", 'text'], ['father_dob', "Father's date of birth", 'date'],
                    ['mother_surname', "Mother's surname", 'text'], ['mother_given', "Mother's given names", 'text'], ['mother_dob', "Mother's date of birth", 'date'],
                ],
                'Family information: spouse' => self::spouse(),
                'Present work information' => [
                    ['work_company', 'Present employer', 'text'], ['work_address', 'Street address', 'text'], ['work_city', 'City', 'text'],
                    ['work_province', 'State / province', 'text'], ['work_postal', 'Postal zone / ZIP code', 'text'], ['work_country', 'Country / region', 'text'],
                    ['work_phone_no', 'Contact number', 'text'], ['job_title', 'Job title', 'text'], ['work_start_date', 'Start date', 'date'],
                    ['monthly_income', 'Monthly income', 'text'],
                ],
                'Previous work information' => self::previousWork(),
                'Education / training information' => self::education(),
                'Personal information of sponsor' => [
                    ['sponsor_name', 'Full name', 'text'], ['sponsor_sex', 'Sex', 'select', self::SEX], ['sponsor_dob', 'Date of birth', 'date'],
                    ['sponsor_place_of_birth', 'Place of birth', 'text'], ['sponsor_relationship', 'Relationship to you', 'text'],
                    ['sponsor_phone', 'Phone number', 'text'], ['sponsor_email', 'Email address', 'text'], ['sponsor_address', 'Street address', 'text'],
                    ['sponsor_city', 'City', 'text'], ['sponsor_state', 'State', 'text'], ['sponsor_zip', 'ZIP code', 'text'],
                ],
                'Source of income of sponsor' => [
                    ['sponsor_employer', 'Present employer', 'text'], ['sponsor_employer_address', 'Street address', 'text'],
                    ['sponsor_employer_city', 'City', 'text'], ['sponsor_employer_province', 'State / province', 'text'],
                    ['sponsor_employer_postal', 'Postal zone / ZIP code', 'text'], ['sponsor_job_title', 'Job title', 'text'],
                    ['sponsor_start_date', 'Start date', 'date'], ['sponsor_monthly_income', 'Monthly income', 'text'], ['sponsor_employer_phone', 'Contact number', 'text'],
                ],
            ],

            'australia_tourist' => [
                'Purpose of stay' => [
                    ['applying_for', 'Applicant is applying for', 'select', self::APPLYING_FOR],
                    ['significant_dates', 'Significant dates the applicant needs to be in Australia', 'text'],
                ],
                ...self::australiaCommon(),
                'Work information of applicant (present and previous)' => [
                    ['work_company', 'Company name', 'text'], ['work_address', 'Street address', 'text'], ['work_city', 'City', 'text'],
                    ['work_province', 'Province', 'text'], ['job_title', 'Job title', 'text'], ['work_start_date', 'Start date', 'date'],
                    ['work_phone_no', 'Contact number', 'text'], ['work_email', 'Email address', 'text'],
                ],
                ...self::australiaContact(),
                'Address of sponsor / inviter' => [
                    ['sponsor_country', 'Country', 'text'], ['sponsor_address', 'Address', 'text'], ['sponsor_suburb', 'Suburb / town', 'text'],
                    ['sponsor_state', 'State / province', 'text'], ['sponsor_home_phone', 'Home phone', 'text'], ['sponsor_mobile', 'Mobile phone', 'text'],
                ],
            ],

            'australia_business' => [
                'Purpose of stay' => [
                    ['significant_dates', 'Significant dates the applicant needs to be in Australia', 'text'],
                ],
                'Passport details' => [
                    ['passport_country', 'Country of passport', 'text'],
                    ['passport_issued_on', 'Date of issue', 'date'], ['passport_issued_by', 'Issuing authority', 'text'],
                ],
                ...self::australiaCommon(),
                'Work information of applicant (present and previous)' => [
                    ['employment_status', 'Employment status', 'text'], ['work_address', 'Street address', 'text'], ['work_city', 'City', 'text'],
                    ['work_province', 'Province', 'text'], ['job_title', 'Job title', 'text'], ['work_start_date', 'Start date', 'date'],
                    ['work_start_from', 'Start from', 'text'], ['work_phone_no', 'Contact number', 'text'], ['work_email', 'Email address', 'text'],
                ],
                'Entry to Australia' => [
                    ['multiple_entries', 'Does the applicant intend to enter Australia on more than one occasion?', 'yesno'],
                    ['stay_length', 'How long does the applicant plan to stay (days, months, years)?', 'text'],
                    ['arrival_date', 'Planned arrival date', 'date'], ['departure_date', 'Planned final departure date', 'date'],
                ],
                ...self::australiaContact(),
                'Address of sponsor' => [
                    ['sponsor_country', 'Country', 'text'], ['sponsor_address', 'Address', 'text'], ['sponsor_suburb', 'Suburb / town', 'text'],
                    ['sponsor_state', 'State / province', 'text'], ['sponsor_postal', 'Postal code', 'text'],
                ],
                'Company in the Philippines' => self::company('ph'),
                'Company in Australia' => self::company('auc'),
                'Business activities' => [
                    ['business_activity', 'Intended business activity', 'text'], ['activity_details', 'Details of the activity', 'text'],
                    ['activity_location', 'Location of the conference or meeting', 'text'],
                    ['activity_from', 'Date from', 'date'], ['activity_to', 'Date to', 'date'],
                    ['activity_org', 'Organization / company name', 'text'], ['activity_phone', 'Contact number', 'text'], ['activity_email', 'Email address', 'text'],
                ],
            ],

            'canada' => [
                'Personal information' => [
                    ['place_of_birth', 'Place of birth', 'text'], ['civil_status', 'Marital status', 'select', $civil],
                    ['canada_activities', "Tell us more about what you'll do in Canada", 'text'],
                    ['stay_from', 'Stay: date from', 'date'], ['stay_to', 'Stay: date to', 'date'],
                ],
                'National identity document' => [
                    ['national_id', 'Document number', 'text'], ['national_id_issued', 'Date of issue', 'date'],
                ],
                'Contact details: current address' => [
                    ['home_address', 'Address', 'text'], ['city', 'City', 'text'], ['province', 'Province', 'text'], ['postal_code', 'Postal code', 'text'],
                    ['address_since', 'Living at this address since (yyyy-mm)', 'text'], ['phone', 'Phone number', 'text'], ['email', 'Email address', 'text'],
                ],
                'Previous address (last 10 years)' => [
                    ['prev_home_address', 'Address', 'text'], ['prev_home_from', 'Date started living there', 'text'], ['prev_home_to', 'Date ended living there', 'text'],
                    ['prev_home_phone', 'Phone number', 'text'], ['prev_home_email', 'Email address', 'text'],
                ],
                'Canada contact information' => [
                    ['ca_surname', 'Surname', 'text'], ['ca_given', 'Given name', 'text'], ['ca_phone', 'Phone number', 'text'],
                    ['ca_relationship', 'Relationship to you', 'text'], ['ca_email', 'Email address', 'text'], ['ca_address', 'Address', 'text'],
                    ['ca_city', 'City', 'text'], ['ca_province', 'Province', 'text'], ['ca_postal', 'Postal code', 'text'],
                ],
                'Finance' => [
                    ['funds', 'How much money do you have for your stay in Canada?', 'text'],
                    ['funds_other', 'Is someone else giving you money for your stay? Explain', 'text'],
                ],
                'Current occupation' => [
                    ['occupation', 'Occupation', 'text'], ['work_company', 'Company / employer / facility name', 'text'], ['work_address', 'Address', 'text'],
                    ['work_city', 'City', 'text'], ['work_province', 'Province', 'text'], ['work_postal', 'Postal code', 'text'],
                    ['work_from', 'Date from', 'date'], ['work_to', 'Date to', 'date'],
                ],
                'Previous occupation (past 10 years)' => [
                    ['prev_occupation', 'Occupation', 'text'], ['prev_company', 'Company / employer / facility name', 'text'], ['prev_address', 'Address', 'text'],
                    ['prev_city', 'City', 'text'], ['prev_province', 'Province', 'text'], ['prev_postal', 'Postal code', 'text'],
                    ['prev_start', 'Date from', 'date'], ['prev_end', 'Date to', 'date'],
                ],
                'Educational background' => self::education(),
                'Travel history (past 5 years)' => self::people('trip', 3, ['purpose' => 'Purpose of travel', 'from' => 'Travel date from', 'to' => 'Travel date to', 'country' => 'Country', 'location' => 'Location']),
                'Family information: spouse' => [
                    ...self::spouse(), ['spouse_occupation', 'Occupation', 'text'], ['spouse_phone', 'Contact number', 'text'],
                    ['spouse_email', 'Email address', 'text'], ['spouse_address', 'Address', 'text'],
                ],
                'Family information: children' => self::people('child', 3, ['surname' => 'Surname', 'given' => 'Given names', 'dob' => 'Date of birth', 'address' => 'Address']),
                'Family information: father' => self::parent('father'),
                'Family information: mother' => [['mother_maiden', 'Maiden name', 'text'], ...self::parent('mother')],
            ],

            'canada_eta' => [
                'Personal information' => [
                    ['place_of_birth', 'Place of birth', 'text'],
                    ['us_green_card', 'Are you a lawful permanent resident of the United States with a valid USCIS number?', 'yesno'],
                    ['held_canada_trv', 'In the past 10 years, did you hold a valid Canadian Temporary Resident Visa?', 'yesno'],
                    ['us_visa', 'Do you currently hold a valid U.S. nonimmigrant visa?', 'yesno'],
                    ['us_visa_number', 'U.S. nonimmigrant visa number', 'text'], ['us_visa_expiry', 'Date of expiry', 'date'],
                ],
                'Passport details of applicant' => [['passport_issued_on', 'Date of issue', 'date']],
                'Personal details of applicant' => [
                    ['civil_status', 'Marital status', 'select', $civil],
                    ['applied_canada_before', 'Have you ever applied for or obtained a visa, an eTA or a permit to visit, live, work or study in Canada?', 'yesno'],
                    ['home_address', 'Current address', 'text'], ['phone', 'Phone number', 'text'], ['email', 'Email address', 'text'],
                ],
                'Source of income information' => [
                    ['occupation', 'Occupation', 'text'], ['job_title', 'Job title', 'text'], ['since_year', 'Since what year', 'text'],
                    ['work_company', 'Name of employer or school', 'text'], ['work_address', 'Address', 'text'],
                    ['reason_for_travel', 'Briefly indicate your reason to travel to Canada', 'text'],
                ],
            ],

            'uk' => [
                'Personal information' => [
                    ['surname_at_birth', 'Surname at birth (former family name, if married)', 'text'],
                    ['place_of_birth', 'Place of birth', 'text'], ['country_of_birth', 'Country of birth', 'text'],
                    ['civil_status', 'Relationship status', 'select', $civil], ['phone', 'Phone number', 'text'], ['email', 'Email address', 'text'],
                    ['home_address', "Applicant's home address", 'text'], ['years_at_address', 'How long have you lived at this address?', 'text'],
                    ['home_ownership', 'What is the ownership status of your home?', 'text'],
                ],
                'Travel information' => [
                    ['visit_reason', 'What is the main reason for your visit to the UK?', 'text'],
                    ['visit_details', 'Details of the main purpose of your visit and anything else you plan to do', 'text'],
                    ['arrival_date', 'Date you plan to arrive in the UK', 'date'], ['departure_date', 'Date you plan to leave the UK', 'date'],
                ],
                'Family information: spouse / partner' => [
                    ['partner_given', 'Given names', 'text'], ['partner_family', 'Family name', 'text'], ['partner_dob', 'Date of birth', 'date'],
                    ['partner_nationality', 'Country of nationality', 'text'], ['partner_lives_with', 'Do they currently live with you?', 'yesno'],
                    ['partner_travelling', 'Will they be travelling with you to the UK?', 'yesno'],
                ],
                'Family information: mother and father' => [
                    ['mother_given', "Mother's given names", 'text'], ['mother_family', "Mother's family name", 'text'],
                    ['mother_dob', "Mother's date of birth", 'date'], ['mother_nationality', "Mother's country of nationality", 'text'],
                    ['father_given', "Father's given names", 'text'], ['father_family', "Father's family name", 'text'],
                    ['father_dob', "Father's date of birth", 'date'], ['father_nationality', "Father's country of nationality", 'text'],
                ],
                'Employment and income' => [
                    ['occupation', 'Occupation', 'text'], ['employment_status', 'Employment status', 'text'],
                    ['yearly_income', 'How much money do you earn from this job in a year?', 'text'],
                    ['other_income', 'Do you have another income or any savings?', 'text'],
                    ['monthly_spending', 'What is the total amount of money you spend each month?', 'text'],
                    ['trip_budget', 'How much money are you personally planning to spend on your visit?', 'text'],
                    ['someone_paying', 'Will anyone be paying towards the cost of your visit?', 'yesno'],
                    ['payer_name', 'Who is paying: name', 'text'], ['payer_address', 'Their address', 'text'],
                    ['payer_amount', 'How much will they be paying?', 'text'], ['payer_reason', 'Why are they helping to pay for your visit?', 'text'],
                ],
                'Family information in the UK' => [
                    ['uk_relationship', 'Relationship with you', 'text'], ['uk_given', 'Given names', 'text'], ['uk_family', 'Family name', 'text'],
                    ['uk_dob', 'Date of birth', 'date'], ['uk_nationality', 'Country of nationality', 'text'], ['uk_phone', 'Contact number', 'text'],
                    ['uk_email', 'Email', 'text'], ['uk_address', 'Address', 'text'],
                ],
                'Travel companions and accommodation' => [
                    ['organised_group', 'Will you be travelling to the UK as part of an organised group?', 'yesno'],
                    ['with_someone', 'Will you travel with someone who is not your partner, spouse or dependant?', 'yesno'],
                    ['comp_given', 'Companion: given names', 'text'], ['comp_family', 'Companion: family name', 'text'], ['comp_dob', 'Companion: date of birth', 'date'],
                    ['comp_nationality', 'Companion: country of nationality', 'text'], ['comp_relationship', 'Companion: relationship to you', 'text'],
                    ['stay_details', 'Where are you staying in the UK? Accommodation details', 'text'], ['stay_address', 'Accommodation address', 'text'],
                ],
                'Travel history: the UK' => [
                    ['been_to_uk', 'Have you been to the UK in the past 10 years?', 'yesno'],
                    ['uk_visits_count', 'How many times have you been to the UK in the past 10 years?', 'text'],
                    ['uk_reason', 'Why were you in the UK?', 'text'],
                    ...self::people('uk_trip', 5, ['date' => 'Date you arrived in the UK', 'length' => 'Number of days, weeks, months or years']),
                    ['uk_visa_10y', 'Have you been issued with a UK visa in the past 10 years?', 'yesno'], ['uk_visa_applied', 'Date of application', 'date'],
                ],
                'Travel history: other countries (past 10 years)' => self::people('trip', 6, ['country' => 'Country', 'date' => 'Date', 'length' => 'Number of days, weeks, months or years']),
            ],

            'usa' => [
                'Personal information' => [
                    ['place_of_birth', 'Place of birth', 'text'], ['civil_status', 'Marital status', 'select', $civil],
                    ['other_nationality', 'Do you hold or have you held any other nationality?', 'yesno'], ['national_id', 'National identification number', 'text'],
                ],
                'Travel information' => [
                    ['visa_type', 'Type of visa', 'select', ['b1b2' => 'Tourist - B1/B2 (Tourism/Business)', 'fiance' => 'Fiancé', 'spouse' => 'Spouse']],
                    ['specific_plans', 'Have you made specific travel plans?', 'yesno'], ['arrival_date', 'Intended date of arrival', 'date'],
                    ['us_places', 'Location you plan to visit in the US', 'text'],
                ],
                'Address in the US where you are staying' => self::address('us', true),
                'Person / entity paying for your trip' => [
                    ['payer', 'Paid by', 'select', self::PAYER], ['payer_name', 'Other person', 'text'], ['payer_relationship', 'Relationship to you', 'text'],
                ],
                'Travel companion information' => self::people('companion', 2, ['surname' => 'Surname', 'given' => 'Given name', 'relationship' => 'Relationship to you']),
                'U.S. travel information' => [
                    ['been_to_us', 'Have you ever been in the U.S.?', 'yesno'],
                    ...self::people('us_trip', 5, ['date' => 'Date arrived', 'length' => 'Length of stay']),
                    ['us_license', "Do you or did you ever hold a U.S. driver's license?", 'yesno'],
                    ['us_visa_before', 'Have you ever been issued a U.S. visa?', 'yesno'], ['us_last_visa_date', 'Date the last visa was issued', 'date'],
                    ['us_visa_number', 'Visa number', 'text'], ['same_visa_type', 'Are you applying for the same type of visa?', 'yesno'],
                    ['same_place', 'Are you applying in the same country or location where that visa was issued, and is it your place of principal residence?', 'yesno'],
                    ['ten_printed', 'Have you been ten-printed?', 'yesno'], ['visa_lost', 'Has your U.S. visa ever been lost or stolen?', 'yesno'],
                    ['visa_cancelled', 'Has your U.S. visa ever been cancelled or revoked?', 'yesno'],
                    ['refused', 'Have you ever been refused a U.S. visa, refused admission, or withdrawn your application for admission at the port of entry?', 'yesno'],
                    ['petition', 'Has anyone ever filed an immigrant petition on your behalf with USCIS?', 'yesno'],
                ],
                'Address and phone information' => [
                    ['home_address', 'Street address', 'text'], ['city', 'City', 'text'], ['province', 'Province', 'text'], ['postal_code', 'Postal zone / ZIP code', 'text'],
                    ['mailing_same', 'Is your mailing address the same as your home address?', 'yesno'],
                    ['phone', 'Primary phone number', 'text'], ['work_phone', 'Work phone number', 'text'], ['email', 'Email address', 'text'],
                    ['social_platform', 'Social media provider / platform', 'text'], ['social_id', 'Social media identifier', 'text'],
                ],
                'Passport information' => self::passport(),
                'U.S. contact information' => self::address('usc', true),
                'Family information' => [
                    ['father_surname', "Father's surname", 'text'], ['father_given', "Father's given names", 'text'], ['father_dob', "Father's date of birth", 'date'],
                    ['father_in_us', 'Is your father in the US?', 'yesno'],
                    ['mother_surname', "Mother's surname", 'text'], ['mother_given', "Mother's given names", 'text'], ['mother_dob', "Mother's date of birth", 'date'],
                    ['mother_in_us', 'Is your mother in the US?', 'yesno'],
                    ['us_relatives', 'Do you have any immediate relatives, not including parents, in the US?', 'yesno'],
                    ['rel_surname', 'Relative: surname', 'text'], ['rel_given', 'Relative: given name', 'text'],
                    ['rel_relationship', 'Relative: relationship to you', 'text'], ['rel_status', 'Relative: status in the U.S.', 'text'],
                ],
                'Family information: spouse' => self::spouse(),
                'Present work information' => [
                    ['employment_status', 'Employment', 'select', ['employed' => 'Employed', 'homemaker' => 'Homemaker', 'unemployed' => 'Unemployed', 'self_employed' => 'Business / self-employed']],
                    ['occupation', 'Type of occupation', 'text'], ['work_company', 'Company name', 'text'], ['work_address', 'Address', 'text'],
                    ['work_start_date', 'Start date', 'date'], ['monthly_salary', 'Monthly salary', 'text'], ['work_phone_no', 'Contact number of the company', 'text'],
                    ['work_duties', 'Describe your duties', 'text'],
                ],
                'Previous work' => [
                    ['prev_company', 'Company name', 'text'], ['prev_title', 'Job title', 'text'], ['prev_address', 'Address', 'text'],
                    ['prev_start', 'Start date', 'date'], ['prev_end', 'End date', 'date'], ['prev_phone', 'Contact number of the company', 'text'],
                    ['prev_supervisor', 'Supervisor full name', 'text'], ['prev_duties', 'Describe your duties', 'text'],
                ],
                'Educational background' => self::education(),
                'Additional information' => [
                    ['countries_visited', 'Countries visited in the last 5 years', 'text'], ['languages', 'Languages you speak', 'text'],
                ],
            ],

            default => [],
        };
    }

    /** The Australia forms' shared questions, tourist and business alike. */
    private static function australiaCommon(): array
    {
        return [
            'COVID-19 related travel restrictions' => [
                ['covid_impacted', 'Did the applicant hold a previous Visitor Visa impacted by COVID-19 travel restrictions?', 'yesno'],
            ],
            'National identity card and citizenship' => [
                ['has_national_id', 'Does this applicant have a national identity card?', 'yesno'], ['national_id', 'National ID number', 'text'],
                ['place_of_birth', 'Place of birth', 'text'], ['civil_status', 'Relationship status', 'select', VisaWorksheets::CIVIL_STATUSES],
                ['other_names', 'Is the applicant currently, or have they ever been, known by any other names?', 'yesno'], ['other_names_list', 'Other names / spellings', 'text'],
                ['citizen_of_passport', 'Is this applicant a citizen of the selected country of passport?', 'yesno'],
                ['other_citizenship', 'Is this applicant a citizen of any other country?', 'yesno'],
            ],
            'Previous travel to Australia' => [
                ['travelled_before', 'Has this applicant previously travelled to Australia?', 'yesno'],
                ['applied_before', 'Has this applicant previously applied for a visa to Australia?', 'yesno'], ['grant_number', 'Grant number', 'text'],
                ['other_passport', 'Does this applicant have any other passport or documents for travel?', 'yesno'],
            ],
            'Health examination' => [
                ['health_exam', 'Has this applicant undertaken a health examination for an Australian visa in the last 12 months?', 'yesno'],
            ],
            'Travelling companion' => [
                ['companions', 'Any other persons travelling with the applicant to Australia?', 'yesno'],
                ...self::people('companion', 3, ['family' => 'Family name', 'given' => 'Given name', 'dob' => 'Date of birth', 'relationship' => 'Relationship to you']),
            ],
            'Country of residence and contact details of applicant' => [
                ['res_country', 'Country', 'text'], ['home_address', 'Address', 'text'], ['city', 'City / suburb', 'text'],
                ['province', 'State / province', 'text'], ['postal_code', 'Postal code', 'text'],
                ['phone', 'Mobile phone', 'text'], ['email', 'Email address', 'text'],
            ],
            'Non-accompanying members of the family unit' => self::people('family', 3, ['family' => 'Family name', 'given' => 'Given name', 'dob' => 'Date of birth', 'relationship' => 'Relationship to you']),
        ];
    }

    /** Australia: relatives, friends or a contact in Australia. */
    private static function australiaContact(): array
    {
        return [
            'Relatives, friends or contact in Australia' => [
                ['visiting_contact', 'Will the applicant visit any relatives, friends or contact while in Australia?', 'yesno'],
                ['au_family', 'Family name', 'text'], ['au_given', 'Given name', 'text'], ['au_sex', 'Sex', 'select', self::SEX],
                ['au_dob', 'Date of birth', 'date'], ['au_relationship', 'Relationship to you', 'text'], ['au_email', 'Email address', 'text'],
                ['au_postal', 'Postal code', 'text'], ['au_residency', 'Australian residency status', 'text'],
            ],
        ];
    }

    /**
     * $count people, each with the same questions: companion1_family, companion1_given...
     *
     * @param  array<string, string>  $fields  suffix => label
     */
    private static function people(string $prefix, int $count, array $fields): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            foreach ($fields as $suffix => $label) {
                $type = in_array($suffix, ['dob', 'birthday', 'from', 'to', 'date'], true) ? 'date' : 'text';
                $rows[] = ["{$prefix}{$i}_{$suffix}", ($count > 1 ? "#{$i} " : '').$label, $type];
            }
        }

        return $rows;
    }

    /** A relative on the China form: full name, birthday, address, contact number, email. */
    private static function relative(string $prefix, string $who): array
    {
        return [
            ["{$prefix}_name", "{$who}: full name", 'text'], ["{$prefix}_birthday", "{$who}: birthday", 'date'],
            ["{$prefix}_address", "{$who}: address", 'text'], ["{$prefix}_phone", "{$who}: contact number", 'text'], ["{$prefix}_email", "{$who}: email address", 'text'],
        ];
    }

    /** A parent on the Canada form. */
    private static function parent(string $prefix): array
    {
        return [
            ["{$prefix}_surname", 'Surname', 'text'], ["{$prefix}_given", 'Given name', 'text'], ["{$prefix}_place_of_birth", 'Place of birth', 'text'],
            ["{$prefix}_dob", 'Date of birth', 'date'], ["{$prefix}_email", 'Email address', 'text'], ["{$prefix}_phone", 'Contact number', 'text'],
            ["{$prefix}_address", 'Address', 'text'], ["{$prefix}_occupation", 'Occupation', 'text'],
        ];
    }

    /** A contact person: surname, given name, relationship, phone, email, street, city, state, ZIP. */
    private static function address(string $prefix, bool $person): array
    {
        return [
            ...($person ? [["{$prefix}_surname", 'Surname', 'text'], ["{$prefix}_given", 'Given name', 'text'], ["{$prefix}_relationship", 'Relationship to you', 'text']] : []),
            ["{$prefix}_phone", 'Phone number', 'text'], ["{$prefix}_email", 'Email address', 'text'], ["{$prefix}_address", 'Street address', 'text'],
            ["{$prefix}_city", 'City', 'text'], ["{$prefix}_state", 'State', 'text'], ["{$prefix}_zip", 'ZIP code', 'text'],
        ];
    }

    private static function passport(): array
    {
        return [
            ['travel_document', 'Passport / travel document type', 'select', VisaWorksheets::TRAVEL_DOCUMENTS],
            ['passport_issued_on', 'Date of issue', 'date'], ['passport_issued_by', 'Issued by', 'text'],
            ['lost_passport', 'Have you ever lost a passport or had one stolen?', 'yesno'],
        ];
    }

    private static function spouse(): array
    {
        return [
            ['spouse_surname', 'Surname', 'text'], ['spouse_given', 'Given name', 'text'], ['spouse_maiden', 'Maiden name', 'text'],
            ['spouse_dob', 'Date of birth', 'date'], ['spouse_place_of_birth', 'Place of birth', 'text'],
        ];
    }

    private static function previousWork(): array
    {
        return [
            ['prev_company', 'Name of company / institution', 'text'], ['prev_address', 'Street address', 'text'], ['prev_city', 'City', 'text'],
            ['prev_province', 'State / province', 'text'], ['prev_country', 'Country / region', 'text'], ['prev_title', 'Job title', 'text'],
            ['prev_start', 'Start date', 'date'], ['prev_end', 'End date', 'date'],
        ];
    }

    private static function education(): array
    {
        return [
            ['school_name', 'Name of school / institution', 'text'], ['school_address', 'Address', 'text'], ['course', 'Course of study', 'text'],
            ['school_from', 'Date of attendance from', 'text'], ['school_to', 'Date of attendance to', 'text'],
        ];
    }

    private static function company(string $prefix): array
    {
        return [
            ["{$prefix}_company", 'Company name', 'text'], ["{$prefix}_contact_name", 'Name of main contact', 'text'], ["{$prefix}_position", 'Position', 'text'],
            ["{$prefix}_email", 'Email address', 'text'], ["{$prefix}_phone", 'Contact number', 'text'], ["{$prefix}_address", 'Address of the company', 'text'],
        ];
    }
}
