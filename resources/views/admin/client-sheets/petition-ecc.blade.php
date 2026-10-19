@php
    use App\Models\ImmigrationPetition;
    use App\Support\BoxedForm as B;

    /**
     * The BI's Emigration Clearance Certificate (ECC) Application Form (ARD04.QF.003
     * Rev. Lev. 0, effective 20 AUG 2026), one page on long bond paper, filled from the
     * client sheet and its ECC case, or blank.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $dt = fn (string $key) => $blankForm ? null : $petition->dateDetail($key);
    $c = fn ($value) => $blankForm ? null : $value;

    $kind = $blankForm ? null : ($d('ecc_kind') ?: 'ecc');
    $sex = $d('sex');
    $status = strtolower(trim((string) $client->civil_status));
    $status = $status === 'widow' ? 'widowed' : $status;

    // The address over the form's three lines: street, barangay and town, province and ZIP.
    $parts = $blankForm ? [] : array_values(array_filter(array_map('trim', explode(',', (string) $client->address))));
    $address = match (true) {
        count($parts) >= 3 => [implode(', ', array_slice($parts, 0, -2)), $parts[count($parts) - 2], end($parts)],
        default => [implode(', ', $parts), null, null],
    };
    $mobile = $blankForm ? '' : preg_replace('/^(\+?63|0)/', '', preg_replace('/[^\d+]/', '', (string) $client->mobile_number));
    $printed = $blankForm ? '' : mb_strtoupper(collect([$client->last_name, collect([$client->given_name, $d('middle_name')])->filter()->implode(' ')])->filter()->implode(', '));
    $representative = $d('by_representative') ? mb_strtoupper((string) $d('representative_name')) : '';

    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
    $markPath = 'images/logoapproved/bagong-pilipinas.png';
    $hasMark = file_exists(public_path($markPath));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ECC Application Form{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 12pt 18pt 0 18pt; font-family: Verdana, Tahoma, Arial, sans-serif; font-size: 7.4pt; line-height: 1.2; }
        .cells { display: inline-flex; vertical-align: top; }
        .cell { width: 13pt; height: 13pt; border: 0.75px solid #000; margin-left: -0.75px; display: inline-flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; font-weight: bold; font-size: 8pt; color: #0b2a6b; }
        .cell:first-child { margin-left: 0; }
        .date-cells { display: inline-flex; gap: 10pt; }
        .tick { display: inline-flex; width: 12pt; height: 12pt; border: 0.75px solid #000; align-items: center; justify-content: center; font-weight: bold; font-size: 9pt; color: #0b2a6b; vertical-align: middle; margin-right: 2pt; }
        .box { display: flex; align-items: center; border: 0.75px solid #000; height: 14pt; padding: 0 4pt; font-family: Arial, sans-serif; font-weight: bold; font-size: 8pt; color: #0b2a6b; text-transform: uppercase; overflow: hidden; white-space: nowrap; }
        .lbl { display: block; margin: 4pt 0 1pt; font-size: 7.6pt; }
        .sec { font-weight: bold; font-size: 8.6pt; margin-top: 6pt; }
        .row { display: flex; gap: 16pt; }
        .row > div { flex: 1; min-width: 0; }
        .opt { display: inline-flex; align-items: center; white-space: nowrap; }
        .ln { display: inline-block; border-bottom: 0.75px solid #000; text-align: center; font-weight: bold; color: #0b2a6b; vertical-align: bottom; }
        @media print { .cell, .tick, .box, .ln { color: #000; } }
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
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale. One page.</span>
</div>

<div class="sheet">
    <div style="text-align:right;font-weight:bold;text-decoration:underline;font-size:6.8pt;">THIS DOCUMENT MAY BE REPRODUCED AND IS NOT FOR SALE</div>
    <div class="masthead" style="gap:56pt;margin-top:2pt;">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:46pt;height:46pt;object-fit:contain;">
        <div class="office serif" style="font-size:8.6pt;line-height:1.1;">REPUBLIC OF THE PHILIPPINES<br>DEPARTMENT OF JUSTICE<br><span style="font-size:12pt;letter-spacing:0.6pt;">BUREAU OF IMMIGRATION</span></div>
        @if ($hasMark)
            <img src="{{ asset($markPath) }}" alt="Bagong Pilipinas" style="width:46pt;height:46pt;object-fit:contain;">
        @else
            <span style="width:46pt;"></span>
        @endif
    </div>
    <div style="text-align:center;font-weight:900;font-size:12.4pt;margin-top:8pt;font-family:Arial, sans-serif;">EMIGRATION CLEARANCE CERTIFICATE (ECC) APPLICATION FORM</div>

    <div class="sec" style="margin-top:6pt;">INSTRUCTION</div>
    <div style="font-size:6.6pt;">Enter legibly all the information required into the appropriate spaces on the form. If the information required is not applicable, please write "<b>N/A</b>". Do not leave any item blank. Do not abbreviate. Please place a check mark (✓) in the designated box that corresponds to your answer. Application(s) with incomplete/incorrect information will not be processed.</div>

    <div class="sec">I. APPLICATION INFORMATION</div>
    <div style="display:flex;justify-content:space-between;margin-top:3pt;font-size:6.6pt;">
        @foreach (ImmigrationPetition::ECC_KINDS as $code => $kindLabel)
            <span class="opt">{{ B::tick($kind === $code, $blankForm) }}{{ $kindLabel }}</span>
        @endforeach
    </div>

    <div class="row" style="margin-top:4pt;">
        <div>
            <div class="sec" style="margin-top:2pt;">II. APPLICANT’S PERSONAL INFORMATION</div>
            <span class="lbl" style="margin-top:2pt;">Last Name</span><div class="box">{{ $c($client->last_name) }}</div>
            <span class="lbl">First/Given Name(s)</span><div class="box">{{ $blankForm ? '' : trim($client->given_name.' '.$d('middle_name')) }}</div>
            <span class="lbl">Other Name(s) Alias(es)</span><div class="box">{{ $d('other_name_1') }}</div>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;">
                <div><span class="lbl">Date of Birth <i>(DD MMM YYYY)</i></span>{{ B::date($c($client->date_of_birth), $blankForm) }}</div>
                <div><span class="lbl">Sex</span><span class="opt" style="margin-right:6pt;">{{ B::tick($sex === 'M', $blankForm) }}Male</span><span class="opt">{{ B::tick($sex === 'F', $blankForm) }}Female</span></div>
            </div>
            <span class="lbl">Place of Birth</span><div class="box">{{ $d('place_of_birth') }}</div>
        </div>
        <div>
            <div style="border:1.5px solid #000;border-radius:10pt;padding:6pt 8pt 4pt;margin-top:2pt;">
                <b>Special Security Registration Number (SSRN)</b>
                <div style="margin-top:1pt;">{{ B::boxes(null, 20, true) }}</div>
                <div style="margin-top:4pt;">Derogatory Record:</div>
                <div style="border-bottom:0.75px solid #000;height:14pt;"></div>
                <div style="border-bottom:0.75px solid #000;height:14pt;"></div>
                <div style="display:flex;gap:10pt;margin-top:14pt;font-style:italic;font-size:6.6pt;text-align:center;">
                    <span style="flex:1;border-top:0.75px solid #000;">Derogatory Verifier</span>
                    <span style="flex:1;border-top:0.75px solid #000;">Date and Time (DD MMM YYYY/HH:MM)</span>
                </div>
            </div>
            <span class="lbl">Citizenship/Nationality</span><div class="box">{{ $c($client->nationality) }}</div>
        </div>
    </div>

    <span class="lbl">Marital Status</span>
    <div style="display:flex;justify-content:space-between;">
        @foreach (['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'annulled' => 'Annulled', 'divorced' => 'Divorced', 'separated' => 'Separated'] as $code => $statusLabel)
            <span class="opt">{{ B::tick($status === $code, $blankForm) }}{{ $statusLabel }}</span>
        @endforeach
    </div>
    <span class="lbl">Spouse Name <i>(Last Name, Given Name, Middle Name)</i></span><div class="box">{{ $d('spouse_name') }}</div>
    <span class="lbl">Parent/Guardian Name traveling with the minor applicant in the [Certificate of Exemption] <i>(Last Name, Given Name, Middle Name)</i></span><div class="box">{{ $d('guardian_name') }}</div>

    <div class="sec" style="margin-top:8pt;">Residential / Registered Address in the Philippines</div>
    <span class="lbl" style="margin-top:0;">House/Unit No., Street, Subdivision/Village</span><div class="box">{{ $address[0] ?? '' }}</div>
    <div class="row">
        <div><span class="lbl">Barangay, Municipality</span><div class="box">{{ $address[1] ?? '' }}</div></div>
        <div><span class="lbl">Province, Zip Code</span><div class="box">{{ $address[2] ?? '' }}</div></div>
    </div>

    <div class="sec" style="margin-top:8pt;">Contact Information in the Philippines</div>
    <div style="display:grid;grid-template-columns:1fr 1.7fr 1fr;gap:8pt;">
        <div><span class="lbl" style="margin-top:0;">Mobile Number</span><div class="box">+63&nbsp;{{ $mobile }}</div></div>
        <div><span class="lbl" style="margin-top:0;">E-mail Address</span><div class="box" style="text-transform:none;">{{ $c($client->email) }}</div></div>
        <div><span class="lbl" style="margin-top:0;">Landline Number</span><div class="box">{{ $d('landline') }}</div></div>
    </div>

    <div class="row">
        <div>
            <div class="sec">III. TRAVEL INFORMATION</div>
            <span class="lbl" style="margin-top:0;">Passport Number</span><div class="box">{{ $c($client->passport_number) }}</div>
            <span class="lbl">Applicant’s Visa Status per Latest Admission</span><div class="box">{{ $d('present_status') }}</div>
        </div>
        <div style="padding-top:12pt;">
            <span class="lbl">Date of Latest Admission <i>(DD MMM YYYY)</i></span>{{ B::date($dt('arrival_date'), $blankForm) }}
            <span class="lbl">Valid Authorized Stay <i>(DD MMM YYYY)</i></span>{{ B::date($dt('last_day_of_stay') ?? ($blankForm ? null : $client->visa_expiry_date), $blankForm) }}
        </div>
    </div>
    <div style="display:flex;gap:4pt;align-items:flex-end;margin-top:8pt;">Reason for Leaving: <span class="ln" style="flex:1;text-align:left;padding-left:6pt;">{{ $d('leaving_reason') }}</span></div>
    <div style="display:flex;gap:10pt;align-items:flex-end;">
        <div><span class="lbl">Date of Intended Departure <i>(DD MMM YYYY)</i></span>{{ B::date($dt('departure_date'), $blankForm) }}</div>
        <div style="flex:1;"><span class="lbl">Next City/Country of Destination</span><div class="box">{{ $d('destination') }}</div></div>
    </div>
    <span class="lbl">Airline/Vessel Details</span><div class="box">{{ $d('airline') }}</div>

    <div style="border:1px solid #000;margin-top:8pt;padding:5pt 14pt 6pt;">
        <b style="font-size:8pt;">PROSECUTION CLAUSE AND DATA PRIVACY CONSENT</b>
        <div style="font-style:italic;font-size:6.8pt;line-height:1.35;margin-top:6pt;">
            By signing this application/form, I hereby certify that:
            <div style="padding-left:30pt;">1. I have read and understood the questions in the application form and that my answers are true and correct to the best of my knowledge;<br>
                2. All documents submitted were legally obtained from and authenticated by appropriate government agency/ies or private entity/ies; and<br>
                3. I understand that any false or misleading statement made, or falsified document submitted by me or my representative, may:
                <div style="padding-left:50pt;">i. Result in the summary denial of my application, if applicable,<br>ii. Subject me to criminal prosecution and/or deportation, or<br>iii. Subject my representative to criminal prosecution and/or administrative proceeding.</div>
            </div>
        </div>
        <div style="font-style:italic;font-size:6.8pt;margin-top:10pt;display:flex;align-items:flex-end;gap:3pt;">I, <span class="ln" style="flex:1;font-style:normal;">{{ $printed }}</span>, personally or through my agent/representative <span class="ln" style="flex:1;font-style:normal;">{{ $representative }}</span>,</div>
        <div style="display:flex;font-style:italic;font-size:5.8pt;"><span style="flex:1;text-align:center;">(Last Name, First Name/Given Name(s), Middle Name)</span><span style="flex:1;text-align:right;padding-right:30pt;">(Last Name, First/Given Name(s), Middle Name)</span></div>
        <div style="font-style:italic;font-size:7.6pt;line-height:1.45;text-align:justify;">voluntarily and freely consent to: (1) The collection and sharing of my personal information only in relation to BI protocols; (2) The collection, use, and transfer, in electronic or other forms, of my personal data, as described in the form by and among, as applicable, the latter’s representative, petitioner, and any related entity for the exclusive purpose of filing, processing, and implementing the submitted application/form consistent with provisions of Republic Act 10173 or the Data Privacy Act of 2012 and its Implementing Rules and Regulations (IRR); (3) Data Sharing when it is expressly authorized by law: Provided, that there are adequate safeguards for data privacy and security, and processing which adheres to the principle of transparency, legitimate purpose, and proportionality.</div>
        <div style="display:flex;justify-content:space-between;margin-top:18pt;font-style:italic;font-size:6.4pt;text-align:center;">
            <div style="width:180pt;"><div class="ln" style="display:block;font-style:normal;font-size:7.6pt;min-height:10pt;"></div>Date (DD MMM YYYY)</div>
            <div style="width:190pt;"><div class="ln" style="display:block;font-style:normal;font-size:7.6pt;min-height:10pt;">{{ $printed }}</div>Applicant’s Signature over Printed Name</div>
        </div>
    </div>
    <div style="border:1px solid #000;margin-top:6pt;padding:2pt 4pt;font-style:italic;font-size:6.8pt;">The Bureau of Immigration (BI) commits to protect and respect personal data privacy pursuant to the provisions of Republic Act No. 10173 or the Data Privacy Act of 2012 and its Implementing Rules and Regulations. The entire Data Privacy Notice of the BI is accessible in this link: https://immigration.gov.ph/information/data-privacy.</div>
    <div style="text-align:right;font-size:7pt;margin-top:3pt;">ARD04.QF.003 Rev. Lev. 0 Effective 20 AUG 2026</div>
</div>
</body>
</html>
