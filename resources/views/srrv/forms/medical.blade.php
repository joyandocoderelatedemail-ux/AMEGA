@php
    /**
     * The PRA Medical Examination for SRRV Applicants (PRA-CR-FORM-0012, Issue 0001,
     * January 2017), on A4. The desk fills in who is being examined; the rest is the
     * examining physician's.
     */
    $a = $application;
    [$lastName, $firstName, $middleName] = $name;
    $gender = ['M' => 'Male', 'F' => 'Female'][$d('gender')] ?? '';
    $fullName = $blank ? '' : collect([$firstName, $middleName, $lastName])->filter()->implode(' ');

    $logo = asset('images/logoapproved/PhilipineretirementAutorityLogo.jpg');
    $paper = 'a4';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SRRV Medical Certificate{{ $blank ? '' : ' - '.$file->retiree_name }}</title>
    @include('srrv.forms._base')
    <style>
        .sheet { padding: 50pt 42pt 0 50pt; font-family: Arial, Helvetica, sans-serif; font-size: 9pt; }
        .side { position: absolute; left: 22pt; top: 640pt; transform: rotate(-90deg); transform-origin: left top; font: bold 17pt Arial, sans-serif; color: #8a8a8a; letter-spacing: 0.6pt; white-space: nowrap; }
        table.form td { padding: 1pt 2pt; }
        .cls td { font-size: 9pt; }
        .center { text-align: center; }
        .line { border-bottom: 0.75px solid #000; height: 18pt; }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print form</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">A4, no margins, 100% scale. One page, for the examining physician.</span>
</div>

<div class="sheet">
    <div class="side">MEDICAL CERTIFICATE FOR SRRV APPLICANTS</div>

    <table class="form">
        <colgroup><col style="width:14%"><col style="width:58%"><col style="width:28%"></colgroup>
        <tr>
            <td rowspan="2" style="text-align:center;vertical-align:middle;"><img src="{{ $logo }}" alt="Philippine Retirement Authority" style="width:58pt;height:58pt;object-fit:contain;"></td>
            <td class="center" style="font-weight:bold;font-size:9.6pt;">MEDICAL EXAMINATION FOR SRRV APPLICANTS</td>
            <td style="font-size:7.4pt;">SRRV APPLICATION NO.: <span class="f">{{ $blank ? '' : $a?->pra_reference }}</span></td>
        </tr>
        <tr>
            <td class="center" style="font-size:8.4pt;line-height:1.3;">Republic of the Philippines<br><span style="font-size:7.4pt;">DEPARTMENT OF TOURISM</span><br><b>PHILIPPINE RETIREMENT AUTHORITY</b><br><span style="font-size:6.6pt;">29/F Citibank Tower, Paseo de Roxas, Makati City, 1227 Philippines<br>Tel. No.: +632 8481412, FAX: +632 8481411, Email: inquiry@pra.gov.ph; Website: www.pra.gov.ph</span></td>
            <td rowspan="3" class="center" style="vertical-align:middle;font-size:9pt;">Place passport size photo here<br>not taken more than 6 months ago</td>
        </tr>
        <tr><td colspan="2" style="height:22pt;vertical-align:middle;"><span style="display:inline-block;width:180pt;">PLACE:</span>DATE</td></tr>
        <tr><td colspan="2" class="center" style="height:40pt;vertical-align:middle;">As requested by the Philippine Retirement Authority</td></tr>
    </table>

    <div class="center" style="font-size:11.4pt;margin:6pt 0;">I certify that I was examined on the date stated above</div>
    <table class="form">
        <colgroup><col style="width:42%"><col style="width:14%"><col style="width:16%"><col style="width:28%"></colgroup>
        <tr>
            <td style="height:22pt;vertical-align:middle;">Name: <span class="f">{{ $fullName }}</span></td>
            <td style="vertical-align:middle;border-left:0;border-right:0;">Age: <span class="f">{{ $blank ? '' : $a?->age() }}</span></td>
            <td style="vertical-align:middle;border-left:0;border-right:0;">Gender: <span class="f">{{ $gender }}</span></td>
            <td style="vertical-align:middle;border-left:0;">Nationality: <span class="f">{{ $blank ? '' : $a?->nationality }}</span></td>
        </tr>
    </table>

    <div class="center" style="margin-top:4pt;font-size:9.4pt;">Under the Philippine Immigration Regulation, the applicant should be classified as follows:<br>(<span class="italic">Encircle the appropriate class</span>)</div>
    <table class="form cls" style="margin-top:1pt;">
        <colgroup><col style="width:42%"><col style="width:58%"></colgroup>
        <tr><td style="font-weight:bold;">Class A</td><td class="center">DANGEROUS AND CONTAGIOUS DISEASE</td></tr>
        <tr><td rowspan="3"></td><td>Chancroid, Gonorrhea, Granuloma Inquinale, Leprosy (Infectious), Lymphogranuloma Venareum, Syphilis (Infectious Stage), and Tuberculosis (Active)</td></tr>
        <tr><td class="center">SERIOUS MENTAL DISORDER</td></tr>
        <tr><td>Mental Retardation (Mental Deficiency), Insanity, Previous Occurrence of one or more attacks of Isanity, Anti-Social Personality, Mental Defects, Epilepsy, Sexual Deviation, Narcotic Drug Addiction, Chronic Alcoholism</td></tr>
        <tr><td style="font-weight:bold;">Class B</td><td class="center">PHYSICAL DEFECTS AND DISORDER</td></tr>
        <tr><td></td><td>Physical defects, disease or disability serious in degree or permanent in nature that impairs the ability to earn a living as to make them likely to be a public charge</td></tr>
        <tr><td style="font-weight:bold;">Class C</td><td class="center">MINOR CONDITIONS</td></tr>
    </table>

    <div class="center" style="font-size:13pt;letter-spacing:5pt;text-decoration:underline;margin-top:6pt;">MEDICAL RECORD</div>
    <ol style="margin:16pt 0 0 30pt;padding-left:12pt;font-size:9.4pt;line-height:1.3;">
        <li style="margin-bottom:16pt;">Pertinent Medical History:</li>
        <li style="margin-bottom:16pt;">Significant Physical Examination:</li>
        <li style="margin-bottom:16pt;">Chest X-ray report: (for ages 11 years &amp; above)<br><span class="italic">Present recent x-ray film (14x17 inches)</span></li>
        <li>Laboratory examination: (attach laboratory reports)
            <div style="display:grid;grid-template-columns:40pt 1fr;row-gap:2pt;margin-top:10pt;">
                <span>a.</span><span>Blood Serology: RPR/VDRL (Ages: 15 yrs. And above)</span>
                <span>b.</span><span>Urinalysis: (Age: 1 yr. and above)</span>
                <span>c.</span><span>Stool (Ova and Parasite) : (Ages: 1 yr. and above)</span>
                <span>d.</span><span>Other examination(s), if necessary</span>
            </div>
            <div style="display:grid;grid-template-columns:40pt 1fr;margin-top:10pt;"><span>(&nbsp;&nbsp;&nbsp;)</span><span>Not physically and mentally defective or diseased</span></div>
        </li>
    </ol>

    <div style="border-top:0.9px solid #000;margin-top:24pt;display:grid;grid-template-columns:1.4fr 1fr 0.6fr;column-gap:30pt;padding-top:3pt;font-size:9.4pt;">
        <span>EXAMINING PHYSICIAN / License No.:</span><span>SIGNATURE</span><span>DATE</span>
        <div class="line" style="margin:0 20pt 0 40pt;"></div><div class="line"></div><div class="line"></div>
    </div>
    <div style="display:grid;grid-template-columns:1.4fr 1.6fr;column-gap:30pt;margin-top:12pt;font-size:9.4pt;">
        <span>NAME OF CLINIC OR HOSPITAL:</span><span>ADDRESS:</span>
        <div class="line" style="margin:0 20pt 0 40pt;"></div><div class="line" style="margin-right:50pt;"></div>
    </div>

    <div class="code" style="margin-top:30pt;">PRA-CR-FORM-0012<br>ISSUE NO.: 0001<br>ISSUE DATE: JANUARY 2017</div>
</div>
</body>
</html>
