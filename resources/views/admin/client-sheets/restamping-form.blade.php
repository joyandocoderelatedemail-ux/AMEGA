@php
    use App\Models\ImmigrationRestamping;

    /**
     * The BI's IRD Consolidated General Application Form (BI Form No. IRD01.QF.006
     * Rev. 03), positioned on the form's own page in points (--u = 8.5in / 612) for
     * 8.5 x 13 in paper. Filled from the client sheet and its re-stamping case, one
     * character per box as the BI asks, or blank to fill in by hand.
     */
    $isBlank = $blank;
    $case = $restamping;

    $at = function (float $x, float $y, ?float $w = null, ?float $h = null): string {
        $style = "left:calc(var(--u)*{$x});top:calc(var(--u)*{$y});";
        $style .= $w !== null ? "width:calc(var(--u)*{$w});" : '';
        $style .= $h !== null ? "height:calc(var(--u)*{$h});" : '';

        return $style;
    };

    // A row of character boxes, filled one character per box (in capitals) unless blank.
    $boxes = function (float $x, float $y, int $count, ?string $value = null, float $width = 15.0, float $height = 16.5) use ($at, $isBlank): string {
        $chars = $isBlank ? [] : mb_str_split(mb_strtoupper(trim((string) $value)));
        $html = '<div class="a cells" style="'.$at($x, $y, $width * $count, $height).'">';
        for ($i = 0; $i < $count; $i++) {
            $html .= '<span class="cell"><span class="fill">'.e($chars[$i] ?? '').'</span></span>';
        }

        return $html.'</div>';
    };

    $day = fn ($date) => $date?->format('d');
    $month = fn ($date) => $date ? strtoupper($date->format('M')) : null;
    $year = fn ($date) => $date?->format('Y');

    $transfer = $case->isTransfer();
    $oldPassport = $case->old_passport_number ?: ($transfer ? null : $client->passport_number);
    $newPassport = $transfer || filled($case->old_passport_number) ? $client->passport_number : null;
    $address = trim((string) $client->address);
    $addressFits = mb_strlen($address) <= 46;
    $signedOn = $case->filed_on ?? now();
    $printedName = strtoupper((string) $client->full_name);
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IRD Application Form{{ $isBlank ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { --u: calc(8.5in / 612); font-family: Tahoma, Verdana, Arial, sans-serif; }
        .a { position: absolute; }
        .label { font-size: 7.4pt; line-height: 1; white-space: nowrap; }
        .cells { display: flex; }
        .cell { flex: 1 1 0; border: 1px solid #8a8a8a; margin-left: -1px; display: flex; align-items: center; justify-content: center; font-size: 9pt; }
        .cell:first-child { margin-left: 0; }
        .tick { width: calc(var(--u)*15); height: calc(var(--u)*15); border: 1px solid #8a8a8a; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 10pt; }
        .rule { border-top: 1px solid #000; height: 0; }
        .center { transform: translateX(-50%); text-align: center; white-space: nowrap; }
        .small { font-size: 7.1pt; line-height: 1.2; }
        .fill { font-family: Arial, Helvetica, sans-serif; }
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
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale.</span>
</div>

<div class="sheet">
    {{-- Masthead --}}
    <div class="a" style="{{ $at(46, 16, 52, 52) }}"><img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:100%;height:100%;object-fit:contain;"></div>
    <div class="a" style="{{ $at(109, 24) }}font-weight:bold;font-size:10.6pt;white-space:nowrap;">
        BI FORM NO. IRD01.QF.006 REV. 03
        <span style="font-weight:normal;font-size:7pt;color:#777;margin-left:6px;">This document may be reproduced and is <b>NOT FOR SALE</b></span>
    </div>
    <div class="a" style="{{ $at(109, 37) }}font-weight:bold;font-size:12.6pt;line-height:1.12;white-space:nowrap;">CONSOLIDATED GENERAL APPLICATION FORM<br>IMMIGRATION REGULATION DIVISION</div>

    {{-- Name --}}
    <div class="a label" style="{{ $at(36, 70) }}">Last Name</div>
    {!! $boxes(41, 79, 36, $client->last_name) !!}
    <div class="a label" style="{{ $at(36, 97) }}">First/Given Name</div>
    {!! $boxes(41, 106, 36, $client->given_name) !!}
    <div class="a label" style="{{ $at(36, 124) }}">Middle Name</div>
    {!! $boxes(41, 133, 36, $case->middle_name) !!}

    {{-- Birth date and nationality --}}
    <div class="a label" style="{{ $at(36, 152) }}">Date of Birth [DD-MMM-YYYY e.g. 01 JAN 1990]</div>
    {!! $boxes(41, 160, 2, $day($client->date_of_birth)) !!}
    {!! $boxes(79, 160, 3, $month($client->date_of_birth)) !!}
    {!! $boxes(133, 160, 4, $year($client->date_of_birth)) !!}
    <div class="a label" style="{{ $at(224, 152) }}">Citizenship/Nationality</div>
    {!! $boxes(225, 160, 23, $client->nationality, 15.4) !!}

    {{-- Passports and contact --}}
    <div class="a label" style="{{ $at(36, 179) }}">Passport Number [Old]</div>
    {!! $boxes(41, 188, 11, $oldPassport, 15.6) !!}
    <div class="a label" style="{{ $at(225, 179) }}">Landline</div>
    {!! $boxes(225, 188, 9, $case->landline, 16.1) !!}
    <div class="a label" style="{{ $at(382, 179) }}">Mobile</div>
    {!! $boxes(383, 188, 13, $client->mobile_number, 15.2) !!}

    <div class="a label" style="{{ $at(36, 206) }}">Passport Number [New]</div>
    {!! $boxes(41, 214, 11, $newPassport, 15.6) !!}
    <div class="a label" style="{{ $at(226, 206) }}">Email Address</div>
    <div class="a" style="{{ $at(225, 214, 261, 17) }}border:1px solid #8a8a8a;display:flex;align-items:center;padding-left:4px;">
        <span class="fill" style="font-size:8.6pt;">{{ $isBlank ? '' : $client->email }}</span>
    </div>

    {{-- Arrival --}}
    <div class="a label" style="{{ $at(36, 233) }}">Latest Date of Arrival [DD-MMM-YYYY e.g. 01 JAN 1990]</div>
    {!! $boxes(41, 242, 2, $day($case->arrival_date)) !!}
    {!! $boxes(78, 242, 3, $month($case->arrival_date)) !!}
    {!! $boxes(129, 242, 4, $year($case->arrival_date)) !!}
    <div class="a label" style="{{ $at(253, 233) }}">Flight Number</div>
    {!! $boxes(255, 242, 5, preg_replace('/\s+/', '', (string) $case->flight_number)) !!}
    <div class="a label" style="{{ $at(343, 233) }}">BIBS No.</div>
    {!! $boxes(343, 242, 4, $case->bibs_number, 15.5) !!}
    <div class="a label" style="{{ $at(429, 233) }}">Status of Admission</div>
    {!! $boxes(430, 242, 10, $case->admission_status) !!}

    {{-- Application --}}
    <div class="a" style="{{ $at(36, 272) }}font-size:9.6pt;">Application: [please check]</div>
    @foreach (array_keys(ImmigrationRestamping::APPLICATIONS) as $i => $key)
        @php $y = [289, 307, 324, 342, 359][$i]; @endphp
        <div class="a tick" style="{{ $at(34, $y) }}"><span class="fill">{{ ! $isBlank && $case->applies($key) ? '✓' : '' }}</span></div>
        <div class="a" style="{{ $at(57, $y + 3) }}font-size:8.8pt;white-space:nowrap;">{{ ImmigrationRestamping::APPLICATIONS[$key] }}</div>
    @endforeach

    {{-- Address --}}
    <div class="a label" style="{{ $at(224, 266) }}font-size:8pt;">Residential/Registered Address in the Philippines</div>
    @if ($isBlank || $addressFits)
        {!! $boxes(224, 274, 23, $isBlank ? null : mb_substr($address, 0, 23), 15.35, 22) !!}
        {!! $boxes(224, 301, 23, $isBlank ? null : mb_substr($address, 23, 23), 15.35, 28) !!}
    @else
        {{-- Too long for the boxes: written across both rows instead --}}
        {!! $boxes(224, 274, 23, null, 15.35, 22) !!}
        {!! $boxes(224, 301, 23, null, 15.35, 28) !!}
        <div class="a" style="{{ $at(228, 276, 346, 50) }}background:#fff;display:flex;align-items:center;line-height:1.25;">
            <span class="fill" style="font-size:8.6pt;text-transform:uppercase;">{{ $address }}</span>
        </div>
    @endif

    {{-- Data privacy consent --}}
    <div class="a" style="{{ $at(224, 333, 355, 96) }}border:1px solid #c8c8c8;padding:calc(var(--u)*3) calc(var(--u)*3);">
        <div style="text-align:center;font-weight:bold;font-size:7.6pt;">DATA PRIVACY CONSENT</div>
        <div style="font-size:7.3pt;line-height:1.2;text-align:justify;">By completing this form, I give permission for my data to be held in the Bureau of Immigration database and agree that BI may process my personal data relating to my application in accordance with the Data Privacy Act of 2012 (R.A. 10178).</div>
    </div>
    <div class="a center" style="{{ $at(501, 382) }}"><span class="fill" style="font-size:7.6pt;">{{ $isBlank ? '' : $printedName }}</span></div>
    <div class="a rule" style="{{ $at(430, 397, 143) }}"></div>
    <div class="a small" style="{{ $at(573, 401) }}transform:translateX(-100%);text-align:right;white-space:nowrap;">Signature over PRINTED NAME of<br>Applicant or Authorized Representative</div>

    {{-- Reason --}}
    <div class="a" style="{{ $at(36, 421) }}font-size:9pt;">Reason/Explanation</div>
    @foreach ([444, 459, 474] as $lineY)
        <div class="a rule" style="{{ $at(36, $lineY, 537) }}border-top-color:#555;"></div>
    @endforeach
    @unless ($isBlank)
        <div class="a" style="{{ $at(38, 431, 533, 45) }}overflow:hidden;line-height:calc(var(--u)*15);">
            <span class="fill" style="font-size:8.4pt;">{{ $case->reason }}</span>
        </div>
    @endunless

    {{-- Certification --}}
    <div class="a center" style="{{ $at(306, 489) }}font-weight:bold;font-size:8.6pt;">CERTIFICATION</div>
    <div class="a" style="{{ $at(36, 500, 541) }}font-size:7.3pt;line-height:1.3;text-align:justify;">
        I/We certify that: 1. All information in the application is truthful, complete and correct; 2. All documents are authentic and were legally obtained from the corresponding government agencies and private entities; 3. I/We understand that my/our application may be summarily denied if: a. Any statement is false; b. Any documents submitted is falsified; or c. I/We fall to comply with all the BI requirements without prejudice to whatever action the BI may take; and 4. I/We have not filed this or any similar application before any office of the Bureau.
    </div>

    <div class="a center" style="{{ $at(144, 555) }}"><span class="fill" style="font-size:8.6pt;">{{ $isBlank ? '' : $printedName }}</span></div>
    <div class="a rule" style="{{ $at(36, 568, 217) }}border-top-width:1.5px;"></div>
    <div class="a center small" style="{{ $at(144, 572) }}">Signature over PRINTED NAME of Applicant<br>or Authorized Representative</div>

    {!! $boxes(377, 565, 2, $isBlank ? null : $day($signedOn), 14.5, 17) !!}
    {!! $boxes(420, 565, 3, $isBlank ? null : $month($signedOn), 14.5, 17) !!}
    {!! $boxes(478, 565, 4, $isBlank ? null : $year($signedOn), 14.5, 17) !!}
    <div class="a center small" style="{{ $at(445, 586) }}">Date [DD-MMM-YYYY e.g. 01 JAN 1990]</div>

    {{-- For the BI --}}
    <div class="a" style="{{ $at(36, 598) }}font-size:7.4pt;font-weight:bold;font-style:italic;">[To be filled out by Authorized BI Personnel Only]</div>
    <div class="a rule" style="{{ $at(344, 603, 234) }}border-top-width:2px;"></div>
    <div class="a" style="{{ $at(36, 618, 554, 210) }}border:3px solid #000;"></div>
    <div class="a" style="{{ $at(331, 618, 3, 210) }}background:#000;"></div>
    <div class="a center" style="{{ $at(184, 625) }}font-weight:bold;font-size:11pt;">IRD &nbsp;STAMP/NOTATION</div>
    <div class="a center" style="{{ $at(461, 625) }}font-weight:bold;font-size:11pt;">NO DEROGATORY RECORD STAMP</div>
    <div class="a rule" style="{{ $at(48, 799, 113) }}"></div>
    <div class="a rule" style="{{ $at(202, 799, 114) }}"></div>
    <div class="a center small" style="{{ $at(104, 803) }}">Signature over PRINTED NAME<br>Immigration Officer</div>
    <div class="a center small" style="{{ $at(259, 803) }}">Signature over PRINTED NAME<br>Reviewer/Supervisor</div>

    <div class="a" style="{{ $at(36, 831, 554, 32) }}background:#ddd;display:flex;align-items:center;gap:calc(var(--u)*6);padding:0 calc(var(--u)*8);font-weight:bold;font-size:9.6pt;white-space:nowrap;">
        DOCUMENTS RELEASED TO: <span style="flex:1;border-bottom:1px solid #000;height:1em;"></span>
        DATE: <span style="width:calc(var(--u)*72);border-bottom:1px solid #000;height:1em;"></span>
        TIME: <span style="width:calc(var(--u)*62);border-bottom:1px solid #000;height:1em;"></span>
    </div>

    <div class="a" style="{{ $at(576, 873) }}transform:translateX(-100%);font-size:7.4pt;white-space:nowrap;">IRD01.QF.006 effective 08 October 2020 Rev 03</div>
</div>
</body>
</html>
