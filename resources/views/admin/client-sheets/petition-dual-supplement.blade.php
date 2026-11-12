@php
    use App\Models\ImmigrationPetition;

    /**
     * The BI's Supplement for Dependent (BI Form 2014-01-006 Rev 0), attached to the R.A. 9225
     * petition: one page for each unmarried child below 18 named in the dual citizenship case, on
     * 8.5 x 13 in paper. Blank, it prints one page to fill in by hand.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $date = fn ($value) => $value ? strtoupper($value->format('d M Y')) : null;
    $c = fn ($value) => $blankForm ? null : $value;
    $line = fn ($value, string $width) => '<span class="blank" style="min-width:'.$width.';">'.e((string) $value).'</span>';

    // The petitioner as named in the child's birth certificate: the name in the Philippine document.
    $petitioner = $blankForm ? null : strtoupper((string) ($petition->detail('name_ph') ?: $client->full_name));
    $age = $blankForm ? null : $client->date_of_birth?->age;
    $sexes = ['M' => 'Male', 'F' => 'Female'];

    $dependents = $blankForm
        ? [null]
        : collect(ImmigrationPetition::DEPENDENT_SLOTS)->filter(fn (int $n) => filled($petition->detail("dep_{$n}_name")))->values()->all();
    $seal = asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplement for Dependent{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 0.32in 0.62in 0 0.62in; font-family: Verdana, Tahoma, Arial, sans-serif; font-size: 9.6pt; line-height: 1.45; }
        .sheet + .sheet { page-break-before: always; }
        .blank { display: inline-block; border-bottom: 1px solid #000; text-align: center; line-height: 1.15; padding: 0 3px; font-family: Arial, sans-serif; font-weight: bold; font-size: 9pt; color: #0b2a6b; text-transform: uppercase; vertical-align: baseline; }
        .under { display: block; font-size: 6.6pt; font-style: italic; text-align: center; line-height: 1; margin-top: -1px; }
        .row { display: flex; align-items: flex-end; gap: 4px; }
        .row .grow { flex: 1; }
        .facts .row { margin-top: 0.12in; }
        ol.items { margin: 0.14in 0 0; padding-left: 0.28in; }
        ol.items > li { padding-left: 0.04in; margin-bottom: 0.1in; text-align: justify; }
        @media print { .blank { color: #000; } }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print supplement</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in), no margins, 100% scale. One page per dependent.</span>
</div>

@forelse ($dependents as $n)
    @php
        $dep = fn (string $key) => $n === null ? null : $petition->detail("dep_{$n}_{$key}");
        $dob = $n === null ? null : $petition->dateDetail("dep_{$n}_dob");
        $issued = $n === null ? null : $petition->dateDetail("dep_{$n}_passport_issued_on");
    @endphp
    <div class="sheet">
        <div style="display:flex;gap:12pt;align-items:flex-start;">
            <img src="{{ $seal }}" alt="Bureau of Immigration seal" style="width:52pt;height:52pt;object-fit:contain;">
            <div style="flex:1;line-height:1.2;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;">
                    <b style="font-size:11pt;">BI FORM 2014-01-006 Rev 0</b>
                    <span style="font-size:7pt;">This document may be reproduced and is <b>NOT FOR SALE</b></span>
                </div>
                <div style="font-weight:bold;font-size:12.6pt;">SUPPLEMENT FOR DEPENDENT</div>
            </div>
        </div>

        <div style="text-align:center;font-weight:bold;font-size:15pt;margin:0.22in 0 0.1in;">SUPPLEMENT FOR DEPENDENT</div>

        <div class="row" style="padding-left:0.5in;"><span>I,</span><span class="grow">{!! $line($petitioner, '100%') !!}<span class="under">[Full name of the Petitioner as appearing in the Dependent’s Birth Certificate]</span></span><span>,</span></div>
        <div class="row">{!! $line($age, '0.55in') !!}<span>years of age, a citizen of</span><span class="grow">{!! $line($c($client->nationality), '100%') !!}</span><span>and a resident of</span></div>
        <div class="row"><span class="grow">{!! $line($c($client->address), '100%') !!}</span><span>with contact number</span></div>
        <div class="row">{!! $line($c($client->mobile_number), '1.6in') !!}<span>, after being sworn to in accordance with law, hereby declare under oath that:</span></div>

        <ol class="items">
            <li>I am the petitioner in the above Petition for Retention/Re-acquisition of Philippine Citizenship under Republic Act No. 9225;</li>
            <li>I am including in my petition my unmarried child below eighteen (18) years of age pursuant to Section 4 of R.A. 9225, to wit:</li>
        </ol>

        <div style="width:2.2in;height:2.2in;border:1.5px solid #000;padding:6pt;font-size:7.4pt;line-height:1.25;text-align:center;margin:0.1in auto 0;">
            Attach your 2x2 colored photograph <b>with white background</b> using permanent glue in the photograph box.<br><br>
            The photograph must be taken within the last three (3) months from the date of application.<br><br>
            A scanned photograph is not allowed. A photograph of the applicant wearing eyewear (i.e. sunglasses, colored contact lenses, etc.) or headwear is not acceptable.
        </div>

        <div class="facts" style="padding-left:0.32in;margin-top:0.12in;">
            <div class="row"><span>Name :</span><span class="grow">{!! $line($dep('name'), '100%') !!}<span class="under">[as appearing in the Birth Certificate]</span></span></div>
            <div class="row"><span>Also known as:</span><span class="grow">{!! $line($dep('aka'), '100%') !!}<span class="under">[as appearing in the foreign passport]</span></span></div>
            <div class="row"><span>Date of Birth:</span>{!! $line($date($dob), '1.9in') !!}<span>Age:</span>{!! $line($dob?->age, '0.8in') !!}<span>Gender:</span><span class="grow">{!! $line($sexes[$dep('sex')] ?? null, '100%') !!}</span></div>
            <div class="row"><span>Place of Birth:</span><span class="grow">{!! $line($dep('birth_place'), '100%') !!}</span><span>Civil Status:</span>{!! $line($n === null ? null : ($dep('civil_status') ?: 'Single'), '1.6in') !!}</div>
            <div class="row"><span>Nationality:</span><span class="grow">{!! $line($dep('nationality'), '100%') !!}</span><span>Valid Passport No.:</span>{!! $line($dep('passport'), '1.7in') !!}</div>
            <div class="row"><span>Date of Issuance:</span><span class="grow">{!! $line($date($issued), '100%') !!}</span><span>Place of Issuance:</span>{!! $line($dep('passport_place'), '1.8in') !!}</div>
            <div class="row"><span>Father’s Name:</span><span class="grow">{!! $line($dep('father'), '100%') !!}</span></div>
            <div class="row"><span>Mother’s Name:</span><span class="grow">{!! $line($dep('mother'), '100%') !!}</span></div>
        </div>

        <ol class="items" start="3" style="margin-top:0.3in;">
            <li>I surrender for cancellation his/her original Alien Certificate of Registration (ACR) No. {!! $line($dep('acr'), '1.5in') !!} and Immigrant Certificate of Residence/ Certificate of Residence for Temporary Visitors (ICR/CRTV) No. {!! $line($dep('icr'), '1.6in') !!}.</li>
        </ol>

        <div style="margin:0.6in 0 0 auto;width:3.6in;text-align:center;">{!! $line($petitioner, '100%') !!}<div style="font-style:italic;font-size:9pt;">(Signature over Printed Name of the Petitioner)</div></div>

        <div style="position:absolute;right:0.62in;bottom:0.45in;font-size:8.6pt;">Page <b>1</b> of <b>1</b></div>
    </div>
@empty
    <div class="sheet" style="display:flex;align-items:center;justify-content:center;text-align:center;font-family:Arial, sans-serif;">
        <p>No dependent is named in this petition. Add the child in the Dependents part of the Dual Citizenship folder, then print again.</p>
    </div>
@endforelse
@unless ($blankForm)
    @include('admin.client-sheets._na-fill')
@endunless
</body>
</html>
