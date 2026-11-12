@php
    use App\Support\ImmigrationServices;

    /**
     * The joint letter request to the BI Commissioner from the applicant and the petitioning
     * Filipino spouse (item 1 of the 13(A) checklists): a conversion to the probationary
     * spousal visa, or an amendment from probationary to permanent. Signed by both and sworn
     * before a notary. A permanent case adds page 2, the Joint Affidavit of Continuous
     * Cohabitation. Blank, it is a template with lines to fill in by hand.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $line = fn (?string $value, string $width = '160pt') => filled($value)
        ? e($value)
        : '<span class="na" style="display:inline-block;width:'.$width.';border-bottom:0.75px solid #000;"></span>';
    $upper = fn ($value) => filled($value) ? mb_strtoupper(trim((string) $value)) : null;

    $permanent = $petition->type === 'permanent';
    $signedOn = $blankForm ? null : ($petition->filed_on ?? now());

    $applicant = $blankForm ? null : $upper(collect([$client->given_name, $d('middle_name'), $client->last_name])->filter()->implode(' '));
    // The permanent letter names the applicant surname first, as the BI files them: SAWYER JR, GEORGE W.
    $applicantFormal = $blankForm ? null : $upper(trim($client->last_name.', '.collect([$client->given_name, $d('middle_name')])->filter()->implode(' '), ', '));
    $named = $permanent ? $applicantFormal : $applicant;
    $address = $blankForm ? null : $upper($client->address);
    $petitioner = $upper(collect([$d('petitioner_first'), $d('petitioner_middle'), $d('petitioner_last')])->filter()->implode(' '));
    $nationality = $blankForm ? null : (filled($client->nationality) ? mb_convert_case(trim($client->nationality), MB_CASE_TITLE) : null);

    // Husband or wife: the applicant's sex on the case, else the client's profile.
    $sex = $d('sex') ?: ['male' => 'M', 'female' => 'F'][strtolower((string) ($profile?->gender ?? ''))] ?? null;
    $applicantRole = ['M' => 'husband', 'F' => 'wife'][$sex] ?? null;
    $petitionerRole = ['M' => 'Wife', 'F' => 'Husband'][$sex] ?? 'Spouse';
    $his = ['M' => 'his', 'F' => 'her'][$sex] ?? 'his/her';

    $date = fn ($value) => $value ? strtoupper($value->format('d M Y')) : null;
    $passportValid = $blankForm ? null : ($petition->dateDetail('passport_expires_on') ?? $profile?->passport_expiry);
    $idValid = $blankForm ? null : $petition->dateDetail('petitioner_id_valid_until');
    $idType = $d('petitioner_id_type');

    $fromStatus = $upper($d('present_status'));
    $title = $permanent
        ? 'JOINT REQUEST FOR AMENDMENT OF STATUS FROM PROBATIONARY TO PERMANENT VISA (UNDER SEC. 13 (A))'
        : 'JOINT REQUEST FOR CONVERSION OF STATUS FROM '.($fromStatus ?: 'TEMPORARY VISITOR’S VISA').' TO PROBATIONARY SPOUSAL VISA (UNDER SEC. 13 (A))';
    $city = ImmigrationServices::SIGNING_CITY;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Joint Letter Request{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet + .sheet { page-break-before: always; }
        .sheet { padding: 80pt 66pt 0 66pt; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.2; }
        .sheet p { margin: 0 0 13pt; text-align: justify; }
        .indent { text-indent: 36pt; }
        .block { padding-left: 36pt; }
        .sig { margin-left: 3.55in; line-height: 1.2; }
        .sig .role { padding-left: 30pt; }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print letter</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in). Signed by both spouses and notarized.</span>
</div>

<div class="sheet">
    <p>Date:&nbsp; {!! $line($date($signedOn), '110pt') !!}</p>

    <p style="margin-top:20pt;text-align:left;">To: {{ ImmigrationServices::BI_COMMISSIONER }}<br>Commissioner<br>Bureau of Immigration<br>Manila</p>

    <p>Sir:</p>

    <p class="block" style="margin-top:22pt;text-align:left;">{{ $title }}</p>

    <p class="block" style="text-align:left;">
        RE: {!! $line($named, '200pt') !!}, {!! $line($nationality, '80pt') !!} Citizen<br>
        DOB: {!! $line($blankForm ? null : $date($client->date_of_birth), '90pt') !!}<br>
        Passport #: {!! $line($blankForm ? null : $client->passport_number, '110pt') !!}
    </p>

    @if ($permanent)
        <p class="indent">On behalf of one of us ({!! $line($applicantRole, '50pt') !!}) {!! $line($named, '200pt') !!}, {!! $line($nationality, '70pt') !!} citizen, who intends to reside permanently in the Philippines, may we have the honor to request that {{ $his }} probationary residence granted under Sec. 13 (a) be formally amended to permanent status;</p>
        <p class="indent">Kindly be informed that we are living together as husband and wife without any impediment since our marriage and we are financially capable of supporting ourselves, hence we will not become public burden.</p>
    @else
        <p class="indent">In behalf of one of us ({!! $line($applicantRole, '50pt') !!}), {!! $line($applicant, '200pt') !!}, {!! $line($nationality, '70pt') !!} citizen, who intends to reside permanently in the Philippines, may we have the honor to request that {{ $his }} {!! $line($fromStatus, '150pt') !!} be formally CONVERTED to Sec. 13-A Probationary Residence Spousal Visa;</p>
        <p class="indent">Kindly be informed that we are living together as husband and wife without any impediment since our marriage and we are financially capable of supporting ourselves, hence we will not become public burden.</p>
    @endif

    <p class="indent">Attached herewith are true and genuine documents, which were legally obtained and secured from the government agency or entity issuing the same for ready reference.</p>

    @include('admin.client-sheets._joint-signatures')
</div>

@if ($permanent)
    {{-- Page 2: the joint affidavit the permanent checklist asks for --}}
    <div class="sheet">
        <p>Date:&nbsp; {!! $line($date($signedOn), '110pt') !!}</p>

        <p style="text-align:center;margin:26pt 0 30pt;">JOINT AFFIDAVIT OF CONTINUOUS COHABITATION</p>

        <p class="indent">WE, {!! $line($applicant, '170pt') !!} and {!! $line($petitioner, '170pt') !!}, both of legal age, {!! $line($nationality, '70pt') !!} and Filipino citizens, respectively and are resident of {!! $line($address, '260pt') !!} after being duly sworn to in accordance with law, depose and say:</p>
        <p class="indent">That we are husband and wife lawfully married in accordance with law and one of us ({!! $line($applicantRole, '50pt') !!}) is a holder of probationary residence visa under Sec. 13 (A), now subject for amendment of status to permanent visa under Sec. 13 (a);</p>
        <p class="indent">That since the time of our marriage, we have been living together as husband and wife without any legal impediment and we are looking forward for a better future;</p>
        <p class="indent">That we hereby attest that we are financially capable of supporting ourselves here in the Philippines for we have sufficient income for our financial needs, so we shall not become public burden;</p>
        <p class="indent">That we are executing this sworn affidavit to attest the foregoing facts and for other lawful purposes.</p>
        <p style="text-align:left;padding-left:2.7in;">Further affiants sayeth none.</p>

        <div style="height:30pt;"></div>
        @include('admin.client-sheets._joint-signatures')
    </div>
@endif
@unless ($blankForm)
    @include('admin.client-sheets._na-fill')
@endunless
</body>
</html>
