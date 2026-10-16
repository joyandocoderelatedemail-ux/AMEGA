@php
    /**
     * The BI's Request for Reconsideration on the Updating and Extension of Authorized
     * Stay (BI FORM 2014-01-011 Rev 0), on 8.5 x 13 in paper like the original. Filled
     * from the client sheet and its overstay case, or blank to fill in by hand.
     */
    $isBlank = $blank;
    $date = fn ($value) => $value ? strtoupper($value->format('d M Y')) : '';
    $show = fn ($value) => $isBlank ? '' : (string) $value;

    $name = $isBlank ? '' : collect([$client->last_name, $client->given_name])->filter()->implode(', ');
    $statuses = ['single' => 'single', 'married' => 'married', 'annulled' => 'annulled', 'widow' => 'widow', 'separated' => 'separated', 'divorced' => 'divorced'];
    $status = strtolower(trim((string) $client->civil_status));
    $status = $status === 'widowed' ? 'widow' : $status;
    // One line on the form: the mobile number, or the email when there is none.
    $contact = $isBlank ? '' : (string) ($client->mobile_number ?: $client->email);
    $representative = ! $isBlank && $overstay->by_representative;
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request for Reconsideration{{ $isBlank ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 0.42in 0.62in 0.4in 0.62in; font-size: 10.2pt; line-height: 1.24; }
        .head { display: flex; align-items: flex-start; gap: 0.2in; }
        .head img { width: 0.82in; height: 0.82in; object-fit: contain; }
        .head .code { font-weight: bold; font-size: 11pt; }
        .head .title { font-weight: bold; font-size: 13.6pt; line-height: 1.12; }
        .head .sale { position: absolute; top: 0.47in; right: 0.62in; font-size: 7.6pt; white-space: nowrap; }
        .line { display: inline-block; border-bottom: 1px solid #000; min-height: 1.25em; vertical-align: bottom; padding: 0 4px; line-height: 1.15; text-align: center; }
        .hint { display: block; font-size: 9pt; font-style: italic; text-align: center; line-height: 1.1; }
        .para { text-indent: 0.55in; margin: 0.12in 0 0; text-align: justify; }
        .ruled {
            margin-top: 0.02in;
            line-height: 0.235in;
            min-height: calc(0.235in * 4);
            background-image: linear-gradient(to bottom, transparent calc(0.235in - 1px), #000 calc(0.235in - 1px));
            background-size: 100% 0.235in;
            word-break: break-word;
        }
        .ruled.lead { text-indent: 0; }
        .boxes { display: flex; justify-content: space-between; margin-top: 0.04in; }
        .boxes span { white-space: nowrap; }
        .boxes .box { width: 11px; height: 11px; font-size: 9pt; margin-right: 3px; }
        .footer { position: absolute; left: 0; right: 0; bottom: 0.32in; text-align: center; font-size: 8.6pt; line-height: 1.25; }
        .page-no { position: absolute; right: 0.62in; bottom: 0.22in; font-size: 8.6pt; }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print request</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale.</span>
</div>

<div class="sheet">
    <div class="head">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal">
        <div>
            <div class="code">BI FORM 2014-01-011 Rev 0</div>
            <div class="title">REQUEST FOR RECONSIDERATION ON THE<br>UPDATING AND EXTENSION OF AUTHORIZED STAY</div>
        </div>
        <div class="sale">This document may be reproduced and is <b>NOT FOR SALE</b></div>
    </div>

    <div style="margin-top:0.14in;text-align:right;">
        <span class="line fill" style="width:3.4in;">{{ $isBlank ? '' : $date($overstay->filed_on ?? now()) }}</span>
        <span class="hint" style="text-align:right;">Date [DD-MMM-YYYY e.g. 01 JAN 1990]</span>
    </div>

    <div style="margin-top:0.04in;line-height:1.25;">
        <b>The Honorable</b><br>
        <b>COMMISSIONER</b><br>
        Bureau of Immigration<br>
        Magallanes Drive<br>
        Intramuros, Manila
    </div>

    <div style="margin-top:0.16in;text-align:center;font-weight:bold;line-height:1.25;">
        REQUEST FOR RECONSIDERATION ON THE<br>UPDATING AND EXTENSION OF AUTHORIZED STAY
    </div>

    {{-- I, (name), born on (date) --}}
    <div style="margin-top:0.14in;display:flex;align-items:flex-end;gap:4px;padding-left:0.55in;">
        <span>I,</span>
        <span style="flex:1;"><span class="line fill" style="width:100%;">{{ $name }}</span></span>
        <span>, born on</span>
        <span style="width:2.5in;"><span class="line fill" style="width:100%;">{{ $isBlank ? '' : $date($client->date_of_birth) }}</span></span>
        <span>,</span>
    </div>
    <div style="display:flex;gap:4px;padding-left:0.55in;">
        <span class="hint" style="flex:1;">(Last Name, First Name, Middle Name)</span>
        <span class="hint" style="width:2.6in;font-size:7.8pt;">Date [DD-MMM-YYYY e.g. 01 JAN 1990]</span>
    </div>

    {{-- Civil status and nationality --}}
    <div class="boxes" style="padding-left:0.55in;">
        @foreach ($statuses as $key => $statusLabel)
            <span><span class="box">{{ ! $isBlank && $status === $key ? '✓' : '' }}</span>{{ $statusLabel }}</span>
        @endforeach
        <span>, a citizen of</span>
    </div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-top:0.04in;">
        <span style="width:2.6in;"><span class="line fill" style="width:100%;">{{ $show($client->nationality) }}</span></span>
    </div>
    <div style="display:flex;justify-content:space-between;">
        <span class="hint" style="width:2.6in;margin-left:2.2in;">(Civil Status)</span>
        <span class="hint" style="width:2.6in;">(Nationality)</span>
    </div>

    <div style="display:flex;align-items:flex-end;gap:4px;">
        <span style="white-space:nowrap;">with contact number (s)</span>
        <span style="width:2.9in;"><span class="line fill" style="width:100%;">{{ $contact }}</span></span>
        <span style="white-space:nowrap;">, respectfully request from your good office for the</span>
    </div>
    <span class="hint" style="width:2.9in;margin-left:1.6in;">(Contact Information)</span>
    <div>updating and/or extension of my authorized stay as a tourist.</div>

    <p class="para" style="margin-bottom:0;">I failed to extend my authorized stay as a tourist for the reason that</p>
    <div class="ruled fill" style="text-transform:none;">{{ $show($overstay->reason_failed) }}</div>

    <p class="para" style="margin-bottom:0;">I would like to stay longer in this country because</p>
    <div class="ruled fill" style="text-transform:none;">{{ $show($overstay->reason_stay) }}</div>

    <div class="para" style="display:flex;align-items:flex-end;gap:4px;text-indent:0;padding-left:0.55in;">
        <span>My authorized stay expired on</span>
        <span style="flex:1;"><span class="line fill" style="width:100%;">{{ $isBlank ? '' : $date($overstay->expired_on) }}</span></span>
        <span>and from that time on, NO extension</span>
    </div>
    <div>has been made. I am willing to pay all the necessary fees, fines and penalty to regularize my stay.</div>

    <p style="margin:0.14in 0 0;font-style:italic;text-align:justify;">*If the applicant/petitioner intends to depart the country within a month or two, attach a copy of a confirmed departure ticket and an original affidavit that he/she will be leaving the Philippines.</p>

    <div class="para" style="display:flex;align-items:flex-end;gap:4px;text-indent:0;padding-left:0.55in;">
        <span>I will be leaving the Philippines on</span>
        <span style="width:1.8in;"><span class="line fill" style="width:100%;">{{ $isBlank ? '' : $date($overstay->departure_date) }}</span></span>
        <span>bound to</span>
        <span style="flex:1;"><span class="line fill" style="width:100%;">{{ $show($overstay->destination) }}</span></span>
        <span>.</span>
    </div>
    <div style="display:flex;padding-left:2.85in;">
        <span class="hint" style="width:1.8in;">(Date of Departure)</span>
        <span class="hint" style="flex:1;margin-left:0.65in;">(Final Destination)</span>
    </div>

    <p class="para">Rest assured that I will never again allow myself to be an overstaying foreign national in this country and will not in any manner, violate any law, policy, rules and regulations imposed by the Philippine Government.</p>
    <p class="para">Hoping for your favorable action on this request.</p>

    <div style="margin-top:0.22in;margin-left:auto;width:3.5in;text-align:center;">
        <span class="line fill" style="width:100%;">{{ $isBlank ? '' : strtoupper($client->full_name) }}</span>
        <span class="hint" style="font-style:normal;font-size:10.2pt;">(Signature over Printed Name)</span>
    </div>

    <div style="margin-top:0.1in;display:flex;gap:0.4in;">
        <div style="width:2.6in;">If filed by a Representative:</div>
        <div style="flex:1;margin-top:0.18in;">If filed by a representative, please attach a photocopy of valid government-issued identification and Special Power of Attorney (SPA).</div>
    </div>

    <div style="margin-top:0.1in;width:2.6in;text-align:center;">
        <span class="line fill" style="width:100%;">{{ $representative ? strtoupper((string) $overstay->representative_name) : '' }}</span>
        <span class="hint" style="font-style:normal;font-size:10.2pt;">(Signature over Printed Name)</span>
    </div>

    <table style="margin-top:0.12in;border-collapse:collapse;font-size:10.2pt;">
        <tr>
            <td style="width:2.4in;">Representative Accreditation No.</td>
            <td style="width:0.3in;">:</td>
            <td><span class="line fill" style="width:2.5in;">{{ $representative ? $overstay->representative_accreditation_no : '' }}</span></td>
        </tr>
        <tr>
            <td>Relationship to the Subject</td>
            <td>:</td>
            <td><span class="line fill" style="width:2.5in;">{{ $representative ? $overstay->representative_relationship : '' }}</span></td>
        </tr>
    </table>

    <div class="footer">
        <b>Website:</b> www.immigration.gov.ph<br>
        <b>E-mail:</b> xinfo@immigration.gov.ph<br>
        <b>Facebook:</b> officialbureauofimmigration<br>
        <b>Twitter:</b> immigrationPH
    </div>
    <div class="page-no">Page <b>1</b> of <b>1</b></div>
</div>
</body>
</html>
