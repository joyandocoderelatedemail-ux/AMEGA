@php
    /** @var \App\Support\VisaWaiver $waiver */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visa Waiver - {{ $waiver->clientName }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #E9EDF3;
            font-family: "Open Sans", "Segoe UI", Arial, Helvetica, sans-serif;
            color: #1F2937;
            font-size: 10.5pt;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 16mm 20mm 12mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
            display: flex;
            flex-direction: column;
        }

        /* ---- letterhead (same as the Data Privacy Consent Form) ---- */
        .letterhead { display: flex; align-items: center; gap: 12px; }
        .letterhead img { height: 46px; display: block; }
        .letterhead .tagline {
            font-size: 9pt;
            font-style: italic;
            color: #003B95;
            border-left: 1.5px solid #003B95;
            padding-left: 12px;
        }
        .reference { margin-left: auto; font-size: 8pt; color: #6B7280; text-align: right; }

        h1 { text-align: center; font-size: 15pt; font-weight: bold; margin: 12mm 0 3mm; }
        .subtitle { text-align: center; font-size: 9pt; color: #4B5563; margin: 0 0 8mm; }

        p { margin: 0 0 5mm; text-align: justify; }

        table.passengers { width: 100%; border-collapse: collapse; margin: 0 0 6mm; font-size: 9.5pt; }
        table.passengers th, table.passengers td { border: 1px solid #111827; padding: 4px 8px; text-align: left; vertical-align: middle; }
        table.passengers th { background: #F3F4F6; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .03em; }

        ol.terms { margin: 0 0 6mm; padding-left: 6mm; }
        ol.terms li { margin: 0 0 2.4mm; text-align: justify; }

        .signed-line { margin: 2mm 0 0; text-align: left; white-space: nowrap; }
        .blank { display: inline-block; border-bottom: 1px solid #111827; min-width: 18mm; }
        .blank.wide { min-width: 32mm; }
        .blank.short { min-width: 8mm; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 14mm; margin-top: 16mm; }
        .signatures .line {
            border-bottom: 1px solid #111827;
            min-height: 8mm;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            padding-bottom: 1mm;
        }
        .signatures .caption { text-align: center; margin-top: 2mm; }

        .footer {
            margin-top: auto;
            text-align: center;
            font-size: 8.5pt;
            color: #4B5563;
            line-height: 1.45;
            padding-top: 6mm;
            border-top: 1px solid #E5E7EB;
        }

        /* ---- screen-only toolbar ---- */
        .toolbar {
            width: 210mm;
            margin: 18px auto 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar a, .toolbar button {
            font-family: inherit;
            font-size: 12px;
            font-weight: bold;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #003B95;
            background: #003B95;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar a.ghost { background: #fff; color: #003B95; }
        .toolbar .hint { font-size: 12px; color: #40506B; margin-left: auto; }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; min-height: 297mm; }
            .toolbar { display: none; }
        }

        @media screen and (max-width: 900px) {
            .sheet, .toolbar { width: auto; max-width: 100%; min-height: 0; }
            .sheet { padding: 8mm; }
            .toolbar .hint { display: none; }
            .signed-line { white-space: normal; }
        }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print waiver</button>
    <a class="ghost" href="{{ $waiver->pdfUrl }}" target="_blank" rel="noopener">Open PDF</a>
    <a class="ghost" href="{{ $waiver->backUrl }}">Back to booking</a>
    <span class="hint">Have the client sign before the ticket is paid for.</span>
</div>

<main class="sheet">
    <header class="letterhead">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
        <span class="tagline">Endless Possibilities in Travel and Tourism</span>
        <span class="reference">Visa Waiver · {{ $waiver->reference }}</span>
    </header>

    <h1>Visa Waiver and Acknowledgment</h1>
    <p class="subtitle">Booking made before the visa is approved</p>

    @include('ticketing.waivers._body', ['waiver' => $waiver])

    <p class="signed-line">
        Signed this <span class="blank"></span> day of <span class="blank wide"></span>
        20<span class="blank short"></span> at Angeles City, Pampanga, Philippines.
    </p>

    <div class="signatures">
        <div>
            <div class="line">{{ $waiver->clientName }}</div>
            <div class="caption">Client</div>
        </div>
        <div>
            <div class="line">{{ $waiver->handlingAgent }}</div>
            <div class="caption">Handling Agent</div>
        </div>
    </div>

    <footer class="footer">
        Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
        +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703<br>
        sales@amegatravelandtour.com<br>
        www.amegatravelandtour.com &nbsp;|&nbsp; Facebook: @AmegaTravel
    </footer>
</main>

</body>
</html>
