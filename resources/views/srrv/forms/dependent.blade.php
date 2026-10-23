@php
    use App\Support\BoxedForm as B;
    use App\Support\SrrvForms;

    /**
     * The PRA Application Form for Dependent Retiree (PRA-CR-FORM-0008, Issue 0002, January 2019):
     * three A4 pages, one per dependent. Filled from the retiree file's Family section for the
     * dependent asked for ($who: spouse or child_1..4), with the principal's details; everything
     * about the dependent the file does not hold is left to fill in by hand. Blank from the dashboard.
     */
    $a = $application;
    $fmt = fn ($date) => $date ? strtoupper($date->format('d M Y')) : '';
    $ageOf = fn ($date) => $date ? $date->age : '';
    $filled = ! $blank && $who;
    $isSpouse = $filled && $who === 'spouse';

    // The dependent's name is kept whole on the file: the last word is taken as the surname.
    $dependentName = $filled ? trim((string) $d("{$who}_name")) : '';
    $words = preg_split('/\s+/', $dependentName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $depLast = count($words) > 1 ? array_pop($words) : '';
    $depFirst = implode(' ', $words);
    $depDob = $filled ? $dt("{$who}_dob") : null;

    // Principal retiree.
    [$pLast, $pFirst, $pMiddle] = $name;
    $principalName = $filled ? collect([$pFirst, $pMiddle, $pLast])->filter()->implode(' ') : '';
    $option = $filled ? $d('srrv_option', $a?->visa_class) : null;
    $included = $filled ? $d("{$who}_included") : null;

    // Family information: a spouse lists the children; a child lists their siblings.
    $family = [];
    if ($filled) {
        foreach (['child_1', 'child_2', 'child_3', 'child_4'] as $child) {
            if ($child !== $who && filled($d("{$child}_name"))) {
                $family[] = $child;
            }
        }
    }
    $familyRows = array_pad($family, 5, null);
    $familyRow = function (?string $key, string $label) use ($d, $dt, $fmt, $ageOf, $blank) {
        return [
            'label' => $label,
            'name' => $key ? $d("{$key}_name") : '',
            'dob' => $key ? $fmt($dt("{$key}_dob")) : '',
            'age' => $key ? $ageOf($dt("{$key}_dob")) : '',
            'id' => $key ? $d("{$key}_id") : '',
            'yes' => $key && $d("{$key}_included") === 'yes',
            'no' => $key && $d("{$key}_included") === 'no',
        ];
    };

    $logo = asset('images/logoapproved/PhilipineretirementAutorityLogo.jpg');
    $paper = 'a4';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SRRV Dependent Application Form{{ $filled ? ' - '.$dependentName : '' }}</title>
    @include('srrv.forms._base')
    <style>
        .sheet { padding: 34pt 38pt 0 58pt; }
        .side { position: absolute; left: 10pt; top: 330pt; transform: rotate(-90deg); transform-origin: left top; font: bold 15pt Arial, sans-serif; white-space: nowrap; translate: 0 190pt; }
        table.form td { height: 34pt; }
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
    @if (! $blank)
        @foreach (SrrvForms::dependents($a) as $dependentKey => $dependentLabel)
            @if ($dependentKey !== $who)
                <a class="ghost" href="{{ route('srrv.applications.forms', [$a, 'dependent', 'who' => $dependentKey]) }}">Dependent: {{ $dependentLabel }}</a>
            @endif
        @endforeach
    @endif
    <span class="hint">A4, no margins, 100% scale. Three pages, one form per dependent.</span>
</div>

@if (! $blank && ! $who)
    <p style="max-width:210mm;margin:12pt auto;font:10pt Arial, sans-serif;color:#9f1239;">No dependent is named on this file yet. Add the spouse or children under Edit &rarr; PRA Form Details &rarr; Family, or print this form blank from the dashboard.</p>
@endif

{{-- ===================== PAGE 1 ===================== --}}
<div class="sheet">
    <div class="side">FOR DEPENDENT RETIREE USE</div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:6pt;">
        <div style="font:bold 9pt Arial, sans-serif;">SPECIAL RESIDENT RETIREE’S VISA APPLICATION<br>( DEPENDENT )</div>
        <div>Application No. <span class="ln f" style="width:90pt;text-align:center;">{{ $filled ? $a?->pra_reference : '' }}</span></div>
    </div>

    <table class="form">
        <colgroup><col style="width:20%"><col style="width:52%"><col style="width:28%"></colgroup>
        <tr>
            <td style="text-align:center;vertical-align:middle;height:78pt;"><img src="{{ $logo }}" alt="Philippine Retirement Authority" style="width:70pt;height:70pt;object-fit:contain;"></td>
            <td style="vertical-align:middle;">
                <div style="font:bold 12pt Arial, sans-serif;">PHILIPPINE RETIREMENT AUTHORITY</div>
                <div style="font-size:8.6pt;margin-top:2pt;">29F Citibank Tower, Paseo de Roxas, Makati City, 1227 Philippines<br>Tel. Nos. + 632-848-1412 + 632- 848-1418 + 632-848-1411<br>Email: inquiry@pra.gov.ph &nbsp;Website: pra.gov.ph</div>
            </td>
            <td rowspan="3" style="text-align:center;vertical-align:middle;font-size:8.6pt;">Attach 2” x 2” colored<br>photo taken not more<br>than 6 months ago</td>
        </tr>
        <tr><td colspan="2" style="height:26pt;vertical-align:middle;"><b style="font-size:10pt;">APPLICATION FORM FOR DEPENDENT RETIREE</b> <span class="italic" style="font-size:8.4pt;">(Entries must be typewritten)</span></td></tr>
        <tr>
            <td colspan="2" style="height:26pt;vertical-align:middle;">
                <span class="opt" style="margin:0 60pt 0 6pt;">{{ B::tick($isSpouse, $blank) }}Dependent - Spouse</span>
                <span class="opt">{{ B::tick($filled && ! $isSpouse, $blank) }}Dependent - Child</span>
            </td>
        </tr>
    </table>

    <table class="form" style="border-top:2px solid #000;margin-top:-1px;">
        <colgroup>@for ($i = 0; $i < 12; $i++)<col style="width:8.333%">@endfor</colgroup>
        <tr>
            <td colspan="3"><span class="lab">Last Name</span><span class="val f">{{ $depLast }}</span></td>
            <td colspan="4"><span class="lab">First Name</span><span class="val f">{{ $depFirst }}</span></td>
            <td colspan="3"><span class="lab">Alias (AKA)</span></td>
            <td colspan="2"><span class="lab">Religion</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="lab">Gender</span>
                <span class="opt" style="margin-top:2pt;">{{ B::tick(false, true) }}Male</span><br>
                <span class="opt">{{ B::tick(false, true) }}Female</span></td>
            <td colspan="3"><span class="lab">Date of Birth</span><span class="val f">{{ $fmt($depDob) }}</span></td>
            <td colspan="3"><span class="lab">Place of Birth</span></td>
            <td colspan="4"><span class="lab">Nationality</span>
                <span class="lab" style="margin-top:8pt;">ID No. <span class="f" style="margin-left:6pt;">{{ $filled ? $d("{$who}_id") : '' }}</span></span></td>
        </tr>
        <tr>
            <td colspan="7"><span class="lab">Civil Status</span>
                <div style="display:flex;justify-content:space-between;margin-top:5pt;padding-right:12pt;">
                    @foreach (SrrvForms::CIVIL_STATUSES as $code => $statusLabel)
                        <span class="opt">{{ B::tick($isSpouse && $code === 'married', $blank) }}{{ $statusLabel }}</span>
                    @endforeach
                </div></td>
            <td colspan="3"><span class="lab">Height</span></td>
            <td colspan="2"><span class="lab">Weight</span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="lab">Passport No.</span></td>
            <td colspan="3"><span class="lab">Place of Issue</span></td>
            <td colspan="3"><span class="lab">Date of Issue</span></td>
            <td colspan="3"><span class="lab">Valid Until</span></td>
        </tr>
        <tr><td colspan="12"><span class="lab">Home Country Address <span class="italic">(Please specify)</span></span></td></tr>
        <tr>
            <td colspan="3"><span class="lab">Telephone No.</span></td>
            <td colspan="3"><span class="lab">Fax No.</span></td>
            <td colspan="3"><span class="lab">Mobile No.</span></td>
            <td colspan="3"><span class="lab">E-mail</span></td>
        </tr>
        <tr><td colspan="12"><span class="lab">Primary Address in the Philippines <span class="italic">(Please specify)</span></span></td></tr>
        <tr><td colspan="12"><span class="lab">Secondary Address in the Philippines <span class="italic">(Please specify)</span></span></td></tr>
        <tr>
            <td colspan="3"><span class="lab">Telephone No. .</span></td>
            <td colspan="3"><span class="lab">Fax No.</span></td>
            <td colspan="3"><span class="lab">Mobile No.</span></td>
            <td colspan="3"><span class="lab">E-mail</span></td>
        </tr>
    </table>

    <table class="form" style="border-top:2px solid #000;margin-top:-1px;">
        <colgroup><col style="width:25%"><col style="width:75%"></colgroup>
        <tr>
            <td style="height:22pt;vertical-align:middle;">Principal Retiree Information</td>
            <td style="vertical-align:middle;">Name of Principal : <span class="f" style="margin-left:4pt;">{{ $principalName }}</span></td>
        </tr>
        <tr>
            <td colspan="2" style="height:38pt;">
                <div style="display:flex;align-items:center;">
                    <span style="width:150pt;">Included in this application?</span>
                    <span class="opt" style="margin-right:12pt;">{{ B::tick($included === 'yes', $blank) }}Yes</span>
                    <span class="opt">{{ B::tick($included === 'no', $blank) }}No <span class="italic">&nbsp;(Please provide principal’s SRRV information)</span></span>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10pt;margin-top:6pt;padding-right:20pt;">
                    <span>SRRV No. <span class="f" style="margin-left:4pt;">{{ $included === 'no' ? $a?->srrv_card_number : '' }}</span></span>
                    <span style="margin-left:60pt;">Date Issued</span>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="height:22pt;vertical-align:middle;">
                <div style="display:flex;justify-content:space-between;padding:0 20pt;">
                    @foreach (['smile' => 'SMILE', 'classic' => 'Classic', 'courtesy' => 'Courtesy', 'human_touch' => 'Human Touch'] as $code => $optionLabel)
                        <span class="opt">{{ B::tick($option === $code, $blank) }}{{ $optionLabel }}</span>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    <table class="form" style="border-top:2px solid #000;margin-top:-1px;">
        <colgroup><col style="width:31%"><col style="width:13%"><col style="width:9%"><col style="width:13%"><col style="width:9%"><col style="width:25%"></colgroup>
        <tr>
            <td colspan="2" style="height:30pt;">Family Information</td>
            <td colspan="4" class="italic" style="font-size:8.4pt;">For applying dependent-spouse please list name(s) of children below 21 years old; for applying dependent-child, please list name(s) of siblings.</td>
        </tr>
        @foreach (array_slice($familyRows, 0, 3) as $row)
            @php $r = $familyRow($row, 'Name of Child :'); @endphp
            <tr>
                <td style="height:38pt;"><span class="lab">{{ $r['label'] }}</span><span class="val f">{{ $r['name'] }}</span></td>
                <td colspan="2"><span class="lab">Date of Birth</span><span class="val f">{{ $r['dob'] }}</span></td>
                <td><span class="lab">Age</span><span class="val f">{{ $r['age'] }}</span></td>
                <td><span class="lab">ID No. (Required)</span><span class="val f">{{ $r['id'] }}</span></td>
                <td><span class="lab">Included in your application?</span>
                    <span class="opt" style="margin:5pt 14pt 0 0;">{{ B::tick($r['yes'], $blank) }}Yes</span><span class="opt">{{ B::tick($r['no'], $blank) }}No</span></td>
            </tr>
        @endforeach
    </table>
    <div class="code" style="margin-top:14pt;">PRA-CR-FORM-0008<br>ISSUE NO. 0002<br>ISSUE DATE: JANUARY 2019</div>
</div>

{{-- ===================== PAGE 2 ===================== --}}
<div class="sheet" style="padding:26pt 30pt 0 38pt;">
    <table class="form">
        <colgroup><col style="width:31%"><col style="width:13%"><col style="width:9%"><col style="width:13%"><col style="width:9%"><col style="width:25%"></colgroup>
        @foreach (array_slice($familyRows, 3, 2) as $row)
            @php $r = $familyRow($row, 'Name:'); @endphp
            <tr>
                <td style="height:38pt;"><span class="lab">{{ $r['label'] }}</span><span class="val f">{{ $r['name'] }}</span></td>
                <td colspan="2"><span class="lab">Date of Birth</span><span class="val f">{{ $r['dob'] }}</span></td>
                <td><span class="lab">Age</span><span class="val f">{{ $r['age'] }}</span></td>
                <td><span class="lab">ID No. (Required)</span><span class="val f">{{ $r['id'] }}</span></td>
                <td><span class="lab">Included in your application?</span>
                    <span class="opt" style="margin:5pt 14pt 0 0;">{{ B::tick($r['yes'], $blank) }}Yes</span><span class="opt">{{ B::tick($r['no'], $blank) }}No</span></td>
            </tr>
        @endforeach
    </table>

    <table class="form" style="border-top:2px solid #000;margin-top:-1px;">
        <colgroup>@for ($i = 0; $i < 12; $i++)<col style="width:8.333%">@endfor</colgroup>
        <tr><td colspan="12" style="height:22pt;vertical-align:middle;">Parent’s Information</td></tr>
        <tr>
            <td colspan="6" style="height:32pt;"><span class="lab">Name of Father</span><span class="lab">Age</span></td>
            <td colspan="6"><span class="lab">Name of Mother</span><span class="lab">Age</span></td>
        </tr>
        <tr style="border-top:2px solid #000;">
            <td colspan="4" rowspan="2"><span class="lab">Name of Contact Person in Case of Emergency</span></td>
            <td colspan="3" style="height:28pt;"><span class="lab">Contact No:</span></td>
            <td colspan="2"><span class="lab">Nationality</span></td>
            <td colspan="3"><span class="lab">Relationship</span></td>
        </tr>
        <tr><td colspan="8" style="height:26pt;"><span class="lab">Address</span></td></tr>
        <tr>
            <td colspan="4" style="height:38pt;"><span class="lab">Date of Arrival in the Philippines</span></td>
            <td colspan="4"><span class="lab">Expiration Date of Tourist Visa / Others</span></td>
            <td colspan="4"><span class="lab">Entry Visa to the Philippines</span></td>
        </tr>
        <tr>
            <td colspan="12" style="padding:6pt 8pt;">
                <div style="display:flex;"><span style="flex:1;">Have you visited Philippines prior to this travel?</span>
                    <span class="opt" style="width:140pt;">{{ B::tick(false, true) }}Yes</span><span class="opt" style="width:60pt;">{{ B::tick(false, true) }}No</span></div>
                <div style="margin-top:8pt;">If the answer is “yes” What kind of entry visa?</div>
                <div style="display:grid;grid-template-columns:150pt 130pt 1fr;row-gap:6pt;margin-top:8pt;">
                    @foreach (SrrvForms::PRIOR_VISAS as $code => $visaLabel)
                        <span class="opt">{{ B::tick(false, true) }}{{ $visaLabel }}@if ($code === 'others') <span class="italic">&nbsp;(Please specify)</span>@endif</span>
                    @endforeach
                </div>
                <div class="ln" style="display:block;width:220pt;margin:6pt 0 0 auto;"></div>
            </td>
        </tr>
        <tr style="border-top:2px solid #000;">
            <td colspan="12" style="padding:6pt 8pt 10pt;">
                <div style="display:grid;grid-template-columns:1fr 1.3fr 0.8fr;column-gap:24pt;row-gap:4pt;">
                    <span>Educational Attainment</span><span>School and Location</span><span>From / to <span class="italic">(mm/yyyy - mm/yyyy)</span></span>
                    @foreach (range(1, 3) as $i)
                        <span style="display:flex;gap:3pt;">{{ $i }}.<span class="ln" style="flex:1;"></span></span>
                        <span class="ln"></span>
                        <span class="ln"></span>
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    <div class="terms" style="margin-top:14pt;padding:0 6pt;">
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
            <li><b>Engagement in Gainful Employment.</b> I hereby agree to secure from the Department of Labor and Employment (DOLE) the required Alien Employment Permit (AEP) before engaging in gainful employment in the Philippines, and abide by the existing labor laws of the country governing alien employment.</li>
        </ol>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:30pt;">
        <div class="code">PRA-CR-FORM-0008<br>ISSUE NO. 0002<br>ISSUE DATE: JANUARY 2019</div>
        <div class="code">ENGLISH DEPENDENT</div>
    </div>
</div>

{{-- ===================== PAGE 3 ===================== --}}
<div class="sheet" style="padding:30pt 30pt 0 30pt;">
    <div class="terms" style="border:1.5px solid #000;border-bottom:2.5px solid #000;padding:8pt 10pt 14pt;">
        <ol start="4" style="padding-left:12pt;">
            <li><b>Confidential Disclosure of Information.</b> Pursuant to this application, and in line with the provisions of Data Privacy Act of 2012, I hereby give my consent to PRA to share my contact details and other information to a third-party consultant or agency duly authorized by PRA to conduct PRA-initiated surveys, such as, but not limited to, Customer Satisfaction Survey/Stakeholder’s Survey, and Retiree’s Expenditure Survey. I understand that PRA shall take all necessary measures and steps to protect and secure such information from unauthorized or unlawful use.</li>
        </ol>
        <div style="border-top:0.75px solid #000;width:120pt;margin:14pt 0 0 6pt;"></div>
        <p class="italic" style="font-size:6.8pt;margin:3pt 0 0 6pt;">Gainful employment refers to a state or condition that creates an employee-employer relationship between the Philippine-based employer and the foreign national. If there is no employee-employer relationship, there is no need for SRRV Holders to apply for an AEP (Department Order No. 186, Series of 2017, otherwise known as Revised Rule for the Issuance of Employment Permits to Foreign Nationals).</p>
        <p style="font-weight:bold;font-size:8.6pt;margin:10pt 0 0 6pt;">I hereby affirm that I have read and understood the above terms and conditions. By affixing my signature herein, I understand and agree that I am bound by such terms and conditions to the fullest extent allowed by the laws of the Philippines. I further certify that the information above are true and correct and that any misrepresentation on my part will be grounds for denial of SRRV and/or revocation of my current Visa:</p>
        <div style="font-family:'Arial Narrow', Arial, sans-serif;font-size:9pt;padding:0 6pt;">
            <div class="row-line" style="margin-top:22pt;"><span>Signature</span><span class="ln"></span></div>
            <div class="row-line" style="margin-top:14pt;"><span>Name/s of Retiree-applicant</span><span class="ln f">{{ $dependentName }}</span></div>
            <div class="row-line" style="margin-top:14pt;"><span>Date Signed:</span><span class="ln"></span></div>
        </div>
    </div>

    <div style="padding:0 6pt;">
        <div class="italic" style="text-align:center;margin-top:12pt;">(To be accomplished by PRA Front Desk Personnel)</div>
        <div class="row-line" style="margin-top:14pt;width:70%;"><span>Date of Receipt of Application:</span><span class="ln"></span></div>
        <div style="margin-top:2pt;">Papers Reviewed &amp; Certified Complete by: (Please indicate complete name, designation, and long-form signature</div>
        <div style="margin-top:16pt;">Comments / Remarks:</div>
        <div class="ln" style="display:block;margin-top:14pt;"></div>
        <div class="ln" style="display:block;margin-top:10pt;"></div>
        <div class="italic" style="text-align:center;margin-top:30pt;">(To be accomplished upon issuance of SRRV)</div>
        <div style="display:flex;gap:6pt;align-items:flex-end;margin-top:16pt;">
            <span>SRRV Number:</span><span class="ln" style="flex:1.3;"></span>
            <span>Date of Issuance:</span><span class="ln" style="flex:1;"></span>
            <span>Date of Oath-taking:</span><span class="ln" style="flex:1;"></span>
        </div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:330pt;">
        <div class="code">PRA-CR-FORM-0008<br>ISSUE NO.: 0002<br>ISSUE DATE : JANUARY 2019</div>
        <div class="code">ENGLISH DEPENDENT</div>
    </div>
</div>
</body>
</html>
