<?php

/**
 * Document checklists for the Non-Immigrant flow, transcribed from the counter's
 * per-country checklist sheets (ORIG and COPY columns). Keys are the destination
 * names on the fee schedule; each destination has one or more variants.
 *
 * A variant is split into sections:
 *  - applicant (and the US "to start the application" part) gate the Receiving
 *    Documents step: every row must be received (original or copy ticked),
 *    except rows marked `optional`, and a `group` needs at least one of its items.
 *  - minor gates only for an applicant under 18.
 *  - sponsor / inviter is ticked off but never gates, since not every file has one.
 *
 * Row ids are unique per owner (applicant rows and minor rows share one namespace,
 * sponsor rows use the sp_ prefix). `doc` ties a row to an upload type, so an
 * uploaded passport scan counts as the passport copy being received.
 */
$r = fn (string $id, string $label, array $o = []): array => ['id' => $id, 'label' => $label] + $o;
$g = fn (string $id, string $label, array $items, array $o = []): array => ['id' => $id, 'label' => $label, 'items' => $items] + $o;

// ---- Applicant rows, shared across sheets --------------------------------------------------
$origPassport = $r('orig_passport', 'Original current and old passport');
$passportCopy = $r('passport_copy', 'Copy of passport: bio page and all used pages with amendments, visas and entry/exit stamps (current and old)', ['doc' => 'passport_scan']);
$photos = $r('photos', '2 pcs passport-sized photo (colored / white background)');
$photos2x2 = $r('photos', '2 pcs 2x2 photo (colored / white background)');
$formAmega = $r('app_form', 'Application form c/o Amega');
$form = $r('app_form', 'Application form');
$psaBirth = $r('psa_birth', 'PSA birth certificate, issued within a year (late registered: baptismal certificate and Form 137)');
$psaBirthPlain = $r('psa_birth', 'PSA birth certificate / marriage certificate');
$psaMarriage = $r('psa_marriage', 'PSA marriage certificate, issued within a year', ['optional' => true]);
$prevVisa = $r('prev_visa', 'Copy of previous visa (if any)', ['optional' => true]);
$govId = $r('gov_id', 'Government ID');
$itinerary = $r('itinerary', 'Daily itinerary');
$flightHotel = $r('flight_hotel', 'Flight reservation and hotel accommodation');
$coverLetter = $r('cover_letter', 'Cover letter');
$prcId = $r('prc_id', 'PRC ID', ['optional' => true]);

// Proof of income / financial support: any one of these will do, for the applicant
$income = fn (string $id, string $label, string $bank, bool $bankStatementsRow = true): array => $g($id, $label, [
    $r('bank_cert', $bank),
    $r('employment_cert', 'Employment certification'),
    $r('pay_slip', 'Pay slip'),
    $r('itr', 'Income tax return'),
    $r('leave_of_absence', 'Leave of absence'),
    $r('company_id', 'Company ID'),
    $r('company_profile', 'Company profile (business)'),
    $r('business_reg', 'Business registration (Mayors permit, DTI, Brgy. clearance)'),
]);

$japanIncome = $g('income', 'Proof of income', [
    $r('employment_cert', 'Employment certification (with compensation, address of company, contact number and email)'),
    $r('pay_slip', 'Pay slip'),
    $r('leave_of_absence', 'Leave of absence'),
    $r('company_id', 'Company ID'),
    $r('company_profile', 'Company profile (business)'),
    $r('business_reg', 'Business registration (Mayor permit, DTI, Brgy. clearance, SEC)'),
]);

$minor = [
    $r('minor_school', 'School registration'),
    $r('minor_baptismal', 'Certificate of baptismal'),
    $r('minor_letter', 'Letter of explanation to support travel'),
];

// ---- Sponsor / inviter rows ----------------------------------------------------------------
$spPassport = $r('sp_passport', 'Copy of passport: bio page and all used pages with amendments, visas and entry/exit stamps');
$spInvitation = $r('sp_invitation', 'Invitation letter');
$spGuarantee = $r('sp_guarantee', 'Guarantee letter');
$spObligation = $r('sp_obligation', 'Formal obligation');
$spIncome = fn (string $bank = 'Bank certification with bank statements for the past 6 months'): array => $g('sp_income', 'Proof of income', [
    $r('sp_bank_cert', $bank),
    $r('sp_employment_cert', 'Employment certification'),
    $r('sp_company_profile', 'Company profile (business)'),
    $r('sp_business_reg', 'Business registration (Mayors permit, DTI, Brgy. clearance)'),
    $r('sp_itr', 'Income tax return'),
]);
$spRelationshipDocs = $r('sp_relationship_docs', 'PSA birth certificate / marriage certificate (to prove the relationship), if any', ['optional' => true]);
$spRelationship = $g('sp_relationship', 'Proof of relationship with sponsor', [
    $r('sp_rel_pictures', 'Pictures'),
    $r('sp_rel_conversation', 'Conversation'),
    $r('sp_rel_remittances', 'Remittances'),
]);
$spDriver = $r('sp_driver', 'Driver license');

/**
 * Assemble a variant from its rows.
 *
 * @param  list<array<string, mixed>>  $applicant
 * @param  list<array<string, mixed>>  $sponsor
 */
$variant = fn (string $label, string $purpose, array $applicant, array $sponsor, ?array $minorRows = null, ?string $note = null, array $extra = []): array => [
    'label' => $label,
    'purpose' => $purpose,
    'note' => $note,
    'sections' => array_values(array_filter([
        ['key' => 'applicant', 'label' => 'For applicant', 'owner' => 'applicant', 'gating' => true, 'rows' => $applicant],
        ...$extra,
        $minorRows ? ['key' => 'minor', 'label' => 'For a minor applicant', 'owner' => 'applicant', 'gating' => 'minor', 'rows' => $minorRows] : null,
        ['key' => 'sponsor', 'label' => 'For sponsor / inviter', 'owner' => 'sponsor', 'gating' => false, 'rows' => $sponsor],
    ])),
];

// Japan: the bank rows are top-level, the rest of the income proof is a group.
$japanApplicant = [
    $origPassport, $passportCopy, $photos, $formAmega,
    $r('multiple_visa_form', 'Multiple visa application form', ['optional' => true]),
    $r('bank_cert', 'Bank certificate with ADB for the past 6 months'),
    $r('bank_statements', 'Bank statements for the past 6 months'),
    $r('itr', 'Income tax return'),
    $psaBirth, $psaMarriage, $japanIncome, $prcId, $prevVisa, $govId, $itinerary,
];
$japanSponsorBase = [$spPassport, $spInvitation, $spGuarantee, $spIncome(), $spRelationshipDocs];
$japanResidence = [$r('sp_residence', 'Proof of residence (Juminhiyo)'), $r('sp_residence_id', 'Residence ID')];

return [
    'Japan' => [
        'japan_tourist' => $variant('Tourist', 'tourist', $japanApplicant, [...$japanSponsorBase, $spRelationship], $minor, 'Fees PHP 3,500 (service fee of Amega; appointment fee excluded).'),
        'japan_visit_relative' => $variant('Visit Relative', 'family', $japanApplicant, [...$japanSponsorBase, ...$japanResidence, $spRelationship], $minor),
        'japan_sofa' => $variant('SOFA Status', 'family', $japanApplicant, [...$japanSponsorBase, ...$japanResidence, $spRelationship], $minor),
    ],

    'Australia' => [
        'australia_tourist' => $variant('Tourist', 'tourist', [
            $passportCopy, $r('old_passport', 'Old passport (if any)', ['optional' => true]), $photos,
            $income('income', 'Proof of income', 'Bank certification with bank statements for the past 6 months'),
            $psaBirthPlain, $r('psa_children', 'PSA birth certificate of children (if any)', ['optional' => true]),
            $r('other_assets', 'Other proof of assets', ['optional' => true]), $prevVisa, $govId,
        ], [$spPassport, $spInvitation, $spIncome(), $spDriver, $spRelationshipDocs, $spRelationship], $minor, 'Fees PHP 17,500 (visa fee and service fee of Amega; other fees excluded).'),
        'australia_business' => $variant('Business', 'business', [
            $passportCopy, $r('old_passport', 'Old passport (if any)', ['optional' => true]), $photos,
            $income('income', 'Proof of income', 'Bank certification with bank statements for the past 6 months'),
            $psaBirthPlain, $r('psa_children', 'PSA birth certificate of children (if any)', ['optional' => true]),
            $r('other_assets', 'Other proof of assets', ['optional' => true]), $prevVisa, $govId,
        ], [
            $spPassport, $spInvitation, $r('sp_itinerary', 'Travel itinerary'),
            $g('sp_income', 'Proof of income', [
                $r('sp_bank_cert', 'Bank certification with bank statements for the past 6 months'),
                $r('sp_itr', 'Income tax return'),
                $r('sp_company_profile', 'Company profile (business)'),
                $r('sp_business_reg', 'Business registration'),
            ]),
            $spDriver, $spRelationshipDocs, $spRelationship,
        ], null, 'Fees PHP 17,500 (visa fee and service fee of Amega; other fees excluded). Sponsor is the inviter in Australia.'),
    ],

    'Canada' => [
        'canada_tourist' => $variant('Tourist', 'tourist', [
            $origPassport, $passportCopy, $photos, $formAmega, $psaBirth, $psaMarriage,
            $income('income', 'Proof of financial support', 'Bank certification with bank statements for the past 3 months'),
            $prevVisa, $govId, $flightHotel, $r('cover_letter', 'Cover letter / other proof of ties in the Philippines'),
        ], [
            $spPassport, $spInvitation, $spIncome(), $spRelationshipDocs, $spRelationship, $r('sp_residence', 'Proof of residence'),
        ], $minor),
    ],

    'United Kingdom' => [
        'uk_tourist' => $variant('Tourist', 'tourist', [
            $origPassport, $passportCopy, $photos, $form, $psaBirth, $psaMarriage,
            $income('income', 'Proof of financial support', 'Bank certification with bank statements for the past 3 months'),
            $prevVisa, $govId, $r('ticket_copy', 'Copy of ticket'), $r('hotel', 'Hotel accommodation'),
        ], [
            $spPassport, $spInvitation, $spIncome(), $spRelationshipDocs, $spRelationship, $r('sp_residence', 'Proof of residence in the UK'),
        ], $minor),
    ],

    'USA' => [
        'us_tourist_business' => $variant('Tourist / Business', 'tourist', [
            $passportCopy, $photos2x2, $r('ds160', 'DS-160 application form c/o Amega'),
        ], [
            $spPassport, $spInvitation,
            $g('sp_income', 'Proof of income', [
                $r('sp_bank_cert', 'Bank certification with bank statements for the past 6 months'),
                $r('sp_employment_cert', 'Employment certification'),
                $r('sp_company_profile', 'Company profile (business)'),
                $r('sp_business_reg', 'Business registration (Mayors permit, DTI, Brgy. clearance)'),
            ]),
            $spDriver, $spRelationshipDocs, $spRelationship,
        ], $minor, 'Fees PHP 21,000 (visa fee and service fee of Amega; other fees excluded).', [
            ['key' => 'interview', 'label' => 'Documents to prepare before the interview (optional)', 'owner' => 'applicant', 'gating' => false, 'rows' => [
                $income('income', 'Proof of income', 'Bank certification with bank statements for the past 6 months'),
                $psaBirthPlain, $r('psa_children', 'PSA birth certificate of children (if any)', ['optional' => true]),
                $r('other_assets', 'Other proof of assets', ['optional' => true]), $prevVisa, $govId,
            ]],
        ]),
    ],

    'Schengen (Europe)' => [
        'schengen_tourist' => $variant('Tourist', 'tourist', [
            $origPassport, $passportCopy, $photos, $form, $psaBirth, $psaMarriage, $r('travel_insurance', 'Travel insurance'),
            $income('income', 'Proof of financial support', 'Bank certification with bank statements for the past 3 months'),
            $prcId, $prevVisa, $govId, $itinerary, $flightHotel, $r('cover_letter', 'Cover letter: ties in the Philippines'),
        ], [
            $spPassport, $spInvitation, $spObligation, $spIncome(), $spRelationshipDocs, $spRelationship,
            $r('sp_residence', 'Proof of residence in the Schengen country (if any)', ['optional' => true]),
        ], $minor),
        'germany_visit_relative' => $variant('Germany: Visit Relative', 'family', [
            $origPassport, $passportCopy, $photos, $form, $psaBirth, $psaMarriage, $r('travel_insurance', 'Travel insurance'),
            $income('income', 'Proof of financial support', 'Bank certification with bank statements for the past 3 months'),
            $prcId, $prevVisa, $govId, $itinerary, $flightHotel, $coverLetter,
        ], [
            $spPassport, $spInvitation, $r('sp_obligation', 'Formal obligations'), $spIncome(), $spRelationshipDocs, $spRelationship,
            $r('sp_residence', 'Proof of residence in the Schengen country (if any)', ['optional' => true]),
        ], $minor),
    ],

    'Korea' => [
        'korea_tourist' => $variant('Tourist', 'tourist', [
            $origPassport, $passportCopy, $photos, $form, $psaBirth, $psaMarriage,
            $g('income', 'Proof of income', [
                $r('bank_cert', 'Bank certification with ADB, 3 months'),
                $r('bank_statements', 'Bank statements for the past 3 months'),
                $r('itr', 'Income tax return'),
                $r('employment_cert', 'Employment certification (declared: with compensation, address of company, telephone number, email address)'),
                $r('pay_slip', 'Pay slip, 3 months'),
                $r('leave_of_absence', 'Leave of absence'),
                $r('company_id', 'Company ID'),
                $r('company_profile', 'Company profile (business)'),
                $r('business_reg', 'Business registration (Mayors permit, DTI, Brgy. clearance, SEC)'),
            ]),
            $r('prc_ibp_id', 'PRC or IBP ID copy', ['optional' => true]), $prevVisa, $govId, $coverLetter,
        ], [
            $spPassport, $spInvitation,
            $g('sp_income', 'Proof of income', [
                $r('sp_bank_cert', 'Bank certification with ADB'),
                $r('sp_bank_statements', 'Bank statements for the past 3 months'),
                $r('sp_employment_cert', 'Employment certification (declared: with compensation, address of company, telephone number, email address)'),
                $r('sp_company_profile', 'Company profile (business)'),
                $r('sp_business_reg', 'Business registration (Mayors permit, DTI, Brgy. clearance)'),
                $r('sp_itr', 'Income tax return'),
            ]),
            $spRelationshipDocs, $spRelationship,
        ], $minor, 'Fees PHP 3,500 (visa fee and service fee of Amega; other fees excluded).'),
    ],

    'China' => [
        'china_tourist' => $variant('Tourist', 'tourist', [
            $origPassport, $passportCopy, $photos, $formAmega, $psaBirth, $psaMarriage,
            $income('income', 'Proof of financial support', 'Bank certification with bank statements for the past 3 months, issued one month before the planned travel'),
            $prevVisa, $govId, $flightHotel, $coverLetter,
        ], [
            $spPassport, $r('sp_invitation', 'Invitation letter with stamps and the name of the applicant, passport number and date of birth'),
            $spIncome('Bank certification with bank statements for the past 6 months, issued 1 month before the planned travel'),
            $spRelationshipDocs, $spRelationship,
        ], $minor),
        'china_business' => $variant('Business', 'business', [
            $origPassport, $passportCopy, $photos, $formAmega, $psaBirth, $psaMarriage,
            $income('income', 'Proof of financial support', 'Bank certification with bank statements for the past 3 months, issued one month before the planned travel'),
            $prevVisa, $govId, $flightHotel, $coverLetter,
        ], [
            $spPassport, $r('sp_invitation', 'Invitation letter with stamps and the name of the applicant, passport number and date of birth'),
            $spIncome('Bank certification with bank statements for the past 6 months, issued 1 month before the planned travel'),
            $spRelationshipDocs, $spRelationship,
        ], $minor),
    ],

    'New Zealand' => [
        'nz_tourist' => $variant('Tourist', 'tourist', [
            $origPassport, $passportCopy, $photos, $form, $psaBirth, $psaMarriage,
            $income('income', 'Proof of financial support', 'Bank certification with bank statements for the past 3 months'),
            $prevVisa, $govId, $flightHotel, $coverLetter,
        ], [
            $spPassport, $spInvitation, $spIncome(), $spRelationshipDocs, $spRelationship, $r('sp_residence', 'Proof of residence'),
        ], $minor),
    ],
];
