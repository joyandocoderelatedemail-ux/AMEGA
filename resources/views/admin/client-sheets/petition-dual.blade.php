@php
    use App\Models\ImmigrationPetition;

    /**
     * The BI's Petition Form for Retention/Re-acquisition of Philippine Citizenship under
     * R.A. 9225 (BI Form 2014-01-005 Rev 0), on 8.5 x 13 in paper. Filled from the client
     * sheet and the dual citizenship case, or blank to fill in by hand. "Underline
     * applicable" choices are printed with the one that applies underlined.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $date = fn ($value) => $value ? strtoupper($value->format('d M Y')) : null;
    $dd = fn (string $key) => $blankForm ? null : $date($petition->dateDetail($key));
    $c = fn ($value) => $blankForm ? null : $value;

    // A blank line, filled when there is a value.
    $line = fn ($value, string $width) => '<span class="blank" style="min-width:'.$width.';">'.e((string) $value).'</span>';

    // "single/widowed/divorced/..." with the choice that applies underlined.
    $choose = function (array $options, ?string $selected): string {
        return collect($options)->map(fn ($text, $key) => $selected !== null && (string) $key === $selected
            ? '<u class="chosen">'.e($text).'</u>'
            : e($text))->implode('/');
    };

    $status = strtolower(trim((string) $client->civil_status));
    $status = ['widow' => 'widowed'][$status] ?? $status;
    $foreignName = $blankForm ? null : strtoupper((string) $client->full_name);
    $philippineName = $blankForm ? null : strtoupper((string) ($petition->detail('name_ph') ?: $client->full_name));
    $age = $blankForm ? null : $client->date_of_birth?->age;
    $alien = ! $blankForm && $petition->flag('registered_alien');
    $acquired = ['birth' => 'birth', 'election' => 'election', 'marriage' => 'marriage', 'naturalization' => 'naturalization'];
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>R.A. 9225 Petition{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 0.32in 0.62in 0 0.62in; font-family: Verdana, Tahoma, Arial, sans-serif; font-size: 9.6pt; line-height: 1.45; }
        .blank { display: inline-block; border-bottom: 1px solid #000; text-align: center; line-height: 1.15; padding: 0 3px; font-family: Arial, sans-serif; font-weight: bold; font-size: 9pt; color: #0b2a6b; text-transform: uppercase; vertical-align: baseline; }
        .under { display: block; font-size: 6.6pt; font-style: italic; text-align: center; line-height: 1; margin-top: -1px; }
        .row { display: flex; align-items: flex-end; gap: 4px; }
        .row .grow { flex: 1; }
        .chosen { font-weight: bold; color: #0b2a6b; }
        ol.items { margin: 0.06in 0 0; padding-left: 0.28in; }
        ol.items > li { padding-left: 0.04in; margin-bottom: 0.04in; }
        .dep th { font-weight: normal; font-size: 9.4pt; }
        .dep td { padding: 1px 6px; }
        @media print { .blank, .chosen { color: #000; } }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print petition</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale.</span>
</div>

<div class="sheet">
    <div style="display:flex;gap:12pt;align-items:flex-start;">
        <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:52pt;height:52pt;object-fit:contain;">
        <div style="flex:1;line-height:1.2;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;">
                <b style="font-size:11pt;">BI FORM 2014-01-005 Rev 0</b>
                <span style="font-size:7pt;">This document may be reproduced and is <b>NOT FOR SALE</b></span>
            </div>
            <div style="font-weight:bold;font-size:12.6pt;">PETITION FORM FOR RETENTION/RE-ACQUISITION OF<br>PHILIPPINE CITIZENSHIP UNDER R.A. 9225</div>
        </div>
    </div>

    <div style="text-align:center;font-weight:bold;font-size:15pt;margin:4pt 0 2pt;">PETITION</div>

    <div class="row" style="padding-left:0.5in;"><span>I,</span><span class="grow">{!! $line($philippineName, '100%') !!}<span class="under">[Name in Philippine Document]</span></span><span>,</span></div>
    <div class="row"><span>also known as</span><span class="grow">{!! $line($foreignName, '100%') !!}<span class="under">[Name in foreign passport]</span></span><span>,</span>{!! $line($age, '0.42in') !!}<span>years of age,</span></div>
    <div class="row" style="flex-wrap:wrap;">
        <span>{!! $choose(['M' => 'male', 'F' => 'female'], $d('sex')) !!}, am {!! $choose(['single' => 'single', 'widowed' => 'widowed', 'divorced' => 'divorced', 'annulled' => 'annulled', 'separated' => 'separated', 'married' => 'married'], $blankForm ? null : $status) !!} to</span>
        <span class="grow">{!! $line($status === 'married' ? $d('spouse_name') : null, '100%') !!}</span>
    </div>
    <div class="under" style="text-align:left;padding-left:2.4in;">(underline applicable)</div>
    <div class="row">
        <span class="grow">{!! $line(null, '100%') !!}</span><span>. I, a citizen of</span>{!! $line($c($client->nationality), '1.6in') !!}<span>and resident of</span><span class="grow">{!! $line(null, '100%') !!}</span>
    </div>
    <div class="row">
        <span class="grow">{!! $line($c($client->address), '100%') !!}</span><span>with contact number</span>{!! $line($c($client->mobile_number), '1.8in') !!}<span>,</span>
    </div>
    <div>after being sworn to in accordance with law, hereby declare under oath that:</div>

    <ol class="items">
        <li>
            <div class="row"><span>I am a former natural-born Philippine citizen, born on</span><span class="grow">{!! $line($blankForm ? null : $date($client->date_of_birth), '100%') !!}<span class="under">[DD-MMM-YYYY e.g. 01 JAN 1990]</span></span></div>
            <div class="row"><span>at</span><span style="width:2.2in;">{!! $line($d('birth_place'), '100%') !!}<span class="under">[as appearing in Philippine document]</span></span><span>to</span><span class="grow">{!! $line($d('father_name'), '100%') !!}<span class="under">[Father’s name]</span></span><span>,</span></div>
            <div class="row"><span style="width:2.6in;">{!! $line($d('father_citizenship'), '100%') !!}<span class="under">[Father’s citizenship at the time of petitioner’s birth]</span></span><span>citizen by {!! $choose($acquired, $d('father_acquired')) !!}, who was</span></div>
            <div class="row"><span>{!! $choose(['1' => 'married', '0' => 'not married'], $blankForm || ! $petition->exists ? null : ($petition->flag('parents_married') ? '1' : '0')) !!} to</span><span class="grow">{!! $line($d('mother_name'), '100%') !!}<span class="under">[Mother’s name]</span></span><span>,</span></div>
            <div class="row"><span style="width:2.6in;">{!! $line($d('mother_citizenship'), '100%') !!}<span class="under">[Mother’s citizenship at the time of petitioner’s birth]</span></span><span>citizen by {!! $choose($acquired, $d('mother_acquired')) !!};</span></div>
        </li>
        <li>
            <div class="row"><span>I lost my Philippine citizenship when I became a citizen of</span><span class="grow">{!! $line($d('lost_country'), '100%') !!}</span></div>
            <div class="row"><span>on</span>{!! $line($dd('lost_on'), '1.5in') !!}<span>. Presently, I am a holder of a valid</span><span class="grow">{!! $line($d('passport_country'), '100%') !!}</span><span>passport</span></div>
            <div class="row"><span>with number</span>{!! $line($c($client->passport_number), '1.4in') !!}<span>issued on</span><span style="width:1.6in;">{!! $line($dd('passport_issued_on'), '100%') !!}</span><span>at</span><span class="grow">{!! $line($d('passport_place'), '100%') !!}</span><span>;</span></div>
        </li>
        <li>
            <div class="row"><span>I took my Oath of Allegiance to the Republic of the Philippines on</span><span class="grow">{!! $line($dd('oath_date'), '100%') !!}</span></div>
            <div class="row"><span>at</span>{!! $line($d('oath_place'), '3in') !!}<span>;</span></div>
        </li>
    </ol>
    <div style="font-weight:bold;font-style:italic;">(If the petitioner is a registered alien with the Bureau of Immigration):</div>
    <ol class="items" start="4" style="margin-top:0;">
        <li>I surrender for cancellation my original Alien Certificate of Registration (ACR) no. {!! $line($alien ? $d('acr_number') : null, '1.7in') !!} and/or Immigrant Certificate of Residence/Certificate of Residence for Temporary Visitors (ICR/CRTV) no. {!! $line($alien ? $d('icr_number') : null, '1.9in') !!};</li>
    </ol>
    <div style="font-weight:bold;font-style:italic;">(If with Dependent/s):</div>
    <ol class="items" start="5" style="margin-top:0;">
        <li>Included in this petition is/are my unmarried child/ren below eighteen (18) years of age, namely:</li>
    </ol>
    <table class="dep" style="width:100%;border-collapse:collapse;">
        <tr><th style="width:52%;">Name</th><th style="width:12%;">Gender</th><th style="width:28%;">Date of Birth<br><span style="font-size:6.6pt;">[DD-MMM-YYYY e.g. 01 JAN 1990]</span></th><th>Age</th></tr>
        @foreach ([1, 2, 3] as $dependent)
            @php $dob = $blankForm ? null : $petition->dateDetail("dep_{$dependent}_dob"); @endphp
            <tr>
                <td>{!! $line($d("dep_{$dependent}_name"), '100%') !!}</td>
                <td>{!! $line($d("dep_{$dependent}_sex"), '100%') !!}</td>
                <td>{!! $line($date($dob), '100%') !!}</td>
                <td>{!! $line($dob?->age, '100%') !!}</td>
            </tr>
        @endforeach
    </table>
    <div style="text-align:center;font-style:italic;font-size:8.8pt;">(Please use BI Form 2014-01-006 Rev 0 for each dependent to be attached to this petition.)</div>

    <div style="display:flex;gap:0.4in;margin-top:8pt;">
        <div style="flex:1;">
            <p style="margin:0;text-align:justify;text-indent:0.3in;"><b>WHEREFORE</b>, it is prayed that this petition for Retention and/or Re-acquisition of Philippine Citizenship under Republic Act No. 9225 be GRANTED.</p>
            <div style="margin:0.32in 0 0 1.2in;text-align:center;">{!! $line($foreignName, '100%') !!}<div style="font-size:8.6pt;">Signature over Printed Name</div></div>
            <div style="margin:0.12in 0 0 1.2in;text-align:center;">{!! $line($blankForm ? null : $date($petition->filed_on ?? now()), '100%') !!}</div>
            <div style="font-size:8.6pt;">Date [DD-MMM-YYYY e.g. 01 JAN 1990]</div>
        </div>
        <div style="width:2.2in;height:2.1in;border:1.5px solid #000;padding:6pt;font-size:7.4pt;line-height:1.25;text-align:center;">
            Attach your 2x2 colored photograph <b>with white background</b> using permanent glue in the photograph box.<br><br>
            The photograph must be taken within the last three (3) months from the date of application.<br><br>
            A scanned photograph is not allowed. A photograph of the applicant wearing eyewear (i.e. sunglasses, colored contact lenses, etc.) or headwear is not acceptable.
        </div>
    </div>

    <div style="text-align:center;font-weight:bold;margin-top:8pt;">VERIFICATION</div>
    <p style="margin:2pt 0 0;text-indent:0.4in;font-size:9pt;">I certify under oath that the above statements are true and correct to my own personal knowledge, and based on authentic documents in my possession.</p>
    <div style="margin:0.22in 0 0 auto;width:3.2in;text-align:center;">{!! $line($foreignName, '100%') !!}<div style="font-size:8.6pt;">Applicant’s Signature over Printed Name</div></div>

    <p style="margin:8pt 0 0;text-indent:0.6in;font-size:9pt;">
        SUBSCRIBED AND SWORN TO before me this {!! $line(null, '1.1in') !!} in {!! $line(null, '1.7in') !!}, Petitioner personally appeared and exhibited to me his/her passport with number {!! $line($c($client->passport_number), '1.5in') !!} issued on {!! $line($dd('passport_issued_on'), '1.6in') !!} at {!! $line($d('passport_place'), '1.4in') !!}.
    </p>
    <div style="font-size:9pt;line-height:1.35;margin-top:2pt;">
        Doc No.: <span class="blank" style="min-width:0.8in;"></span><br>
        Page No.: <span class="blank" style="min-width:0.8in;"></span><br>
        Book No.: <span class="blank" style="min-width:0.8in;"></span><br>
        Series of <span class="blank" style="min-width:0.8in;"></span>
    </div>
    <div style="text-align:right;font-size:8.6pt;">Page <b>1</b> of <b>1</b></div>
</div>
</body>
</html>
