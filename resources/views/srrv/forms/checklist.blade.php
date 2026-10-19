@php
    use App\Support\SrrvForms;

    /**
     * The PRA Checklist of Requirements for the SRRV Application (PRA-CR-FORM-0001-SYSGEN,
     * Issue 0002, October 2019), on A4: the applicants on the file, what has been handed in,
     * and the visa deposit, or blank.
     */
    $a = $application;
    [$lastName, $firstName, $middleName] = $name;
    $fmt = fn ($date) => $date ? strtoupper($date->format('d M Y')) : '';
    $option = $blank ? null : (SrrvForms::SRRV_OPTIONS[$d('srrv_option', $a?->visa_class)] ?? null);

    // The principal, then each family member included in the application.
    $applicants = [];
    if (! $blank) {
        $applicants[] = ['Principal', trim(collect([$lastName, $firstName])->filter()->implode(', ').' '.$middleName), $d('alias'), $a?->nationality,
            $a?->date_of_birth, $dt('arrival_date'), $dt('visa_expires_on'), SrrvForms::CIVIL_STATUSES[$d('civil_status')] ?? '', $d('passport_no')];
        foreach (array_merge(['spouse'], array_map(fn ($i) => "child_{$i}", range(1, 4))) as $prefix) {
            if ($d("{$prefix}_name") && $d("{$prefix}_included") === 'yes') {
                $applicants[] = [$prefix === 'spouse' ? 'Spouse' : 'Child', $d("{$prefix}_name"), null, null, $dt("{$prefix}_dob"), null, null, null, null];
            }
        }
    }
    $applicants = array_pad(array_slice($applicants, 0, 5), 5, null);

    $requirements = $blank ? [] : array_map(fn ($item) => [$item['label'], $item['received'] ? 'Submitted' : ''], $a->documentChecklist());
    $requirements = array_pad(array_slice($requirements, 0, 10), 10, null);
    $deposit = $d('actual_deposit') ?? (! $blank && (float) $a?->investment_amount > 0 ? $a->currency.' '.number_format((float) $a->investment_amount, 2) : null);

    $logo = asset('images/logoapproved/PhilipineretirementAutorityLogo.jpg');
    $paper = 'a4';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SRRV Checklist{{ $blank ? '' : ' - '.$file->retiree_name }}</title>
    @include('srrv.forms._base')
    <style>
        .sheet { padding: 18pt 26pt 0 26pt; font-family: Arial, Helvetica, sans-serif; font-size: 7.6pt; }
        table.form td { height: 13pt; padding: 1pt 3pt; border-width: 0.75px; }
        table.form.thick { border: 1.6px solid #000; }
        .band { background: #d9d9d9; padding: 1pt 3pt; font-size: 7.4pt; }
        .f { font-size: 7pt; }
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
    <div style="text-align:center;line-height:1.25;">
        <img src="{{ $logo }}" alt="Philippine Retirement Authority" style="width:30pt;height:30pt;object-fit:contain;"><br>
        <span style="font-size:4.6pt;">REPUBLIC OF THE PHILIPPINES<br>DEPARTMENT OF TOURISM</span><br>
        <b>PHILIPPINE RETIREMENT AUTHORITY</b><br>
        <b style="font-size:6.4pt;">Marketing Department</b><br>
        <b style="text-decoration:underline;font-size:7pt;">CHECKLIST OF REQUIREMENTS FOR THE SRRV APPLICATION</b>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin:4pt 0 8pt;">
        <span style="padding-left:12pt;">SRRV OPTION: <span class="f">{{ $option }}</span></span>
        <span style="text-align:right;"><b class="italic" style="text-decoration:underline;">RRSD-PD EVAL :</b><br><span class="italic" style="font-size:5.4pt;">Date printed:</span> <span class="f" style="font-size:6pt;">{{ $blank ? '' : strtoupper(now()->format('d M Y')) }}</span></span>
    </div>

    <table class="form thick">
        <colgroup>
            <col style="width:8%"><col style="width:18%"><col style="width:9%"><col style="width:11%"><col style="width:8.5%">
            <col style="width:4%"><col style="width:8%"><col style="width:8%"><col style="width:9%"><col style="width:11.5%"><col style="width:5%">
        </colgroup>
        <tr>@foreach (['Applicant', 'Full name', 'AKA/Alias', 'Nationality', 'Birth Date', 'Age', 'Entry', 'Expiry', 'Status', 'Passport', 'Page'] as $heading)<td>{{ $heading }}</td>@endforeach</tr>
        @foreach ($applicants as $row)
            <tr>
                @if ($row)
                    @php [$role, $fullName, $alias, $nationality, $born, $entry, $expiry, $status, $passport] = $row; @endphp
                    <td class="f">{{ $role }}</td><td class="f">{{ $fullName }}</td><td class="f">{{ $alias }}</td><td class="f">{{ $nationality }}</td>
                    <td class="f">{{ $born?->format('d/m/Y') }}</td><td class="f">{{ $born?->age }}</td><td class="f">{{ $entry?->format('d/m/Y') }}</td>
                    <td class="f">{{ $expiry?->format('d/m/Y') }}</td><td class="f">{{ $status }}</td><td class="f">{{ $passport }}</td><td></td>
                @else
                    @for ($i = 0; $i < 11; $i++)<td></td>@endfor
                @endif
            </tr>
        @endforeach
    </table>

    <div class="band" style="margin-top:2pt;height:12pt;"></div>
    <table class="form thick">
        <colgroup><col style="width:31%"><col style="width:69%"></colgroup>
        <tr><td style="font-size:9.6pt;height:20pt;vertical-align:middle;">SUBMITTED REQUIREMENTS</td><td></td></tr>
        @foreach ($requirements as $item)
            <tr><td class="f" style="height:15pt;vertical-align:middle;">{{ $item[0] ?? '' }}</td><td class="f" style="vertical-align:middle;">{{ $item[1] ?? '' }}</td></tr>
        @endforeach
    </table>
    <div class="band" style="margin-top:2pt;height:12pt;"></div>
    <table class="form thick">
        <colgroup><col style="width:22%"><col style="width:20%"><col style="width:58%"></colgroup>
        <tr><td style="text-align:center;height:22pt;padding-top:3pt;">Additional Document for</td><td></td><td></td></tr>
        <tr><td style="text-align:center;height:32pt;">Additional Documents<br>(Previously holder of other visas<br>except tourists or BB visa)</td><td colspan="2"></td></tr>
    </table>
    <div class="band">DEPOSITORY BANK</div>
    <table class="form thick">
        <colgroup><col style="width:58%"><col style="width:20%"><col style="width:22%"></colgroup>
        <tr><td style="padding-left:12pt;">BANK</td><td style="padding-left:14pt;">REFERENCE NO.</td><td style="padding-left:12pt;">ACTUAL DEPOSIT</td></tr>
        <tr><td class="f" style="height:14pt;">{{ $d('bank') }}</td><td class="f">{{ $d('bank_reference') }}</td><td class="f">{{ $deposit }}</td></tr>
        <tr><td style="height:14pt;"></td><td></td><td></td></tr>
        <tr><td style="height:14pt;"></td><td></td><td></td></tr>
    </table>
    <div class="band">PAYMENT</div>
    <table class="form thick">
        <colgroup><col style="width:58%"><col style="width:20%"><col style="width:22%"></colgroup>
        <tr><td style="height:15pt;"></td><td></td><td></td></tr>
        <tr><td style="height:15pt;"></td><td></td><td></td></tr>
    </table>

    <div style="text-align:center;color:#1f3fbf;font-weight:bold;font-size:8pt;margin-top:2pt;line-height:1.2;">* * * FOLLOW UP * * *<br>CALL # (02) 8848-1412/PROCESSING DIVISION</div>
    <table class="form thick">
        <tr><td style="height:34pt;font-size:9pt;">STATUS:</td></tr>
        <tr>
            <td class="italic" style="font-size:8.2pt;line-height:1.3;padding:4pt 3pt;text-align:justify;">
                Note:<br><br>
                By affixing my signature, <b>I HEREBY CERTIFY THAT ALL INFORMATION PROVIDED HEREIN ARE TRUE AND CORRECT AND THAT ALL SUPPORTING DOCUMENTS SUBMITTED ARE <u>VALID, PROPER AND LEGAL</u></b>. Any misrepresentation may be grounds for denial of my SRRV Application and/or revocation of my current visa and filing of appropriate administrative or criminal case.<br><br>
                Processing will commence only upon receipt of complete documents and payment of necessary fees. In case tourist visa expires during the process period, the cost of extension will be shouldered by the applicant/s.
            </td>
        </tr>
        <tr>
            <td style="padding:3pt;">
                <div style="display:flex;align-items:flex-end;gap:10pt;">
                    <div style="width:110pt;line-height:1.9;">With my conformity: (Applicant/Rep):<br>Documents/Fees submitted by:</div>
                    <div style="flex:1.4;text-align:center;"><div class="f" style="min-height:10pt;">{{ $blank ? '' : collect([$firstName, $middleName, $lastName])->filter()->implode(' ') }}</div><div style="border-top:0.75px solid #000;font-size:6.4pt;" class="italic">Signature over printed name</div></div>
                    <div style="flex:1;text-align:center;"><div class="f" style="min-height:10pt;">{{ $blank ? '' : $a?->retiree_phone }}</div><div style="border-top:0.75px solid #000;font-size:6.4pt;" class="italic">Contact Number</div></div>
                </div>
            </td>
        </tr>
    </table>
    <table class="form thick" style="border-top:0;">
        <colgroup><col style="width:11%"><col style="width:50%"><col style="width:21%"><col style="width:18%"></colgroup>
        <tr><td style="height:16pt;vertical-align:middle;">PRA Evaluator</td><td style="vertical-align:middle;">Name:</td><td style="vertical-align:middle;">Date:</td><td style="vertical-align:middle;">Time:</td></tr>
    </table>

    <div style="position:absolute;left:60pt;right:60pt;bottom:30pt;display:flex;justify-content:space-between;font-size:7.4pt;">
        <span>PRA-CR-FORM-0001-SYSGEN</span><span>Issue No: 0002</span><span>Issue Date: October 2019</span>
    </div>
</div>
</body>
</html>
