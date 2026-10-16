@php
    /**
     * The BI's checklists of documentary requirements for the 13(A) services, on
     * 8.5 x 13 in paper, ticked from the case:
     *  - spouse:    BI Form V-I-002-Rev 1, Conversion to Non-Quota Immigrant Visa by Marriage (Probationary)
     *  - permanent: BI Form V-I-005-Rev 1, Amendment to Permanent Non-Quota Immigrant Visa by Marriage
     *  - renewal:   BI Form 2014-08-008 Rev 0, Renewal of the ACR I-Card
     */
    $blankForm = $blank;
    $type = $petition->type ?: 'spouse';
    $tick = fn (string $item) => ! $blankForm && $petition->hasChecked($item) ? '✓' : '';
    $name = $blankForm ? '' : strtoupper(collect([$client->last_name, $client->given_name, $petition->detail('middle_name')])->filter()->implode(', '));
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
    $annexA = 'Original or certified true copy of Bureau of Quarantine Medical Clearance, if applicant is a national of any of the countries listed under Annex “A” of Immigration Operations Order No. SBM-14-059-A who arrived in the Philippines on or after June 2014.';

    $forms = [
        'spouse' => [
            'code' => 'BI FORM V-I-002-Rev 1',
            'title' => 'CONVERSION TO NON-QUOTA IMMIGRANT VISA BY MARRIAGE<br>(PROBATIONARY)',
            'heading' => 'CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR<br>CONVERSION TO NON-QUOTA IMMIGRANT VISA BY MARRIAGE (PROBATIONARY)',
            'items' => [
                'joint_letter' => 'Joint letter request addressed to the Commissioner from the applicant and the petitioning Filipino spouse;',
                'cgaf' => 'Duly accomplished CGAF (BI Form CGAF-001-Rev 2);',
                'marriage_certificate' => 'Marriage Certificate or Marriage Contract;',
                'spouse_birth_certificate' => 'Birth Certificate or certified true copy of BI-issued Identification Certificate as Filipino citizen of the Filipino spouse;',
                'passport_copy' => 'Photocopy of passport bio-page and latest admission with valid authorized stay;',
                'nbi_clearance' => 'Valid National Bureau of Investigation (NBI) Clearance, if application is filed six (6) months or more from the date of first arrival in the Philippines;',
                'bi_clearance' => 'BI Clearance Certificate; and',
                'quarantine_clearance' => $annexA,
            ],
            'dependents' => [
                'Duly accomplished CGAF (BI Form CGAF-001-Rev 2);',
                'Photocopy of passport bio-page and latest admission with valid authorized stay',
                'Proof of filiation with the petitioner;',
                'Valid NBI Clearance for each dependent 15 years of age or above, if application is filed six (6) months or more from the date of first arrival in the Philippines;',
                'BI Clearance Certificate; and',
                $annexA,
            ],
            'instructions' => 'cgaf',
            'evaluator' => 'Central Receiving Unit Evaluator:',
        ],
        'permanent' => [
            'code' => 'BI FORM V-I-005-Rev 1',
            'title' => 'AMENDMENT TO PERMANENT NON-QUOTA<br>IMMIGRANT VISA BY MARRIAGE',
            'heading' => 'CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR AMENDMENT TO<br>PERMANENT NON-QUOTA IMMIGRANT VISA BY MARRIAGE',
            'items' => [
                'joint_letter' => 'Joint letter request addressed to the Commissioner from the applicant and the petitioning Filipino spouse;',
                'cgaf' => 'Duly accomplished CGAF (BI Form CGAF-001-Rev 1);',
                'cohabitation_affidavit' => 'Joint affidavit of continuous cohabitation of applicant and the petitioning spouse;',
                'passport_copy' => 'Photocopy of passport bio-page, visa implementation page and latest admission with valid authorized stay;',
                'nbi_clearance' => 'Valid National Bureau of Investigation (NBI) Clearance;',
                'bi_clearance' => 'BI Clearance Certificate; and',
                'quarantine_clearance' => $annexA,
            ],
            'dependents' => [
                'Duly accomplished CGAF (BI Form CGAF-001-Rev 1);',
                'Photocopy of passport bio-page, visa implementation page and latest admission with valid authorized stay;',
                'Proof of filiation with the petitioner;',
                'Valid NBI Clearance for each dependent 15 years of age or above;',
                'BI Clearance Certificate; and',
                $annexA,
            ],
            'instructions' => 'cgaf',
            'evaluator' => 'Central Receiving Unit Evaluator:',
        ],
        'renewal' => [
            'code' => 'BI FORM 2014-08-008 Rev 0',
            'title' => 'RENEWAL OF ALIEN CERTIFICATE OF REGISTRATION<br>IDENTITY CARD (ACR I-CARD)',
            'heading' => 'CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR RENEWAL OF<br>ALIEN CERTIFICATE OF REGISTRATION IDENTITY CARD (ACR I-CARD)',
            'items' => [
                'application_form' => 'Accomplished application form (BI Form 2014-08-010 Rev 0);',
                'acr_card' => 'Original ACR I-Card (to be surrendered); and',
                'passport_copy' => 'Photocopy of passport bio-page, visa implementation page and latest admission with valid authorized stay.',
            ],
            'dependents' => [],
            'instructions' => 'renewal',
            'evaluator' => 'Alien Registration Division Evaluator:',
        ],
    ];
    $form = $forms[$type];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>13(A) Checklist{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 20pt 34pt 0 34pt; font-family: Arial, Helvetica, sans-serif; font-size: 8.4pt; line-height: 1.22; }
        .item { display: flex; gap: 4pt; align-items: flex-start; }
        .item .box { width: 15pt; height: 12pt; flex: 0 0 auto; font-size: 9pt; margin-top: -1pt; }
        .instructions { margin: 4pt 0 0; padding-left: 16pt; text-align: justify; }
        .heading { text-align: center; font-weight: bold; text-decoration: underline; font-size: 9pt; margin-top: 8pt; }
        .sub { margin: 4pt 0 0 0; }
        .fillin { font-weight: bold; color: #0b2a6b; }
        @media print { .fillin { color: #000; } }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print checklist</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale.</span>
</div>

<div class="sheet">
    <div style="display:flex;gap:12pt;align-items:flex-start;">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:52pt;height:52pt;object-fit:contain;">
        <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;">
                <b style="font-size:11pt;">{{ $form['code'] }}</b>
                <span style="font-size:7pt;">This document may be reproduced and is <b>NOT FOR SALE</b></span>
            </div>
            <div style="font-weight:bold;font-size:13pt;line-height:1.15;">{!! $form['title'] !!}</div>
        </div>
    </div>

    <div style="text-align:center;font-weight:bold;letter-spacing:0.45em;font-size:11pt;margin-top:12pt;">GENERAL INSTRUCTIONS</div>
    @if ($form['instructions'] === 'cgaf')
        <div style="text-align:center;font-weight:bold;font-size:9pt;">To avoid summary dismissal/delay in the processing of your application/petition,<br>please be advised of the following:</div>
        <ol class="instructions">
            <li>Enter legibly all the information required into the appropriate spaces on the form. If the information required is not applicable, please write “N/A”. Do not leave any item blank. Your answers must be in English and all entries must be in CAPITAL LETTERS using English characters only.</li>
            <li>The Consolidated General Application Form (CGAF) must be accomplished in two (2) <b>ORIGINAL</b> copies. The first copy shall be submitted together with all the documents pertaining to this application and the second copy is intended for application of Alien Certificate of Registration Identity Card (ACR I-Card) to be submitted in a separate folder.</li>
            <li>All documents required for submission must be complied with, arranged in the order as listed in the Checklist of Documentary Requirements (CDR), compiled in a legal size (8½ x 14 in.) folder and shall be submitted within the prescribed period, if applicable.</li>
            <li>All sworn statements or affidavits must be original and duly notarized.</li>
            <li>All civil registry documents issued in the Philippines (i.e. Birth Certificate, Marriage Certificate, Death Certificate, etc.) must be original and issued by the Philippine Statistics Authority (PSA).</li>
            <li>All foreign documents must be original and authenticated by the Philippine Foreign Service Post (FSP), which has jurisdiction over the place of issuance, or by the Department of Foreign Affairs (DFA) if said document is issued by the local Embassy in the Philippines, with English translation if written in other foreign language.</li>
            <li>If the application is filed by an authorized representative, attach a photocopy of the Bureau of Immigration (BI) Accreditation Identification (ID) Certificate or an original Special Power of Attorney (SPA) for <b>EACH</b> applicant with a photocopy of a valid government-issued ID of attorney-in-fact.</li>
            <li>The original valid passport of the applicant must be submitted to the Central Receiving Unit (CRU) personnel or concerned frontline officer for scanning. Thereafter, it shall be returned.</li>
            @if ($type === 'permanent')
                <li>If the applicant is joined by his/her unmarried children below twenty-one (21) years of age, submit all corresponding documentary requirements for <b>EACH</b> dependent.</li>
            @endif
            <li>The applicant and/or petitioner must always bring his/her original passport and/or valid ID (if Filipino) upon evaluation or interview by Visa Officers.</li>
        </ol>
    @else
        <div style="text-align:center;font-weight:bold;font-size:8pt;">To avoid denial/delay in the processing of your application,<br>please be advised of the following:</div>
        <ol class="instructions">
            <li>The application form is written in English language. Enter legibly all the information required into the appropriate spaces on the form. If the information required is not applicable, please write “N/A”. Do not leave any item blank. The Bureau will not act on application forms that are incomplete and incorrectly accomplished. Your answers must be in English and all entries must be in CAPITAL LETTERS using English characters only. Letters like ñ, é, ü and ç are not recognized by the system. For instance, please enter names like “Muñoz” and “Sémonin” as “Munoz” and “Semonin” respectively.</li>
            <li>All documents required for submission must be complied with, arranged in the order as listed in the Checklist of Documentary Requirements (CDR), compiled in a legal size (8½ x 14 in.) folder and shall be submitted within the prescribed period, if applicable. Otherwise, your application shall not be accepted.</li>
            <li>All civil registry documents issued in the Philippines (i.e. Birth Certificate, Marriage Certificate, Death Certificate, etc.) must be original and issued by the National Statistics Office (NSO) of the Philippines.</li>
            <li>If the application is filed by an authorized representative, attach a photocopy of the Bureau of Immigration (BI) Accreditation Identification (ID) Certificate or an original Special Power of Attorney (SPA) for <b>EACH</b> applicant with a photocopy of a valid government-issued ID of attorney-in-fact.</li>
            <li>The original valid passport of the applicant must be presented to the concerned frontline officer for identification purposes. Thereafter, it shall be returned.</li>
        </ol>
    @endif

    <div style="border-top:1.5px solid #000;margin-top:6pt;"></div>
    <div class="heading">{!! $form['heading'] !!}</div>
    <div style="text-align:center;font-style:italic;font-weight:bold;font-size:8.2pt;margin-top:4pt;">Please read carefully the above-stated General Instructions<br>before proceeding with the completion of the documentary requirements.</div>

    <div style="margin-top:6pt;">
        @foreach (array_values($form['items']) as $i => $itemText)
            @php $key = array_keys($form['items'])[$i]; @endphp
            <div class="item"><span class="box"><span class="fill">{{ $tick($key) }}</span></span><span>{{ $i + 1 }}.&nbsp; {{ $itemText }}</span></div>
        @endforeach
    </div>

    @if ($form['dependents'])
        <div style="text-align:center;text-decoration:underline;margin-top:6pt;">Checklist of Documentary Requirements for <b>EACH</b> Dependent</div>
        <div style="margin-top:4pt;">
            @foreach ($form['dependents'] as $i => $itemText)
                <div class="item"><span class="box"></span><span>{{ $i + 1 }}.&nbsp; {{ $itemText }}</span></div>
            @endforeach
        </div>
    @endif

    @if ($type === 'renewal')
        <div style="font-weight:bold;text-decoration:underline;margin-top:8pt;font-size:9pt;">Additional Requirements:</div>
        <div style="padding-left:20pt;font-size:8.6pt;">
            <div class="sub"><b>For Immigrant Visa, MCL 07-021, MCL 08-003 - Amendment from Probationary to Permanent Resident Visa, Temporary Resident Visa, Treaty Trader’s Visa, and Pre-Arranged Employee Visa (Commercial/Missionary):</b></div>
            <div class="item"><span class="box"><span class="fill">{{ $tick('boc_order') }}</span></span><span>Photocopy of the Board of Commissioners (BOC)’s Order of Approval</span></div>
            <div class="sub"><b>For Sec. 47(a)2:</b></div>
            <div class="item"><span class="box"></span><span>Photocopy of endorsement from the Department of Justice (DOJ)</span></div>
            <div class="sub"><b>For Special Subic/Clark Investors Visa, Special Subic/Clark Working Visa, Cagayan Economic Zone Investor/Working Visa:</b></div>
            <div class="item"><span class="box"></span><span>Photocopy of Endorsement from Subic Bay Metropolitan Authority (SBMA)/Clark Development Corporation (CDC)</span></div>
            <div class="sub"><b>For Student Visa:</b></div>
            <div class="item"><span class="box"></span><span>Photocopy of the Student Desk-approved Order granting a Student Visa extension</span></div>
            <div class="sub"><b>For Re-Registration of Dependents:</b></div>
            <div class="item"><span class="box"></span><span>Certified true copy of the Board of Commissioners (BOC)’s Order, for a non-immigrant applicant</span></div>
            <div class="item"><span class="box"></span><span>Photocopy of parent’s passport bio-page and passport page bearing the visa implementation as well as the ACR I-Card (front and back portions)</span></div>
            <div class="item"><span class="box"></span><span>Two (2) pieces of 2x2 colored photograph with white background and must be taken within the last three months from the date of application</span></div>
            <div class="sub"><b>For Philippine Born (Permanent Residents):</b></div>
            <div class="item"><span class="box"></span><span>Birth Certificate</span></div>
            <div class="item"><span class="box"></span><span>Certified true copy of ACR and Native-Born Certificate of Residence (NBCR), if applicable (issued by the Records Section for internal verification purposes)</span></div>
            <div class="item"><span class="box"></span><span>Photocopy of parent’s ACR (front and back portions) and Immigrant Certificate of Residence (ICR)</span></div>
        </div>
    @endif

    <div style="border-top:1.5px solid #000;margin-top:6pt;font-weight:bold;font-style:italic;font-size:7.4pt;">[To be filled out by Authorized BI Personnel Only]</div>
    <div style="text-align:center;font-weight:bold;font-size:10pt;margin-top:10pt;">CERTIFICATION</div>
    <div style="margin-top:10pt;padding-left:40pt;">
        This is to certify that the documents submitted in support of the application of
        <span style="display:inline-block;min-width:190pt;border-bottom:0.75px solid #000;text-align:center;" class="fillin">{{ $name }}</span>
        <div style="text-align:right;font-style:italic;font-size:7.4pt;padding-right:60pt;">(Name of Applicant)</div>
    </div>
    <div><span style="display:inline-block;width:180pt;border-bottom:0.75px solid #000;"></span> are complete and in accordance with the {{ $type === 'renewal' ? 'above' : 'provided' }} checklist.</div>

    <div style="margin-top:14pt;">{{ $form['evaluator'] }}</div>
    <div style="display:flex;justify-content:space-between;margin-top:26pt;">
        <div style="width:150pt;border-top:0.75px solid #000;padding-top:2pt;">Signature over Printed Name</div>
        <div style="width:110pt;border-top:0.75px solid #000;padding-top:2pt;text-align:center;">Date</div>
    </div>

    <div style="margin-top:16pt;font-size:7.4pt;">Note: Submission of additional supporting documents may be required by the Bureau for further verification and evaluation of application.</div>
    <div style="text-align:center;font-size:7.4pt;margin-top:8pt;line-height:1.25;">
        <b>Website:</b> www.immigration.gov.ph<br><b>E-mail:</b> xinfo@immigration.gov.ph<br><b>Facebook:</b> officialbureauofimmigration<br><b>Twitter:</b> immigrationPH
    </div>
    <div style="text-align:right;font-size:7.4pt;">Page 1 of 1</div>
</div>
</body>
</html>
