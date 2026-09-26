@php
    /**
     * The Booking Agreement as a PDF for the client's email. Same content as
     * ticketing/agreements/show, laid out with tables because dompdf has no
     * flexbox or grid.
     */
    $money = fn (float $amount): string => number_format($amount, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Booking Agreement - {{ $agreement->agreement_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 11mm 16mm 24mm; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            color: #1F2937;
            font-size: 8pt;
            line-height: 1.4;
        }

        table { border-collapse: collapse; }

        .letterhead td { vertical-align: middle; padding: 0; }
        .letterhead img { height: 40px; }
        .letterhead .tagline {
            font-size: 7.5pt;
            font-style: italic;
            color: #003B95;
            border-left: 1.5px solid #003B95;
            padding-left: 10px;
        }

        .title-block { width: 100%; margin-top: 5mm; }
        .title-block td { vertical-align: top; padding: 0; }
        .title-block h1 { margin: 0; font-size: 17pt; font-weight: normal; letter-spacing: .5px; }
        .title-block .client { margin-top: 3mm; font-size: 11pt; text-transform: uppercase; }
        .quote-meta td { padding: 0 0 0 8mm; font-size: 8pt; }
        .quote-meta th { padding: 0 0 0 8mm; font-size: 8pt; text-align: left; }

        table.lines { width: 100%; margin-top: 5mm; }
        table.lines th {
            font-size: 8.5pt;
            text-align: left;
            padding: 0 5px 4px;
            border-bottom: 1.2px solid #111827;
        }
        table.lines td { padding: 5px; vertical-align: top; font-size: 7.5pt; }
        table.lines .num { text-align: right; white-space: nowrap; }
        table.lines tr.category td { padding-top: 6px; text-transform: uppercase; }
        table.lines tr.item td { border-bottom: 1px solid #D1D5DB; }
        .desc { text-transform: uppercase; }
        .desc .segment { margin-top: 5px; }
        .desc .details { text-transform: none; color: #4B5563; }

        .totals { width: 44%; margin-left: 56%; margin-top: 4mm; font-size: 8pt; }
        .totals td { padding: 3px 5px; text-align: right; }
        .totals tr.grand td {
            border-top: 1.2px solid #111827;
            border-bottom: 1.2px solid #111827;
            font-size: 10pt;
            font-weight: bold;
            padding: 5px;
        }

        .conditions { width: 100%; margin-top: 4mm; font-size: 8pt; }
        .conditions td { padding: 1mm 0; }
        .box {
            display: inline-block;
            width: 3.6mm;
            height: 3.6mm;
            border: 1.1px solid #111827;
            text-align: center;
            line-height: 3.3mm;
            font-size: 10pt;
            font-weight: bold;
            color: #003B95;
            margin-right: 2mm;
            vertical-align: middle;
        }

        .rule { border: 0; border-top: 1px solid #003B95; margin: 4mm 0 3mm; }

        h2 { text-align: center; font-size: 9.5pt; margin: 0 0 4mm; text-transform: uppercase; }
        .declaration p { margin: 0 0 3mm; text-align: justify; }
        .fill { border-bottom: 1px solid #111827; padding: 0 2mm; }
        .fill.empty { display: inline-block; width: 40mm; padding: 0; }
        .declaration ol { margin: 0; padding-left: 6mm; }
        .declaration li { margin-bottom: 1.5mm; text-align: justify; padding-left: 1mm; }

        .signature { width: 70mm; margin: 7mm auto 0; text-align: center; }
        .signature .name { text-transform: uppercase; padding-bottom: 1mm; border-bottom: 1px solid #111827; }
        .signature .caption { margin-top: 1mm; }

        .declaration .certify { margin-top: 5mm; }
        .extras { margin-top: 2mm; font-size: 7.5pt; color: #374151; }
        .extras strong { color: #111827; }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -16mm;
            text-align: center;
            font-size: 7pt;
            color: #4B5563;
            line-height: 1.45;
        }
    </style>
</head>
<body>

<div class="footer">
    Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
    +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703 &nbsp;|&nbsp; sales@amegatravelandtours.com<br>
    www.amegatravelandtours.com &nbsp;|&nbsp; Facebook: @AmegaTravel
</div>

<table class="letterhead">
    <tr>
        @if ($logo)
            <td style="padding-right: 10px;"><img src="{{ $logo }}" alt="Amega Travel and Tours Services"></td>
        @endif
        <td class="tagline">Endless Possibilities in Travel and Tourism</td>
    </tr>
</table>

<table class="title-block">
    <tr>
        <td>
            <h1>BOOKING AGREEMENT</h1>
            <div class="client">{{ $agreement->client_names }}</div>
        </td>
        <td style="width: 1%; white-space: nowrap;">
            <table class="quote-meta">
                <tr><th>Quote Number</th><th>Date</th></tr>
                <tr>
                    <td>{{ $agreement->agreement_number }}</td>
                    <td>{{ $agreement->agreement_date?->format('d M Y') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

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
                    <div>{{ $line['description'] }}</div>
                    @if ($loop->first)
                        @if ($carriers->isNotEmpty())
                            <div>VIA {{ $carriers->implode(' / ') }}</div>
                        @endif
                        @foreach ($segmentLines as $segmentLine)
                            <div class="segment">{{ $segmentLine }}</div>
                        @endforeach
                    @endif
                    @if ($line['details'])
                        <div class="details">{{ $line['details'] }}</div>
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
        <td>Subtotal</td>
        <td>{{ $money($total) }}</td>
    </tr>
    <tr>
        <td>Total Sales Tax 0%</td>
        <td>0.00</td>
    </tr>
    <tr class="grand">
        <td>TOTAL PHP</td>
        <td>{{ $money($total) }}</td>
    </tr>
</table>

<table class="conditions">
    @foreach (array_chunk($conditions, 4) as $conditionRow)
        <tr>
            @foreach ($conditionRow as [$conditionLabel, $isTicked])
                <td><span class="box">{{ $isTicked ? '✓' : '' }}</span>{{ $conditionLabel }}</td>
            @endforeach
        </tr>
    @endforeach
</table>

<hr class="rule">

<div class="declaration">
    <h2>Airline Ticketing (Domestic/International)</h2>

    <p>
        I <span class="fill" style="text-transform: uppercase;">{{ $declarant }}</span>, of legal age, Citizen of
        <span class="fill">{{ $citizenship }}</span>, with residential address at
        <span class="fill {{ blank($agreement->home_hotel_address) ? 'empty' : '' }}">{{ $agreement->home_hotel_address }}</span>, after having read and understood the terms
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
</div>

@if ($ticket->isCustomPackage() || ! empty($packageSpecs))
    <div class="extras">
        <strong>Hotel Policies &amp; Transfer:</strong>
        {{ ($packageSpecs['smoking_preference'] ?? '') === 'smoking' ? 'Smoking' : 'Non-Smoking' }}
        &middot; {{ ! empty($packageSpecs['pet_friendly']) ? 'Pets Allowed' : 'No Pets' }}
        &middot; {{ ! empty($packageSpecs['has_transportation']) ? ($packageSpecs['transportation_type'] ?? 'Arranged') : 'No Transport' }}
    </div>
@endif

@if ($agreement->payment_terms)
    <div class="extras"><strong>Remarks:</strong> {!! nl2br(e($agreement->payment_terms)) !!}</div>
@endif

@if ($agreement->agent_name)
    <div class="extras">Prepared by {{ $agreement->agent_name }}</div>
@endif

</body>
</html>
