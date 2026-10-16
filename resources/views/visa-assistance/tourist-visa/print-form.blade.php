@php
    use App\Models\TouristVisaApplication as TVA;

    /**
     * The BI's CGAF for Extension of Stay (IRD04.QF.004 Rev. Lev. 4), laid out
     * element by element on its own page. Positions are in units of the form's
     * 880 x 1350 page image (--u = 8.5in / 880), so every box sits where the
     * BI's does on 8.5 x 13 in paper.
     */
    $app = $application;
    // The Immigration desk fills this from a client sheet without saving an application.
    $isBlank = $blank ?? ! $app->exists;

    // style="left/top/width/height" in form units
    $at = function (float $x, float $y, ?float $w = null, ?float $h = null): string {
        $style = "left:calc(var(--u)*{$x});top:calc(var(--u)*{$y});";
        $style .= $w !== null ? "width:calc(var(--u)*{$w});" : '';
        $style .= $h !== null ? "height:calc(var(--u)*{$h});" : '';

        return $style;
    };

    $tick = fn (bool $on) => $on ? '&#10003;' : '';
    $parts = fn ($date) => TVA::dateParts($date);
    $dob = $parts($app->date_of_birth);
    $passportValidity = $parts($app->passport_validity);
    $arrival = $parts($app->latest_arrival_date);
    $extension = $parts($app->latest_extension_validity);
    $acrValidity = $parts($app->acr_validity);

    $markPath = 'images/logoapproved/bagong-pilipinas.png';
    $hasMark = file_exists(public_path($markPath));
    $signatory = $app->application_method === 'representative' ? $app->representative_name : $app->full_name;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CGAF - Extension of Stay{{ $isBlank ? '' : ' - '.$app->formal_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { --u: calc(8.5in / 880); font-size: 9pt; }
        .a { position: absolute; white-space: nowrap; }
        .lbl { font-size: 8.6pt; line-height: 1; transform: translateY(-50%); }
        .lbl i, .cap i { font-style: italic; }
        .hd { font-weight: bold; font-size: 9pt; line-height: 1; transform: translateY(-50%); }
        .hd.serif { font-size: 9.2pt; }
        .field { border: 1px solid #000; background: #fff; display: flex; align-items: center; padding: 0 calc(var(--u)*4); overflow: hidden; font-size: 8.4pt; }
        .field.center { justify-content: center; padding: 0; }
        .cap { font-size: 6.2pt; font-style: italic; line-height: 1; transform: translateY(-50%); }
        .dash { font-size: 9pt; line-height: 1; transform: translate(-50%, -50%); }
        .chk { width: calc(var(--u)*14); height: calc(var(--u)*13); font-size: 8pt; transform: translateY(-50%); }
        .chk + .lbl { transform: translateY(-50%); }
        .rule { border-top: 1px solid #000; height: 0; }
        .photo { border: 1px solid #000; display: flex; align-items: center; padding: 0 calc(var(--u)*10); font-size: 7.2pt; line-height: 1.32; white-space: normal; text-align: justify; }
        .reason { white-space: normal; line-height: calc(var(--u)*21.2); font-size: 9pt; overflow: hidden; word-break: break-word; }
        .cert { border: 1px solid #000; white-space: normal; padding: calc(var(--u)*5) calc(var(--u)*9); font-size: 5.5pt; font-style: italic; line-height: 1.24; overflow: hidden; }
        .cert ol { margin: 0; padding-left: calc(var(--u)*14); }
        .cert ol.roman { list-style: lower-roman; padding-left: calc(var(--u)*30); }
        .cert .blank { display: inline-block; min-width: calc(var(--u)*110); border-bottom: 1px solid #000; text-align: center; font-style: normal; }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print application form</button>
    @if (isset($links))
        @foreach ($links as [$linkLabel, $linkUrl])
            <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
        @endforeach
    @elseif ($isBlank)
        <a class="ghost" href="{{ route('visa.tourist-visa.index') }}">Back to applications</a>
    @else
        <a class="ghost" href="{{ route('visa.tourist-visa.edit', $app) }}">Back to application</a>
        <a class="ghost" href="{{ route('visa.tourist-visa.print-checklist', $app) }}">Checklist &amp; claim slip</a>
    @endif
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale.</span>
</div>

<div class="sheet">
    {{-- Masthead --}}
    <div class="a" style="{{ $at(783, 22) }}transform:translate(-100%,-50%);font-size:8pt;font-style:italic;color:#888;">This document may be reproduced and is NOT for sale.</div>
    <div class="a" style="{{ $at(108, 45, 70, 70) }}"><img src="{{ asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png') }}" alt="Bureau of Immigration seal" style="width:100%;height:100%;object-fit:contain;"></div>
    @if ($hasMark)
        <div class="a" style="{{ $at(700, 42, 76, 80) }}"><img src="{{ asset($markPath) }}" alt="Bagong Pilipinas" style="width:100%;height:100%;object-fit:contain;"></div>
    @endif
    <div class="a serif" style="{{ $at(443, 46) }}transform:translateX(-50%);text-align:center;line-height:1.12;">
        <div style="font-size:9.6pt;letter-spacing:.2px;">REPUBLIC OF THE PHILIPPINES</div>
        <div style="font-size:9.6pt;letter-spacing:.2px;">DEPARTMENT OF JUSTICE</div>
        <div style="font-size:15.5pt;letter-spacing:.3px;">BUREAU OF IMMIGRATION</div>
        <div style="font-size:9.6pt;letter-spacing:.2px;">MAGALLANES DRIVE, INTRAMUROS</div>
        <div style="font-size:9pt;font-variant:small-caps;">1002 Manila</div>
    </div>

    <div class="a" style="{{ $at(443, 172) }}transform:translateX(-50%);text-align:center;font-weight:bold;font-size:13.4pt;line-height:1.3;">
        CONSOLIDATED GENERAL APPLICATION FORM (CGAF)<br>FOR EXTENSION OF STAY OF TEMPORARY VISITOR’S VISA
    </div>

    {{-- Photo box --}}
    <div class="a photo" style="{{ $at(103, 222, 204, 200) }}{{ $app->photo_path ? 'padding:0;' : '' }}">
        @if ($app->photo_path)
            <img src="{{ route('visa.tourist-visa.photo', $app) }}?v={{ $app->updated_at?->timestamp }}" alt="Applicant photo" style="display:block;width:100%;height:100%;object-fit:cover;">
        @else
        <p style="margin:0;">If the applicant overstayed for six (6) months or more than the allowable stay, kindly attach a colored photograph with white background using permanent glue in the photograph box. The <b>photo size should be 2x2 inches</b> and must be in standard close-up full-face photo, taken within the past three (3) months. Computer generated or photocopied picture is not acceptable.</p>
        @endif
    </div>

    {{-- Method and I. Application information --}}
    <div class="a hd" style="{{ $at(316, 243) }}">Method of Application:</div>
    <span class="a box chk" style="{{ $at(316, 259) }}">{!! $tick($app->application_method === 'personal') !!}</span>
    <div class="a lbl" style="{{ $at(334, 259) }}">Personal</div>
    <span class="a box chk" style="{{ $at(474, 259) }}">{!! $tick($app->application_method === 'representative') !!}</span>
    <div class="a lbl" style="{{ $at(492, 259) }}">Authorized Representative</div>
    <div class="a lbl" style="{{ $at(316, 275) }}">Name of Authorized Representative <i>(Last name, First name, Middle name)</i></div>
    <div class="a field" style="{{ $at(316, 283, 467, 18) }}"><span class="fill">{{ $app->representative_name }}</span></div>
    <div class="a lbl" style="{{ $at(316, 310) }}">BI Accreditation Number <i>(if applicable)</i></div>
    <div class="a field" style="{{ $at(316, 318, 467, 18) }}"><span class="fill">{{ $app->bi_accreditation_number }}</span></div>
    <div class="a hd serif" style="{{ $at(316, 345) }}">I. APPLICATION INFORMATION:<span style="font-weight:normal;font-style:italic;font-family:Arial, sans-serif;font-size:8.4pt;">(please check)</span></div>
    <div class="a lbl" style="{{ $at(316, 360) }}">Mode of Application:</div>
    <span class="a box chk" style="{{ $at(316, 376) }}">{!! $tick($app->application_mode === 'regular') !!}</span>
    <div class="a lbl" style="{{ $at(334, 376) }}">Regular</div>
    <span class="a box chk" style="{{ $at(420, 376) }}">{!! $tick($app->application_mode === 'express') !!}</span>
    <div class="a lbl" style="{{ $at(438, 376) }}">Express</div>
    <div class="a lbl" style="{{ $at(316, 392) }}">Number of Months Requested:</div>
    @foreach ([1 => [316, 'One (1) month'], 2 => [452, 'Two (2) months'], 6 => [590, 'Six (6) months']] as $months => [$x, $text])
        <span class="a box chk" style="{{ $at($x, 408) }}">{!! $tick($app->months_requested === $months && ! $isBlank) !!}</span>
        <div class="a lbl" style="{{ $at($x + 18, 408) }}">{{ $text }}</div>
    @endforeach

    {{-- Reason, written on the form's ruled lines --}}
    <div class="a hd" style="{{ $at(103, 433) }}">State your reason for extension of stay including reason for overstaying, if applicable:</div>
    @foreach ([456, 477, 498, 519, 541, 562] as $y)
        <div class="a rule" style="{{ $at(103, $y, 680) }}"></div>
    @endforeach
    <div class="a rule" style="{{ $at(103, 583, 300) }}"></div>
    <div class="a reason fill" style="{{ $at(106, 438, 674, 127.2) }}text-transform:none;">{{ $app->reason }}</div>
    <div class="a field" style="{{ $at(413, 566, 364, 22) }}font-size:6.6pt;">Name and signature of evaluator/ assessor:&nbsp;<span style="flex:1;border-bottom:1px solid #000;height:calc(var(--u)*9);"></span></div>

    {{-- The framed lower half: contact, II to V, and the certification --}}
    <div class="a" style="{{ $at(103, 590, 680, 706) }}border:1px solid #000;border-bottom:0;"></div>

    <div class="a hd" style="{{ $at(110, 598) }}">Contact Information:</div>
    <div class="a lbl" style="{{ $at(110, 612) }}">Mobile No. <i>(in the Philippines)</i>:</div>
    <div class="a lbl" style="{{ $at(463, 612) }}">E-mail address:</div>
    <div class="a field" style="{{ $at(103, 621, 322, 19) }}"><span class="fill">{{ $app->mobile_number }}</span></div>
    <div class="a field" style="{{ $at(457, 621, 323, 19) }}"><span class="fill" style="text-transform:none;">{{ $app->email }}</span></div>

    <div class="a hd serif" style="{{ $at(110, 650) }}">II. PERSONAL INFORMATION:</div>
    @foreach ([[665, 'Last name:', $app->last_name], [699, 'First name:', $app->first_name], [732, 'Middle name:', $app->middle_name], [766, 'Other name/Alias:', $app->alias]] as [$y, $text, $value])
        <div class="a lbl" style="{{ $at(110, $y) }}">{{ $text }}</div>
        <div class="a field" style="{{ $at(103, $y + 9, 674, 18) }}"><span class="fill">{{ $value }}</span></div>
    @endforeach

    {{-- Date of birth, sex, birthplace --}}
    <div class="a lbl" style="{{ $at(110, 800) }}">Date of Birth <i>(e.g. 01 JAN 1990)</i>:</div>
    <div class="a lbl" style="{{ $at(355, 800) }}">Sex:</div>
    <div class="a lbl" style="{{ $at(524, 800) }}">Birthplace:</div>
    @include('visa-assistance.tourist-visa._date-boxes', ['x' => [103, 165, 257], 'w' => [42, 72, 65], 'y' => 806, 'h' => 16, 'date' => $dob, 'captionY' => 829])
    <span class="a box" style="{{ $at(350, 806, 22, 16) }}font-size:8pt;">{!! $tick($app->sex === 'M') !!}</span>
    <div class="a lbl" style="{{ $at(386, 814) }}">M</div>
    <span class="a box" style="{{ $at(415, 806, 22, 16) }}font-size:8pt;">{!! $tick($app->sex === 'F') !!}</span>
    <div class="a lbl" style="{{ $at(442, 814) }}">F</div>
    <div class="a field" style="{{ $at(518, 806, 262, 16) }}"><span class="fill">{{ $app->birthplace }}</span></div>

    {{-- Citizenship, height, weight, civil status --}}
    <div class="a lbl" style="{{ $at(110, 840) }}">Country of Citizenship:</div>
    <div class="a lbl" style="{{ $at(352, 840) }}font-size:7.6pt;">Height <i>(cm)</i>:</div>
    <div class="a lbl" style="{{ $at(413, 840) }}font-size:7.6pt;">Weight <i>(kg)</i>:</div>
    <div class="a lbl" style="{{ $at(529, 838) }}">Civil Status:</div>
    <div class="a field" style="{{ $at(103, 848, 219, 16) }}"><span class="fill">{{ $app->citizenship }}</span></div>
    <div class="a field center" style="{{ $at(352, 848, 52, 16) }}"><span class="fill">{{ $app->height_cm }}</span></div>
    <div class="a field center" style="{{ $at(413, 848, 53, 16) }}"><span class="fill">{{ $app->weight_kg }}</span></div>
    @foreach ([['single', 529, 853], ['married', 618, 853], ['annulled', 706, 853], ['separated', 529, 868], ['widowed', 618, 868], ['divorced', 706, 868]] as [$status, $x, $y])
        <span class="a box chk" style="{{ $at($x, $y) }}">{!! $tick($app->civil_status === $status) !!}</span>
        <div class="a lbl" style="{{ $at($x + 18, $y) }}">{{ TVA::CIVIL_STATUSES[$status] }}</div>
    @endforeach

    {{-- Residential address --}}
    <div class="a hd" style="{{ $at(110, 876) }}">Residential Address in the Philippines:</div>
    <div class="a lbl" style="{{ $at(110, 891) }}">House/Unit No., Street, Subdivision/Village:</div>
    <div class="a field" style="{{ $at(103, 901, 674, 18) }}"><span class="fill">{{ $app->address_street }}</span></div>
    @foreach ([[110, 103, 125, 'Barangay:', $app->barangay], [270, 262, 173, 'City/Municipality:', $app->city], [474, 466, 145, 'Province:', $app->province], [640, 637, 140, 'Zip Code:', $app->zip_code]] as [$lx, $bx, $bw, $text, $value])
        <div class="a lbl" style="{{ $at($lx, 927) }}">{{ $text }}</div>
        <div class="a field" style="{{ $at($bx, 934, $bw, 17) }}"><span class="fill">{{ $value }}</span></div>
    @endforeach

    {{-- III. Travel information --}}
    <div class="a hd serif" style="{{ $at(110, 960) }}">III. TRAVEL INFORMATION:</div>
    <div class="a lbl" style="{{ $at(110, 975) }}">Passport/Travel Document No.:</div>
    <div class="a lbl" style="{{ $at(474, 975) }}">Date of Validity <i>(e.g. 01 JAN 1990)</i>:</div>
    <div class="a field" style="{{ $at(103, 983, 184, 17) }}"><span class="fill">{{ $app->passport_number }}</span></div>
    @include('visa-assistance.tourist-visa._date-boxes', ['x' => [466, 518, 590], 'w' => [30, 52, 66], 'y' => 983, 'h' => 16, 'date' => $passportValidity, 'captionY' => 1007])

    <div class="a lbl" style="{{ $at(110, 1018) }}">Date of Latest Arrival <i>(e.g. 01 JAN 1990)</i>:</div>
    <div class="a lbl" style="{{ $at(474, 1018) }}">Flight/Voyage Number:</div>
    @include('visa-assistance.tourist-visa._date-boxes', ['x' => [103, 165, 257], 'w' => [42, 72, 65], 'y' => 1026, 'h' => 16, 'date' => $arrival, 'captionY' => 1049])
    <div class="a field" style="{{ $at(466, 1026, 311, 17) }}"><span class="fill">{{ $app->flight_number }}</span></div>

    {{-- IV. Latest tourist visa extension (the BI's own spelling) --}}
    <div class="a hd serif" style="{{ $at(110, 1058) }}">IV. LATEST TOURIST VISA EXTESION:</div>
    <div class="a lbl" style="{{ $at(110, 1073) }}">Date of Validity <i>(e.g. 01 JAN 1990)</i>:</div>
    <div class="a lbl" style="{{ $at(474, 1073) }}">Place of Approval <i>(e.g. BI Main Office)</i>:</div>
    @include('visa-assistance.tourist-visa._date-boxes', ['x' => [103, 165, 257], 'w' => [42, 72, 65], 'y' => 1081, 'h' => 16, 'date' => $extension, 'captionY' => 1102])
    <div class="a field" style="{{ $at(466, 1081, 311, 17) }}"><span class="fill">{{ $app->latest_extension_place }}</span></div>
    <div class="a cap" style="{{ $at(586, 1102) }}">Name of BI office</div>

    {{-- V. ACR I-Card --}}
    <div class="a hd serif" style="{{ $at(110, 1111) }}">V. ACR I-CARD</div>
    <div class="a" style="{{ $at(110, 1122) }}font-size:6.6pt;transform:translateY(-50%);">Alien Certificate Registration (ACR) Number</div>
    <div class="a" style="{{ $at(322, 1122) }}font-size:6.6pt;transform:translateY(-50%);">Date of Validity <i>(e.g. 01 JAN 1990)</i>:</div>
    <div class="a" style="{{ $at(533, 1122) }}font-size:6.6pt;transform:translateY(-50%);">Special Security Registration Number (SSRN)</div>
    <div class="a field" style="{{ $at(103, 1129, 208, 16) }}"><span class="fill">{{ $app->acr_number }}</span></div>
    @include('visa-assistance.tourist-visa._date-boxes', ['x' => [347, 372, 425], 'w' => [20, 40, 45], 'y' => 1129, 'h' => 16, 'date' => $acrValidity, 'captionY' => null])
    <div class="a field" style="{{ $at(533, 1129, 244, 16) }}"><span class="fill">{{ $app->ssrn }}</span></div>

    {{-- Certification --}}
    <div class="a cert" style="{{ $at(103, 1155, 680, 141) }}">
        By signing this application/form, I hereby certify that:
        <ol>
            <li>I have read and understood the questions in the application form and that my answers are true and correct to the best of knowledge;</li>
            <li>All documents submitted were legally obtained from and authenticated by appropriate government agency/ies or private entities; and</li>
            <li>I understand that any false or misleading statement made, or falsified document submitted by me or my representative may:
                <ol class="roman">
                    <li>Result in the summary denial of my application, if applicable,</li>
                    <li>Subject me to criminal prosecution and/or deportation, or</li>
                    <li>Subject my representative to criminal prosecution and/or administrative proceeding.</li>
                </ol>
            </li>
        </ol>
        <span style="padding-left:calc(var(--u)*30);">I ,</span> <span class="blank">{{ $isBlank ? '' : $app->full_name }}</span>, personally or through my agent/representative <span class="blank">{{ $app->representative_name }}</span>, voluntarily and freely consent to: (1) The collection and sharing of my personal information only in relation to BI protocols; (2) The collection, use, and transfer, in electronic or other forms, of my personal data, as described in the form by and among, as applicable, the latter’s representative, petitioner, and any related entity for the exclusive purpose of filing, processing, and implementing the submitted application/form consistent with provisions of Republic Act 10173 or the Data Privacy Act of 2012 and its Implementing Rules and Regulations (IRR); (3) Data Sharing when it is expressly authorized by law: Provided, that there are adequate safeguards for data privacy and security, and processing which adheres to the principle of transparency, legitimate purpose, and proportionality.
    </div>

    {{-- Signature, date and form number --}}
    <div class="a fill" style="{{ $at(222, 1314) }}transform:translate(-50%,-50%);font-size:8pt;">{{ $signatory }}</div>
    <div class="a rule" style="{{ $at(112, 1322, 221) }}"></div>
    <div class="a cap" style="{{ $at(222, 1331) }}transform:translate(-50%,-50%);font-size:6.6pt;">Applicant/Representative’s Signature over printed name</div>
    <div class="a fill" style="{{ $at(462, 1314) }}transform:translate(-50%,-50%);font-size:8pt;">{{ $app->filed_on ? strtoupper($app->filed_on->format('d/M/Y')) : '' }}</div>
    <div class="a rule" style="{{ $at(400, 1322, 125) }}"></div>
    <div class="a cap" style="{{ $at(462, 1331) }}transform:translate(-50%,-50%);font-size:6.6pt;">Date (DD/MMM/YYYY)</div>
    <div class="a" style="{{ $at(781, 1328) }}transform:translate(-100%,-50%);font-size:7.4pt;">IRD04.QF.004 Rev. Lev. 4 Effective 07 AUG 2024</div>
</div>
</body>
</html>
