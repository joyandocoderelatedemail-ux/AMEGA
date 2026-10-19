@php
    use App\Models\ImmigrationPetition;

    /**
     * The counter's ECC checklist: what the client brings for an Emigration Clearance
     * Certificate, ticked from what is in hand. This is AMEGA's own list, so it carries
     * the agency's letterhead rather than a BI form number.
     */
    $blankForm = $blank;
    $d = fn (string $key) => $blankForm ? null : $petition->detail($key);
    $required = $blankForm ? ImmigrationPetition::CHECKLISTS['ecc'] : $petition->requiredItems();
    $tick = fn (string $item) => ! $blankForm && $petition->hasChecked($item) ? '✓' : '';
    $name = $blankForm ? '' : mb_strtoupper(trim($client->full_name ?: collect([$client->given_name, $client->last_name])->filter()->implode(' ')));
    $departure = $blankForm ? null : $petition->dateDetail('departure_date');
    $logo = asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ECC Checklist{{ $blankForm ? '' : ' - '.$client->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        .sheet { padding: 44pt 54pt 0; font-family: Arial, Helvetica, sans-serif; font-size: 10.4pt; line-height: 1.4; }
        .chk { display: inline-flex; width: 15pt; height: 15pt; border: 1.4px solid #000; align-items: center; justify-content: center; font-weight: bold; font-size: 11pt; color: #0b2a6b; flex: 0 0 auto; }
        .ln { display: inline-block; border-bottom: 0.75px solid #000; font-weight: bold; color: #0b2a6b; vertical-align: bottom; padding: 0 4pt; }
        table { border-collapse: collapse; width: 100%; }
        td { border: 1px solid #000; padding: 7pt 8pt; vertical-align: middle; }
        @media print { .chk, .ln { color: #000; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print checklist</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">Long bond paper (8.5 &times; 13 in), 100% scale.</span>
</div>

<div class="sheet">
    <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #003B95;padding-bottom:10pt;">
        <img src="{{ $logo }}" alt="AMEGA Travel and Tours" style="height:52pt;object-fit:contain;">
        <div style="text-align:right;font-size:8.6pt;color:#40506B;line-height:1.35;">Immigration Services<br>amegatravelandtour.com</div>
    </div>

    <div style="text-align:center;margin-top:22pt;">
        <div style="font-weight:bold;font-size:14pt;">CHECKLIST OF REQUIREMENTS</div>
        <div style="font-weight:bold;font-size:11.4pt;margin-top:2pt;">EMIGRATION CLEARANCE CERTIFICATE (ECC)</div>
    </div>

    <div style="display:grid;grid-template-columns:auto 1fr auto 1fr;gap:10pt 8pt;align-items:end;margin-top:24pt;">
        <span>Name:</span><span class="ln">{{ $name }}</span>
        <span>Passport No.:</span><span class="ln">{{ $blankForm ? '' : $client->passport_number }}</span>
        <span>Nationality:</span><span class="ln">{{ $blankForm ? '' : $client->nationality }}</span>
        <span>Departure:</span><span class="ln">{{ $departure ? strtoupper($departure->format('d M Y')) : '' }}{{ $d('destination') ? ' · '.$d('destination') : '' }}</span>
        @unless ($blankForm)
            <span>Reference:</span><span class="ln">{{ $petition->exists ? $petition->reference() : '' }}</span>
        @endunless
    </div>

    <div style="font-weight:bold;margin:26pt 0 8pt;">Required Documents:</div>
    <table>
        @foreach (ImmigrationPetition::CHECKLISTS['ecc'] as $item => $itemLabel)
            <tr>
                <td style="width:30pt;text-align:center;"><span class="chk">{{ $tick($item) }}</span></td>
                <td>{{ $itemLabel }}@if (! $blankForm && ! array_key_exists($item, $required)) <span style="font-size:8.6pt;color:#666;">(not applicable)</span>@endif</td>
            </tr>
        @endforeach
    </table>
    <p style="font-size:8.8pt;color:#40506B;margin-top:10pt;">The ACR I-Card copy applies to holders of an ACR I-Card. Bring the originals for comparison.</p>

    <div style="display:flex;justify-content:space-between;gap:40pt;margin-top:60pt;text-align:center;font-size:9pt;">
        <div style="flex:1;"><div class="ln" style="display:block;min-height:14pt;">{{ $name }}</div>Client’s Signature over Printed Name</div>
        <div style="flex:1;"><div class="ln" style="display:block;min-height:14pt;"></div>Received by / Date</div>
    </div>
</div>
</body>
</html>
