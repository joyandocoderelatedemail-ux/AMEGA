@php
    /**
     * The Data Privacy Consent Form as a PDF for the client's email. Same
     * wording as consent/data-privacy, laid out with tables for dompdf.
     *
     * @var \App\Support\DataPrivacyConsent $consent
     */
    use App\Support\DataPrivacyConsent;

    $services = DataPrivacyConsent::SERVICES;

    // The paper form's grid: two services per row, passporting across the last.
    $rows = [
        ['ticket', 'package'],
        ['immigration', 'visa'],
        ['documentation', 'day_tour'],
        ['passport'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Data Privacy Consent Form - {{ $consent->clientName }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 20mm 28mm; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            color: #1F2937;
            font-size: 10pt;
            line-height: 1.5;
        }

        table { border-collapse: collapse; }

        .letterhead { width: 100%; }
        .letterhead td { vertical-align: middle; padding: 0; }
        .letterhead img { height: 42px; }
        .letterhead .tagline {
            font-size: 8pt;
            font-style: italic;
            color: #003B95;
            border-left: 1.5px solid #003B95;
            padding-left: 10px;
            white-space: nowrap;
        }
        .reference { font-size: 7.5pt; color: #6B7280; text-align: right; }

        h1 { text-align: center; font-size: 13pt; margin: 18mm 0 8mm; }

        p { margin: 0 0 6mm; text-align: justify; }

        table.services { width: 100%; margin: 0 0 8mm; }
        table.services td { border: 1px solid #111827; padding: 5px 9px; vertical-align: middle; }
        table.services td.box { width: 9mm; text-align: center; padding: 4px; }
        .mark {
            display: inline-block;
            width: 4.4mm;
            height: 4.4mm;
            border: 1.1px solid #111827;
            text-align: center;
            line-height: 4.1mm;
            font-size: 11pt;
            font-weight: bold;
            color: #003B95;
        }

        .blank { display: inline-block; border-bottom: 1px solid #111827; width: 18mm; height: 1em; }

        .signatures { width: 100%; margin-top: 22mm; }
        .signatures td { width: 50%; vertical-align: bottom; padding: 0 7mm; }
        .signatures .line {
            border-bottom: 1px solid #111827;
            min-height: 8mm;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            padding-bottom: 1mm;
        }
        .signatures .caption { text-align: center; margin-top: 2mm; }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -20mm;
            text-align: center;
            font-size: 7.5pt;
            color: #4B5563;
            line-height: 1.45;
            padding-top: 4mm;
            border-top: 1px solid #E5E7EB;
        }
    </style>
</head>
<body>

<div class="footer">
    Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
    +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703<br>
    sales@amegatravelandtours.com<br>
    www.amegatravelandtours.com &nbsp;|&nbsp; Facebook: @AmegaTravel
</div>

<table class="letterhead">
    <tr>
        @if ($logo)
            <td style="width: 1%; padding-right: 10px;"><img src="{{ $logo }}" alt="Amega Travel and Tours Services"></td>
        @endif
        <td class="tagline">Endless Possibilities in Travel and Tourism</td>
        <td class="reference">{{ $consent->reference }}</td>
    </tr>
</table>

<h1>Data Privacy Consent Form</h1>

<p>
    In compliance with the Data Privacy Act of 2012, and its Implementing Rules and Regulation (IRR)
    effective since September 08, 2016, I give Amega Travel and Tours Services consent to collect,
    process, use, and store my information exclusively in relation to my transaction/s stated below:
</p>

<table class="services">
    @foreach ($rows as $row)
        <tr>
            @foreach ($row as $service)
                <td class="box"><span class="mark">{{ $consent->isTicked($service) ? '✓' : '' }}</span></td>
                <td @if (count($row) === 1) colspan="3" @endif>{{ $services[$service] }}</td>
            @endforeach
        </tr>
    @endforeach
</table>

<p>
    I acknowledge and warrant that consent from all parties whose information shared to Amega Travel
    and Tours Services in relation to the transaction above was obtained. I hold Amega free and harmless
    from any liability arising out of or from violation of any law including the Data Privacy Act of 2012.
</p>

<p>
    Signed this <span class="blank"></span> day of <span class="blank" style="width: 32mm;"></span>
    20<span class="blank" style="width: 8mm;"></span> at Angeles City, Pampanga, Philippines.
</p>

<table class="signatures">
    <tr>
        <td>
            <div class="line">{{ $consent->clientName }}</div>
            <div class="caption">Client</div>
        </td>
        <td>
            <div class="line">{{ $handlingAgent }}</div>
            <div class="caption">Handling Agent</div>
        </td>
    </tr>
</table>

</body>
</html>
