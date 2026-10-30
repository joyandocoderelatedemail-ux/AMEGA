<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ['termination' => 'Checklist - Termination of Membership', 'restamping' => 'Checklist - Re-stamping of SRRV'][$file->type] ?? 'Checklist - Conversion of Visa Deposit' }} - {{ $file->retiree_name }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; color: #111; margin: 0; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 2mm; }
        .sub { text-align: center; font-size: 9pt; margin-bottom: 4mm; }
        .box { border: 1px solid #000; margin-bottom: 4mm; }
        .box h2 { background: #e6e6e6; font-size: 10.5pt; margin: 0; padding: 1.5mm 2.5mm; border-bottom: 1px solid #000; }
        .row { display: flex; gap: 2.5mm; padding: 1.2mm 2.5mm; align-items: flex-start; }
        .tick { width: 4.2mm; height: 4.2mm; border: 1px solid #000; border-radius: 1mm; flex: none; text-align: center; line-height: 4.2mm; font-weight: bold; }
        .na { color: #999; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 1mm 6mm; font-size: 9.5pt; margin-bottom: 4mm; }
        .foot { font-size: 8pt; text-align: right; margin-top: 4mm; }
        .print { position: fixed; top: 8px; right: 8px; padding: 6px 12px; }
        @media print { .print { display: none; } }
    </style>
</head>
<body onload="window.print()">
@php
    $received = $file->checklist ?? [];
    $sections = collect($file->requirements())->groupBy(fn ($item) => $item[1], true);
@endphp
<button class="print" onclick="window.print()">Print</button>

@if ($file->type === 'termination')
    <h1>CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR TERMINATION OF MEMBERSHIP</h1>
    <div class="sub">PRA-SD-FORM-0008 &middot; Issue No. 0004 &middot; July 2025</div>
@elseif ($file->type === 'restamping')
    <h1>CHECKLIST OF REQUIREMENTS FOR THE RE-STAMPING OF THE SPECIAL RESIDENT RETIREE'S VISA</h1>
    <div class="sub">Transfer of the SRRV sticker to the new passport &middot; PRA Citizen's Charter 2025, Servicing Division</div>
@else
    <h1>CONVERSION OF VISA DEPOSIT INTO AN ACTIVE INVESTMENT THRU SUBLEASE OF RESIDENTIAL PROPERTY</h1>
    <div class="sub">Checklist of Requirements &middot; PRA-SD-FORM-0010 &middot; Issue No. 0002 &middot; October 2022</div>
@endif

<div class="meta">
    <div><b>Retiree:</b> {{ $file->retiree_name }}</div>
    <div><b>SRRV No.:</b> {{ $file->srrv_card_number ?: '________________' }}</div>
    <div><b>Scheme:</b> {{ $file->visa_class ? 'SRRV '.ucfirst($file->visa_class) : '________________' }}</div>
    <div><b>Reference:</b> {{ $file->reference }}</div>
    @if ($file->type === 'termination')
        <div><b>Members:</b> {{ $file->detail('members', 1) }}</div>
        <div><b>Visa:</b> {{ $file->detail('with_downgrading') ? "Cancellation with downgrading to TVV (59 days)" : 'Cancellation of SRRVisa' }}</div>
    @elseif ($file->type === 'restamping')
        <div><b>New passport:</b> {{ $file->detail('new_passport_number') }}</div>
        <div><b>Old passport:</b> {{ $file->detail('old_passport_number') ?: '________________' }}</div>
        <div><b>Members:</b> {{ $file->detail('members', 1) }}</div>
    @else
        <div><b>Sublease:</b> US$ {{ number_format((float) $file->detail('sublease_amount'), 2) }}, {{ $file->detail('sublease_years') }} years</div>
        <div><b>Property:</b> {{ $file->detail('property_address') }}</div>
    @endif
</div>

@foreach ($sections as $section => $items)
    <div class="box">
        <h2>{{ strtoupper($section) }}</h2>
        @foreach ($items as $key => [$text, , $applies])
            <div class="row {{ $applies ? '' : 'na' }}">
                <span class="tick">{{ $applies && in_array($key, $received, true) ? '✓' : '' }}</span>
                <span>{{ $text }}{{ $applies ? '' : ' (not applicable)' }}</span>
            </div>
        @endforeach
    </div>
@endforeach

@if ($file->hasFees())
    <div class="box">
        <h2>BREAKDOWN OF FEES</h2>
        <div class="row"><span>PRA Service Fee: <b>US$ {{ number_format((float) $file->fee_usd, 2) }}</b> &nbsp;+&nbsp; Bureau of Immigration Fee: <b>₱{{ number_format((float) $file->fee_php, 2) }}</b> ({{ $file->detail('members', 1) }} member{{ $file->detail('members', 1) > 1 ? 's' : '' }})</span></div>
    </div>
@else
    <div class="row"><span>Evaluated by: ______________________ &nbsp; Date completed: ______________ &nbsp; Ocular inspection date: {{ $file->detail('ocular_inspection_on') ?: '______________' }}</span></div>
@endif

<div class="foot">Prepared by AMEGA Travel and Tours Services for submission to the Philippine Retirement Authority</div>
</body>
</html>
