@php
    use App\Models\TouristVisaApplication as TVA;

    /**
     * The BI's Checklist of Documentary Requirements for Extension and Updating
     * of Stay of Temporary Visitors (IRD04.QF.007 Rev. Lev.03), with its
     * certification and claim slip, positioned on the form's own 880 x 1350
     * page grid (--u = 8.5in / 880) for 8.5 x 13 in paper.
     */
    $app = $application;
    // The Immigration desk fills this from a client sheet without saving an application.
    $isBlank = $blank ?? ! $app->exists;

    $at = function (float $x, float $y, ?float $w = null, ?float $h = null): string {
        $style = "left:calc(var(--u)*{$x});top:calc(var(--u)*{$y});";
        $style .= $w !== null ? "width:calc(var(--u)*{$w});" : '';
        $style .= $h !== null ? "height:calc(var(--u)*{$h});" : '';

        return $style;
    };

    $tick = fn (string $item) => $app->hasChecked($item) ? '&#10003;' : '';

    $markPath = 'images/logoapproved/bagong-pilipinas.png';
    $hasMark = file_exists(public_path($markPath));
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checklist - Extension of Stay{{ $isBlank ? '' : ' - '.$app->formal_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { --u: calc(8.5in / 880); font-size: 9.6pt; }
        .a { position: absolute; }
        .mid { transform: translateY(-50%); white-space: nowrap; }
        .center { transform: translate(-50%, -50%); white-space: nowrap; text-align: center; }
        .right { transform: translate(-100%, -50%); white-space: nowrap; }
        .rule { border-top: 1px solid #000; height: 0; }
        .item-box { width: calc(var(--u)*23); height: calc(var(--u)*24); font-size: 12pt; }
        .instructions { margin: 0; padding-left: calc(var(--u)*22); font-size: 9.9pt; line-height: 1.24; text-align: justify; }
        .instructions li { padding-left: calc(var(--u)*2); }
        .slip td { border: 1px solid #000; padding: 0 calc(var(--u)*10); font-size: 9pt; line-height: 1.1; vertical-align: middle; }
        .slip small { display: block; font-size: 5.6pt; font-style: italic; line-height: 1; }
        .fill-line { border-bottom: 1px solid #000; display: flex; align-items: flex-end; justify-content: center; line-height: 1; padding-bottom: calc(var(--u)*2); }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print checklist</button>
    @if (isset($links))
        @foreach ($links as [$linkLabel, $linkUrl])
            <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
        @endforeach
    @elseif ($isBlank)
        <a class="ghost" href="{{ route('visa.tourist-visa.index') }}">Back to applications</a>
    @else
        <a class="ghost" href="{{ route('visa.tourist-visa.edit', $app) }}">Back to application</a>
        <a class="ghost" href="{{ route('visa.tourist-visa.print-form', $app) }}">Application form</a>
    @endif
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale.</span>
</div>

<div class="sheet">
    <div class="a right" style="{{ $at(838, 30) }}font-size:7.4pt;font-weight:bold;text-decoration:underline;">THIS DOCUMENT MAY BE REPRODUCED AND IS NOT FOR SALE</div>

    {{-- Masthead --}}
    <div class="a" style="{{ $at(183, 52, 67, 67) }}"><img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:100%;height:100%;object-fit:contain;"></div>
    @if ($hasMark)
        <div class="a" style="{{ $at(624, 52, 68, 68) }}"><img src="{{ asset($markPath) }}" alt="Bagong Pilipinas" style="width:100%;height:100%;object-fit:contain;"></div>
    @endif
    <div class="a serif" style="{{ $at(443, 52) }}transform:translateX(-50%);text-align:center;line-height:1.12;">
        <div style="font-size:10.2pt;">REPUBLIC OF THE PHILIPPINES</div>
        <div style="font-size:10.2pt;">DEPARTMENT OF JUSTICE</div>
        <div style="font-size:16.4pt;font-weight:bold;">BUREAU OF IMMIGRATION</div>
    </div>

    <div class="a center" style="{{ $at(443, 142) }}font-weight:bold;font-size:12.6pt;">EXTENSION AND UPDATING OF STAY OF TEMPORARY VISITORS</div>
    <div class="a center" style="{{ $at(443, 174) }}font-weight:bold;font-size:9.6pt;letter-spacing:4px;">GENERAL INSTRUCTIONS</div>

    {{-- General instructions --}}
    <div class="a" style="{{ $at(52, 190, 786) }}">
        <ol class="instructions">
            <li>All documents required for submission must be complied with, arranged in the order as listed in the Checklist of Documentary Requirements (CDR), compiled in a legal sized folder and shall be submitted within the prescribed period, if applicable. Otherwise, the application shall not be accepted.</li>
            <li>All civil registry documents issued in the Philippines (i.e. Birth Certificate, Marriage Certificate, Death Certificate, etc.) must be original and issued by the Philippine Statistics Authority (PSA).</li>
            <li>All foreign documents must be original and authenticated by the Philippine Foreign Service Post (FSP) which has jurisdiction over the place of issuance, or by the Department of Foreign Affairs (DFA) if said document is issued by the local Embassy in the Philippines with English translation if written in another foreign language.</li>
            <li>If the application is filed by an authorized representative, attach a photocopy of the Bureau of Immigration (BI) Accreditation Identification (ID) Certificate or an original Special Power of Attorney (SPA) for <b>EACH</b> applicant with a photocopy of a valid government-issued ID of the attorney in-fact.</li>
        </ol>
    </div>

    <div class="a rule" style="{{ $at(47, 425, 791) }}"></div>

    {{-- Checklist --}}
    <div class="a center" style="{{ $at(443, 457) }}font-weight:bold;font-size:12.2pt;line-height:1.15;">CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR EXTENSION<br>AND UPDATING OF STAY OF TEMPORARY VISITORS</div>
    <div class="a" style="{{ $at(57, 484, 783) }}font-size:10.2pt;line-height:1.24;">Kindly read carefully the above-stated General Instruction before proceeding with the completion of Documentary requirements.</div>

    @foreach (array_keys(TVA::CHECKLIST) as $i => $key)
        @php $y = [528, 563, 597][$i]; @endphp
        <span class="a box item-box" style="{{ $at(57, $y) }}">{!! $tick($key) !!}</span>
        <div class="a mid" style="{{ $at(90, $y + 12) }}font-size:10.4pt;">
            {{ $i + 1 }}.&nbsp;{{ TVA::CHECKLIST[$key] }}@if (in_array($key, TVA::CHECKLIST_IF_APPLICABLE, true))&nbsp;<i>(If applicable)</i>@endif
        </div>
    @endforeach

    <div class="a mid" style="{{ $at(62, 640) }}font-size:10.2pt;font-style:italic;">Additional requirement(s) if applicant overstayed for more than six (6) months or more than the maximum allowable stay:</div>
    <span class="a box item-box" style="{{ $at(57, 665) }}">{!! $tick('overstay_letter') !!}</span>
    <div class="a" style="{{ $at(87, 664, 751) }}font-size:10.4pt;line-height:1.15;">{{ TVA::OVERSTAY_CHECKLIST['overstay_letter'] }}</div>

    <div class="a center" style="{{ $at(443, 722) }}font-size:10.2pt;font-style:italic;"><b>Note:</b> The BI may require the submission of additional documents for further evaluation of application.</div>

    <div class="a rule" style="{{ $at(47, 746, 795) }}"></div>

    {{-- Certification --}}
    <div class="a center" style="{{ $at(443, 770) }}font-weight:bold;font-size:14pt;">CERTIFICATION</div>
    <div class="a mid" style="{{ $at(52, 818) }}font-size:11.4pt;">This is to certify that the documents submitted in support of the application of</div>
    <div class="a fill-line" style="{{ $at(610, 806, 232, 20) }}"><span class="fill" style="font-size:8.6pt;">{{ $app->formal_name }}</span></div>
    <div class="a mid" style="{{ $at(640, 831) }}font-size:6.2pt;font-style:italic;">(Last name, First name, Middle name)</div>
    <div class="a rule" style="{{ $at(47, 864, 233) }}"></div>
    <div class="a mid" style="{{ $at(283, 858) }}font-size:11.4pt;">are complete and in accordance with the provided checklist.</div>

    <div class="a center fill" style="{{ $at(211, 929) }}font-size:8.6pt;">{{ $app->evaluator_name }}</div>
    <div class="a rule" style="{{ $at(47, 938, 328) }}"></div>
    <div class="a mid" style="{{ $at(100, 948) }}font-size:9.4pt;">Evaluator <i style="font-size:8pt;">(Signature over Printed Name)</i></div>
    <div class="a center fill" style="{{ $at(713, 929) }}font-size:8.6pt;"></div>
    <div class="a rule" style="{{ $at(615, 938, 197) }}"></div>
    <div class="a mid" style="{{ $at(624, 949) }}font-size:9.4pt;">Date:<i>(DD MMM YYYY)</i></div>

    <div class="a" style="{{ $at(47, 966, 791, 49) }}border:1px solid #000;padding:calc(var(--u)*4) calc(var(--u)*5);font-size:6.9pt;font-style:italic;line-height:1.2;">
        The Bureau of Immigration (BI) commits to protect and respect personal data privacy pursuant to the provisions of Republic Act No. 10173 or the Data Privacy Act of 2012 and its Implementing Rules and Regulations. The entire Data Privacy Notice of the BI is accessible in this link: https://immigration.gov.ph/information/data-privacy.
    </div>
    <div class="a right" style="{{ $at(838, 1022) }}font-size:7.2pt;font-style:italic;">Page <b>1</b> of <b>1</b></div>
    <div class="a right" style="{{ $at(838, 1038) }}font-size:8pt;">IRD04.QF.007 Rev. Lev.03 Effective: 04 JUN 2026</div>

    {{-- Cut here: the claim slip --}}
    <div class="a" style="{{ $at(0, 1056, 880) }}border-top:2px dotted #000;"></div>

    <div class="a" style="{{ $at(52, 1068, 64, 64) }}"><img src="{{ $seal }}" alt="" style="width:100%;height:100%;object-fit:contain;"></div>
    @if ($hasMark)
        <div class="a" style="{{ $at(782, 1070, 58, 58) }}"><img src="{{ asset($markPath) }}" alt="" style="width:100%;height:100%;object-fit:contain;"></div>
    @endif
    <div class="a center" style="{{ $at(443, 1079) }}font-weight:bold;font-size:12.4pt;">CLAIM SLIP</div>
    <div class="a center" style="{{ $at(443, 1108) }}font-size:9.4pt;font-style:italic;">Present this slip to claim your documents at the Tourist Visa Section (TVS), Ground Floor, BI Main Office.</div>

    <table class="a slip" style="{{ $at(47, 1141, 785, 86) }}border-collapse:collapse;">
        <tr style="height:calc(var(--u)*44);">
            <td style="width:calc(var(--u)*163);">NAME OF APPLICANT:<small>(Last name, First name, Middle name)</small></td>
            <td style="width:calc(var(--u)*320);"><span class="fill" style="font-size:8.6pt;">{{ $app->formal_name }}</span></td>
            <td style="width:calc(var(--u)*98);padding:0 calc(var(--u)*5);white-space:nowrap;">DATE FILED:<small>(DD MMM YYYY)</small></td>
            <td><span class="fill" style="font-size:8.6pt;">{{ TVA::formDate($app->filed_on) }}</span></td>
        </tr>
        <tr style="height:calc(var(--u)*42);">
            <td>PASSPORT/TRAVEL<br>DOCUMENT NO.:</td>
            <td><span class="fill" style="font-size:8.6pt;">{{ $app->passport_number }}</span></td>
            <td style="padding:0 calc(var(--u)*5);white-space:nowrap;">COUNTRY OF<br>CITIZENSHIP:</td>
            <td><span class="fill" style="font-size:8.6pt;">{{ $app->citizenship }}</span></td>
        </tr>
    </table>

    <div class="a center" style="{{ $at(443, 1272) }}font-size:8pt;font-weight:bold;font-style:italic;line-height:1.2;">
        Note: Failure to claim the passport within five (5) working days from the scheduled date of release shall result in the turnover<br>of the passport to the concerned Embassy or Consulate, pursuant to applicable immigration laws and regulations.
    </div>
    <div class="a center" style="{{ $at(443, 1312) }}font-size:6.8pt;font-style:italic;line-height:1.25;">
        If claimed by an authorized representative, present a Special Power of Attorney and original valid Goverment-issued Identification (ID) Card.<br>For inquiries e-mail to ird.tvs@immigration.gov.ph.
    </div>
</div>
</body>
</html>
