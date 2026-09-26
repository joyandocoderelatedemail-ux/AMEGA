@php
    /** @var \App\Support\DataPrivacyConsent $consent */
    use App\Support\DataPrivacyConsent;

    $services = DataPrivacyConsent::SERVICES;

    // The paper form's grid: two services per row, passporting across the last.
    $rows = [
        ['ticket', 'package'],
        ['immigration', 'visa'],
        ['documentation', 'day_tour'],
        ['passport'],
    ];

    $handlingAgent = auth()->user()?->name;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Privacy Consent Form - {{ $consent->clientName }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #E9EDF3;
            font-family: "Open Sans", "Segoe UI", Arial, Helvetica, sans-serif;
            color: #1F2937;
            font-size: 11.5pt;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            height: 297mm;
            margin: 18px auto;
            padding: 16mm 20mm 12mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
            display: flex;
            flex-direction: column;
        }

        /* ---- letterhead ---- */
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

        h1 {
            text-align: center;
            font-size: 15pt;
            font-weight: bold;
            margin: 20mm 0 8mm;
        }

        p { margin: 0 0 6mm; text-align: justify; }

        /* ---- services table ---- */
        table.services {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 8mm;
        }
        table.services td { border: 1px solid #111827; padding: 6px 10px; vertical-align: middle; }
        table.services td.box { width: 9mm; text-align: center; padding: 4px; }

        label.tick { display: inline-block; cursor: pointer; line-height: 0; }
        label.tick input { position: absolute; opacity: 0; width: 1px; height: 1px; }
        label.tick .mark {
            display: inline-block;
            position: relative;
            width: 5mm;
            height: 5mm;
            border: 1.2px solid #111827;
            border-radius: 1px;
            background: #fff;
        }
        label.tick input:checked + .mark::after {
            content: "";
            position: absolute;
            left: 1.4mm;
            top: 0.2mm;
            width: 1.4mm;
            height: 3mm;
            border: solid #003B95;
            border-width: 0 2.2px 2.2px 0;
            transform: rotate(45deg);
        }
        label.tick input:focus-visible + .mark { outline: 2px solid #003B95; outline-offset: 2px; }

        .signed-line { margin: 2mm 0 0; text-align: left; white-space: nowrap; }
        .blank { display: inline-block; border-bottom: 1px solid #111827; min-width: 18mm; }
        .blank.wide { min-width: 32mm; }
        .blank.short { min-width: 8mm; }

        /* ---- signatures ---- */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14mm;
            margin-top: 22mm;
        }
        .signatures .line {
            border-bottom: 1px solid #111827;
            min-height: 8mm;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            padding-bottom: 1mm;
        }
        .signatures .caption { text-align: center; margin-top: 2mm; }

        /* ---- footer ---- */
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
            .sheet { margin: 0; box-shadow: none; }
            .toolbar { display: none; }
        }

        @media screen and (max-width: 900px) {
            .sheet, .toolbar { width: auto; max-width: 100%; height: auto; }
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
    <button type="button" onclick="window.print()">Print consent form</button>
    <a class="ghost" href="{{ $consent->backUrl }}">Back to file</a>
    <span class="hint">Tick any other service the client is availing before printing.</span>
</div>

<main class="sheet">
    <header class="letterhead">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
        <span class="tagline">Endless Possibilities in Travel and Tourism</span>
        <span class="reference">{{ $consent->reference }}</span>
    </header>

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
                    <td class="box">
                        <label class="tick">
                            <input type="checkbox" name="services[]" value="{{ $service }}"
                                   aria-label="{{ $services[$service] }}" @checked($consent->isTicked($service))>
                            <span class="mark"></span>
                        </label>
                    </td>
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

    <p class="signed-line">
        Signed this <span class="blank"></span> day of <span class="blank wide"></span>
        20<span class="blank short"></span> at Angeles City, Pampanga, Philippines.
    </p>

    <div class="signatures">
        <div>
            <div class="line">{{ $consent->clientName }}</div>
            <div class="caption">Client</div>
        </div>
        <div>
            <div class="line">{{ $handlingAgent }}</div>
            <div class="caption">Handling Agent</div>
        </div>
    </div>

    <footer class="footer">
        Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
        +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703<br>
        sales@amegatravelandtours.com<br>
        www.amegatravelandtours.com &nbsp;|&nbsp; Facebook: @AmegaTravel
    </footer>
</main>

</body>
</html>
