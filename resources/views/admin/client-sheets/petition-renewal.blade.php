@php
    use App\Models\ImmigrationPetition;
    use App\Support\BoxedForm as B;

    /**
     * The BI's Application for Renewal of Alien Certificate of Registration Identity Card
     * (BI Form 2014-08-010 Rev 0), both pages, on 8.5 x 13 in paper. Filled from the
     * client sheet and its 13(A) renewal case, or blank to fill in by hand.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $dt = fn (string $key) => $blankForm ? null : $petition->dateDetail($key);
    $c = fn ($value) => $blankForm ? null : $value;
    $has = fn (string $transaction) => ! $blankForm && in_array($transaction, (array) $petition->detail('transactions'), true);

    $number = fn ($value) => preg_match('/\d+(\.\d+)?/', (string) $value, $match) ? $match[0] : null;
    $status = strtolower(trim((string) $client->civil_status));
    $status = $status === 'widow' ? 'widowed' : $status;
    $age = $client->date_of_birth?->age;
    $leaving = $petition->detail('leaving_soon');
    $applicantName = collect([$client->last_name, $client->given_name, $d('middle_name')])->filter()->implode(' ');
    $printedName = $blankForm ? '' : strtoupper(collect([$client->given_name, $d('middle_name'), $client->last_name])->filter()->implode(' '));
    $representative = ! $blankForm && $petition->flag('by_representative');
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');

    $transactionGroups = [
        'ACR I-Card Renewal' => [['renewal', 'expired'], ['reregistration', 'replacement'], ['change_status']],
        'ACR I-Card Re-issuance' => [['lost', 'rejected'], ['damaged', 'new_validity'], ['amendments']],
        'OTHERS:' => [['rp_src'], ['annual_report']],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ACR I-Card Renewal{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 22pt 34pt 0 34pt; font-family: Verdana, Tahoma, Arial, sans-serif; font-size: 7pt; line-height: 1.2; }
        .sheet + .sheet { page-break-before: always; }
        .cells { display: inline-flex; vertical-align: top; }
        .cell { width: 13pt; height: 15pt; border: 0.75px solid #9a9a9a; margin-left: -0.75px; display: inline-flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; font-weight: bold; font-size: 8.2pt; color: #0b2a6b; }
        .cell:first-child { margin-left: 0; }
        .date-cells { display: inline-flex; gap: 6pt; }
        .tick { display: inline-flex; width: 12pt; height: 12pt; border: 0.75px solid #9a9a9a; align-items: center; justify-content: center; font-weight: bold; font-size: 9pt; color: #0b2a6b; vertical-align: middle; }
        .lbl { display: block; margin: 4pt 0 1pt; }
        .opt { display: inline-flex; align-items: center; gap: 6pt; font-size: 8pt; }
        .grid { display: grid; grid-template-columns: 196pt 1fr 1fr; column-gap: 8pt; }
        .boxline { border: 0.75px solid #9a9a9a; height: 15pt; display: flex; align-items: center; padding: 0 4pt; font-family: Arial, sans-serif; font-weight: bold; font-size: 8pt; color: #0b2a6b; }
        .panel { border: 1.5px solid #000; }
        .panel-row { border-bottom: 1.5px solid #000; padding: 3pt 4pt; font-size: 6.8pt; }
        .panel-row:last-child { border-bottom: none; }
        .u { text-decoration: underline; font-weight: bold; }
        .rule { border-bottom: 0.75px solid #444; height: 11pt; }
        .fillin { font-family: Arial, sans-serif; font-weight: bold; color: #0b2a6b; }
        @media print { .cell, .tick, .boxline, .fillin { color: #000; } }
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
    <div style="display:flex;gap:12pt;align-items:flex-start;">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:50pt;height:50pt;object-fit:contain;">
        <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;">
                <b style="font-size:10.6pt;">BI FORM 2014-08-010 Rev 0</b>
                <span style="font-size:6.6pt;">This document cannot be reproduced and is <b>NOT FOR SALE</b></span>
            </div>
            <div style="font-weight:bold;font-size:12.6pt;line-height:1.15;">APPLICATION FOR RENEWAL OF ALIEN CERTIFICATE OF<br>REGISTRATION IDENTITY-CARD (ACR I-CARD)</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 182pt;column-gap:8pt;margin-top:6pt;">
        <div>
            <div style="height:24pt;"></div>
            <div class="u" style="font-size:8pt;">TRANSACTION/S</div>
            @foreach ($transactionGroups as $group => $rows)
                <div style="font-size:7.6pt;margin-top:2pt;">{{ $group }}</div>
                @foreach ($rows as $row)
                    <div style="display:grid;grid-template-columns:1fr 1fr;padding-left:14pt;margin-top:2pt;">
                        @foreach ($row as $key)
                            <span class="opt" @if (count($row) === 1) style="grid-column:span 2;" @endif>{{ B::tick($has($key), $blankForm) }} {{ ImmigrationPetition::TRANSACTIONS[$key] }}</span>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
            <div style="font-size:7.6pt;margin-top:4pt;">CHANGE OF ADDRESS:</div>
            <div style="border:2px solid #000;margin-top:2pt;">
                <div style="padding:4pt 8pt;border-bottom:2px solid #000;">
                    New Address:
                    <div class="fillin" style="min-height:33pt;line-height:11pt;background-image:linear-gradient(to bottom, transparent 10.25pt, #777 10.25pt);background-size:100% 11pt;font-size:7.6pt;text-transform:uppercase;">{{ $d('new_address') }}</div>
                </div>
                <div style="padding:4pt 8pt;">
                    Remarks:
                    <div class="fillin" style="min-height:33pt;line-height:11pt;background-image:linear-gradient(to bottom, transparent 10.25pt, #777 10.25pt);background-size:100% 11pt;font-size:7.6pt;">{{ $d('remarks') }}</div>
                </div>
            </div>
        </div>
        <div>
            <div style="font-weight:bold;font-size:7pt;line-height:2;">CONTROL NO:<br>APPLICATION NO:</div>
            <div class="panel">
                <div class="panel-row"><span class="u">DEROGATORY RECORD</span><br>
                    <span style="padding-left:30pt;">{{ B::tick(false, true) }} NO DERO &nbsp;&nbsp; {{ B::tick(false, true) }} W/ DERO</span><br>
                    Checked by : <span style="display:inline-block;width:110pt;border-bottom:0.75px solid #000;"></span><br>
                    Date &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <span style="display:inline-block;width:110pt;border-bottom:0.75px solid #000;"></span></div>
                <div class="panel-row"><span class="u">IDENTITY VERIFIED</span><div class="rule"></div><div style="display:flex;justify-content:space-around;">Fingerprint Examiner <span>Date</span></div></div>
                <div class="panel-row"><span class="u">RECOMMENDING</span><br>
                    <span style="padding-left:30pt;">{{ B::tick(false, true) }} APPROVAL &nbsp;&nbsp; {{ B::tick(false, true) }} DENIAL</span><br>
                    Remarks: <span style="display:inline-block;width:130pt;border-bottom:0.75px solid #000;"></span><div class="rule"></div><div class="rule"></div>
                    <div style="display:flex;justify-content:space-around;">Registration Officer <span>Date</span></div></div>
                <div class="panel-row"><span class="u">REVIEWED BY:</span><div class="rule"></div><div style="display:flex;justify-content:space-around;">Reviewing Officer <span>Date</span></div></div>
                <div class="panel-row"><span class="u">APPROVED BY:</span><div class="rule"></div><div style="display:flex;justify-content:space-around;">COMM/ARD CHIEF <span>Date</span></div></div>
            </div>
        </div>
    </div>

    <div style="border:1.5px solid #000;margin-top:6pt;padding:3pt 8pt;display:flex;align-items:center;gap:8pt;font-weight:bold;font-size:7.6pt;">
        ARE YOU PLANNING TO LEAVE THE COUNTRY WITHIN THIRTY (30) DAYS UPON ISSUANCE OF ACR I-CARD?
        <span class="opt" style="margin-left:auto;">{{ B::tick($leaving === true, $blankForm) }} YES</span>
        <span class="opt">{{ B::tick(! $blankForm && $petition->exists && ! $leaving, $blankForm) }} NO</span>
    </div>

    <div class="grid">
        <div><span class="lbl">ACR Number</span>{{ B::boxes($d('acr_number'), 14, $blankForm) }}</div>
        <div><span class="lbl">Certificate of Residence Number</span>{{ B::boxes($d('crn'), 12, $blankForm) }}</div>
        <div><span class="lbl">Visa Status</span>{{ B::boxes($d('visa_status'), 12, $blankForm) }}</div>
    </div>
    <div class="grid" style="grid-template-columns:1fr 162pt;">
        <div>
            <span class="lbl">Last Name</span>{{ B::boxes($c($client->last_name), 26, $blankForm) }}
            <span class="lbl">First/Given Name</span>{{ B::boxes($c($client->given_name), 26, $blankForm) }}
            <span class="lbl">Middle Name</span>{{ B::boxes($d('middle_name'), 26, $blankForm) }}
        </div>
        <div>
            <span class="lbl">Valid Authorized Stay <span style="font-size:5.6pt;">[DD-MMM-YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('valid_stay'), $blankForm) }}
            <div style="display:flex;gap:10pt;">
                <div><span class="lbl">Age</span>{{ B::boxes($c($age), 3, $blankForm) }}</div>
                <div><span class="lbl">Gender</span><span class="opt">{{ B::tick($d('sex') === 'M', $blankForm) }} Male</span> <span class="opt">{{ B::tick($d('sex') === 'F', $blankForm) }} Female</span></div>
            </div>
            <div style="display:flex;gap:10pt;">
                <div><span class="lbl">Height [cm]</span>{{ B::boxes($c($number($client->height)), 3, $blankForm) }}</div>
                <div><span class="lbl">Weight [Kg]</span>{{ B::boxes($c($number($client->weight)), 3, $blankForm) }}</div>
            </div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:150pt 1fr;column-gap:8pt;">
        <div><span class="lbl">Date of Birth [DD-MMM-YYYY e.g. 01 JAN 1990]</span>{{ B::date($c($client->date_of_birth), $blankForm) }}</div>
        <div>
            <span class="lbl">Civil Status</span>
            @foreach (['single' => 'Single', 'married' => 'Married', 'annulled' => 'Annulled', 'separated' => 'Separated', 'widowed' => 'Widowed', 'divorced' => 'Divorced'] as $code => $statusLabel)
                <span class="opt" style="margin-right:6pt;">{{ B::tick($status === $code, $blankForm) }} {{ $statusLabel }}</span>
            @endforeach
        </div>
    </div>
    <div class="grid">
        <div><span class="lbl">Passport Number</span>{{ B::boxes($c($client->passport_number), 14, $blankForm) }}</div>
        <div><span class="lbl">Date of Issuance <span style="font-size:5.6pt;">[DD-MMM-YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('passport_issued_on'), $blankForm) }}</div>
        <div><span class="lbl">Expiry Date/ Valid <span style="font-size:5.6pt;">[DD-MMM-YYYY e.g. 01 JAN 1990]</span></span>{{ B::date($dt('passport_expires_on'), $blankForm) }}</div>
    </div>
    <div class="grid">
        <div><span class="lbl">Citizenship/Nationality</span>{{ B::boxes($c($client->nationality), 14, $blankForm) }}</div>
        <div><span class="lbl">Landline Number</span>{{ B::boxes($d('landline'), 12, $blankForm) }}</div>
        <div><span class="lbl">Mobile Number</span>{{ B::boxes($c($client->mobile_number), 12, $blankForm) }}</div>
    </div>
    <span class="lbl">Email Address</span><div class="boxline">{{ $blankForm ? '' : $client->email }}</div>

    <div style="border-top:1.5px solid #000;margin:8pt -34pt 0;"></div>
    <p style="font-size:9.4pt;line-height:1.3;text-align:justify;text-indent:30pt;margin:8pt 0 0;">I hereby swear/affirm that the information provided herein are true and correct to the best of my knowledge and belief. I voluntarily submitted authenticated/original documents/card to further verify my application and that any misrepresentation, omission or falsification of facts may justify denial and cancellation of my application. Further, I have no pending application not given due course in other immigration office.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:60pt;margin-top:26pt;font-size:9.4pt;text-align:center;">
        <div><div class="fillin" style="font-size:8.6pt;">{{ $blankForm ? '' : strtoupper(($petition->filed_on ?? now())->format('d M Y')) }}</div><div style="border-top:0.75px solid #000;padding-top:2pt;">Date [DD-MMM-YYYY e.g. 01 JAN 1990]</div></div>
        <div><div class="fillin" style="font-size:8.6pt;">{{ $printedName }}</div><div style="border-top:0.75px solid #000;padding-top:2pt;">Applicant’s Signature over Printed Name</div></div>
    </div>

    <div style="border-top:1.5px dashed #666;margin:12pt -34pt 6pt;"></div>
    <div style="display:flex;gap:12pt;align-items:center;">
        <img src="{{ $seal }}" alt="" style="width:46pt;height:46pt;object-fit:contain;">
        <div style="flex:1;">
            <div style="font-weight:bold;font-size:8.6pt;">APPLICANT’S ACR I-CARD CLAIM STUB</div>
            <div style="font-size:7pt;">Applicant’s Name [Last Name, First/Given Name, Middle Name (Please leave a box after each name)]</div>
            {{ B::boxes($blankForm ? null : $applicantName, 34, $blankForm) }}
            <div style="display:flex;align-items:center;gap:6pt;margin-top:3pt;font-size:7.6pt;">
                <span>ACR Number</span>{{ B::boxes($d('acr_number'), 14, $blankForm) }}
                <span style="margin-left:auto;">Visa Type</span>{{ B::boxes($d('visa_status'), 10, $blankForm) }}
            </div>
        </div>
    </div>
    <div style="text-align:center;font-size:7pt;margin-top:6pt;">[IF THE ACR I-CARD IS CLAIMED BY AN AUTHORIZED REPRESENTATIVE, PLEASE SEE REVERSE SIDE FOR INSTRUCTIONS.]</div>
    <div style="text-align:right;font-size:7pt;margin-top:4pt;">Page <b>1</b> of <b>2</b></div>
</div>

{{-- ===================== PAGE 2 ===================== --}}
<div class="sheet">
    <div style="display:flex;gap:12pt;align-items:flex-start;">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:50pt;height:50pt;object-fit:contain;">
        <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;">
                <b style="font-size:10.6pt;">BI FORM 2014-08-010 Rev 0</b>
                <span style="font-size:6.6pt;">This document cannot be reproduced and is <b>NOT FOR SALE</b></span>
            </div>
            <div style="font-weight:bold;font-size:12.6pt;line-height:1.15;">APPLICATION FOR ALIEN CERTIFICATE OF REGISTRATION<br>IDENTITY CARD (ACR I-CARD)</div>
        </div>
    </div>

    <div style="border:1.5px solid #000;margin-top:18pt;padding:10pt 14pt;font-size:8.4pt;line-height:1.5;">
        <b>ACR I-CARD WILL ONLY BE RELEASED UPON COMPLIANCE/SUBMISSION OF THE FF:</b><br>
        1. Photocopy of passport bio-page of the ACR I-Card holder<br>
        2. Valid ID of either parent claiming the ACR I-Card, if applicant is a minor<br>
        3. Photocopy of the BI-Accreditation ID card, if claimed by a travel agent or law firm<br>
        4. Special Power of Attorney (SPA), if claimed by an authorized representative other than the parent or BI accredited entity
        <div style="display:flex;gap:30pt;margin-top:16pt;">
            <div>ACR I-Card Holder: <span style="display:inline-block;width:130pt;border-bottom:0.75px solid #000;"></span><br><span style="padding-left:90pt;font-size:7pt;">Signature over PRINTED NAME</span></div>
            <div>Claimant: <span style="display:inline-block;width:120pt;border-bottom:0.75px solid #000;"></span><br><span style="padding-left:80pt;font-size:7pt;">Signature</span></div>
        </div>
        <div style="text-align:center;margin-top:8pt;">[Please call (+632) 525-7557 to check the status of your application]</div>
    </div>

    <div style="border:1.5px solid #000;margin-top:14pt;padding:10pt 14pt;font-size:8.4pt;line-height:2.1;">
        @foreach ([
            'Name of Representative' => $representative ? strtoupper((string) $d('representative_name')) : '',
            'Accredited Travel Agency/Law Office' => '',
            'BI Accreditation No.' => $representative ? $d('accreditation_no') : '',
            'Contact No.' => '',
            'Residential /Office Address' => '',
            'Signature' => '',
        ] as $field => $fieldValue)
            <div style="display:flex;gap:6pt;"><span>{{ $field }}</span><span class="fillin" style="flex:1;max-width:240pt;border-bottom:0.75px solid #000;line-height:1.4;padding-left:4pt;">{{ $fieldValue }}</span></div>
        @endforeach
    </div>
    <div style="text-align:right;font-size:7pt;margin-top:8pt;">Page <b>2</b> of <b>2</b></div>
</div>
</body>
</html>
