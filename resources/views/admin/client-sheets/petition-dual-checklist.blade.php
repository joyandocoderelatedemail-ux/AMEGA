@php
    /**
     * The BI's Checklist of Documentary Requirements for Petition for Retention/Re-acquisition
     * of Philippine Citizenship under R.A. 9225 (BSI03.QF.001 Rev.Lev. 1, effective 28 November
     * 2022), on 8.5 x 13 in paper, ticked from the dual citizenship case. Item 3 ticks the
     * proof of birth chosen (a, b or c).
     */
    $blankForm = $blank;
    $tick = fn (string $item) => ! $blankForm && $petition->hasChecked($item) ? '✓' : '';
    $proof = $blankForm ? null : $petition->birthProof();
    $proofTick = fn (string $code) => $proof === $code && $petition->hasChecked('birth_'.$code) ? '✓' : '';
    $birthTick = $proof && $petition->hasChecked('birth_'.$proof) ? '✓' : '';
    $name = $blankForm ? '' : strtoupper((string) ($petition->detail('name_ph') ?: $client->full_name));
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>R.A. 9225 Checklist{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 14pt 32pt 0 32pt; font-family: Arial, Helvetica, sans-serif; font-size: 7.6pt; line-height: 1.17; }
        .instructions { margin: 3pt 0 0; padding-left: 16pt; text-align: justify; font-size: 6.9pt; line-height: 1.25; }
        .item { display: flex; gap: 4pt; align-items: flex-start; margin-top: 0.8pt; }
        .item .box { width: 13pt; height: 9.6pt; flex: 0 0 auto; font-size: 8.4pt; }
        .l1 { margin-left: 18pt; }
        .l2 { margin-left: 42pt; }
        .l3 { margin-left: 74pt; }
        .fillin { font-weight: bold; color: #0b2a6b; }
        @media print { .fillin { color: #000; } }
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
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale.</span>
</div>

<div class="sheet">
    <div style="text-align:right;font-size:7.6pt;">This document may be reproduced and is <b>NOT FOR SALE</b></div>
    <div style="display:flex;gap:12pt;align-items:center;">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:44pt;height:44pt;object-fit:contain;">
        <div style="font-weight:bold;font-size:12.4pt;line-height:1.15;">PETITION FOR RETENTION/RE-ACQUISITION OF<br>PHILIPPINE CITIZENSHIP UNDER R.A. 9225</div>
    </div>

    <div style="text-align:center;font-weight:bold;letter-spacing:0.45em;font-size:10pt;margin-top:6pt;">GENERAL INSTRUCTIONS</div>
    <div style="text-align:center;font-weight:bold;font-size:8.6pt;">To avoid summary dismissal/delay in the processing of your application/petition, please be advised of the following:</div>
    <ol class="instructions">
        <li>The application form is written in English language. <b>Enter legibly all the information required into the appropriate spaces on the form.</b> If the information required is not applicable, please write “N/A”. Do not leave any item blank. The Bureau will not act on application forms that are incomplete and incorrectly accomplished.</li>
        <li><b>All documents required for submission must be complied with, arranged in the order as listed in the Checklist of Documentary Requirements (CDR), and compiled in a legal size (8½ x 14 in.) folder and shall be submitted within the prescribed period, if applicable.</b> Otherwise, your application or petition shall not be accepted.</li>
        <li><b>All sworn statements or affidavits</b> must be original and duly notarized (if executed in the Philippines). If executed abroad, it must be duly authenticated or acknowledged by a competent Philippine Foreign Service Post (FSP).</li>
        <li><b>If there are discrepancies in the entries of the documents for submission, particularly the names (please see requirement no. 7 below) and date of birth, petitioner is advised to execute an Affidavit of One and the Same Person and/or Affidavit of Explanation, whichever is applicable, with supporting documents, to avoid delay in the processing of this petition.</b></li>
        <li><b>All civil registry documents</b> issued in the Philippines (i.e. Birth Certificate, Marriage Certificate, Death Certificate, etc.) must be original and issued by the Philippine Statistics Authority (PSA).</li>
        <li><b>All foreign documents</b> must be original/certified true copy (CTC) and authenticated by the Philippine FSP, which has jurisdiction over the place of issuance, or by the Department of Foreign Affairs (DFA) if said document is issued by the local Embassy in the Philippines, or with affixed Apostille by the competent authority designated by the government of those contracting states under the Apostille Convention, whichever is applicable; with English translation if written in other foreign language.</li>
        <li><b>If your surname is “foreign sounding”,</b> you are advised to submit your original LCR/NSO birth certificate as requirement No. 3 below.</li>
        <li><b>All original documents submitted shall form part of the Bureau’s record.</b></li>
        <li><b>The original valid passport of the applicant</b> must be submitted to the Central Receiving Unit (CRU) personnel or concerned frontline officer for scanning. Thereafter, it shall be returned.</li>
        <li>If the application is filed by an authorized representative, attach a photocopy of the Bureau of Immigration (BI) Accreditation Identification (ID) Certificate or an original Special Power of Attorney (SPA) for <b>EACH</b> applicant with a photocopy of a valid government-issued ID of attorney-in-fact.</li>
        <li>It is recommended that you <b>seek legal advice from lawyers and/or BI-accredited entities.</b></li>
        <li><b>Submission of additional supporting documents may be required by the Bureau for further verification and evaluation of application.</b></li>
    </ol>

    <div style="border-top:1.5px solid #000;margin-top:5pt;"></div>
    <div style="text-align:center;font-weight:bold;text-decoration:underline;font-size:9.6pt;margin-top:8pt;">CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR PETITION FOR<br>RETENTION/RE-ACQUISITION OF PHILIPPINE CITIZENSHIP UNDER R.A. 9225</div>
    <div style="text-align:center;font-style:italic;font-size:8pt;">(Former natural-born Philippine citizens who acquired foreign citizenship by naturalization)</div>
    <div style="font-weight:bold;font-style:italic;font-size:8.4pt;margin-top:5pt;">Please read carefully the above-stated General Instructions before proceeding with the completion of the documentary requirements.</div>

    <div class="item"><span class="box"><span class="fill">{{ $tick('petition_form') }}</span></span><span>1. <b>Duly signed and notarized petition/application form (BI Form 2014-01-005 Rev 1);</b></span></div>
    <div class="item"><span class="box"><span class="fill">{{ $tick('photos') }}</span></span><span>2. <b>Two (2) pieces 2x2 photograph</b> with white background, without any facial obstruction (e.g. eyeglasses, face mask, etc.), and must be taken within the last three months from the date of application;</span></div>
    <div class="item"><span class="box"><span class="fill">{{ $birthTick }}</span></span><span>3. <b>ORIGINAL COPY</b> of proof as natural-born Philippine citizen:</span></div>
    <div class="item l1"><span class="box"><span class="fill">{{ $proofTick('psa') }}</span></span><span>a. Original Philippine Birth Certificate issued by the PSA;</span></div>
    <div class="item l1"><span class="box"><span class="fill">{{ $proofTick('lcr') }}</span></span><span>b. In the absence of Philippine Birth Certificate issued by the PSA, submit:</span></div>
    <div class="item l2"><span class="box"></span><span>i. Original Certificate of No Record of Birth issued by the PSA; <b>AND</b></span></div>
    <div class="item l2"><span class="box"></span><span>ii. Certification of Birth issued by the Local Civil Registrar (LCR) of the place where the petitioner is born.</span></div>
    <div class="item l1"><span class="box"><span class="fill">{{ $proofTick('other') }}</span></span><span>c. In case no PSA and LCR Birth Certificate, submit:</span></div>
    <div class="item l2"><span class="box"></span><span>i. Original Certificate of No Record of Birth issued by the PSA;</span></div>
    <div class="item l2"><span class="box"></span><span>ii. Original Certificate of No Record of Birth issued by the LCR of the place where petitioner is born; <b>AND</b></span></div>
    <div class="item l2"><span class="box"></span><span>iii. Any two (2) of the following documents in the order herein provided:</span></div>
    @foreach (['Original Philippine Marriage Certificate issued by the PSA;', 'Cancelled Philippine passport;', 'Voter’s Identification Card;', 'Baptismal Certificate; <b>OR</b>', 'Any other Philippine issued official document proving natural-born Philippine citizenship.'] as $i => $doc)
        <div class="item l3"><span class="box"></span><span>{{ $i + 1 }}. {!! $doc !!}</span></div>
    @endforeach
    <div class="item"><span class="box"><span class="fill">{{ $tick('foreign_passport') }}</span></span><span>4. <b>Original and photocopy of valid foreign passport;</b></span></div>
    <div class="item"><span class="box"><span class="fill">{{ $tick('naturalization') }}</span></span><span>5. <b>Photocopy of Certificate of Naturalization</b> or other naturalization documents, with English translation if written in other foreign language, or, in case of absence or loss thereof, an affidavit stating how and when foreign citizenship was acquired;</span></div>
    <div class="item"><span class="box"><span class="fill">{{ $tick('acr_surrender') }}</span></span><span>6. For BI-Registered Aliens: <i>(submit Affidavit of Loss if (a) and/or (b) are lost)</i></span></div>
    <div class="l1">a. Original ACR I-Card or Alien Certificate of Registration; and<br>b. Original Immigrant Certificate of Residence (ICR) or Certificate of Residence for Temporary Visitor (CRTV).</div>
    <div class="item"><span class="box"><span class="fill">{{ $tick('name_variance') }}</span></span><span>7. In case of variance between the applicant’s name as appearing in his/her Birth Certificate or other documents to prove his/her being a former natural-born Philippine citizen and his/her name in the foreign passport’s machine readable zone (MRZ) or biographical page or Certificate of Naturalization or Citizenship or any document proving foreign citizenship, submit the following:</span></div>
    <div class="item l1"><span class="box"></span><span>a. <b>Affidavit of One and the Same Person</b> executed by the applicant with the statement that he/she <b>acknowledges all obligations, responsibilities and liabilities under all names AND an explanation on the discrepancies</b>; and</span></div>
    <div class="item l1"><span class="box"></span><span>b. Substantial proof on how the applicant acquired the varied name/s, such as but not limited to:</span></div>
    @foreach (['<b>Marriage Certificate;</b>', 'Annotation in the passport or Birth Certificate of the varied name;', 'Judicial Decree of Change of Name, Adoption, Annulment or Declaration of Nullity of Marriage, and Divorce or equivalent document;', 'Annotation in the Certificate of Naturalization or Citizenship of the varied name; or', 'Philippine issued official document indicating the varying name/s.'] as $i => $doc)
        <div class="item l2"><span class="box"></span><span>{{ ['i', 'ii', 'iii', 'iv', 'v'][$i] }}. {!! $doc !!}</span></div>
    @endforeach

    <div style="text-align:center;font-weight:bold;text-decoration:underline;font-size:8.6pt;margin-top:6pt;">CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR EACH DEPENDENT CHILD</div>
    @foreach ([
        'Duly signed and notarized Supplement for Dependent Form (BI Form 2014-01-006 Rev 1);',
        'Two (2) pieces 2x2 photograph with white background, without any facial obstruction (e.g. eyeglasses, face mask, etc.), and must be taken within the last three months from the date of application;',
        'Photocopy of Birth Certificate of Dependent Child;',
        'Photocopy of valid foreign passport bio-page of Dependent Child; and',
        'For Dependent Child who are BI-Registered Aliens: <i>(submit Affidavit of Loss executed by Principal if (a) and/or (b) are lost)</i><br>a. Original ACR I-Card or Alien Certificate of Registration; and<br>b. Original Immigrant Certificate of Residence (ICR) or Certificate of Residence for Temporary Visitor (CRTV).',
    ] as $i => $doc)
        <div class="item"><span class="box"></span><span>{{ $i + 1 }}. {!! $doc !!}</span></div>
    @endforeach

    <div style="border-top:1.5px solid #000;margin-top:4pt;font-weight:bold;font-style:italic;font-size:7.4pt;">[To be filled out by Authorized BI Personnel Only]</div>
    <div style="text-align:center;font-weight:bold;font-size:8.6pt;margin-top:6pt;">CERTIFICATION</div>
    <div style="margin-top:8pt;padding-left:40pt;">
        This is to certify that the documents submitted in support of the application/petition of
        <span class="fillin" style="display:inline-block;min-width:170pt;border-bottom:0.75px solid #000;text-align:center;">{{ $name }}</span>
        are complete and in accordance with the provided checklist.
        <div style="text-align:center;font-style:italic;font-size:7.4pt;padding-left:160pt;">(Name of Applicant)</div>
    </div>
    <div style="margin-top:8pt;">Dual Citizenship Office Evaluator:</div>
    <div style="display:flex;justify-content:space-between;margin-top:14pt;">
        <div style="width:140pt;border-top:0.75px solid #000;padding-top:2pt;">Signature over Printed Name</div>
        <div style="width:100pt;border-top:0.75px solid #000;padding-top:2pt;text-align:center;">Date</div>
    </div>
    <div style="text-align:right;font-size:6.8pt;margin-top:4pt;line-height:1.2;">Page <b>1</b> of <b>1</b><br>BSI03.QF.001 Rev.Lev. 1<br>Effective 28 November 2022</div>
    <div style="text-align:center;font-size:7pt;margin-top:4pt;">Website: www.immigration.gov.ph &bull; E-mail: xinfo@immigration.gov.ph &bull; Facebook: officialbureauofimmigration &bull; Twitter: immigrationPH</div>
</div>
</body>
</html>
