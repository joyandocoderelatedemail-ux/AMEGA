@php
    use App\Models\ImmigrationPetition;
    use App\Support\BoxedForm as B;

    /**
     * The BI's Consolidated General Application Form for Immigrant Visa (BOC04.QF.001
     * Rev. Lev. 0), both pages, on 8.5 x 13 in paper. Filled from the client sheet and
     * its 13(A) case, one capital letter per box, or blank to fill in by hand.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $dt = fn (string $key) => $blankForm ? null : $petition->dateDetail($key);
    $c = fn ($value) => $blankForm ? null : $value;

    $type = $petition->type ?: 'spouse';
    $kind = $d('application_kind') ?? ($blankForm ? null : ($type === 'permanent' ? 'amendment' : 'conversion'));
    $representative = ! $blankForm && $petition->flag('by_representative');
    $visaType = $d('visa_type') ?? ($blankForm ? null : '13(A) NON-QUOTA IMMIGRANT');
    $number = fn ($value) => preg_match('/\d+(\.\d+)?/', (string) $value, $match) ? $match[0] : null;
    $status = strtolower(trim((string) $client->civil_status));
    $status = $status === 'widow' ? 'widowed' : $status;

    // An address over three rows of boxes, broken at word ends.
    $lines = function (?string $text, int $width = 20): array {
        $wrapped = $text ? explode("\n", wordwrap(mb_strtoupper(trim($text)), $width, "\n", true)) : [];

        return array_pad(array_slice($wrapped, 0, 3), 3, null);
    };
    $homeLines = $blankForm ? [null, null, null] : $lines($client->address);
    $petitionerName = collect([$d('petitioner_last'), $d('petitioner_first'), $d('petitioner_middle')])->filter()->implode(' ');
    $applicantName = collect([$client->last_name, $client->given_name, $d('middle_name')])->filter()->implode(' ');
    $printedName = $blankForm ? '' : strtoupper(collect([$client->given_name, $d('middle_name'), $client->last_name])->filter()->implode(' '));
    $petitionerPrinted = $blankForm ? '' : strtoupper(collect([$d('petitioner_first'), $d('petitioner_middle'), $d('petitioner_last')])->filter()->implode(' '));

    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
    $markPath = 'images/logoapproved/bagong-pilipinas.png';
    $hasMark = file_exists(public_path($markPath));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Immigrant Visa CGAF{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 10pt 26pt 0 26pt; font-family: Tahoma, Verdana, Arial, sans-serif; font-size: 6.8pt; line-height: 1.15; }
        .sheet + .sheet { page-break-before: always; }
        .cells { display: inline-flex; vertical-align: top; }
        .cell { width: 13.2pt; height: 12.6pt; border: 0.75px solid #9a9a9a; margin-left: -0.75px; display: inline-flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; font-weight: bold; font-size: 8.2pt; color: #0b2a6b; }
        .cell:first-child { margin-left: 0; }
        .date-cells { display: inline-flex; gap: 8pt; }
        .tick { display: inline-flex; width: 11.5pt; height: 11.5pt; border: 0.75px solid #9a9a9a; align-items: center; justify-content: center; font-weight: bold; font-size: 9pt; color: #0b2a6b; vertical-align: middle; }
        .lbl { display: block; margin: 2.2pt 0 0.6pt; }
        .sec { font-weight: bold; font-size: 8.4pt; margin-top: 3pt; }
        .row { display: flex; gap: 12pt; align-items: flex-end; }
        .half { width: 266pt; flex: 0 0 auto; }
        .boxline { border: 0.75px solid #9a9a9a; height: 12.6pt; display: flex; align-items: center; padding: 0 4pt; font-family: Arial, sans-serif; font-weight: bold; font-size: 8pt; color: #0b2a6b; }
        .opt { display: inline-flex; align-items: center; gap: 4pt; margin-right: 18pt; }
        .num { display: inline-block; width: 10pt; font-weight: bold; }
        .masthead { display: flex; align-items: center; justify-content: space-between; padding: 0 30pt; }
        .office { text-align: center; font-family: 'Book Antiqua', Palatino, 'Times New Roman', serif; line-height: 1.08; font-size: 8.6pt; }
        .title { text-align: center; font-weight: 900; font-size: 12.6pt; margin: 5pt 0 0; white-space: nowrap; font-family: Arial, Helvetica, sans-serif; letter-spacing: 0.2pt; }
        .italic { font-style: italic; }
        .sign { border-top: 0.75px solid #000; text-align: center; padding-top: 2pt; font-style: italic; font-size: 7.6pt; }
        .signed { text-align: center; font-family: Arial, sans-serif; font-weight: bold; font-size: 8pt; color: #0b2a6b; min-height: 10pt; }
        .dashed { border-top: 1.5px dashed #000; margin: 6pt -26pt; }
        .para { font-style: italic; font-size: 8pt; line-height: 1.22; text-align: justify; }
        @media print { .cell, .tick, .boxline, .signed { color: #000; } }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print form</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale. Two pages.</span>
</div>

{{-- ===================== PAGE 1 ===================== --}}
<div class="sheet">
    <div style="text-align:center;font-style:italic;text-decoration:underline;color:#777;font-size:8pt;">THIS DOCUMENT MAY BE REPRODUCED AND IS <b>NOT FOR SALE</b></div>
    <div class="masthead">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:46pt;height:46pt;object-fit:contain;">
        <div class="office">
            REPUBLIC OF THE PHILIPPINES<br>DEPARTMENT OF JUSTICE<br>
            <span style="font-size:13pt;">BUREAU OF IMMIGRATION</span><br>
            MAGALLANES DRIVE, INTRAMUROS<br><span style="font-size:8.4pt;">1002 MANILA</span>
        </div>
        @if ($hasMark)
            <img src="{{ asset($markPath) }}" alt="Bagong Pilipinas" style="width:42pt;height:42pt;object-fit:contain;">
        @else
            <span style="width:50pt;"></span>
        @endif
    </div>
    <div class="title">CONSOLIDATED GENERAL APPLICATION FORM FOR IMMIGRANT VISA</div>

    <div class="sec">I. APPLICATION INFORMATION</div>
    <span class="lbl">Present Immigration Status</span>{{ B::boxes($d('present_status'), 42, $blankForm) }}
    <span class="lbl">Type of Visa Application</span>
    @foreach (ImmigrationPetition::APPLICATION_KINDS as $code => $kindLabel)
        <span class="opt">{{ B::tick($kind === $code, $blankForm) }} {{ $kindLabel }}</span>
    @endforeach
    <span class="lbl">Type of Visa</span>{{ B::boxes($visaType, 42, $blankForm) }}
    <span class="lbl">Method of Application</span>
    <span class="opt">{{ B::tick(! $blankForm && ! $representative, $blankForm) }} Personal</span>
    <span class="opt">{{ B::tick($representative, $blankForm) }} Authorized Representative</span>
    <span class="lbl">BI Accreditation Number</span>{{ B::boxes($representative ? $d('accreditation_no') : null, 42, $blankForm) }}
    <span class="lbl">Name of Authorized Representative <span class="italic">[Last Name, First/Given Name, Middle Name]</span></span>{{ B::boxes($representative ? $d('representative_name') : null, 42, $blankForm) }}

    <div class="sec">II. APPLICANT’S TRAVEL INFORMATION</div>
    <div class="row">
        <div class="half"><span class="lbl">Passport Number</span>{{ B::boxes($c($client->passport_number), 16, $blankForm) }}</div>
        <div><span class="lbl">Date of Latest Arrival <span class="italic">[DD MMM YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('arrival_date'), $blankForm) }}</div>
    </div>
    <span class="lbl">Expiry Date/Valid Until <span class="italic">[DD MMM YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('passport_expires_on'), $blankForm) }}
    <div class="row">
        <div class="half"><span class="lbl">Place of Issuance</span>{{ B::boxes($d('passport_place'), 20, $blankForm) }}</div>
        <div><span class="lbl">Last Day of Authorized Stay <span class="italic">[DD MMM YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('last_day_of_stay'), $blankForm) }}</div>
    </div>

    <div class="sec">III. APPLICANT’S PERSONAL INFORMATION</div>
    <span class="lbl">Last Name</span>{{ B::boxes($c($client->last_name), 42, $blankForm) }}
    <span class="lbl">First/Given Name</span>{{ B::boxes($c($client->given_name), 42, $blankForm) }}
    <span class="lbl">Middle Name <span class="italic">(if applicable)</span></span>{{ B::boxes($d('middle_name'), 42, $blankForm) }}
    <span class="lbl">Other Name(s)/Alias(es)</span>
    <div><span class="num">1</span>{{ B::boxes($d('other_name_1'), 41, $blankForm) }}</div>
    <div><span class="num">2</span>{{ B::boxes($d('other_name_2'), 41, $blankForm) }}</div>
    <div class="row">
        <div style="width:200pt;"><span class="lbl">Date of Birth <span class="italic">[DD MMM YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($c($client->date_of_birth), $blankForm) }}</div>
        <div style="width:60pt;"><span class="lbl">Sex</span><span class="opt" style="margin-right:6pt;">{{ B::tick($d('sex') === 'M', $blankForm) }} M</span><span class="opt" style="margin-right:0;">{{ B::tick($d('sex') === 'F', $blankForm) }} F</span></div>
        <div><span class="lbl">Country of Birth</span>{{ B::boxes($d('country_of_birth'), 20, $blankForm) }}</div>
    </div>
    <div class="row" style="align-items:flex-start;">
        <div class="half">
            <span class="lbl">Country of Citizenship</span>{{ B::boxes($c($client->nationality), 20, $blankForm) }}
            <div class="row" style="gap:6pt;">
                <div><span class="lbl">Height <span class="italic">[cm]</span></span>{{ B::boxes($c($number($client->height)), 3, $blankForm) }}</div>
                <div style="margin-left:auto;"><span class="lbl">Weight <span class="italic">[kg]</span></span>{{ B::boxes($c($number($client->weight)), 3, $blankForm) }}</div>
            </div>
            <span class="lbl">Profession/Occupation</span>{{ B::boxes($d('occupation'), 20, $blankForm) }}
        </div>
        <div>
            <span class="lbl">Civil Status</span>
            <div style="display:grid;grid-template-columns:repeat(3, 90pt);row-gap:4pt;">
                @foreach (['single' => 'Single', 'married' => 'Married', 'annulled' => 'Annulled', 'separated' => 'Separated', 'widowed' => 'Widowed', 'divorced' => 'Divorced'] as $code => $statusLabel)
                    <span class="opt">{{ B::tick($status === $code, $blankForm) }} {{ $statusLabel }}</span>
                @endforeach
            </div>
        </div>
    </div>
    <div class="row" style="align-items:flex-start;">
        <div class="half">
            <span class="lbl" style="font-weight:bold;">Contact Number(s) in the Philippines</span>
            <div style="display:flex;align-items:center;gap:6pt;margin-top:2pt;"><span style="width:58pt;">Landline</span>{{ B::boxes($d('landline'), 12, $blankForm) }}</div>
            <div style="display:flex;align-items:center;gap:6pt;margin-top:2pt;"><span style="width:58pt;">Mobile</span>{{ B::boxes($c($client->mobile_number), 12, $blankForm) }}</div>
        </div>
        <div style="flex:1;"><span class="lbl">E-mail Address</span><div class="boxline">{{ $blankForm ? '' : $client->email }}</div></div>
    </div>
    <div class="row" style="align-items:flex-start;">
        <div class="half">
            <span class="lbl" style="font-weight:bold;">Residential Address in the Philippines</span>
            <span class="lbl">House/Unit No., Street, Subdivision/Village Barangay,</span>{{ B::boxes($homeLines[0], 20, $blankForm) }}
            <span class="lbl">Municipality/City</span>{{ B::boxes($homeLines[1], 20, $blankForm) }}
            <span class="lbl">Province, Zip Code</span>{{ B::boxes($homeLines[2], 20, $blankForm) }}
        </div>
        <div>
            <span class="lbl" style="font-weight:bold;">Residential Address Abroad</span>
            <span class="lbl">House/Unit No., Street, Subdivision/Village</span>{{ B::boxes($d('abroad_street'), 20, $blankForm) }}
            <span class="lbl">City, State</span>{{ B::boxes($d('abroad_city'), 20, $blankForm) }}
            <span class="lbl">Country, Zip Code</span>{{ B::boxes($d('abroad_country'), 20, $blankForm) }}
        </div>
    </div>
    <span class="lbl"><b>Name of Spouse</b> <span class="italic">[Last Name, First/Given Name, Middle Name]</span></span>{{ B::boxes($blankForm ? null : $petitionerName, 42, $blankForm) }}
    <span class="lbl">Other Name(s)/Alias(es)</span>
    <div><span class="num">1</span>{{ B::boxes(null, 41, true) }}</div>
    <div><span class="num">2</span>{{ B::boxes(null, 41, true) }}</div>
    <span class="lbl"><b>Name(s) of Child(ren) and Date(s) of Birth</b> <span class="italic">[Last Name, First/Given Name, Middle Name]</span></span>
    @foreach ([1, 2] as $child)
        <div><span class="num">{{ $child }}</span>{{ B::boxes($d("child_{$child}_name"), 41, $blankForm) }}</div>
        <span class="lbl">Date of Birth <span class="italic">[DD MMM YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt("child_{$child}_dob"), $blankForm) }}
    @endforeach
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:4pt;">
        <b class="italic" style="font-size:7pt;">Note: If the applicant has more than two (2) children, use Consolidated General Application Form for Information on Applicant’s Child(ren)</b>
        <span style="text-align:right;font-size:6.6pt;">Page <b>1</b> of <b>2</b><br>BOC04.QF.001 Rev. Lev. 0 Effective: 05 AUG 2024</span>
    </div>

    <div class="dashed" style="margin:4pt -26pt;"></div>
    <div style="display:flex;gap:10pt;align-items:center;">
        <img src="{{ $seal }}" alt="" style="width:46pt;height:46pt;object-fit:contain;">
        <div style="flex:1;">
            <div style="font-weight:bold;font-size:9.4pt;">APPLICANT’S ACR I-CARD CLAIM STUB</div>
            <div style="font-size:8pt;">Applicant’s Name <span class="italic">[Last Name, First/Given Name, Middle Name (Please leave a box after each name)]</span></div>
            {{ B::boxes($blankForm ? null : $applicantName, 36, $blankForm) }}
            <div style="display:flex;align-items:center;gap:6pt;margin-top:3pt;font-size:8.4pt;">
                <span style="white-space:nowrap;">ACR Number</span>{{ B::boxes($d('acr_number'), 13, $blankForm) }}
                <span style="margin-left:auto;white-space:nowrap;">Visa Type</span>{{ B::boxes($visaType, 9, $blankForm) }}
            </div>
        </div>
    </div>
    <div class="italic" style="text-align:center;font-size:7pt;margin-top:5pt;">[PLEASE SEE REVERSE SIDE FOR INSTRUCTIONS IF THE ACR I-CARD WILL BE CLAIMED BY AN AUTHORIZED REPRESENTATIVE.]</div>
</div>

{{-- ===================== PAGE 2 ===================== --}}
<div class="sheet">
    <div style="text-align:center;font-style:italic;text-decoration:underline;color:#777;font-size:8pt;">THIS DOCUMENT MAY BE REPRODUCED AND IS <b>NOT FOR SALE</b></div>
    <div class="masthead">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:46pt;height:46pt;object-fit:contain;">
        <div class="office">
            REPUBLIC OF THE PHILIPPINES<br>DEPARTMENT OF JUSTICE<br>
            <span style="font-size:13pt;">BUREAU OF IMMIGRATION</span><br>
            MAGALLANES DRIVE, INTRAMUROS<br><span style="font-size:8.4pt;">1002 MANILA</span>
        </div>
        @if ($hasMark)
            <img src="{{ asset($markPath) }}" alt="Bagong Pilipinas" style="width:42pt;height:42pt;object-fit:contain;">
        @else
            <span style="width:50pt;"></span>
        @endif
    </div>
    <div class="title">CONSOLIDATED GENERAL APPLICATION FORM FOR IMMIGRANT VISA</div>

    <div class="sec" style="font-size:7.4pt;">Character References in the Philippines</div>
    <span class="lbl" style="margin-top:0;">Last Name, First/Given Name, Middle Name</span>{{ B::boxes($d('reference_name'), 42, $blankForm) }}
    <div class="row" style="align-items:flex-start;">
        <div class="half">
            <span class="lbl" style="font-weight:bold;">Residential Address in the Philippines</span>
            <span class="lbl">House/Unit No., Street, Subdivision/Village, Barangay</span>{{ B::boxes($d('reference_street'), 20, $blankForm) }}
            <span class="lbl">Municipality/City</span>{{ B::boxes($d('reference_city'), 20, $blankForm) }}
            <span class="lbl">Province, Zip Code</span>{{ B::boxes($d('reference_province'), 20, $blankForm) }}
        </div>
        <div style="flex:1;">
            <span class="lbl" style="font-weight:bold;">Contact Number(s) in the Philippines</span>
            <span class="lbl">Landline</span>{{ B::boxes($d('reference_landline'), 12, $blankForm) }}
            <span class="lbl">Mobile</span>{{ B::boxes($d('reference_mobile'), 12, $blankForm) }}
            <span class="lbl">E-mail Address</span><div class="boxline">{{ $d('reference_email') }}</div>
        </div>
    </div>

    <div class="sec" style="font-size:7.4pt;">IV. PETITIONER’S INFORMATION</div>
    <span class="lbl" style="margin-top:0;">Last Name</span>{{ B::boxes($d('petitioner_last'), 42, $blankForm) }}
    <span class="lbl">First/Given Name</span>{{ B::boxes($d('petitioner_first'), 42, $blankForm) }}
    <span class="lbl">Middle Name <span class="italic">(if applicable)</span></span>{{ B::boxes($d('petitioner_middle'), 42, $blankForm) }}
    <div class="row" style="align-items:flex-start;">
        <div class="half">
            <span class="lbl" style="font-weight:bold;">Registered Address in the Philippines</span>
            <span class="lbl">House/Unit No., Street, Subdivision/Village</span>{{ B::boxes($d('petitioner_street'), 20, $blankForm) }}
            <span class="lbl">Barangay, Municipality/City</span>{{ B::boxes($d('petitioner_city'), 20, $blankForm) }}
            <span class="lbl">Province, Zip Code</span>{{ B::boxes($d('petitioner_province'), 20, $blankForm) }}
        </div>
        <div style="flex:1;">
            <span class="lbl" style="font-weight:bold;">Contact Number(s) in the Philippines</span>
            <span class="lbl">Landline</span>{{ B::boxes($d('petitioner_landline'), 12, $blankForm) }}
            <span class="lbl">Mobile</span>{{ B::boxes($d('petitioner_mobile'), 12, $blankForm) }}
            <span class="lbl">E-mail Address</span><div class="boxline">{{ $d('petitioner_email') }}</div>
        </div>
    </div>

    <div class="row" style="align-items:flex-start;margin-top:4pt;">
        <div class="half">
            <div class="sec" style="font-size:7.4pt;margin-top:0;">V. ACR I-Card</div>
            <span class="lbl" style="margin-top:0;">Alien Certificate of Registration (ACR) Number</span>{{ B::boxes($d('acr_number'), 20, $blankForm) }}
            <span class="lbl">Date of Issuance <span class="italic">[DD MMM YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('acr_issued_on'), $blankForm) }}
            <span class="lbl">Expiry Date/Valid Until <span class="italic">[DD MMM YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('acr_expires_on'), $blankForm) }}
            <span class="lbl">Certificate of Residence Number (CRN)</span>{{ B::boxes($d('crn'), 20, $blankForm) }}
        </div>
        <div style="flex:1;border:1.5px solid #555;border-radius:10pt;padding:6pt 10pt;font-size:7.6pt;">
            <div style="text-align:center;font-weight:bold;font-size:9pt;">DO NOT FILL OUT THIS PORTION</div>
            <span class="lbl">Application Number</span>{{ B::boxes(null, 18, true) }}
            @foreach (['Received/Recommended by:', 'Reviewed by:', 'Approved by:'] as $signer)
                <div style="display:flex;gap:4pt;margin-top:8pt;"><span>{{ $signer }}</span><span style="flex:1;border-bottom:0.75px solid #000;"></span></div>
                <div class="italic" style="text-align:right;font-size:6.6pt;">(Signature over printed name)</div>
            @endforeach
        </div>
    </div>

    <p class="italic" style="font-weight:bold;font-size:8.2pt;margin:8pt 0 4pt;">Note: Failure to apply for visa implementation and claim the ACR I-Card within ninety (90) days after visa approval will lead to automatic visa cancellation and ACR I-Card deactivation, respectively.</p>

    <div style="text-align:center;font-weight:bold;font-size:8.6pt;">CERTIFICATION AND CONSENT</div>
    <p class="para" style="margin:2pt 0;text-indent:30pt;">I/We certify that: (1) All the information in the application is truthful, complete and correct; (2) All documents are authentic and were legally obtained from the corresponding government agencies or private entities; (3) I/We understand that my/our application may be summarily denied if: (a) Any statement is false; (b) Any document submitted is falsified; or (c) I/We fail to comply with all the BI requirements without prejudice to whatever action the BI may take; and (4) I/We have not filed this or any similar application before any office of the Bureau.</p>
    <p class="para" style="margin:2pt 0 0;text-indent:24pt;">By signing this application/form, I hereby certify that:</p>
    <p class="para" style="margin:0;">1. I have read and understood the questions in the application form and that my answers are true and correct to the best of knowledge and belief;<br>
        2. All documents submitted were legally obtained from and authenticated by appropriate government agency/ies or private entities; and<br>
        3. I understand that any false or misleading statement made, withholding of material information, or falsified document submitted by me or my representative may:<br>
        <span style="padding-left:24pt;">i. Result in the summary denial of my application, if applicable,</span><br>
        <span style="padding-left:24pt;">ii. Subject me to criminal prosecution and/or deportation, or</span><br>
        <span style="padding-left:24pt;">iii. Subject my representative to criminal prosecution and/or administrative proceeding.</span></p>
    <p class="para" style="margin:6pt 0 0;">
        {{ B::tick(false, true) }} I, <span style="display:inline-block;min-width:150pt;border-bottom:0.75px solid #000;text-align:center;font-style:normal;font-weight:bold;color:#0b2a6b;">{{ $printedName }}</span>, personally or through my agent/representative
        <span style="display:inline-block;min-width:150pt;border-bottom:0.75px solid #000;text-align:center;font-style:normal;font-weight:bold;color:#0b2a6b;">{{ $representative ? strtoupper((string) $d('representative_name')) : '' }}</span>,
        voluntarily and freely consent to: (1) The collection and sharing of my personal information only in relation to BI protocols; (2) The collection, use, and transfer, in electronic or other forms, of my personal data, as described in the form by and among, as applicable, the latter’s representative, petitioner, and any related entity for the exclusive purpose of filing, processing, and implementing the submitted application/form consistent with provisions of Republic Act 10173 or the Data Privacy Act of 2012 and its Implementing Rules and Regulations (IRR); (3) Data Sharing when it is expressly authorized by law: Provided, that there are adequate safeguards for data privacy and security, and processing which adheres to the principle of transparency, legitimate purpose, and proportionality.
    </p>

    <div style="display:grid;grid-template-columns:110pt 1fr 1fr;gap:28pt;margin-top:12pt;align-items:end;">
        <div><div class="signed">{{ $blankForm ? '' : strtoupper(($petition->filed_on ?? now())->format('d M Y')) }}</div><div class="sign" style="text-align:left;">Date <span style="font-size:6pt;">[DD MMM YYYY e.g. 01 JAN 1990]</span></div></div>
        <div><div class="signed">{{ $petitionerPrinted }}</div><div class="sign">Petitioner’s Signature over printed name</div></div>
        <div><div class="signed">{{ $printedName }}</div><div class="sign">Applicant’s Signature over printed name</div></div>
    </div>
    <div style="text-align:right;font-size:6.6pt;margin-top:2pt;">Page <b>2</b> of <b>2</b><br>BOC04.QF.001 Rev. Lev. 0 Effective: 05 AUG 2024</div>

    <div class="dashed" style="margin-top:2pt;"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;column-gap:24pt;row-gap:3pt;font-size:7.8pt;">
        <div style="display:flex;gap:4pt;"><span>Name of Representative:</span><span style="flex:1;border-bottom:0.75px solid #000;text-align:center;font-weight:bold;color:#0b2a6b;">{{ $representative ? strtoupper((string) $d('representative_name')) : '' }}</span></div>
        <div style="display:flex;gap:4pt;"><span>Accredited Travel Agency/Law Office:</span><span style="flex:1;border-bottom:0.75px solid #000;"></span></div>
        <div style="display:flex;gap:4pt;"><span>BI Accreditation No.:</span><span style="flex:1;border-bottom:0.75px solid #000;text-align:center;font-weight:bold;color:#0b2a6b;">{{ $representative ? $d('accreditation_no') : '' }}</span></div>
        <div style="display:flex;gap:4pt;"><span>Contact No.:</span><span style="flex:1;border-bottom:0.75px solid #000;"></span></div>
        <div style="display:flex;gap:4pt;"><span>Residential/Office Address:</span><span style="flex:1;border-bottom:0.75px solid #000;"></span></div>
        <div style="display:flex;gap:4pt;"><span>Signature:</span><span style="flex:1;border-bottom:0.75px solid #000;"></span></div>
    </div>
    <div style="border-top:1.5px solid #000;margin-top:6pt;padding-top:3pt;text-align:center;font-weight:bold;font-size:7.8pt;">ACR I-CARD WILL ONLY BE RELEASED UPON COMPLIANCE/SUBMISSION OF THE FOLLOWING:</div>
    <div style="font-size:7pt;line-height:1.3;">
        1. Photocopy of passport bio-page of the ACR I-Card holder<br>
        2. Valid ID of either parent claiming the ACR I-Card, if applicant is a minor<br>
        3. Photocopy of the BI-Accreditation ID card, if claimed by a travel agent or law firm<br>
        4. Special Power of Attorney (SPA), if claimed by an authorized representative other than the parent or BI accredited entity
    </div>
    <div style="display:flex;justify-content:center;gap:40pt;margin-top:8pt;font-size:7.6pt;">
        <div style="text-align:center;">ACR I-Card Holder: <span style="display:inline-block;width:130pt;border-bottom:0.75px solid #000;"></span><br><span class="italic" style="font-size:6.6pt;padding-left:70pt;">(Signature over printed name)</span></div>
        <div style="text-align:center;">Claimant: <span style="display:inline-block;width:130pt;border-bottom:0.75px solid #000;"></span><br><span class="italic" style="font-size:6.6pt;padding-left:40pt;">(Signature over printed name)</span></div>
    </div>
    <div style="text-align:center;font-size:6.6pt;margin-top:3pt;">[For ACR I-Card concerns, please call (+632)525-7557. To check the status of your visa application, please visit https://e-services.immigration.gov.ph/VisaApprovalVerification or https://immigration.gov.ph/resources/visa-application-status/. ]</div>
</div>
</body>
</html>
