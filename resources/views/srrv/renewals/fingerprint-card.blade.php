@php
    /**
     * PRA-SD-FORM-0016 (Issue No. 0002, October 2024): the SRRV holder's fingerprint and signature card, filled from the
     * renewal. Expects $renewal. The impressions and the signature are taken on the printed card.
     */
    $detail = fn (string $key) => $renewal->detail($key);
    $sex = ['M' => 'Male', 'F' => 'Female'][$detail('gender')] ?? $detail('gender');
    $takenOn = $detail('impressions_on') ? \Illuminate\Support\Carbon::parse($detail('impressions_on'))->format('M d, Y') : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fingerprint Card - {{ $renewal->reference }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #E9EDF3; font-family: Arial, Helvetica, sans-serif; color: #000; }
        .card { width: 100mm; margin: 18px auto; padding: 8mm 7mm 6mm; background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .12); }
        .seal { text-align: center; }
        .seal img { width: 18mm; height: 18mm; }
        .foot img { width: 7mm; height: 7mm; }
        .rule { border-top: 1.5px solid #a0685a; border-bottom: 1.5px solid #a0685a; height: 3px; margin: 3mm 0; }
        .field { display: flex; align-items: flex-end; gap: 2mm; font-size: 9.5pt; font-weight: bold; margin: 2mm 0; }
        .field span { flex: 1; border-bottom: 1px solid #555; min-height: 5mm; font-weight: normal; padding-left: 1mm; }
        .hands { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
        .hands h3 { text-align: center; font-size: 9.5pt; margin: 1mm 0; }
        .box { border: 1.2px solid #a0685a; }
        .box .label { border-bottom: 1.2px solid #a0685a; text-align: center; font-size: 7.5pt; font-weight: bold; padding: 1.5mm 1mm; min-height: 9mm; display: flex; align-items: center; justify-content: center; }
        .box .print { height: 30mm; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .signature { border: 1.2px solid #a0685a; margin-top: 4mm; padding: 1.5mm 2mm; }
        .signature b { font-size: 9pt; }
        .signature small { display: block; font-size: 6.5pt; color: #888; font-style: italic; }
        .signature .print { height: 16mm; display: flex; align-items: center; justify-content: center; }
        .officer-note { font-size: 7pt; font-style: italic; color: #a0685a; margin: 3mm 0 1mm; }
        table { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        td { border: 1.2px solid #a0685a; vertical-align: top; padding: 1mm 1.5mm; height: 11mm; }
        td b { display: block; text-align: center; margin-bottom: 1mm; }
        .taken { border: 1.2px solid #a0685a; margin-top: 3mm; padding: 1.5mm 2mm; min-height: 16mm; font-size: 8.5pt; }
        .taken .row { display: flex; justify-content: space-between; font-weight: bold; }
        .taken small { display: block; font-size: 6.5pt; color: #888; font-style: italic; }
        .foot { display: flex; gap: 4mm; font-size: 7pt; margin-top: 3mm; color: #333; }
        .toolbar { width: 100mm; margin: 18px auto 0; display: flex; gap: 10px; }
        .toolbar button, .toolbar a { font: bold 12px Arial, sans-serif; padding: 9px 16px; border-radius: 8px; border: 0; background: #003B95; color: #fff; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #fff; color: #003B95; border: 1px solid #003B95; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .card { margin: 0 auto; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print card</button>
    <a href="{{ route('srrv.renewals.edit', [$renewal, 'step' => 4]) }}">Back to renewal</a>
</div>

<div class="card">
    <div class="seal"><img src="{{ asset('images/pra-seal.png') }}" alt="Philippine Retirement Authority"></div>
    <div class="rule"></div>
    <div class="field">Name: <span>{{ $renewal->retiree_name }}</span></div>
    <div class="field">SRRV Number: <span>{{ $renewal->srrv_card_number }}</span></div>
    <div class="rule"></div>

    <div class="hands">
        <h3>LEFT HAND</h3>
        <h3>RIGHT HAND</h3>
        @foreach (['1. Left Fore Finger (Index Finger)', '2. Right Fore Finger (Index Finger)', '3. Left Thumb', '4. Right Thumb'] as $label)
            <div class="box">
                <div class="label">{{ $label }}</div>
                <div class="print"></div>
            </div>
        @endforeach
    </div>

    <div class="signature">
        <b>Signature of SRRV Holder</b>
        <small>(or of the Principal/Guardian, in case of a minor SRRV Holder)</small>
        <div class="print"></div>
    </div>

    <div class="officer-note">This section is to be filled out by Officer taking the impressions</div>
    <table>
        <tr>
            <td style="width: 22%"><b>Height</b>{{ $detail('height') }}</td>
            <td style="width: 22%"><b>Eye Color</b>{{ $detail('eye_color') }}</td>
            <td rowspan="2"><b>Distinguishing Facial Marks, if any</b>{{ $detail('facial_marks') }}</td>
        </tr>
        <tr>
            <td><b>Sex</b>{{ $sex }}</td>
            <td><b>Blood Type</b>{{ $detail('blood_type') }}</td>
        </tr>
    </table>

    <div class="taken">
        <div class="row"><span>Impressions taken by:</span><span>Date: <span style="font-weight: normal">{{ $takenOn }}</span></span></div>
        <small>(Name &amp; signature of facilitating PRA officer)</small>
        <div style="margin-top: 2mm">{{ $detail('impressions_by') }}</div>
    </div>

    <div class="foot" style="align-items: center"><img src="{{ asset('images/pra-seal.png') }}" alt=""><b>PRA-SD-FORM-0016</b><span>Issue No. 0002</span><span>Issue Date October 2024</span></div>
</div>

</body>
</html>
