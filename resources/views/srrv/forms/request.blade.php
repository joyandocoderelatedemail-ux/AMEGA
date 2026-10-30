@php
    use App\Support\BoxedForm as B;
    use App\Support\SrrvForms;

    /**
     * The PRA Retiree Request Form (PRA-SD-FORM-0006, Issue 0007, April 2026), on legal
     * paper: ID renewal, ID replacement, re-stamping and certificates. Printed from a
     * renewal (ID Renewal ticked), a re-stamping request (Restamping ticked) or a retiree
     * file, or blank. The PRA Staff Action half
     * is left for the PRA.
     */
    $a = $application;
    $restamping = ! $blank && $file instanceof \App\Models\SrrvServiceRequest;
    [$lastName, $firstName, $middleName] = $name;
    $principal = $blank ? '' : trim(collect([$lastName, $firstName])->filter()->implode(', ').' '.$middleName);
    $card = $blank ? '' : $file->srrv_card_number;

    $defaultTransactions = match (true) {
        $blank => [],
        $renewal !== null => ['id_renewal'],
        $restamping => ['restamping'],
        $a?->service_type === 'restamping' => ['restamping'],
        default => [],
    };
    // A re-stamping request prints its own transaction, whatever the linked retiree file holds.
    $transactions = $blank ? [] : ($restamping ? $defaultTransactions : (array) ($d('transactions') ?? $defaultTransactions));
    $autoNote = $renewal ? trim(($card ? "{$card}, " : '').$renewal->years_paid.' '.str('year')->plural($renewal->years_paid)) : ($card ?: '');
    $note = $restamping ? $autoNote : ($d('transaction_note') ?? $autoNote);
    $office = $d('request_office');
    $income = $blank ? [] : (array) $d('income', []);
    $business = $blank ? [] : (array) $d('business', []);
    $skills = $blank ? [] : (array) $d('skill', []);
    $hints = [
        'id_renewal' => 'Indicate SRRV Nos. & No. Years', 'id_replacement' => 'Indicate SRRV Nos', 'restamping' => 'Indicate SRRV Nos',
        'certificate_membership' => 'Indicate SRRV Nos. & Purpose', 'certificate_deposit' => '', 'others' => 'Specify',
    ];
    $box = fn (bool $on) => B::tick($on, $blank);

    $logo = asset('images/logoapproved/PhilipineretirementAutorityLogo.jpg');
    $paper = 'legal';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Retiree Request Form{{ $blank ? '' : ' - '.$file->retiree_name }}</title>
    @include('srrv.forms._base')
    <style>
        .sheet { padding: 16pt 40pt 0 40pt; font-family: Calibri, Carlito, Arial, sans-serif; font-size: 7.4pt; }
        .frame { border: 1.6px solid #000; }
        .tick { width: 10pt; height: 10pt; margin-right: 5pt; }
        .grid3 { display: grid; column-gap: 8pt; row-gap: 3pt; }
        .row { display: flex; align-items: flex-end; gap: 4pt; margin-top: 3pt; }
        .row .ln { flex: 1; }
        .prompt { color: #b3b3b3; }
        .staff td { font-size: 6.6pt; }
        .staff .tick { width: 8pt; height: 8pt; margin-right: 3pt; }
        .mini { font-size: 5.6pt; }
        .b { font-weight: bold; }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print form</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Legal paper (8.5 &times; 14 in), no margins, 100% scale. One page.</span>
</div>

<div class="sheet">
    <div class="frame">
        <div style="display:flex;align-items:center;border-bottom:0.75px solid #000;padding:2pt 4pt;">
            <span style="flex:1;text-align:center;color:#d0021b;font-weight:bold;font-size:10.6pt;padding-left:120pt;">RETIREE REQUEST FORM</span>
            @foreach (SrrvForms::OFFICES as $code => $officeLabel)
                <span class="opt" style="margin-left:10pt;font-size:7.6pt;">{{ $box($office === $code) }}{{ $officeLabel }}</span>
            @endforeach
        </div>
        <div style="padding:2pt 14pt 6pt;">
            <div class="italic mini" style="margin-left:-12pt;">*For Retiree / Representative only</div>
            <div class="row" style="margin-top:0;">
                <b style="font-size:8pt;">Name of Principal:</b><span class="ln f" style="flex:3;">{{ $principal }}</span>
                <b style="font-size:8pt;margin-left:6pt;">SRRV No.:</b><span class="ln f">{{ $card }}</span>
            </div>
            <div class="italic mini" style="display:flex;gap:40pt;margin-left:110pt;"><span>Last Name,</span><span>Given Name,</span><span>Middle Name</span></div>
            <div class="b" style="text-decoration:underline;margin-top:5pt;">Transaction/s <span class="italic" style="font-weight:normal;">(Check all that apply)</span></div>
            <table class="form" style="margin-top:3pt;border:1.2px solid #000;">
                <colgroup><col style="width:27%"><col style="width:73%"></colgroup>
                @foreach (SrrvForms::TRANSACTIONS as $code => $transactionLabel)
                    @php $on = in_array($code, $transactions, true); @endphp
                    <tr>
                        <td class="b" style="font-size:8pt;padding:1.5pt 4pt;{{ $loop->first ? 'height:24pt;vertical-align:middle;' : '' }}"><span class="opt">{{ $box($on) }}{{ $transactionLabel }}@if ($code === 'others') <span class="mini" style="font-weight:normal;">&nbsp;(Courier, Certified True Copy, etc)</span>@endif</span></td>
                        <td style="padding:1.5pt 4pt;vertical-align:middle;">@if ($on && $note)<span class="f">{{ $note }}</span>@else<span class="prompt mini">{{ $hints[$code] }}</span>@endif</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div style="border-top:0.75px solid #000;padding:2pt 14pt 8pt;">
            <div class="b" style="text-align:center;font-size:8pt;">RETIREE INFORMATION</div>
            <div class="row"><b style="width:100pt;font-size:8pt;">Address (Local) :</b><span class="ln f">{{ $d('address_local', $d('primary_address')) }}</span></div>
            <div class="row"><b style="width:100pt;font-size:8pt;">Address (Abroad) :</b><span class="ln f">{{ $d('address_abroad', $d('home_address')) }}</span></div>
            <div class="row">
                <b style="font-size:8pt;">Mobile Numbers :</b><span class="ln f">{{ $d('ph_mobile', $a?->retiree_phone ?? ($restamping ? $file->retiree_phone : null)) }}</span>
                <span style="flex:1;margin-left:8pt;"><b style="font-size:8pt;">Other Contact Nos. (</b><span class="italic">indicate platform: e.g. Viber, Whatsapp, QQ,</span></span>
            </div>
            <div class="row">
                <b style="font-size:8pt;">Email Address :</b><span class="ln f" style="text-transform:none;">{{ $blank ? '' : ($file->retiree_email ?: $a?->retiree_email) }}</span>
                <span style="margin-left:8pt;" class="italic">WeChat, KakaoTalk, TG, etc<b style="font-style:normal;">):</b></span><span class="ln f">{{ $d('other_contacts') }}</span>
            </div>
            <div class="row">
                <b style="font-size:8pt;">Alternate Email :</b><span class="ln f" style="text-transform:none;">{{ $d('alternate_email') }}</span>
                <span style="flex:1;margin-left:16pt;" class="ln"></span>
            </div>

            <div style="display:grid;grid-template-columns:24pt 98pt 1fr;margin-top:6pt;">
                <span>I.</span>
                <div><b style="font-size:7.8pt;">Source(s) of Income:</b><br><span class="italic mini">Check all that apply</span></div>
                <div class="grid3" style="grid-template-columns:110pt 110pt 1fr;">
                    @foreach (['pension', 'business', 'others', 'employment', 'investments'] as $code)
                        <span class="opt b">{{ $box(in_array($code, $income, true)) }}{{ SrrvForms::INCOME[$code] }}@if ($code === 'others') <span style="font-weight:normal;">&nbsp;(kindly specify)</span>@endif</span>
                    @endforeach
                    <span class="ln f">{{ $d('income_other') }}</span>
                </div>
            </div>
            <div style="margin-left:60pt;margin-top:4pt;">
                <div class="row"><span style="width:200pt;">Name of Company/Employer/Business/Pension Provider:</span><span class="ln f">{{ $d('income_company') }}</span></div>
                <div class="row"><span style="width:200pt;">Address:</span><span class="ln f">{{ $d('income_address') }}</span></div>
                <div class="row"><span style="width:200pt;">Contact Information:</span><span class="ln f">{{ $d('income_contact') }}</span></div>
            </div>

            <div style="display:grid;grid-template-columns:24pt 98pt 1fr;margin-top:6pt;">
                <span>II.</span>
                <div><b style="font-size:7.8pt;">Nature of Business or Expertise:</b><br><span class="italic mini">Check all that apply</span></div>
                <div class="grid3" style="grid-template-columns:1.15fr 1fr 0.9fr;">
                    @foreach (['government', 'entertainment', 'food', 'technology', 'retail', 'manufacturing', 'transportation', 'construction', 'education', 'tourism', 'financial', 'healthcare', 'agriculture', 'religious'] as $code)
                        <span class="opt b" style="font-size:6.8pt;">{{ $box(in_array($code, $business, true)) }}{{ SrrvForms::BUSINESS[$code] }}</span>
                        @if ($code === 'agriculture')<span></span>@endif
                    @endforeach
                </div>
            </div>
            <div style="display:grid;grid-template-columns:24pt 98pt 1fr;margin-top:6pt;">
                <span>III.</span>
                <div><b style="font-size:7.8pt;">Professional / Skill Level:</b><br><span class="italic mini">Check all that apply</span></div>
                <div class="grid3" style="grid-template-columns:1.15fr 1fr 0.9fr;">
                    @foreach (['executive', 'admin_support', 'others', 'technical', 'manual'] as $code)
                        <span class="opt b" style="font-size:6.8pt;">{{ $box(in_array($code, $skills, true)) }}{{ SrrvForms::SKILLS[$code] }}</span>
                    @endforeach
                </div>
            </div>

            <div class="b italic" style="text-align:center;font-size:8pt;margin-top:8pt;">By affixing my signature, I hereby certify that the above information are true and correct. &nbsp; I also commit to inform PRA in writing, of any change of information presented here:</div>
            <div style="margin:12pt 0 0 auto;width:250pt;text-align:center;">
                <div class="f" style="min-height:10pt;">{{ $blank ? '' : collect([$firstName, $middleName, $lastName])->filter()->implode(' ') }}</div>
                <div class="b italic mini" style="border-top:0.75px solid #000;padding-top:1pt;">Signature of Retiree over Printed Name | Date</div>
            </div>
            <div class="b italic mini" style="margin-top:6pt;font-size:6.4pt;">To be filled-up by authorized representative who must submit notarized <u>Guarantee Letter</u> and <u>Authorization Letter/SPA</u> :</div>
            <div class="b" style="font-size:7pt;">Name of Representative :</div>
            <div style="display:grid;grid-template-columns:1fr 160pt;column-gap:24pt;margin-top:6pt;">
                <span class="ln f" style="display:block;">{{ $d('representative') }}</span><span class="ln f" style="display:block;">{{ $d('representative_phone') }}</span>
                <span class="mini" style="display:flex;justify-content:space-between;padding-right:60pt;"><span>Last Name</span><span>First/Given Name</span><span>Middle Name</span></span><span class="italic mini" style="text-align:center;">Contact Number</span>
            </div>
            <div style="display:grid;grid-template-columns:70pt 1fr 160pt;column-gap:24pt;margin-top:8pt;align-items:end;">
                <b style="font-size:7pt;">Marketer :</b>
                <span style="display:flex;gap:4pt;"><span class="ln f" style="flex:1.3;">{{ $d('marketer') }}</span><span class="ln f" style="flex:1;">{{ $d('accreditation_no') }}</span></span>
                <span class="ln"></span>
                <span></span>
                <span class="mini" style="display:flex;justify-content:space-around;"><span>Name of Marketer</span><span>Accreditation No.</span></span>
                <span class="italic mini" style="text-align:center;">Signature</span>
            </div>
        </div>
    </div>

    {{-- PRA Staff Action --}}
    <div class="frame" style="margin-top:6pt;">
        <div class="b" style="background:#d9d9d9;text-align:center;font-size:8pt;border-bottom:1.2px solid #000;">PRA STAFF ACTION</div>
        <table class="form staff" style="border:0;">
            <colgroup><col style="width:68%"><col style="width:32%"></colgroup>
            <tr>
                <td style="border-left:0;padding:3pt 4pt;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;column-gap:10pt;row-gap:4pt;font-weight:bold;">
                        <span>1. Date of Issuance of SRRV: <span class="ln" style="width:70pt;"></span></span><span>2. Validity of Last ID: <span class="ln" style="width:80pt;"></span></span>
                        <span>3. SRRV Scheme: <span class="ln" style="width:90pt;"></span></span><span>4. Requisite Deposit: <span class="ln" style="width:80pt;"></span></span>
                    </div>
                    <div style="display:grid;grid-template-columns:96pt 86pt 82pt 1fr;row-gap:2pt;margin-top:4pt;font-size:6.4pt;">
                        <span></span><b>5. Police / Interpol Clearance:</b><b>6. Biometrics:</b><b>7. Photo/PP:</b>
                        @foreach (['Principal:', 'Dependent 1:', 'Dependent 2:', 'Dependent 3:'] as $person)
                            <span style="font-size:7pt;">{{ $person }}@unless ($loop->first)<br><span class="italic" style="font-size:5pt;">Indicate SRRV No. ________</span>@endunless</span>
                            <span class="mini" style="line-height:1.35;">{{ B::tick(false, true) }}Complied &nbsp;{{ B::tick(false, true) }}{{ $loop->first ? 'Waived' : 'Not Required' }}<br>{{ B::tick(false, true) }}Not Complied</span>
                            <span class="mini" style="line-height:1.35;">{{ B::tick(false, true) }}Complied{{ $loop->first ? '' : ' / NR' }}<br>{{ B::tick(false, true) }}Not Complied</span>
                            <span class="mini" style="line-height:1.35;">{{ B::tick(false, true) }}New photo<br>{{ B::tick(false, true) }}New passport</span>
                        @endforeach
                    </div>
                </td>
                <td style="border-right:0;padding:3pt 4pt;">
                    <div class="b" style="text-align:center;text-decoration:underline;">EVALUATION :</div>
                    <div class="row" style="margin-top:8pt;">Checked By:<span class="ln"></span></div>
                    <div class="row">Date/Time:<span class="ln"></span></div>
                    <div style="margin-top:4pt;">Remarks:</div>
                </td>
            </tr>
            <tr>
                <td style="border-left:0;padding:3pt 4pt;">
                    <div style="display:grid;grid-template-columns:20pt 150pt 1fr;row-gap:2pt;align-items:center;">
                        <b>8.</b><span class="opt"><b>{{ B::tick(false, true) }}A. Visa Deposit</b>&nbsp;(Maintained at Bank)</span><span class="b">Amount: <span class="ln" style="width:150pt;"></span></span>
                        <span></span><span></span><span class="b">Bank: <span class="ln" style="width:158pt;"></span></span>
                        <span></span><span></span><span class="b">As of: <span class="ln" style="width:156pt;"></span></span>
                        <span></span><span class="opt b">{{ B::tick(false, true) }}B. Converted Into Allowable<br>Investment / Withdrawn</span><span class="b">Date of Conversion (Anniv.) <span class="ln" style="width:80pt;"></span><br>Amount (VF/HF): $ <span class="ln" style="width:60pt;"></span> ₱ <span class="ln" style="width:50pt;"></span></span>
                    </div>
                </td>
                <td style="border-right:0;padding:3pt 4pt;">
                    <div class="b" style="text-align:center;text-decoration:underline;">VISA DEPOSIT VERIFICATION :</div>
                    <div class="row" style="margin-top:6pt;">Checked By:<span class="ln"></span></div>
                    <div class="row">Date/Time:<span class="ln"></span></div>
                    <div style="margin-top:4pt;">Remarks:</div>
                </td>
            </tr>
            <tr>
                <td style="border-left:0;border-bottom:0;padding:3pt 4pt;">
                    <div style="display:grid;grid-template-columns:110pt 1fr 1fr;row-gap:1pt;font-size:6.4pt;" class="b">
                        <span style="font-size:7pt;">9. Investment Monitoring</span><span>{{ B::tick(false, true) }}Condominium</span><span>{{ B::tick(false, true) }}Shares of stocks in Corporation</span>
                        <span></span><span style="padding-left:12pt;">{{ B::tick(false, true) }}CCT with Restriction</span><span style="padding-left:12pt;">{{ B::tick(false, true) }}SEC Cert. of Good Standing</span>
                        <span></span><span style="padding-left:12pt;">{{ B::tick(false, true) }}DOAS <span style="font-weight:normal;">(for Contract to sell)</span></span><span style="padding-left:12pt;">{{ B::tick(false, true) }}General Information Sheet</span>
                        <span></span><span>{{ B::tick(false, true) }}Long-term Lease of Dwelling Place</span><span style="padding-left:12pt;">{{ B::tick(false, true) }}FS</span>
                        <span></span><span style="padding-left:12pt;">{{ B::tick(false, true) }}TCT with Restriction</span><span style="padding-left:12pt;">{{ B::tick(false, true) }}MP</span>
                        <span></span><span style="padding-left:12pt;">{{ B::tick(false, true) }}Valid until: <span class="ln" style="width:60pt;"></span></span><span style="padding-left:12pt;">{{ B::tick(false, true) }}ITR</span>
                        <span></span><span>{{ B::tick(false, true) }}Others (e.g. Golf shares)</span><span style="padding-left:12pt;">{{ B::tick(false, true) }}Philhealth</span>
                        <span></span><span style="padding-left:12pt;">{{ B::tick(false, true) }}Certificate of Membership</span><span style="padding-left:12pt;">{{ B::tick(false, true) }}SSS</span>
                    </div>
                </td>
                <td style="border-right:0;border-bottom:0;padding:3pt 4pt;">
                    <div class="b" style="text-align:center;text-decoration:underline;">INVESTMENT GROUP CLEARANCE:</div>
                    <div style="display:flex;justify-content:space-around;margin-top:6pt;font-size:8pt;"><span class="opt">{{ B::tick(false, true) }}Complied</span><span class="opt">{{ B::tick(false, true) }}Not complied</span></div>
                    <div class="row" style="margin-top:8pt;">Checked By:<span class="ln"></span></div>
                    <div class="row">Date/Time:<span class="ln"></span></div>
                    <div style="margin-top:4pt;">Remarks:</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="display:flex;gap:24pt;margin-top:6pt;font-size:6.8pt;" class="b">
        <div class="frame" style="flex:1;padding:6pt 10pt;line-height:2;">
            <div class="row" style="margin:0;"><span style="width:100pt;">Approved for Printing &amp; Release:</span><span class="ln"></span></div>
            <div class="row" style="margin:0;"><span style="width:100pt;">Date/ Time :</span><span class="ln"></span></div>
        </div>
        <div class="frame" style="flex:1.2;padding:6pt 6pt;line-height:1.8;">
            <div class="row" style="margin:0;">Encoded/ Printed By: <span class="ln"></span> Date/ Time : <span class="ln"></span></div>
            <div class="row" style="margin:0;">Released By: <span class="ln" style="max-width:110pt;"></span><span style="flex:1;"></span></div>
            <div class="row" style="margin:0;">Received By: <span class="ln"></span> Date/ Time : <span class="ln"></span></div>
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;align-items:center;gap:8pt;margin:4pt 30pt 0 0;font-size:6.6pt;" class="b">
        <img src="{{ $logo }}" alt="" style="width:16pt;height:16pt;object-fit:contain;"> PRA-SD-FORM-0006 &nbsp; Issue no. 0007 &nbsp; Issue date: April 2026
    </div>
</div>
</body>
</html>
