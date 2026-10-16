<?php

/**
 * Country playbooks for the Non-Immigrant flow, from the counter's own
 * step-by-step sheets. Keys are the destination names on the fee schedule.
 *
 * `tasks` are what staff tick off inside each stage (id => label); a stage
 * cannot advance until its tasks are ticked. `notes` are reminders shown on the
 * stage. `fees` are what the embassy or portal charges, for the client, and are
 * separate from the counter's own fee schedule.
 */
$assessment = [
    'return_intent' => 'Applicant can prove they intend to return to the Philippines after the visit',
    'funds' => 'Applicant has sufficient funds to cover the stay',
    'no_work_intent' => 'Applicant has no intention to work or immigrate',
];

return [
    'Australia' => [
        'portal' => 'ImmiAccount',
        'fees' => ['Visa fee: AUD 202.80 (Subclass 600)'],
        'tasks' => [
            'pending' => $assessment,
            'requirements' => [
                'cover_page' => 'Cover page prepared',
            ],
            'drafting' => [
                'immi_account' => 'ImmiAccount created and draft application started',
                'draft_sent' => 'Draft sent to the applicant to check and confirm the details',
                'docs_scanned' => 'All documents scanned and saved to the Google Drive',
                'docs_uploaded' => 'All documents uploaded to the ImmiAccount',
                'finalized' => 'Application finalized with the visa officer or consultant and the applicant',
            ],
            'lodged' => [
                'submitted_paid' => 'Application submitted and the visa fee paid (AUD 202.80)',
                'bio_request' => 'Biometric request (s257A) received',
                'bio_scheduled' => "Biometrics (VFS Australia Makati) and medical (St. Luke's) scheduled",
                'attended' => 'Applicant appeared for biometrics and the medical',
            ],
            'result' => [
                'visa_checked' => 'Visa details checked on the visa advice; the applicant has a copy',
            ],
        ],
        'notes' => [
            'pending' => ['If the sponsor is a citizen, collect their bank certificate and statement.'],
            'drafting' => ['Before submitting, the applicant needs three documents: Immi Account, s257A, and the Australia VFS biometrics appointment. Submit the receipt before the documents.'],
            'lodged' => ['Applicants must attend biometrics and the medical every day the appointment runs.'],
            'result' => ['If a notification asks for another document, add it under Additional documents and upload it to the ImmiAccount.'],
        ],
    ],

    'Canada' => [
        'portal' => 'IRCC portal (GCKey)',
        'fees' => ['Visa fee: CAD 100', 'Biometric fee: CAD 85'],
        'tasks' => [
            'pending' => $assessment,
            'requirements' => [
                'cover_page' => 'Cover page prepared',
            ],
            'drafting' => [
                'ircc_account' => 'IRCC account (GCKey) created',
                'draft_started' => 'Draft application started on the IRCC portal',
                'draft_sent' => 'Draft sent to the applicant to check and confirm the details',
                'docs_scanned' => 'All documents scanned and saved to the Google Drive',
                'docs_uploaded' => 'All documents uploaded to the IRCC portal',
                'finalized' => 'Application finalized with the visa officer or consultant and the applicant',
            ],
            'lodged' => [
                'submitted_paid' => 'Application submitted; visa fee (CAD 100) and biometric fee (CAD 85) paid',
                'bio_request' => 'Biometric request notification received',
                'bio_scheduled' => 'Biometrics scheduled (VFS Canada Makati)',
                'attended' => 'Applicant appeared for biometrics',
            ],
            'result' => [
                'visa_checked' => 'Visa details checked on the returned passport',
            ],
        ],
        'notes' => [
            'result' => [
                'Check the IRCC portal every day. If a notification asks for another document, add it under Additional documents and upload it.',
                'On a "Passport request" notice, advise the applicant to submit the original passport at VFS Makati.',
            ],
        ],
    ],

    'USA' => [
        'portal' => 'DS-160 (CEAC) and U.S. Travel Docs',
        'fees' => ['Visa fee: USD 185 or PHP 11,100, paid at the designated bank (RCBC)'],
        'tasks' => [
            'pending' => $assessment,
            'requirements' => [
                'passport_checked' => 'Original passport checked and in good condition',
                'photos' => '2 pcs 2x2 pictures received',
                'cover_page' => 'Cover page prepared',
            ],
            'drafting' => [
                'ds160_drafted' => 'DS-160 drafted through CEAC (Application ID and security question saved)',
                'ds160_confirmed' => 'DS-160 confirmation page (barcode) saved',
                'travel_docs_account' => 'U.S. Travel Docs account created (Non-Immigrant Visa)',
                'draft_sent' => 'Draft sent to the applicant to check and confirm the details',
            ],
            'lodged' => [
                'fee_paid' => 'Visa fee paid and the payment receipt kept',
                'appointment_booked' => 'Biometric and interview appointment scheduled; confirmation page printed',
                'docs_prepared' => 'Appointment documents prepared: DS-160 confirmation, fee receipt, appointment page, passport, 2 pcs 2x2 pictures',
                'briefing_booked' => 'Briefing / practice interview with the visa consultant booked (2 weeks before the interview)',
                'attended' => 'Applicant attended the biometrics and interview',
            ],
            'result' => [
                'visa_checked' => 'Visa details checked on the returned passport',
            ],
        ],
        'notes' => [
            'lodged' => ['The applicant learns at the interview whether they were approved. Processing after that takes several days to a few weeks; the status can be tracked on the U.S. Visa Information and Appointment Services site.'],
        ],
    ],

    'Schengen (Europe)' => [
        'portal' => 'VFS Global, TLS contact or BLS International',
        'fees' => [],
        'tasks' => [
            'pending' => [
                'country_identified' => 'Schengen country identified: the main destination (longest stay), or the first point of entry if the stays are equal',
                'portal_checked' => 'Official visa center website and application platform checked (VFS Global, TLS contact or BLS International)',
            ] + $assessment,
            'requirements' => [
                'cover_page' => 'Cover page prepared',
            ],
            'drafting' => [
                'requirements_checked' => 'Visa requirements checked and all documents arranged in 2 sets (1 original, 1 photocopy)',
            ],
            'lodged' => [
                'appointment_booked' => 'Appointment scheduled at the Visa Application Center or embassy',
                'client_notified' => 'Applicant notified 3-5 days before; documents released, reviewed and the process explained at the Amega office',
                'attended' => 'Applicant appeared at the Visa Application Center',
            ],
            'result' => [
                'visa_checked' => 'Visa details checked on the returned passport',
                'cfo_pdos' => 'CFO or PDOS appointment set',
            ],
        ],
        'notes' => [
            'lodged' => ['After the documents are submitted, processing usually takes 10 to 45 calendar days or more, depending on volume.'],
        ],
    ],
];
