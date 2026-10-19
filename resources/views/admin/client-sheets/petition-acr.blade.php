@php
    use App\Support\BoxedForm as B;

    /**
     * The BI's Application Form for Alien Certificate of Registration Identity Card
     * (BI Form 2014-08-006 Rev 0), both pages, on 8.5 x 13 in paper. Filed with a 13(A)
     * probationary or permanent application; filled from the client sheet and its case,
     * one capital letter per box, or blank to fill in by hand.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $dt = fn (string $key) => $blankForm ? null : $petition->dateDetail($key);
    $c = fn ($value) => $blankForm ? null : $value;

    $number = fn ($value) => preg_match('/\d+(\.\d+)?/', (string) $value, $match) ? $match[0] : null;
    $status = strtolower(trim((string) $client->civil_status));
    $status = $status === 'widow' ? 'widowed' : $status;
    $leaving = $blankForm || $petition->detail('leaving_soon') === null ? null : $petition->flag('leaving_soon');

    // An address over three rows of boxes, broken at word ends.
    $lines = function (?string $text, int $width = 20): array {
        $wrapped = $text ? explode("\n", wordwrap(mb_strtoupper(trim($text)), $width, "\n", true)) : [];

        return array_pad(array_slice($wrapped, 0, 3), 3, null);
    };
    $homeLines = $blankForm ? [null, null, null] : $lines($client->address);

    $petitionerName = collect([$d('petitioner_last'), $d('petitioner_first'), $d('petitioner_middle')])->filter()->implode(' ');
    $spouseName = $d('spouse_name') ?: $petitionerName;
    $spouseNationality = $d('spouse_nationality') ?: ($petitionerName ? 'FILIPINO' : null);
    $applicantName = $blankForm ? '' : collect([$client->last_name, $client->given_name, $d('middle_name')])->filter()->implode(' ');
    $stubRows = $applicantName ? explode("\n", wordwrap(mb_strtoupper($applicantName), 36, "\n", true)) : [];
    $visaType = $d('visa_type') ?? ($blankForm ? null : '13(A)');
    $placeOfBirth = $d('place_of_birth') ?: $d('country_of_birth');

    $line = fn ($value, string $width) => '<span style="display:inline-block;min-width:'.$width.';border-bottom:0.75px solid #000;text-align:center;font-weight:bold;color:#0b2a6b;">'.e((string) $value).'&nbsp;</span>';
    $onDate = fn ($date) => $date ? strtoupper($date->format('d M Y')) : '';
    $documents = [
        ['*Old ACR No.', $d('previous_acr_number'), $dt('previous_acr_issued_on'), $d('previous_acr_place')],
        ['ICR No.', $d('icr_number'), null, null],
        ['CLR No.', null, null, null],
        ['Passport/CI No.', $c($client->passport_number), $dt('passport_issued_on'), $d('passport_place')],
        ['CTC No.', null, null, null],
    ];

    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ACR I-Card Application{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 12pt 26pt 0 26pt; font-family: Verdana, Tahoma, Arial, sans-serif; font-size: 6.8pt; line-height: 1.15; }
        .sheet + .sheet { page-break-before: always; }
        .cells { display: inline-flex; vertical-align: top; }
        .cell { width: 13.2pt; height: 13.4pt; border: 0.75px solid #9a9a9a; margin-left: -0.75px; display: inline-flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; font-weight: bold; font-size: 8.2pt; color: #0b2a6b; }
        .cell:first-child { margin-left: 0; }
        .date-cells { display: inline-flex; gap: 8pt; }
        .tick { display: inline-flex; width: 11.5pt; height: 11.5pt; border: 0.75px solid #9a9a9a; align-items: center; justify-content: center; font-weight: bold; font-size: 9pt; color: #0b2a6b; vertical-align: middle; }
        .lbl { display: block; margin: 2.6pt 0 0.6pt; }
        .sec { font-weight: bold; font-size: 7.6pt; margin-top: 4pt; }
        .sub { font-weight: bold; font-size: 7.2pt; text-decoration: underline; margin-top: 4pt; display: block; }
        .row { display: flex; gap: 14pt; align-items: flex-start; }
        .half { width: 272pt; flex: 0 0 auto; }
        .boxline { border: 0.75px solid #9a9a9a; height: 13.4pt; display: flex; align-items: center; padding: 0 4pt; font-family: Arial, sans-serif; font-weight: bold; font-size: 8pt; color: #0b2a6b; }
        .opt { display: inline-flex; align-items: center; gap: 4pt; }
        .num { display: inline-block; width: 12pt; font-weight: bold; }
        .head { display: flex; gap: 12pt; align-items: flex-start; }
        .formno { font-family: Verdana, Arial, sans-serif; font-weight: bold; font-size: 11pt; }
        .title { font-family: Verdana, Arial, sans-serif; font-weight: bold; font-size: 12.4pt; line-height: 1.12; }
        .panel { border: 1.2px solid #000; font-size: 7pt; }
        .panel > div { border-bottom: 1.2px solid #000; padding: 2pt 3pt; }
        .panel > div:last-child { border-bottom: 0; }
        .panel b { text-decoration: underline; }
        .fill { flex: 1; border-bottom: 0.75px solid #000; }
        .rule { border-top: 1.5px solid #000; margin: 8pt -26pt 0; }
        .dashed { border-top: 1.5px dashed #000; margin: 8pt -26pt; }
        .para { font-size: 7.6pt; line-height: 1.25; text-align: justify; }
        .sign { border-top: 0.75px solid #000; text-align: center; padding-top: 1pt; font-size: 7.4pt; }
        @media print { .cell, .tick, .boxline { color: #000; } }
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
    <div class="head">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:52pt;height:52pt;object-fit:contain;">
        <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;">
                <span class="formno">BI FORM 2014-08-006 Rev 0</span>
                <span style="font-size:7pt;">This document cannot be reproduced and is <b>NOT FOR SALE</b></span>
            </div>
            <div class="title">APPLICATION FORM FOR ALIEN CERTIFICATE OF<br>REGISTRATION IDENTITY CARD (ACR I-CARD)</div>
        </div>
    </div>

    <div class="row" style="gap:10pt;">
        <div style="flex:1;">
            <div style="text-align:right;font-weight:bold;font-size:7.4pt;padding-right:6pt;">CONTROL NO:</div>
            <div class="sec" style="margin-top:0;">I. GENERAL DETAILS</div>
            <div class="row">
                <div style="width:196pt;"><span class="lbl" style="margin-top:0;">ACR Number</span>{{ B::boxes($d('acr_number'), 14, $blankForm) }}</div>
                <div><span class="lbl" style="margin-top:0;">Official Receipt Number</span>{{ B::boxes($d('official_receipt_no'), 12, $blankForm) }}</div>
            </div>
            <div class="row">
                <div style="width:196pt;"><span class="lbl">Date of Issuance <span>[DD-MMM-YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('acr_issued_on'), $blankForm) }}</div>
                <div><span class="lbl">Certificate of Residence Number (CRN)</span>{{ B::boxes($d('crn'), 12, $blankForm) }}</div>
            </div>
            <span class="lbl">Place of Issuance</span>{{ B::boxes($d('acr_place'), 27, $blankForm) }}

            <div class="sec">II. APPLICANT’S INFORMATION</div>
            <span class="lbl" style="margin-top:0;">Name Prefix (e.g. ATTY, FR, SR)</span>{{ B::boxes($d('name_prefix'), 5, $blankForm) }}
            <span class="lbl">Last Name</span>{{ B::boxes($c($client->last_name), 27, $blankForm) }}
            <span class="lbl">First/Given Name</span>{{ B::boxes($c($client->given_name), 27, $blankForm) }}
            <span class="lbl">Middle Name</span>{{ B::boxes($d('middle_name'), 27, $blankForm) }}
            <span class="lbl">Name Suffix (e.g. JR, SR, I-III)</span>{{ B::boxes($d('name_suffix'), 5, $blankForm) }}
        </div>
        <div style="width:150pt;">
            <div style="text-align:center;font-weight:bold;font-size:7.4pt;">APPLICATION NO:</div>
            <div class="panel">
                <div>
                    <b>DEROGATORY RECORD</b><br>
                    <span class="opt" style="margin:2pt 14pt 2pt 20pt;">{{ B::tick(false, true) }} NO DERO</span><span class="opt">{{ B::tick(false, true) }} W/ DERO</span>
                    <div style="display:flex;gap:3pt;margin-top:3pt;">Checked by: <span class="fill"></span></div>
                    <div style="display:flex;gap:3pt;margin-top:4pt;">Date &nbsp; &nbsp; &nbsp; : <span class="fill"></span></div>
                </div>
                <div>
                    <b>IDENTITY VERIFIED</b>
                    <div class="fill" style="height:14pt;"></div>
                    <div style="display:flex;justify-content:space-between;">Fingerprint Examiner <span>Date</span></div>
                </div>
                <div>
                    <b>RECOMMENDING</b><br>
                    <span class="opt" style="margin:2pt 14pt 2pt 20pt;">{{ B::tick(false, true) }} APPROVAL</span><span class="opt">{{ B::tick(false, true) }} DENIAL</span>
                    <div style="display:flex;gap:3pt;margin-top:3pt;">Remarks: <span class="fill"></span></div>
                    <div class="fill" style="height:12pt;"></div>
                    <div class="fill" style="height:12pt;"></div>
                    <div style="display:flex;justify-content:space-between;padding-right:26pt;">Registration Officer <span>Date</span></div>
                </div>
                <div>
                    <b>REVIEWED BY:</b>
                    <div class="fill" style="height:14pt;"></div>
                    <div style="display:flex;justify-content:space-between;padding-right:26pt;">Reviewing Officer <span>Date</span></div>
                </div>
                <div>
                    <b>APPROVED BY:</b>
                    <div class="fill" style="height:14pt;"></div>
                    <div style="display:flex;justify-content:space-between;padding-right:26pt;">COMM/ARD CHIEF <span>Date</span></div>
                </div>
            </div>
        </div>
    </div>

    <span class="lbl">Other Name(s)/Alias(es)</span>
    <div><span class="num">1</span>{{ B::boxes($d('other_name_1'), 41, $blankForm) }}</div>
    <div style="margin-top:2pt;"><span class="num">2</span>{{ B::boxes($d('other_name_2'), 41, $blankForm) }}</div>

    <div class="row">
        <div class="half">
            <span class="sub">Visa/Travel Information</span>
            <span class="lbl" style="margin-top:0;">Visa Number</span>{{ B::boxes($d('visa_number'), 20, $blankForm) }}
            <span class="lbl">Date of Issuance [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($dt('visa_issued_on'), $blankForm) }}
            <span class="lbl">Name of Consulate</span>{{ B::boxes($d('consulate'), 20, $blankForm) }}
            <span class="lbl">Admission Status of Latest Arrival</span>{{ B::boxes($d('admission_status'), 20, $blankForm) }}
            <span class="lbl">Length of Stay</span>{{ B::boxes($d('length_of_stay'), 20, $blankForm) }}
            <span class="lbl">Present Status</span>{{ B::boxes($d('present_status'), 20, $blankForm) }}
        </div>
        <div style="flex:1;">
            <span class="sub">Travel Document Details</span>
            <span class="lbl" style="margin-top:0;">Passport Number</span>{{ B::boxes($c($client->passport_number), 20, $blankForm) }}
            <span class="lbl">Date of Issuance [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($dt('passport_issued_on'), $blankForm) }}
            <span class="lbl">Expiry Date/Valid Until [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($dt('passport_expires_on'), $blankForm) }}
            <span class="lbl">Place of Issuance</span>{{ B::boxes($d('passport_place'), 20, $blankForm) }}
            <span class="lbl" style="font-weight:bold;">Contact Number(s) in the Philippines</span>
            <div style="display:flex;align-items:center;gap:6pt;"><span style="width:40pt;">Landline</span>{{ B::boxes($d('landline'), 12, $blankForm) }}</div>
            <div style="display:flex;align-items:center;gap:6pt;margin-top:2pt;"><span style="width:40pt;">Mobile</span>{{ B::boxes($c($client->mobile_number), 12, $blankForm) }}</div>
            <span class="lbl">Email Address</span><div class="boxline">{{ $blankForm ? '' : $client->email }}</div>
        </div>
    </div>

    <div class="row">
        <div class="half">
            <span class="sub">Other Information</span>
            <span class="lbl" style="margin-top:0;">Previous ACR Number</span>{{ B::boxes($d('previous_acr_number'), 20, $blankForm) }}
            <span class="lbl">Date of Issuance [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($dt('previous_acr_issued_on'), $blankForm) }}
            <span class="lbl">Place of Issuance</span>{{ B::boxes($d('previous_acr_place'), 20, $blankForm) }}
        </div>
        <div style="flex:1;">
            <span class="sub">Residential Address in the Philippines</span>
            <span class="lbl" style="margin-top:0;">House/Unit No., Street, Subdivision/Village</span>{{ B::boxes($homeLines[0], 20, $blankForm) }}
            <span class="lbl">Barangay, City/Municipality</span>{{ B::boxes($homeLines[1], 20, $blankForm) }}
            <span class="lbl">Province, Zip Code</span>{{ B::boxes($homeLines[2], 20, $blankForm) }}
        </div>
    </div>
    <span class="lbl">Citizenship/Nationality</span>{{ B::boxes($c($client->nationality), 42, $blankForm) }}
    <span class="lbl">Previous Subject of</span>{{ B::boxes($d('previous_subject_of'), 42, $blankForm) }}

    <div class="row">
        <div class="half">
            <span class="sub">Personal Description</span>
            <div class="row" style="gap:10pt;">
                <div><span class="lbl" style="margin-top:0;">Race</span>{{ B::boxes($d('race'), 16, $blankForm) }}</div>
                <div><span class="lbl" style="margin-top:0;">Blood Type</span>{{ B::boxes($d('blood_type'), 3, $blankForm) }}</div>
            </div>
            <div class="row" style="gap:10pt;">
                <div style="width:112pt;">
                    <span class="lbl">Height [cm]</span>{{ B::boxes($c($number($client->height)), 8, $blankForm) }}
                    <span class="lbl">Weight [Kg]</span>{{ B::boxes($c($number($client->weight)), 8, $blankForm) }}
                    <span class="lbl">Built</span>{{ B::boxes($d('build'), 8, $blankForm) }}
                </div>
                <div>
                    <span class="lbl">Eye Color</span>{{ B::boxes($d('eye_color'), 10, $blankForm) }}
                    <span class="lbl">Hair Color</span>{{ B::boxes($d('hair_color'), 10, $blankForm) }}
                    <span class="lbl">Complexion</span>{{ B::boxes($d('complexion'), 10, $blankForm) }}
                </div>
            </div>
            <span class="lbl">Distinguishing Marks</span>{{ B::boxes($d('marks'), 20, $blankForm) }}
        </div>
        <div style="flex:1;">
            <span class="sub">Personal Information</span>
            <span class="lbl" style="margin-top:0;">Gender</span>
            <span class="opt" style="width:90pt;">{{ B::tick($d('sex') === 'M', $blankForm) }} Male</span><span class="opt">{{ B::tick($d('sex') === 'F', $blankForm) }} Female</span>
            <span class="lbl">Civil Status</span>
            <div style="display:grid;grid-template-columns:repeat(3, 84pt);row-gap:4pt;">
                @foreach (['single' => 'Single', 'married' => 'Married', 'annulled' => 'Annulled', 'separated' => 'Separated', 'widowed' => 'Widowed', 'divorced' => 'Divorced'] as $code => $statusLabel)
                    <span class="opt">{{ B::tick($status === $code, $blankForm) }} {{ $statusLabel }}</span>
                @endforeach
            </div>
            <span class="lbl">Place of Birth</span>{{ B::boxes($placeOfBirth, 20, $blankForm) }}
            <span class="lbl">Date of Birth [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($c($client->date_of_birth), $blankForm) }}
            <span class="lbl">Occupation</span>{{ B::boxes($d('occupation'), 20, $blankForm) }}
        </div>
    </div>

    <div class="dashed"></div>
    <div style="display:flex;gap:12pt;align-items:flex-start;">
        <img src="{{ $seal }}" alt="" style="width:50pt;height:50pt;object-fit:contain;margin-top:8pt;">
        <div style="flex:1;">
            <div style="font-weight:bold;font-size:8.6pt;">APPLICANT’S ACR I-CARD CLAIM STUB</div>
            <div style="font-size:7pt;">Applicant’s Name [Last Name, First/Given Name, Middle Name (Please leave a box after each name)]</div>
            <div>{{ B::boxes($stubRows[0] ?? null, 36, $blankForm) }}</div>
            <div style="margin-top:2pt;">{{ B::boxes($stubRows[1] ?? null, 36, $blankForm) }}</div>
            <div style="display:flex;align-items:center;gap:6pt;margin-top:4pt;">
                <span style="white-space:nowrap;">ACR Number</span>{{ B::boxes($d('acr_number'), 14, $blankForm) }}
                <span style="margin-left:auto;white-space:nowrap;">Visa Type</span>{{ B::boxes($visaType, 9, $blankForm) }}
            </div>
        </div>
    </div>
    <div style="text-align:center;font-size:6.8pt;margin-top:6pt;">[IF THE ACR I-CARD IS CLAIMED BY AN AUTHORIZED REPRESENTATIVE, PLEASE SEE REVERSE SIDE FOR INSTRUCTIONS.]</div>
    <div style="text-align:right;font-size:7pt;margin-top:4pt;">Page <b>1</b> of <b>2</b></div>
</div>

{{-- ===================== PAGE 2 ===================== --}}
<div class="sheet">
    <div style="display:flex;justify-content:space-between;align-items:baseline;">
        <span class="formno">BI FORM 2014-08-006 Rev 0</span>
        <span style="font-size:7pt;">This document cannot be reproduced and is <b>NOT FOR SALE</b></span>
    </div>
    <div class="title">APPLICATION FORM FOR ALIEN CERTIFICATE OF<br>REGISTRATION IDENTITY-CARD (ACR I-CARD)</div>

    <span class="lbl" style="margin-top:8pt;">Name of Spouse [Last Name, First/Given Name, Middle Name]</span>{{ B::boxes($spouseName, 42, $blankForm) }}
    <span class="lbl">Spouse’s Nationality</span>{{ B::boxes($spouseNationality, 42, $blankForm) }}

    <div style="border:1.2px solid #000;margin-top:10pt;padding:2pt 4pt;display:flex;align-items:center;gap:8pt;font-weight:bold;font-size:7pt;">
        <span style="flex:1;">ARE YOU PLANNING TO LEAVE THE COUNTRY WITHIN THIRTY (30) DAYS UPON ISSUANCE OF ACR I-CARD?</span>
        <span class="opt" style="width:44pt;">{{ B::tick($leaving === true, $blankForm) }} YES</span>
        <span class="opt" style="width:40pt;">{{ B::tick($leaving === false, $blankForm) }} NO</span>
    </div>

    <div class="row" style="margin-top:4pt;">
        <div class="half">
            <span class="lbl">RP/SRC Number</span>{{ B::boxes($d('rp_src_number'), 20, $blankForm) }}
            <span class="lbl">RP/SRC Type</span>{{ B::boxes($d('rp_src_type'), 20, $blankForm) }}
        </div>
        <div style="flex:1;">
            <span class="lbl">RP/SRC Date of Issuance [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($dt('rp_src_issued_on'), $blankForm) }}
            <span class="lbl">Expiry Date/Valid Until [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($dt('rp_src_expires_on'), $blankForm) }}
        </div>
    </div>
    <span class="lbl">Father’s Name [Last Name, First/Given Name, Middle Name]</span>{{ B::boxes($d('father_name'), 42, $blankForm) }}

    <div class="rule" style="margin-top:12pt;"></div>
    <div style="text-align:center;font-weight:bold;font-size:7.8pt;margin-top:8pt;">AFFIDAVIT FOR PERSONS 14 YEARS OF AGE OR OVER</div>
    <p class="para" style="margin:4pt 0 0;text-indent:28pt;">I have read the above statements or have had them read to me, and do hereby swear (or affirm) that these statements are true and complete to the best of my knowledge and belief; furthermore, that I have not filed any application for this purpose still pending or not given due course in another Immigration office.</p>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:18pt;">
        <div style="width:150pt;"><div class="sign">(Signature of Witness in case Applicant does not know how to write)</div></div>
        <div style="width:200pt;">
            <div style="text-align:center;font-weight:bold;color:#0b2a6b;font-size:8pt;min-height:10pt;">{{ $blankForm ? '' : mb_strtoupper(collect([$client->given_name, $d('middle_name'), $client->last_name])->filter()->implode(' ')) }}</div>
            <div class="sign">(Signature of Applicant)</div>
            <div style="display:flex;gap:4pt;margin-top:6pt;">TIN : {!! $line($d('tin'), '170pt') !!}</div>
        </div>
    </div>
    <p class="para" style="margin:8pt 0 0;text-indent:40pt;line-height:1.6;">SUBSCRIBED AND SWORN TO before me this ________ day of ______________________________ 20 ___ at ____________________________________; Affiant/Applicant exhibiting to me his/her Passport/CTC No. {!! $line($c($client->passport_number), '110pt') !!} issued on {!! $line($onDate($dt('passport_issued_on')), '120pt') !!} at {!! $line($d('passport_place'), '130pt') !!}.</p>
    <div style="margin-left:auto;width:200pt;margin-top:22pt;"><div class="sign">(Registration Officer)</div></div>
    <div style="margin-left:auto;width:200pt;margin-top:18pt;"><div class="sign">(Official Designation)</div></div>

    <div class="rule" style="margin-top:10pt;"></div>
    <div style="text-align:center;font-weight:bold;font-size:7.8pt;margin-top:8pt;">AFFIDAVIT FOR PARENT OR GUARDIAN ONLY</div>
    <p class="para" style="margin:4pt 0 0;text-indent:28pt;">I am the parent of/guardian of/person responsible for the above-named alien who is under 14 years of age/insane and have made the above allegations for him/her. I have read or have had the same read to me, and do hereby swear (or affirm) that they are true and complete to the best of my knowledge, information or belief; furthermore, that I have not filed any application for this purpose in behalf of the above-named alien still pending or not given due course in another Immigration office.</p>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:22pt;">
        <div style="width:170pt;"><div class="sign">(Signature of Witness)</div></div>
        <div style="width:200pt;">
            <div class="sign">(Signature of Parent, etc.)</div>
            <div style="display:flex;gap:4pt;margin-top:6pt;">TIN : <span class="fill"></span></div>
        </div>
    </div>
    <p class="para" style="margin:8pt 0 0;text-indent:40pt;line-height:1.6;">SUBSCRIBED AND SWORN TO before me this ________ day of ______________________________ 20 ___ at ____________________________________; Affiant/Applicant exhibiting to me his/her passport/CTC No. ______________________________ issued on ______________________________ at ______________________________________.</p>
    <div style="margin-left:auto;width:200pt;margin-top:22pt;"><div class="sign">(Registration Officer)</div></div>
    <div style="margin-left:auto;width:200pt;margin-top:18pt;"><div class="sign">(Official Designation)</div></div>

    <div class="rule" style="margin-top:10pt;"></div>
    <div style="font-size:6.8pt;margin-top:6pt;">DOCUMENTS PRESENTED: (*) Document to be retained and incorporated in the records.</div>
    <div style="display:grid;grid-template-columns:90pt 1fr;row-gap:3pt;font-size:7pt;margin-top:3pt;">
        @foreach ($documents as $i => [$docLabel, $docNo, $docOn, $docAt])
            <span>{{ $i + 1 }}. {{ $docLabel }}</span>
            <span style="display:flex;gap:4pt;">
                {!! $line($docNo, '150pt') !!} issued on {!! $line($onDate($docOn), '100pt') !!} at <span style="flex:1;border-bottom:0.75px solid #000;text-align:center;font-weight:bold;color:#0b2a6b;">{{ $docAt }}&nbsp;</span>
            </span>
        @endforeach
    </div>

    <div class="dashed" style="margin-bottom:0;"></div>
    <div style="display:flex;font-size:7pt;">
        <div style="width:260pt;border-right:1.5px solid #000;padding:8pt 10pt 4pt 0;display:grid;row-gap:6pt;">
            @foreach (['Name of Representative', 'Accredited Travel Agency/Law Office', 'BI Accreditation No.', 'Contact No.', 'Residential /Office Address', 'Signature'] as $repLabel)
                <div style="display:flex;gap:3pt;"><span>{{ $repLabel }}</span><span class="fill"></span></div>
            @endforeach
        </div>
        <div style="flex:1;padding:4pt 0 0 10pt;">
            <div style="text-align:center;font-weight:bold;font-size:7.4pt;">ACR I-CARD WILL ONLY BE RELEASED UPON COMPLIANCE/SUBMISSION OF THE FF:</div>
            <div style="line-height:1.25;margin-top:4pt;">
                1. Photocopy of passport bio-page of the ACR I-Card holder<br>
                2. Valid ID of either parent claiming the ACR I-Card, if applicant is a minor<br>
                3. Photocopy of the BI-Accreditation ID card, if claimed by a travel agent or law firm<br>
                4. Special Power of Attorney (SPA), if claimed by an authorized representative other than the parent or BI accredited entity
            </div>
            <div style="display:flex;gap:10pt;margin-top:12pt;">
                <div style="flex:1;display:flex;gap:3pt;">ACR I-Card Holder: <span class="fill"></span></div>
                <div style="flex:0.8;display:flex;gap:3pt;">Claimant: <span class="fill"></span></div>
            </div>
            <div style="display:flex;justify-content:space-around;font-size:6.6pt;"><span style="padding-left:40pt;">Signature over PRINTED NAME</span><span>Signature</span></div>
            <div style="text-align:center;font-size:6.6pt;margin-top:2pt;">[Please call (+632) 525-7557 to check the status of your application]</div>
        </div>
    </div>
    <div style="text-align:right;font-size:7pt;margin-top:8pt;">Page <b>2</b> of <b>2</b></div>
</div>
</body>
</html>
