@php
    /**
     * The BI's Application for Downgrading of Visa (BI Form IRD01.QF.001 Rev 02, effective
     * 02 February 2021): the general instructions, the checklist ticked from what is in
     * hand, the evaluator's certification and the IRD claim stub. One A4 page.
     */
    $blankForm = $blank;
    $category = $blankForm ? null : $petition->detail('visa_category');
    $tick = fn (string $item) => ! $blankForm && $petition->hasChecked($item) ? '✓' : '';
    $name = $blankForm ? '' : mb_strtoupper(trim($client->full_name ?: collect([$client->given_name, $client->last_name])->filter()->implode(' ')));

    $extras = [
        'employment_cert' => 'For Pre-Arranged Employee Commercial Visa and Non-Commercial Visa (Missionary), request must be accompanied by a Certificate of Employment/Certification of Missionary Work from the petitioning company/congregation;',
        'marriage_docs' => 'For Non-Quota Immigrant Visa by Marriage or Temporary Resident Visa (TRV), request must come from the applicant or/and spouse indicating their residential address and contact number(s) as well as a photocopy of marriage certificate or contract;',
        'doj_indorsement' => 'For 47(A)2 visa, request must be accompanied by a copy of the DOJ Indorsement granting the visa and proof of notice of downgrading from the DOJ;',
        'cancelation_order' => 'For Special visa (CWV, SCWV, SCIV, FWV, etc), request must be accompanied by a Cancelation Order from the Special Economic Zone authorities; or',
        'embassy_docs' => 'For 9(E) visa (Accredited Foreign Government Officials), request must be accompanied with a Certificate of Employment from the Embassy/Consulate/International Organization, Endorsement from the Department of Foreign Affairs and copy of Embassy’s Note Verbale, if applicable.',
    ];
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Downgrading Checklist{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        @page { size: 210mm 297mm; margin: 0; }
        .sheet { width: 210mm; height: 297mm; padding: 30pt 40pt 0 40pt; font-family: Tahoma, Verdana, Arial, sans-serif; font-size: 8.6pt; line-height: 1.3; }
        .toolbar { width: 210mm; }
        .chk { display: inline-flex; width: 17pt; height: 10pt; border: 1.6px solid #000; align-items: center; justify-content: center; font-weight: bold; font-size: 9pt; color: #0b2a6b; flex: 0 0 auto; margin-top: 1pt; }
        .item { display: flex; gap: 8pt; margin-top: 6pt; margin-left: -20pt; }
        .instr { margin: 0; padding-left: 26pt; font-size: 7.4pt; line-height: 1.32; text-align: justify; }
        .ln { display: inline-block; border-bottom: 0.75px solid #000; vertical-align: bottom; text-align: center; font-weight: bold; color: #0b2a6b; }
        .stub b { font-size: 8pt; }
        @media print { .chk, .ln { color: #000; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print form</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">A4, no margins, 100% scale. One page.</span>
</div>

<div class="sheet">
    <div style="display:flex;align-items:flex-start;gap:8pt;">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:30pt;height:30pt;object-fit:contain;">
        <div style="flex:1;font-weight:bold;font-size:9pt;line-height:1.25;padding-top:3pt;">BI FORM IRD01.QF.001 Rev 02<br>APPLICATION FOR DOWNGRADING OF VISA</div>
        <span style="font-size:7pt;color:#666;padding-top:3pt;">This document may be reproduced and is NOT FOR SALE</span>
    </div>

    <div style="text-align:center;font-weight:bold;font-size:10.4pt;letter-spacing:4pt;margin-top:12pt;">GENERAL INSTRUCTIONS</div>
    <div style="text-align:center;font-size:8pt;margin-top:3pt;">To avoid delay/denial in the processing of your application,<br>please be advised of the following:</div>
    <ol class="instr" style="margin-top:6pt;">
        <li>All documents required for submission must be complied with, arranged in the order as listed in the Checklist of Documentary Requirements (CDR), compiled in a legal sized (8½ x 14 in.) folder and shall be submitted within the prescribed period, if applicable. Otherwise, your application shall not be accepted.</li>
        <li>All civil registry documents issued in the Philippines (i.e. Birth Certificate, Marriage Certificate, Death Certificate, etc.) must be original and issued by the Philippine Statistics Authority (PSA) of the Philippines.</li>
        <li>All foreign documents must be original and authenticated by the Philippine Foreign Service Post (FSP) which has jurisdiction over the place of issuance, or by the Department of Foreign Affairs (DFA) if said document is issued by the local Embassy in the Philippines with English translation if written in other foreign language.</li>
        <li>If the application is filed by an authorized representative, attach a photocopy of the Bureau of Immigration (BI) Accreditation Identification (ID) Certificate or an original Special Power of Attorney (SPA) for EACH applicant with a photocopy of a valid government-issued ID of attorney in-fact.</li>
    </ol>
    <div style="border-top:2px solid #000;margin:12pt 0 0;"></div>

    <div style="text-align:center;font-weight:bold;font-size:10.4pt;text-decoration:underline;margin-top:12pt;">CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR DOWNGRADING OF VISA</div>
    <div style="text-align:center;font-style:italic;font-size:8pt;margin-top:6pt;">Please read carefully the above-stated General Instructions<br>before proceeding with the completion of the documentary requirements.</div>

    <div style="padding-left:20pt;margin-top:8pt;">
        <div class="item"><span class="chk">{{ $tick('letter_request') }}</span><span>1. Letter request addressed to the Commissioner stating the reason(s) for downgrading i.e. resignation, termination, late filing of extension of visa or dissolution of the company, etc.
            <span style="display:block;padding-left:20pt;">(i) &nbsp; If letter request is filed by the applicant, applicant’s address and contact numbers must be indicated therein;<br>(ii) &nbsp;If filed by petitioning company, congregation, accredited travel agency, law office or consultancy firm, letter request must be in representative’s letterhead with address and contact numbers; and</span></span></div>
        <div class="item"><span class="chk">{{ $tick('passport_copy') }}</span><span>2. Photocopy of passport biopage, front and back portions of ACR I-Card (if applicable), visa implementation and latest admission with valid authorized stay.</span></div>
        <div style="font-style:italic;margin-top:6pt;">Additional requirements per visa category:</div>
        @foreach ($extras as $item => $text)
            <div class="item" style="margin-top:7pt;"><span class="chk">{{ $tick($item) }}</span><span>{{ $text }}</span></div>
        @endforeach
    </div>

    <div style="font-style:italic;font-size:6.8pt;margin-top:14pt;">(To be filled out by Authorized BI personnel only)</div>
    <div style="border-top:2px solid #000;margin-top:2pt;"></div>
    <div style="text-align:center;font-weight:bold;font-size:10pt;margin-top:6pt;">CERTIFICATION</div>
    <div style="margin-top:4pt;padding-left:24pt;">This is to certify that the documents submitted in support of the application of <span class="ln" style="width:170pt;">{{ $name }}</span></div>
    <div style="text-align:right;font-style:italic;font-size:6.6pt;padding-right:60pt;">(Name of Applicant)</div>
    <div><span class="ln" style="width:180pt;"></span> are complete and in accordance with the provided checklist.</div>
    <div style="display:flex;gap:6pt;margin-top:8pt;align-items:flex-end;">Evaluator : <span class="ln" style="flex:1.4;"></span><span style="margin-left:20pt;">Date :</span><span class="ln" style="flex:1;"></span></div>
    <div style="font-style:italic;font-size:6.6pt;padding-left:90pt;">Signature over Printed Name</div>

    <div style="border-top:1.2px dashed #000;margin:6pt -40pt 0;"></div>
    <div class="stub" style="border:1.6px solid #777;margin:8pt 6pt 0;padding:6pt 10pt 8pt;">
        <div style="text-align:center;font-weight:bold;font-size:8pt;">IMMIGRATION REGULATION DIVISION (I.R.D)</div>
        <div style="text-align:center;font-weight:bold;font-style:italic;font-size:8.6pt;letter-spacing:2pt;">CLAIM STUB</div>
        <div style="display:flex;gap:4pt;margin-top:4pt;align-items:flex-end;"><b>Name of Subject</b>: <span class="ln" style="flex:1;">{{ $name }}</span></div>
        <div style="display:flex;gap:4pt;margin-top:4pt;align-items:flex-end;"><b>Received by</b>: <span class="ln" style="flex:1.2;"></span> <b style="margin-left:14pt;">Date / Time</b> : <span class="ln" style="flex:1;"></span></div>
        <div style="display:flex;gap:4pt;margin-top:4pt;align-items:flex-end;"><b>Release date :</b> <span class="ln" style="flex:1.2;"></span> <b style="margin-left:14pt;">Officer :</b> <span class="ln" style="flex:1;"></span></div>
    </div>
    <div style="text-align:center;font-size:5.6pt;line-height:1.3;margin-top:4pt;">Website: https://immigration.gov.ph<br>Email: xinfo@immigration.gov.ph<br>Facebook: https://facebook.com/officialbureauofimmigration<br>Twitter: immigrationPh</div>
    <div style="text-align:right;font-size:7.6pt;margin-top:4pt;">IRD01.QF.001 Rev 02 effective 02 February 2021</div>
</div>
</body>
</html>
