<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visa Waiver - {{ $waiver->clientName }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 18mm 26mm; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            color: #1F2937;
            font-size: 9pt;
            line-height: 1.35;
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
        .reference { font-size: 7.5pt; color: #6B7280; text-align: right; white-space: nowrap; width: 1%; }

        h1 { text-align: center; font-size: 13pt; margin: 6mm 0 1mm; }
        .subtitle { text-align: center; font-size: 8pt; color: #4B5563; margin: 0 0 4mm; }

        p { margin: 0 0 3mm; text-align: justify; }

        table.passengers { width: 100%; margin: 0 0 4mm; font-size: 8.5pt; }
        table.passengers th, table.passengers td { border: 1px solid #111827; padding: 2px 6px; text-align: left; vertical-align: middle; }
        table.passengers th { background: #F3F4F6; font-size: 7.5pt; text-transform: uppercase; }

        ol.terms { margin: 0 0 4mm; padding-left: 6mm; }
        ol.terms li { margin: 0 0 1.5mm; text-align: justify; }

        .blank { display: inline-block; border-bottom: 1px solid #111827; width: 18mm; height: 1em; }

        .signatures { width: 100%; margin-top: 10mm; }
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
    sales@amegatravelandtour.com<br>
    www.amegatravelandtour.com &nbsp;|&nbsp; Facebook: @AmegaTravel
</div>

<table class="letterhead">
    <tr>
        @if ($logo)
            <td style="width: 1%; padding-right: 10px;"><img src="{{ $logo }}" alt="Amega Travel and Tours Services"></td>
        @endif
        <td class="tagline">Endless Possibilities in Travel and Tourism</td>
        <td class="reference">Visa Waiver · {{ $waiver->reference }}</td>
    </tr>
</table>

<h1>Visa Waiver and Acknowledgment</h1>
<p class="subtitle">Booking made before the visa is approved</p>

@include('ticketing.waivers._body', ['waiver' => $waiver])

{{-- Kept together, so a long list of passengers never strands the signatures on a page of their own. --}}
<div style="page-break-inside: avoid;">
<p>
    Signed this <span class="blank"></span> day of <span class="blank" style="width: 32mm;"></span>
    20<span class="blank" style="width: 8mm;"></span> at Angeles City, Pampanga, Philippines.
</p>

<table class="signatures">
    <tr>
        <td>
            <div class="line">{{ $waiver->clientName }}</div>
            <div class="caption">Client</div>
        </td>
        <td>
            <div class="line">{{ $waiver->handlingAgent }}</div>
            <div class="caption">Handling Agent</div>
        </td>
    </tr>
</table>
</div>

</body>
</html>
