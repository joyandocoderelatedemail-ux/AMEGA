@php
    /**
     * The Booking Agreement, laid out like the agency's printed quote: an
     * invoice-style line table, the ticket conditions as tick boxes, and the
     * client's Airline Ticketing declaration for signature. The figures come
     * from App\Support\BookingAgreementSheet, shared with the emailed PDF.
     */
    $money = fn (float $amount): string => number_format($amount, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Agreement - {{ $agreement->agreement_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #E9EDF3;
            font-family: "Open Sans", "Segoe UI", Arial, Helvetica, sans-serif;
            color: #1F2937;
            font-size: 9pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 11mm 16mm 8mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
            display: flex;
            flex-direction: column;
        }

        /* ---- letterhead ---- */
        .letterhead { display: flex; align-items: center; gap: 12px; }
        .letterhead img { height: 44px; display: block; }
        .letterhead .tagline {
            font-size: 8.5pt;
            font-style: italic;
            color: #003B95;
            border-left: 1.5px solid #003B95;
            padding-left: 12px;
        }

        /* ---- title block ---- */
        .title-block { display: flex; justify-content: space-between; gap: 16px; margin-top: 7mm; }
        .title-block h1 { margin: 0; font-size: 20pt; font-weight: 400; letter-spacing: .5px; }
        .title-block .client { margin-top: 3mm; font-size: 13pt; text-transform: uppercase; }
        .quote-meta { display: grid; grid-template-columns: auto auto; gap: 0 10mm; align-self: flex-start; font-size: 9pt; }
        .quote-meta dt { font-weight: 700; }
        .quote-meta dd { margin: 0; }

        /* ---- line items ---- */
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6mm; }
        table.lines th {
            font-size: 9.5pt;
            font-weight: 700;
            text-align: left;
            padding: 0 6px 4px;
            border-bottom: 1.2px solid #111827;
        }
        table.lines td { padding: 5px 6px; vertical-align: top; font-size: 8.5pt; }
        table.lines .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        table.lines th.num { text-align: right; }
        table.lines tr.category td { padding-top: 6px; text-transform: uppercase; }
        table.lines tr.item td { border-bottom: 1px solid #D1D5DB; }
        .desc { text-transform: uppercase; }
        .desc .sub { display: block; }
        .desc .segment { display: block; margin-top: 6px; }
        .desc .details { display: block; text-transform: none; color: #4B5563; }

        /* ---- totals ---- */
        .totals { width: 44%; margin-left: auto; margin-top: 4mm; border-collapse: collapse; font-size: 9pt; }
        .totals td { padding: 3px 6px; }
        .totals .num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals tr.grand td {
            border-top: 1.2px solid #111827;
            border-bottom: 1.2px solid #111827;
            font-size: 11pt;
            font-weight: 700;
            padding: 5px 6px;
        }

        /* ---- conditions ---- */
        .conditions {
            display: grid;
            grid-template-columns: repeat(4, auto);
            grid-auto-flow: row;
            justify-content: space-between;
            gap: 1.5mm 6mm;
            margin-top: 6mm;
            font-size: 9.5pt;
        }
        .conditions span { display: inline-flex; align-items: center; gap: 2.5mm; }
        .box {
            width: 4.2mm;
            height: 4.2mm;
            border: 1.2px solid #111827;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .box svg { width: 3.4mm; height: 3.4mm; }

        .rule { border: 0; border-top: 1px solid #003B95; margin: 5mm 0 4mm; }

        /* ---- declaration ---- */
        h2 { text-align: center; font-size: 11pt; font-weight: 700; margin: 0 0 4mm; text-transform: uppercase; }
        .declaration p { margin: 0 0 3mm; text-align: justify; }
        .fill { display: inline-block; border-bottom: 1px solid #111827; min-width: 34mm; padding: 0 2mm; text-align: center; }
        .declaration ol { margin: 0; padding-left: 7mm; }
        .declaration li { margin-bottom: 1.5mm; text-align: justify; padding-left: 1.5mm; }

        .signature { width: 70mm; margin: 9mm auto 0; text-align: center; }
        .signature .name { text-transform: uppercase; padding-bottom: 1mm; border-bottom: 1px solid #111827; }
        .signature .caption { margin-top: 1mm; }

        .declaration .certify { margin-top: 6mm; }
        .extras { margin-top: 4mm; font-size: 8.5pt; color: #374151; }
        .extras strong { color: #111827; }

        /* ---- footer ---- */
        .footer {
            margin-top: auto;
            padding-top: 4mm;
            text-align: center;
            font-size: 8pt;
            color: #4B5563;
            line-height: 1.45;
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
        .toolbar .flash { margin-left: auto; font-size: 12px; font-weight: bold; color: #047857; }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; min-height: 290mm; }
            .toolbar { display: none; }
        }

        @media screen and (max-width: 900px) {
            .sheet, .toolbar { width: auto; max-width: 100%; }
            .sheet { padding: 8mm; }
            .title-block { flex-direction: column; }
            .totals { width: 100%; }
            .conditions { grid-template-columns: repeat(2, auto); }
            .toolbar { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print agreement</button>
    <a class="ghost" href="{{ route('ticketing.agreements.edit', $agreement) }}">Edit details / pricing</a>
    <a class="ghost" href="{{ route('ticketing.tickets.show', $ticket) }}">Back to ticket {{ $ticket->booking_reference }}</a>
    @if (session('success'))
        <span class="flash">{{ session('success') }}</span>
    @endif
</div>

<main class="sheet">
    <header class="letterhead">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
        <span class="tagline">Endless Possibilities in Travel and Tourism</span>
    </header>

    <div class="title-block">
        <div>
            <h1>BOOKING AGREEMENT</h1>
            <div class="client">{{ $agreement->client_names }}</div>
        </div>
        <dl class="quote-meta">
            <dt>Quote Number</dt>
            <dt>Date</dt>
            <dd>{{ $agreement->agreement_number }}</dd>
            <dd>{{ $agreement->agreement_date?->format('d M Y') }}</dd>
        </dl>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 52%;">Description</th>
                <th class="num">Quantity</th>
                <th class="num">Unit Price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr class="category">
                <td colspan="4">{{ $category }}</td>
            </tr>
            @foreach ($lines as $line)
                <tr class="item">
                    <td class="desc">
                        <span class="sub">{{ $line['description'] }}</span>
                        @if ($loop->first)
                            @if ($carriers->isNotEmpty())
                                <span class="sub">VIA {{ $carriers->implode(' / ') }}</span>
                            @endif
                            @foreach ($segmentLines as $segmentLine)
                                <span class="segment">{{ $segmentLine }}</span>
                            @endforeach
                        @endif
                        @if ($line['details'])
                            <span class="details">{{ $line['details'] }}</span>
                        @endif
                    </td>
                    <td class="num">{{ number_format($line['quantity'], 2) }}</td>
                    <td class="num">{{ $money($line['unit_price']) }}</td>
                    <td class="num">{{ $money($line['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="num">Subtotal</td>
            <td class="num">{{ $money($total) }}</td>
        </tr>
        <tr>
            <td class="num">Total Sales Tax 0%</td>
            <td class="num">0.00</td>
        </tr>
        <tr class="grand">
            <td class="num">TOTAL PHP</td>
            <td class="num">{{ $money($total) }}</td>
        </tr>
    </table>

    <div class="conditions">
        @foreach ($conditions as [$conditionLabel, $isTicked])
            <span>
                <span class="box">
                    @if ($isTicked)
                        <svg viewBox="0 0 24 24" fill="none" stroke="#003B95" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                    @endif
                </span>
                {{ $conditionLabel }}
            </span>
        @endforeach
    </div>

    <hr class="rule">

    <section class="declaration">
        <h2>Airline Ticketing (Domestic/International)</h2>

        <p>
            I <span class="fill" style="text-transform: uppercase;">{{ $declarant }}</span>, of legal age, Citizen of
            <span class="fill">{{ $citizenship }}</span>, with residential address at
            <span class="fill">{{ $agreement->home_hotel_address }}</span>, after having read and understood the terms
            and conditions of my airline ticket restrictions, attest that:
        </p>

        <ol>
            <li>I am fully aware of the terms and conditions of Amega Travel and Tours Services, my choice of airline and its IATA Agent.</li>
            <li>The Ticket booked and confirmed shall be subject to the current rules and regulation both of the place of my destination and place of departure, including but not limited to quarantine requirements, Covid-19 Test and other minimum health protocols.</li>
            <li>I am fully aware that the airline might cancel and re-schedule my flight due to unforeseen events and circumstance for my safety and safety of other passengers;</li>
            <li>I am well aware of the terms and conditions of the airline, IATA Agent and of Amega Travel and Tours Services in case of REFUNDS less services fees paid to Amega Travel and Tours Services.</li>
        </ol>

        <div class="signature">
            <div class="name">{{ $declarant }}</div>
            <div class="caption">Signature over printed name</div>
        </div>

        <p class="certify">This Certifies that all above mentioned information are true and correct.</p>
    </section>

    @if ($ticket->isCustomPackage() || ! empty($packageSpecs))
        <div class="extras">
            <strong>Hotel Policies &amp; Transfer:</strong>
            {{ ($packageSpecs['smoking_preference'] ?? '') === 'smoking' ? 'Smoking' : 'Non-Smoking' }}
            &middot; {{ ! empty($packageSpecs['pet_friendly']) ? 'Pets Allowed' : 'No Pets' }}
            &middot; {{ ! empty($packageSpecs['has_transportation']) ? ($packageSpecs['transportation_type'] ?? 'Arranged') : 'No Transport' }}
        </div>
    @endif

    @if ($agreement->payment_terms)
        <div class="extras"><strong>Remarks:</strong> <span style="white-space: pre-line;">{{ $agreement->payment_terms }}</span></div>
    @endif

    @if ($agreement->agent_name)
        <div class="extras">Prepared by {{ $agreement->agent_name }}</div>
    @endif

    <footer class="footer">
        Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
        +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703 &nbsp;|&nbsp; sales@amegatravelandtours.com<br>
        www.amegatravelandtours.com &nbsp;|&nbsp; Facebook: @AmegaTravel
    </footer>
</main>

</body>
</html>
