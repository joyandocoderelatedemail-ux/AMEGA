@php
    use App\Support\BoxedForm as B;
    use App\Support\SrrvForms;

    /**
     * The PRA Application Form for Principal Retiree (PRA-CR-FORM-0004, Issue 0003,
     * January 2019): three A4 pages, filled from the retiree file and its PRA form
     * details, or blank.
     */
    $a = $application;
    [$lastName, $firstName, $middleName] = $name;
    $fmt = fn ($date) => $date ? strtoupper($date->format('d M Y')) : '';
    $ageOf = fn ($date) => $date ? $date->age : '';
    $option = $blank ? null : $d('srrv_option', $a?->visa_class);
    $gender = $d('gender');
    $civil = $d('civil_status');
    $dob = $blank ? null : $a?->date_of_birth;
    $yes = fn (string $key) => $d($key) === 'yes';
    $no = fn (string $key) => $d($key) === 'no';

    $family = [['Name of Spouse:', 'spouse']];
    foreach (range(1, 4) as $i) {
        $family[] = ['Name of Child :', "child_{$i}"];
    }
    $plans = $blank ? [] : (array) $d('plans', []);
    $priorVisa = $d('prior_visa');
    $applicantName = $blank ? '' : collect([$firstName, $middleName, $lastName])->filter()->implode(' ');

    $logo = asset('images/logoapproved/PhilipineretirementAutorityLogo.jpg');
    $paper = 'a4';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SRRV Application Form{{ $blank ? '' : ' - '.$file->retiree_name }}</title>
    @include('srrv.forms._base')
    <style>
        .sheet { padding: 34pt 38pt 0 58pt; }
        .side { position: absolute; left: 10pt; top: 330pt; transform: rotate(-90deg); transform-origin: left top; font: bold 15pt Arial, sans-serif; white-space: nowrap; translate: 0 190pt; }
        table.form td { height: 36pt; }
        .lab { display: block; font-size: 8.6pt; }
        .val { display: block; margin-top: 3pt; font-size: 9pt; }
        .row-line { display: flex; gap: 10pt; align-items: flex-end; margin-top: 6pt; }
        .row-line .ln { flex: 1; }
        .terms { font-family: Arial, Helvetica, sans-serif; font-size: 7.6pt; line-height: 1.32; text-align: justify; }
        .terms ol { margin: 3pt 0; padding-left: 14pt; }
        .terms li { margin-bottom: 4pt; }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print form</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">A4, no margins, 100% scale. Three pages.</span>
</div>

{{-- ===================== PAGE 1 ===================== --}}
<div class="sheet">
    <div class="side">FOR PRINCIPAL RETIREE USE</div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:6pt;">
        <div style="font:bold 9pt Arial, sans-serif;">SPECIAL RESIDENT RETIREE’S VISA APPLICATION<br>(ENGLISH)</div>
        <div>Application No. <span class="ln f" style="width:90pt;text-align:center;">{{ $blank ? '' : $a?->pra_reference }}</span></div>
    </div>

    <table class="form">
        <colgroup><col style="width:20%"><col style="width:52%"><col style="width:28%"></colgroup>
        <tr>
            <td style="text-align:center;vertical-align:middle;height:78pt;"><img src="{{ $logo }}" alt="Philippine Retirement Authority" style="width:70pt;height:70pt;object-fit:contain;"></td>
            <td style="vertical-align:middle;">
                <div style="font:bold 12pt Arial, sans-serif;">PHILIPPINE RETIREMENT AUTHORITY</div>
                <div style="font-size:8.6pt;margin-top:2pt;">29F Citibank Tower, Paseo de Roxas, Makati City, 1227 Philippines<br>Tel. Nos. + 632-848-1412 + 632- 848-1418 + 632-848-1411<br>Email: inquiry@pra.gov.ph &nbsp; Website: pra.gov.ph</div>
            </td>
            <td rowspan="3" style="text-align:center;vertical-align:middle;font-size:8.6pt;">Attach 2” x 2” colored<br>photo taken not more<br>than 6 months ago</td>
        </tr>
        <tr><td colspan="2" style="height:26pt;vertical-align:middle;"><b style="font-size:10pt;">APPLICATION FORM FOR PRINCIPAL RETIREE</b> <span class="italic" style="font-size:8.4pt;">(Entries must be typewritten)</span></td></tr>
        <tr>
            <td colspan="2" style="height:34pt;">
                <b>SRRV Options</b> <span class="italic">(Please Check ✓)</span>
                <div style="display:flex;justify-content:space-between;margin-top:4pt;padding-right:20pt;">
                    @foreach (SrrvForms::SRRV_OPTIONS as $code => $optionLabel)
                        <span class="opt">{{ B::tick($option === $code, $blank) }}{{ $optionLabel }}</span>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    <table class="form" style="border-top:2px solid #000;margin-top:-1px;">
        <colgroup>@for ($i = 0; $i < 12; $i++)<col style="width:8.333%">@endfor</colgroup>
        <tr>
            <td colspan="3"><span class="lab">Last Name</span><span class="val f">{{ $blank ? '' : $lastName }}</span></td>
            <td colspan="3"><span class="lab">First Name</span><span class="val f">{{ $blank ? '' : trim($firstName.' '.$middleName) }}</span></td>
            <td colspan="3"><span class="lab">Alias (AKA)</span><span class="val f">{{ $d('alias') }}</span></td>
            <td colspan="3"><span class="lab">Religion</span><span class="val f">{{ $d('religion') }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="lab">Gender</span>
                <span class="opt" style="margin-top:2pt;">{{ B::tick($gender === 'M', $blank) }}Male</span><br>
                <span class="opt">{{ B::tick($gender === 'F', $blank) }}Female</span></td>
            <td colspan="2"><span class="lab">Date of Birth</span><span class="val f">{{ $fmt($dob) }}</span></td>
            <td colspan="4"><span class="lab">Place of Birth</span><span class="val f">{{ $d('place_of_birth') }}</span></td>
            <td colspan="4"><span class="lab">Nationality <span class="f" style="margin-left:6pt;">{{ $blank ? '' : $a?->nationality }}</span></span>
                <span class="lab" style="margin-top:8pt;">ID No. <span class="f" style="margin-left:6pt;">{{ $d('id_no') }}</span></span></td>
        </tr>
        <tr>
            <td colspan="7"><span class="lab">Civil Status</span>
                <div style="display:flex;justify-content:space-between;margin-top:5pt;padding-right:12pt;">
                    @foreach (SrrvForms::CIVIL_STATUSES as $code => $statusLabel)
                        <span class="opt">{{ B::tick($civil === $code, $blank) }}{{ $statusLabel }}</span>
                    @endforeach
                </div></td>
            <td colspan="3"><span class="lab">Height</span><span class="val f">{{ $d('height') }}</span></td>
            <td colspan="2"><span class="lab">Weight</span><span class="val f">{{ $d('weight') }}</span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="lab">Passport No.</span><span class="val f">{{ $d('passport_no') }}</span></td>
            <td colspan="3"><span class="lab">Place of Issue</span><span class="val f">{{ $d('passport_place') }}</span></td>
            <td colspan="3"><span class="lab">Date of Issue</span><span class="val f">{{ $fmt($dt('passport_issued_on')) }}</span></td>
            <td colspan="3"><span class="lab">Valid Until</span><span class="val f">{{ $fmt($dt('passport_expires_on')) }}</span></td>
        </tr>
        <tr><td colspan="12"><span class="lab">Home Country Address <span class="italic">(Please specify)</span></span><span class="val f">{{ $d('home_address') }}</span></td></tr>
        <tr>
            <td colspan="3"><span class="lab">Telephone No.</span><span class="val f">{{ $d('home_phone') }}</span></td>
            <td colspan="3"><span class="lab">Fax No.</span><span class="val f">{{ $d('home_fax') }}</span></td>
            <td colspan="3"><span class="lab">Mobile No.</span><span class="val f">{{ $d('home_mobile') }}</span></td>
            <td colspan="3"><span class="lab">E-mail</span><span class="val f" style="text-transform:none;font-size:8pt;word-break:break-all;">{{ $d('home_email') }}</span></td>
        </tr>
        <tr><td colspan="12"><span class="lab">Primary Address in the Philippines <span class="italic">(Please specify)</span></span><span class="val f">{{ $d('primary_address') }}</span></td></tr>
        <tr><td colspan="12"><span class="lab">Secondary Address in the Philippines <span class="italic">(Please specify)</span></span><span class="val f">{{ $d('secondary_address') }}</span></td></tr>
        <tr>
            <td colspan="3"><span class="lab">Telephone No.</span><span class="val f">{{ $d('ph_phone') }}</span></td>
            <td colspan="3"><span class="lab">Fax No.</span><span class="val f">{{ $d('ph_fax') }}</span></td>
            <td colspan="3"><span class="lab">Mobile No.</span><span class="val f">{{ $d('ph_mobile', $a?->retiree_phone) }}</span></td>
            <td colspan="3"><span class="lab">E-mail</span><span class="val f" style="text-transform:none;font-size:8pt;word-break:break-all;">{{ $d('ph_email', $a?->retiree_email) }}</span></td>
        </tr>
    </table>

    <table class="form" style="border-top:2px solid #000;margin-top:-1px;">
        <colgroup><col style="width:35%"><col style="width:15%"><col style="width:9%"><col style="width:16%"><col style="width:25%"></colgroup>
        <tr><td colspan="5" style="height:26pt;vertical-align:middle;"><b>Family Member Information</b></td></tr>
        @foreach ($family as [$familyLabel, $prefix])
            <tr>
                <td style="height:40pt;"><span class="lab">{{ $familyLabel }}</span><span class="val f">{{ $d("{$prefix}_name") }}</span></td>
                <td><span class="lab">Date of Birth</span><span class="val f">{{ $fmt($dt("{$prefix}_dob")) }}</span></td>
                <td><span class="lab">Age</span><span class="val f">{{ $ageOf($dt("{$prefix}_dob")) }}</span></td>
                <td><span class="lab">ID No. (Required)</span><span class="val f">{{ $d("{$prefix}_id") }}</span></td>
                <td><span class="lab">Included in your application?</span>
                    <span class="opt" style="margin:5pt 14pt 0 0;">{{ B::tick($yes("{$prefix}_included"), $blank) }}Yes</span><span class="opt">{{ B::tick($no("{$prefix}_included"), $blank) }}No</span></td>
            </tr>
        @endforeach
    </table>
    <div class="italic" style="margin:4pt 0 0 30pt;">(if necessary, use additional sheet)</div>
    <div class="code" style="margin-top:18pt;">PRA-CR-FORM-0004<br>ISSUE NO. 0003<br>ISSUE DATE: JANUARY 2019</div>
</div>

{{-- ===================== PAGE 2 ===================== --}}
<div class="sheet" style="padding:26pt 30pt 0 38pt;">
    <table class="form">
        <colgroup>@for ($i = 0; $i < 12; $i++)<col style="width:8.333%">@endfor</colgroup>
        <tr><td colspan="12" style="height:auto;border-bottom:0;"><b style="text-decoration:underline;">Parent’s Information</b></td></tr>
        <tr>
            <td colspan="6" style="border-top:0;height:56pt;"><span class="lab">Name of Father : <span class="f">{{ $d('father_name') }}</span></span><span class="lab" style="margin-top:12pt;">Age <span class="f" style="margin-left:6pt;">{{ $d('father_age') }}</span></span></td>
            <td colspan="6" style="border-top:0;"><span class="lab">Name of Mother : <span class="f">{{ $d('mother_name') }}</span></span><span class="lab" style="margin-top:12pt;">Age <span class="f" style="margin-left:6pt;">{{ $d('mother_age') }}</span></span></td>
        </tr>
        <tr>
            <td colspan="3" rowspan="2"><span class="lab">Name of Contact Person in Case of Emergency:</span><span class="val f">{{ $d('emergency_name') }}</span></td>
            <td colspan="3" style="height:30pt;"><span class="lab">Contact No. :</span><span class="val f">{{ $d('emergency_contact') }}</span></td>
            <td colspan="3"><span class="lab">Nationality</span><span class="val f">{{ $d('emergency_nationality') }}</span></td>
            <td colspan="3"><span class="lab">Relationship:</span><span class="val f">{{ $d('emergency_relationship') }}</span></td>
        </tr>
        <tr><td colspan="9" style="height:30pt;"><span class="lab">Address :</span><span class="val f">{{ $d('emergency_address') }}</span></td></tr>
        <tr style="border-top:2.5px solid #000;">
            <td colspan="4" style="height:40pt;"><span class="lab">Date of Arrival in the Philippines</span><span class="val f">{{ $fmt($dt('arrival_date')) }}</span></td>
            <td colspan="4"><span class="lab">Expiration Date of Tourist Visa / Others</span><span class="val f">{{ $fmt($dt('visa_expires_on')) }}</span></td>
            <td colspan="4"><span class="lab">Entry Visa to the Philippines</span><span class="val f">{{ $d('entry_visa') }}</span></td>
        </tr>
        <tr>
            <td colspan="12" style="padding:6pt 8pt;">
                <div style="display:flex;"><span style="flex:1;">Have you visited Philippines prior to this travel?</span>
                    <span class="opt" style="width:140pt;">{{ B::tick($yes('visited_before'), $blank) }}Yes</span><span class="opt" style="width:60pt;">{{ B::tick($no('visited_before'), $blank) }}No</span></div>
                <div style="margin-top:6pt;">If the answer is “Yes”, What kind of entry visa?</div>
                <div style="display:grid;grid-template-columns:150pt 90pt 1fr;row-gap:3pt;margin-top:4pt;">
                    @foreach (SrrvForms::PRIOR_VISAS as $code => $visaLabel)
                        <span class="opt">{{ B::tick($priorVisa === $code, $blank) }}{{ $visaLabel }}@if ($code === 'others') <span class="italic">&nbsp;(Please specify)</span>&nbsp;<span class="ln f" style="width:110pt;">{{ $d('prior_visa_other') }}</span>@endif</span>
                    @endforeach
                </div>
                <div style="margin-top:10pt;">Last three years residence</div>
                <div style="display:grid;grid-template-columns:200pt 1fr;column-gap:24pt;row-gap:5pt;margin-top:6pt;">
                    <span>Period of stay <span class="italic">(mm/yyyy - mm/yyyy)</span></span><span>Address</span>
                    @foreach (range(1, 4) as $i)
                        <span style="display:flex;gap:3pt;">{{ $i }}<span class="ln f" style="flex:1;">{{ $d("residence_{$i}_period") }}</span></span>
                        <span class="ln f">{{ $d("residence_{$i}_address") }}</span>
                    @endforeach
                </div>
            </td>
        </tr>
        <tr style="border-top:2.5px solid #000;">
            <td colspan="12" style="padding:6pt 8pt;">
                <div style="display:grid;grid-template-columns:1.1fr 1fr 0.9fr;column-gap:24pt;row-gap:4pt;">
                    <span>Educational Attainment</span><span>School and Location</span><span>From/To <span class="italic">(mm/yyyy - mm/yyyy)</span></span>
                    @foreach (range(1, 3) as $i)
                        <span style="display:flex;gap:3pt;">{{ $i }}<span class="ln f" style="flex:1;">{{ $d("education_{$i}_level") }}</span></span>
                        <span class="ln f">{{ $d("education_{$i}_school") }}</span>
                        <span class="ln f">{{ $d("education_{$i}_period") }}</span>
                    @endforeach
                </div>
                <div class="row-line" style="margin-top:12pt;"><span>Name and Address of Present Company / Business <span class="italic">(if any):</span></span><span class="ln f">{{ $d('company') }}</span></div>
                <div class="row-line"><span>Contact No.</span><span class="ln f">{{ $d('company_contact') }}</span></div>
                <div style="margin-top:14pt;">Aside from retirement, what are the other plans/future actions in the Philippines</div>
                <div style="display:grid;grid-template-columns:150pt 1fr;row-gap:10pt;margin-top:10pt;">
                    @foreach (SrrvForms::PLANS as $code => $planLabel)
                        <span class="opt">{{ B::tick(in_array($code, $plans, true), $blank) }}{{ $planLabel }}@if ($code === 'others') <span class="italic">&nbsp;(Please specify)</span>&nbsp;<span class="ln f" style="width:200pt;">{{ $d('plans_other') }}</span>@endif</span>
                    @endforeach
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="12" style="padding:6pt 8pt 10pt;">
                <div>Employment in the last three <span class="italic">(3)</span> years.</div>
                <div style="display:grid;grid-template-columns:1.6fr 0.8fr 1fr;column-gap:24pt;row-gap:4pt;margin-top:8pt;">
                    <span>Company Name and Address</span><span>Job Title</span><span>From/To <span class="italic">(mm/yyyy~mm/yyyy)</span></span>
                    @foreach (range(1, 3) as $i)
                        <span style="display:flex;gap:3pt;">{{ $i }}<span class="ln f" style="flex:1;">{{ $d("employment_{$i}_company") }}</span></span>
                        <span class="ln f">{{ $d("employment_{$i}_title") }}</span>
                        <span class="ln f">{{ $d("employment_{$i}_period") }}</span>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>
    <div class="code" style="margin-top:14pt;">PRA-CR-FORM-0004<br>ISSUE NO.: 0003<br>ISSUE DATE: JANUARY 2019</div>
</div>

{{-- ===================== PAGE 3 ===================== --}}
<div class="sheet" style="padding:22pt 26pt 0 26pt;">
    <div class="terms" style="border:1.5px solid #000;border-bottom:0;padding:6pt 8pt;">
        <b style="font-size:9pt;">TERMS AND CONDITIONS:</b>
        <ol>
            <li>The following classes of aliens, shall be excluded from entry into the Philippines, and are not eligible to acquire a Special Resident Retiree’s Visa (SRRV):
                <ol type="a" style="margin-top:3pt;">
                    <li style="margin-bottom:1pt;">Insane persons, persons afflicted with a contagious disease, persons with manifestation of any anxiety depressive, psychotic, personality and psychological disorders identified and observed during the conduct of medical examination as certified by the person’s attending physician.</li>
                    <li style="margin-bottom:1pt;">Pauper, vagrant, and beggars, persons who are likely to become a public charge, stowaways, persons who have been excluded or deported from the Philippines, including those deported as indigent aliens or persons not properly documented for admission;</li>
                    <li style="margin-bottom:1pt;">Persons who have been convicted of a crime involving moral turpitude, prostitutes or procures, persons coming for any immoral purposes;</li>
                    <li style="margin-bottom:1pt;">Persons who believe in, advocate the overthrow by force and violence of the Government of the Philippines, or of constituted lawful authority, or who disbelieve in, or are opposed to an organized government, and persons who use force and violence in pursuit of their advocacies;</li>
                    <li style="margin-bottom:1pt;">Persons over fifteen (15) years of age, physically capable of reading who cannot read printed matter in ordinary use in any language selected by the alien, persons who are members of a family accompanying an excluded alien; or</li>
                    <li style="margin-bottom:1pt;">Persons coming to perform unskilled manual labor in pursuit of a promise or offer of employment.</li>
                </ol>
            </li>
            <li><b>Obedience to Philippine Laws, Rules and Regulations.</b> I hereby affirm that I would abide by Philippine laws, rules and regulations, and respect Philippine customs and traditions.</li>
            <li><b>Engagement in Gainful Employment.</b> I hereby agree to secure from the Department of Labor and Employment (DOLE) the required Alien Employment Permit (AEP) before engaging in gainful employment in the Philippines, and abide by the existing labor laws of the country governing alien employment.<b>*</b></li>
            <li><b>Presumption of Death.</b> I understand that, for SRRV purposes, the Philippine Retirement Authority (PRA) may declare the presumptive death of SRRV Holders, 90 years of age and above, who have not been heard of or whose existence have not been known for four (4) years despite diligent efforts to locate said SRRV Holders, such as, but not limited to, communications to last known address and publication of such absence in a newspaper of general circulation in the Philippines. In such case, I allow PRA to deduct from my visa deposit the cancellation fee, visitorial fee, harmonization fee, annual membership fee, and the actual costs of publication and other fees and charges necessary and essential for the maintenance of the SRRV, as the case may be, and my SRRV shall be cancelled accordingly. <b>**</b></li>
            <li><b>Special or Extraordinary Expenses.</b> I understand that unexpected events may occur any time during my stay in the country as SRRV Holder, and expenses may be incurred in relation thereto, such as, but not limited to, medical expenses and hospitalization. In such case, I hereby allow PRA to deduct from my visa deposit such amount, as may be necessary, to cover such extraordinary expenses, when my financial means cannot cover for the payment of the same.</li>
            <li><b>Confidential Disclosure of Information.</b> Pursuant to this application, and in line with the provisions of Data Privacy Act of 2012, I hereby give my consent to PRA to share my contact details and other information to a third-party consultant or agency duly authorized by PRA to conduct PRA-initiated surveys, such as, but not limited to, Customer Satisfaction Survey/Stakeholder’s Survey, and Retiree’s Expenditure Survey. I understand that PRA shall take all necessary measures and steps to protect and secure such information from unauthorized or unlawful use.</li>
        </ol>
        <div style="border-top:0.75px solid #000;width:120pt;margin:4pt 0 0 6pt;"></div>
        <p class="italic" style="font-size:6.8pt;margin:3pt 0 0 6pt;"><b>*</b> Gainful employment refers to a state or condition that creates an employee-employer relationship between the Philippine-based employer and the foreign national. If there is no employee-employer relationship, there is no need for SRRV Holders to apply for an AEP (Department Order No. 186, Series of 2017, otherwise known as Revised Rule for the Issuance of Employment Permits to Foreign Nationals).</p>
        <p class="italic" style="font-size:6.8pt;margin:3pt 0 0 6pt;"><b>**</b> The remaining balance of the visa deposit of the concerned retiree-member declared presumed dead, for SRRV purposes, shall be kept intact until his/her lawful heir/s claims the same. If the concerned retiree-member presumed dead should reappear, he/she can claim the remaining balance of his/her visa deposit, without prejudice to the right of such retiree-member to apply for a new SRRV.</p>
        <p style="font-weight:bold;font-size:8.4pt;margin:6pt 0 0;">I hereby affirm that I have read and understood the above terms and conditions. By affixing my signature herein, I understand and agree that I am bound by such terms and conditions to the fullest extent allowed by the laws of the Philippines. I further certify that the information above are true and correct and that any misrepresentation on my part will be grounds for denial of SRRV and/or revocation of my current Visa:</p>
    </div>
    <table class="form" style="border:1.5px solid #000;border-top:2.5px solid #000;">
        <tr>
            <td style="width:50%;padding:10pt 12pt;line-height:1;">
                <div class="row-line" style="margin-top:20pt;"><span>Signature</span><span class="ln"></span></div>
                <div class="row-line" style="margin-top:14pt;"><span>Name/s of Retiree-applicant</span><span class="ln f">{{ $applicantName }}</span></div>
                <div class="row-line" style="margin-top:14pt;"><span class="ln"></span></div>
                <div class="row-line" style="margin-top:14pt;"><span>Date Signed:</span><span class="ln"></span></div>
            </td>
            <td style="padding:10pt 12pt;line-height:1;">
                <div class="row-line"><span>Registered Name of Marketer:</span><span class="ln f">{{ $d('marketer') }}</span></div>
                <div class="row-line" style="margin-top:14pt;"><span class="ln"></span></div>
                <div class="row-line" style="margin-top:14pt;"><span>PRA Accreditation No:</span><span class="ln f">{{ $d('accreditation_no') }}</span></div>
                <div class="row-line" style="margin-top:14pt;"><span>Name and Signature of Representative:</span><span class="ln f">{{ $d('representative') }}</span></div>
                <div class="row-line" style="margin-top:14pt;"><span>Telephone No:</span><span class="ln f">{{ $d('representative_phone') }}</span></div>
            </td>
        </tr>
    </table>

    <div style="padding:0 20pt;">
        <div class="italic" style="text-align:center;margin-top:6pt;">(To be accomplished by PRA Front Desk Personnel)</div>
        <div class="row-line" style="margin-top:12pt;width:70%;"><span>Date of Receipt of Application:</span><span class="ln"></span></div>
        <div style="margin-top:2pt;">Papers Reviewed &amp; Certified Complete by: (Please indicate complete name, designation, and long-form signature</div>
        <div style="margin-top:16pt;">Comments / Remarks:</div>
        <div class="ln" style="display:block;margin-top:12pt;"></div>
        <div class="ln" style="display:block;margin-top:6pt;"></div>
        <div class="italic" style="text-align:center;margin-top:6pt;">(To be accomplished upon issuance of SRRV)</div>
        <div style="display:flex;gap:6pt;align-items:flex-end;margin-top:14pt;">
            <span>SRRV Number:</span><span class="ln f" style="flex:1.3;">{{ $blank ? '' : $a?->srrv_card_number }}</span>
            <span>Date of Issuance:</span><span class="ln" style="flex:1;"></span>
            <span>Date of Oath-taking:</span><span class="ln f" style="flex:1;">{{ $blank ? '' : $fmt($a?->oath_at) }}</span>
        </div>
        <div class="code" style="margin-top:4pt;">PRA-CR-FORM-0004<br>Issue # 0003<br>Issue Date: January 2019</div>
    </div>
</div>
</body>
</html>
