@php
    /**
     * The BI's checklists for re-stamping: Transfer of Admission Status from Old/Lost/
     * Cancelled Passport to New Passport (BI Form IRD01.QF.002 Rev 3, 8.5 x 13 in) and
     * Amendment/Correction of Admission (BI Form IRD01.QF.003 Rev 1, 8.5 x 11 in),
     * ticked from the re-stamping case.
     */
    $isBlank = $blank;
    $case = $restamping;
    $transfer = $kind === 'transfer';
    $tick = fn (string ...$items) => ! $isBlank && collect($items)->contains(fn ($item) => $case->hasChecked($item)) ? '✓' : '';
    $name = $isBlank ? '' : strtoupper(collect([$client->last_name, $client->given_name, $case->middle_name])->filter()->implode(', '));
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $transfer ? 'Transfer' : 'Amendment' }} Checklist{{ $isBlank ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        @if (! $transfer)
            @page { size: 8.5in 11in; margin: 0; }
            .sheet { height: 11in; }
        @endif
        .sheet { padding: 0.32in 0.72in 0 0.5in; font-family: {{ $transfer ? 'Tahoma, Verdana, Arial' : 'Arial, Helvetica' }}, sans-serif; }
        .head { display: flex; align-items: center; gap: 0.08in; }
        .head img { width: 0.5in; height: 0.5in; object-fit: contain; }
        .head .code { font-weight: bold; font-size: 10.4pt; line-height: 1.1; }
        .head .title { font-weight: bold; font-size: 13.4pt; line-height: 1.1; }
        .head .sale { position: absolute; top: 0.36in; right: 0.62in; font-size: 7.4pt; color: #777; }
        .spaced { letter-spacing: 0.32em; font-weight: bold; font-size: 11pt; text-align: center; }
        .intro { text-align: center; font-size: 10.6pt; line-height: 1.3; }
        .rules { margin: 0 0 0 0.4in; padding-left: 0.28in; font-size: 7.6pt; line-height: 1.22; text-align: justify; border-bottom: 2px solid #000; padding-bottom: 2px; }
        .rules li { padding-left: 0.06in; }
        .heading { text-align: center; font-weight: bold; font-size: 12.6pt; text-decoration: underline; line-height: 1.22; margin: 0.3in 0.4in 0 0.4in; }
        .note { text-align: center; font-style: italic; font-size: 10.6pt; line-height: 1.3; margin: 0.08in 0.6in 0; }
        .items { margin: 0.14in 0 0 {{ $transfer ? '1.5in' : '0.6in' }}; font-size: 10.6pt; }
        .item { display: flex; align-items: flex-start; gap: 6px; margin-bottom: 0.13in; }
        .item .box { width: 16px; height: 16px; flex: 0 0 auto; font-size: 10pt; }
        .sub { margin: -0.04in 0 0.08in 0.28in; line-height: 1.3; }
        .bi-only { margin-left: 0.4in; border-top: 2px solid #000; font-size: 7.4pt; font-style: italic; }
        .cert { text-align: center; font-weight: bold; font-size: 12pt; margin-top: 0.34in; }
        .cert-text { margin: 0.12in 0 0 0.36in; font-size: 10.6pt; line-height: 1.35; text-align: justify; }
        .blankline { display: inline-block; border-bottom: 1px solid #000; width: 3.3in; text-align: center; line-height: 1.1; }
        .sign { display: flex; justify-content: space-between; margin: 0.62in 0.3in 0 1.12in; font-size: 10.6pt; }
        .sign div { width: 2.36in; text-align: center; border-top: 1px solid #000; padding-top: 2px; }
        .foot { position: absolute; right: 0.62in; bottom: {{ $transfer ? '1.12in' : '0.42in' }}; font-size: 9.6pt; }
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
    <span class="hint">{{ $transfer ? 'Long bond paper (8.5 × 13 in)' : 'Letter (8.5 × 11 in)' }}, no margins, 100% scale.</span>
</div>

<div class="sheet">
    <div class="head">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal">
        <div>
            <div class="code">{{ $transfer ? 'BI FORM IRD01.QF.002 Rev 3' : 'BI FORM IRD01.QF.003 Rev 1' }}</div>
            <div class="title">{{ $transfer ? 'APPLICATION FOR TRANSFER OF ADMISSION STATUS' : 'AMENDMENT/CORRECTION OF ADMISSION' }}</div>
        </div>
        <div class="sale">This document may be reproduced and is <b>NOT FOR SALE</b></div>
    </div>

    <div class="spaced" style="margin-top:0.4in;">GENERAL INSTRUCTIONS</div>
    <div class="intro" style="margin-top:0.08in;">To avoid delay/denial in the processing of your application,<br>please be advised of the following:</div>

    <ol class="rules" style="margin-top:0.24in;">
        <li>All documents required for submission must be complied with, arranged in the order as listed in the Checklist of Documentary Requirements (CDR), compiled in a legal size (8&frac12; x 14 in.) folder and shall be submitted within the prescribed period, if applicable. Otherwise, your application shall not be accepted.</li>
        <li>If the application is filed by an authorized representative, attach a photocopy of the Bureau of Immigration (BI) Accreditation Identification (ID) Certificate or an original Special Power of Attorney (SPA) for <b>EACH</b> applicant with a photocopy of a valid government-issued ID of attorney-in-fact.</li>
        <li>It is recommended that you seek legal advice from lawyers and/or BI-accredited entities.</li>
    </ol>

    @if ($transfer)
        <div class="heading">CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR APPLICATION FOR TRANSFER OF ADMISSION STATUS FROM OLD/LOST/CANCELLED PASSPORT TO NEW PASSPORT</div>
    @else
        <div class="heading">CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR<br>AMENDMENT/CORRECTION OF ADMISSION</div>
    @endif
    <div class="note">Please read carefully the above-stated General Instructions before proceeding with the completion of the documentary requirements.</div>

    <div class="items">
        @if ($transfer)
            <div class="item"><span class="box"><span class="fill">{{ $tick('cgaf') }}</span></span><span>1. Duly accomplished application form (<span style="font-size:9pt;">IRD CONSOLIDATED GENERAL APPLICATION FORM</span>);</span></div>
            <div class="item"><span class="box"><span class="fill">{{ $tick('certificate_of_travel') }}</span></span><span>2. Certificate of Travel duly issued by the Certificate and Clearance Section;</span></div>
            <div class="item"><span class="box"><span class="fill">{{ $tick('manifest') }}</span></span><span>3. Passenger Manifest <b>or</b> Airline Certification;</span></div>
            <div class="item"><span class="box"><span class="fill">{{ $tick('old_passport') }}</span></span><span>4. Original old passport (if available);</span></div>
            <div class="item"><span class="box"><span class="fill">{{ $tick('new_passport') }}</span></span><span>5. Original new passport (<b>ONLY</b>, for lost passport)</span></div>
        @else
            <div class="item"><span class="box"><span class="fill">{{ $tick('amendment_form') }}</span></span><span>1. Duly accomplished application form (BI Form IRD01.QF.006 Rev 03);</span></div>
            <div class="item"><span class="box"><span class="fill">{{ $tick('passport') }}</span></span><span>2. Valid original passport;</span></div>
            <div class="item"><span class="box"><span class="fill">{{ $tick('visa_page', 'birth_certificate', 'marriage_contract') }}</span></span>
                <span>3. a. For foreigners, photocopy of passport page with valid visa implementation and Alien<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Certificate of Registration Identification Card (I-Card), if applicable;</span></div>
            <div class="sub" style="margin-left:0.52in;">
                b. For Balikbayan:<br>
                &nbsp;&nbsp;&nbsp;(i)&nbsp; Photocopy of Birth Certificate;<br>
                &nbsp;&nbsp;&nbsp;(ii) If travelling with Filipino spouse, Marriage Contract or any proof of travelling together<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(e.g. passport, etc.).
            </div>
        @endif
    </div>

    <div class="bi-only" style="{{ $transfer ? 'margin-top:0.12in;' : '' }}">{{ $transfer ? '(To be filled out by Authorized BI personnel only)' : '[To be filled out by Authorized BI Personnel Only]' }}</div>

    <div class="cert">CERTIFICATION</div>
    <div class="cert-text">
        This is to certify that the documents submitted in support of the application of
        <span class="blankline"><span class="fill" style="font-size:9pt;">{{ $name }}</span></span>
        are complete and in accordance with the provided checklist.
        <div style="margin-left:0.6in;font-style:italic;">(Name of Applicant)</div>
    </div>

    <div style="margin:0.86in 0 0 0.98in;font-weight:bold;font-size:10.6pt;">Evaluator:</div>
    <div class="sign">
        <div>Signature over Printed Name</div>
        <div>Date</div>
    </div>

    <div class="foot">{{ $transfer ? 'IRD01.QF.002 effective 08 October 2020 Rev 03' : 'IRD01.QF.003 effective 01 September 2018' }}</div>
</div>
</body>
</html>
