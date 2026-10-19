@php
    use App\Models\ImmigrationPetition;

    /**
     * The letter request to the Commissioner that a downgrading of visa is filed with
     * (item 1 of BI checklist IRD01.QF.001): from the applicant, with their address and
     * numbers, or on the petitioning company's letterhead. For a marriage or TRV visa the
     * spouse signs too. Blank, it is a template with lines to fill in by hand.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $line = fn (?string $value, string $width = '160pt') => filled($value)
        ? '<b class="fill-in">'.e($value).'</b>'
        : '<span style="display:inline-block;width:'.$width.';border-bottom:0.75px solid #000;"></span>';

    $name = $blankForm ? null : mb_strtoupper(trim($client->full_name ?: collect([$client->given_name, $client->last_name])->filter()->implode(' ')));
    $category = $d('visa_category');
    $visa = $d('present_status') ?: (ImmigrationPetition::DOWNGRADE_CATEGORIES[$category] ?? null);
    $reason = ImmigrationPetition::DOWNGRADE_REASONS[$d('downgrade_reason')] ?? null;
    // The agent's own explanation reads best; the reason picked stands in when there is none.
    $reasonText = $d('reason_detail') ?: ($reason && $d('downgrade_reason') !== 'other' ? 'my '.strtolower($reason) : null);
    $byCompany = $d('letter_by') === 'company';
    $spouse = in_array($category, ['marriage', 'trv'], true) ? $d('spouse_name') : null;
    $contact = collect([$blankForm ? null : $client->mobile_number, $d('landline')])->filter()->implode(' / ');
    $acr = $d('acr_number');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Downgrading Letter Request{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 72pt 76pt 0 76pt; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        .sheet p { margin: 0 0 12pt; text-align: justify; }
        .fill-in { font-weight: bold; }
        .sign { display: inline-block; min-width: 220pt; border-top: 0.75px solid #000; text-align: center; padding-top: 2pt; margin-top: 40pt; }
        .letterhead { text-align: center; border-bottom: 1.5px solid #000; padding-bottom: 8pt; margin: -36pt 0 24pt; }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print letter</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in). {{ $byCompany ? 'Print on the company letterhead, or use the heading below.' : 'Signed by the applicant.' }}</span>
</div>

<div class="sheet">
    @if ($byCompany)
        <div class="letterhead">
            <div style="font-size:15pt;font-weight:bold;text-transform:uppercase;">{{ $d('company_name') }}</div>
            <div style="font-size:10.5pt;">{{ $d('company_address') }}</div>
        </div>
    @endif

    <p style="margin-bottom:24pt;">{!! $blankForm ? $line(null, '150pt') : e(($petition->filed_on ?? now())->format('d F Y')) !!}</p>

    <p style="text-align:left;line-height:1.3;"><b>THE COMMISSIONER</b><br>Bureau of Immigration<br>Magallanes Drive, Intramuros<br>Manila</p>

    <p style="text-align:left;"><b>Subject: Request for Downgrading of Visa</b>@if ($name) &ndash; {{ $name }}@endif</p>

    <p>Dear Commissioner:</p>

    @if ($byCompany)
        <p style="text-indent:36pt;">We respectfully request the downgrading of the visa of {!! $line($name, '200pt') !!}, a {!! $line($blankForm ? null : $client->nationality, '90pt') !!} national, holder of Passport No. {!! $line($blankForm ? null : $client->passport_number, '110pt') !!}@if ($acr || $blankForm) and ACR I-Card No. {!! $line($acr, '110pt') !!}@endif, presently holding a {!! $line($visa, '200pt') !!} visa petitioned by our {{ $category === 'missionary' ? 'congregation' : 'company' }}.</p>
        <p style="text-indent:36pt;">This request is by reason of {!! $line($reasonText ?: null, '260pt') !!}.</p>
        <p style="text-indent:36pt;">Our office may be reached at {!! $line($d('company_address'), '220pt') !!}. Attached are the documents listed in the Checklist of Documentary Requirements for Downgrading of Visa.</p>
    @else
        <p style="text-indent:36pt;">I, {!! $line($name, '200pt') !!}, a {!! $line($blankForm ? null : $client->nationality, '90pt') !!} national, of legal age, holder of Passport No. {!! $line($blankForm ? null : $client->passport_number, '110pt') !!}@if ($acr || $blankForm) and ACR I-Card No. {!! $line($acr, '110pt') !!}@endif, presently holding a {!! $line($visa, '200pt') !!} visa, respectfully request the downgrading of my visa.</p>
        <p style="text-indent:36pt;">This request is by reason of {!! $line($reasonText ?: null, '260pt') !!}.</p>
        <p style="text-indent:36pt;">I presently reside at {!! $line($blankForm ? null : $client->address, '300pt') !!}, and may be reached at {!! $line($contact ?: null, '150pt') !!}@if (! $blankForm && $client->email) or {{ $client->email }}@endif. Attached are the documents listed in the Checklist of Documentary Requirements for Downgrading of Visa.</p>
    @endif

    <p style="text-indent:36pt;">Thank you for your kind consideration and favorable action on this request.</p>

    <p style="margin-top:24pt;">Respectfully yours,</p>

    <div style="display:flex;justify-content:space-between;gap:30pt;">
        <div style="text-align:center;">
            <span class="sign"><b>{{ $byCompany ? mb_strtoupper((string) $d('company_signatory')) : $name }}</b><br>{{ $byCompany ? 'Authorized Signatory' : 'Applicant' }}</span>
        </div>
        @if ($spouse && ! $byCompany)
            <div style="text-align:center;">
                <span class="sign"><b>{{ mb_strtoupper($spouse) }}</b><br>Spouse</span>
            </div>
        @endif
    </div>
    @if ($d('by_representative') && $d('representative_name'))
        <p style="margin-top:30pt;font-size:10.5pt;text-align:left;">Filed through: {{ $d('representative_name') }}@if ($d('accreditation_no')), BI Accreditation No. {{ $d('accreditation_no') }}@endif</p>
    @endif
</div>
</body>
</html>
