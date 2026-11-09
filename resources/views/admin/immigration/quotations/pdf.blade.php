<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->quote_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 18mm 30mm; }

        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1F2937; font-size: 9pt; line-height: 1.4; }
        table { border-collapse: collapse; }

        .letterhead { width: 100%; }
        .letterhead td { vertical-align: middle; padding: 0; }
        .letterhead img { height: 42px; }
        .letterhead .tagline { font-size: 8pt; font-style: italic; color: #003B95; border-left: 1.5px solid #003B95; padding-left: 10px; white-space: nowrap; }
        .reference { font-size: 7.5pt; color: #6B7280; text-align: right; white-space: nowrap; width: 1%; }

        h1 { text-align: center; font-size: 15pt; letter-spacing: 2px; color: #003B95; margin: 7mm 0 5mm; }

        table.meta { width: 100%; margin-bottom: 5mm; }
        table.meta td { vertical-align: top; padding: 0; width: 50%; }
        .box-title { font-size: 7pt; font-weight: bold; text-transform: uppercase; color: #6B7280; letter-spacing: 0.5px; margin-bottom: 1mm; }
        .client-name { font-size: 11pt; font-weight: bold; color: #111827; }
        table.facts td { padding: 0.5mm 0; }
        table.facts td.k { color: #6B7280; padding-right: 4mm; white-space: nowrap; }
        table.facts td.v { font-weight: bold; }

        table.items { width: 100%; margin-bottom: 3mm; }
        table.items th { background: #003B95; color: #FFFFFF; font-size: 7.5pt; text-transform: uppercase; text-align: left; padding: 2mm 2.5mm; }
        table.items td { border-bottom: 1px solid #E5E7EB; padding: 2mm 2.5mm; vertical-align: top; }
        table.items .num { text-align: right; white-space: nowrap; }
        .service { font-size: 7.5pt; color: #6B7280; }

        table.totals { width: 75mm; margin-left: auto; margin-bottom: 6mm; }
        table.totals td { padding: 1.2mm 2.5mm; }
        table.totals td.num { text-align: right; white-space: nowrap; }
        table.totals tr.grand td { border-top: 1.5px solid #111827; font-size: 11pt; font-weight: bold; color: #003B95; }

        .notes { border: 1px solid #E5E7EB; background: #F9FAFB; padding: 3mm; margin-bottom: 5mm; }
        ol.terms { margin: 0 0 6mm; padding-left: 5mm; font-size: 8pt; color: #374151; }
        ol.terms li { margin-bottom: 1mm; }

        .signatures { width: 100%; margin-top: 12mm; }
        .signatures td { width: 50%; vertical-align: bottom; padding: 0 7mm; }
        .signatures .line { border-bottom: 1px solid #111827; min-height: 8mm; text-align: center; font-weight: bold; text-transform: uppercase; padding-bottom: 1mm; }
        .signatures .caption { text-align: center; margin-top: 2mm; font-size: 8pt; }

        .footer { position: fixed; left: 0; right: 0; bottom: -20mm; text-align: center; font-size: 7.5pt; color: #4B5563; line-height: 1.45; padding-top: 4mm; border-top: 1px solid #E5E7EB; }
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
        <td class="reference">Immigration Services · {{ $quotation->quote_number }}</td>
    </tr>
</table>

<h1>QUOTATION</h1>

<table class="meta">
    <tr>
        <td>
            <div class="box-title">Prepared for</div>
            <div class="client-name">{{ $quotation->client_name }}</div>
            @foreach ([$quotation->nationality, $quotation->passport_number ? 'Passport No. '.$quotation->passport_number : null, $quotation->client_email, $quotation->client_phone] as $line)
                @if ($line) <div>{{ $line }}</div> @endif
            @endforeach
        </td>
        <td>
            <table class="facts" style="margin-left: auto;">
                <tr><td class="k">Quotation No.</td><td class="v">{{ $quotation->quote_number }}</td></tr>
                <tr><td class="k">Date</td><td class="v">{{ $quotation->created_at->format('F j, Y') }}</td></tr>
                <tr><td class="k">Valid until</td><td class="v">{{ $quotation->valid_until->format('F j, Y') }}</td></tr>
                <tr><td class="k">Prepared by</td><td class="v">{{ $quotation->createdBy?->name ?? 'Amega Travel and Tours' }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width: 7mm;">No.</th>
            <th>Service / Description</th>
            <th class="num" style="width: 12mm;">Qty</th>
            <th class="num" style="width: 28mm;">Unit price</th>
            <th class="num" style="width: 30mm;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($quotation->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    {{ $item->description }}
                    @if ($item->serviceLabel() && $item->serviceLabel() !== $item->description) <div class="service">{{ $item->serviceLabel() }}</div> @endif
                </td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="num">₱{{ number_format((float) $item->amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="num">₱{{ number_format((float) $quotation->subtotal, 2) }}</td></tr>
    @if ((float) $quotation->discount > 0)
        <tr><td>Discount</td><td class="num">-₱{{ number_format((float) $quotation->discount, 2) }}</td></tr>
    @endif
    <tr class="grand"><td>Total</td><td class="num">₱{{ number_format((float) $quotation->total, 2) }}</td></tr>
</table>

@if ($quotation->notes)
    <div class="notes">
        <div class="box-title">Notes</div>
        <div style="white-space: pre-line;">{{ $quotation->notes }}</div>
    </div>
@endif

<div class="box-title">Terms</div>
<ol class="terms">
    <li>This quotation is valid until {{ $quotation->valid_until->format('F j, Y') }}.</li>
    <li>Bureau of Immigration (BI) fees are set by the BI and may change without prior notice; the fees in force on the day of filing apply.</li>
    <li>Processing times are set by the BI and are estimates only. Approval rests solely with the BI.</li>
    <li>Fees are payable before filing and are non-refundable once the application has been filed.</li>
</ol>

<div style="page-break-inside: avoid;">
    <table class="signatures">
        <tr>
            <td>
                <div class="line">{{ $quotation->createdBy?->name }}</div>
                <div class="caption">Prepared by, Amega Travel and Tours</div>
            </td>
            <td>
                <div class="line">{{ $quotation->client_name }}</div>
                <div class="caption">Conforme (client's signature and date)</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
