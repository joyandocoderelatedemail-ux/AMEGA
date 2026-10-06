@php
    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
    $isRefund = $payment->isRefund();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isRefund ? 'Refund Receipt' : 'Official Receipt' }} {{ $payment->receiptNumber() }}</title>
    <style>
        @page { size: A5 portrait; margin: 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #E9EDF3;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            font-size: 10pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar { width: 148mm; margin: 14px auto 0; display: flex; gap: 8px; }
        .toolbar button, .toolbar a {
            font: inherit; font-size: 9pt; font-weight: bold; padding: 7px 14px; border-radius: 6px;
            border: 1px solid #003B95; background: #003B95; color: #fff; text-decoration: none; cursor: pointer;
        }
        .toolbar a.ghost { background: #fff; color: #003B95; }

        .sheet {
            width: 148mm;
            margin: 10px auto 18px;
            padding: 10mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
        }

        .masthead {
            display: flex; align-items: flex-end; justify-content: space-between; gap: 12px;
            padding-bottom: 10px; border-bottom: 2.5px solid #003B95;
        }
        .masthead img { height: 36px; display: block; }
        .masthead h1 { margin: 0; font-size: 14pt; color: #003B95; text-align: right; }
        .masthead .no { font-family: "Courier New", Courier, monospace; font-weight: bold; font-size: 11pt; text-align: right; }

        .stamp {
            display: inline-block; margin: 12px 0 4px; border: 2px solid; border-radius: 3px; padding: 2px 9px;
            font-size: 9pt; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase;
        }
        .stamp.payment { color: #047857; border-color: #047857; }
        .stamp.refund { color: #BE123C; border-color: #BE123C; }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; width: 38%; padding: 5px 0; font-size: 8.5pt; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; color: #4B5563; vertical-align: top; }
        td { padding: 5px 0; border-bottom: 1px solid #E5E7EB; }
        tr:last-child td { border-bottom: 0; }
        th { border-bottom: 1px solid #E5E7EB; }
        tr:last-child th { border-bottom: 0; }

        .amount { margin: 14px 0 4px; padding: 10px 12px; background: #F3F4F6; border-radius: 4px; display: flex; justify-content: space-between; align-items: baseline; }
        .amount span { font-size: 9pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #4B5563; }
        .amount strong { font-size: 16pt; }

        .sign { margin-top: 26px; display: flex; justify-content: flex-end; }
        .sign div { width: 60mm; border-top: 1px solid #111827; padding-top: 3px; text-align: center; font-size: 8.5pt; color: #4B5563; }
        .foot { margin-top: 14px; font-size: 8pt; color: #6B7280; text-align: center; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; width: auto; box-shadow: none; }
        }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print receipt</button>
    <a class="ghost" href="{{ route('ticketing.tickets.show', $ticket) }}">Back to ticket</a>
</div>

<main class="sheet">
    <header class="masthead">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
        <div>
            <h1>{{ $isRefund ? 'Refund Receipt' : 'Official Receipt' }}</h1>
            <div class="no">{{ $payment->receiptNumber() }}</div>
        </div>
    </header>

    <span class="stamp {{ $isRefund ? 'refund' : 'payment' }}">{{ $isRefund ? 'Refund to client' : 'Payment received' }}</span>

    <div class="amount">
        <span>{{ $isRefund ? 'Amount refunded' : 'Amount received' }}</span>
        <strong>{{ $peso((float) $payment->amount) }}</strong>
    </div>

    <table>
        <tr><th>{{ $isRefund ? 'Refunded to' : 'Received from' }}</th><td>{{ $ticket->contact_name }}</td></tr>
        <tr><th>Booking</th><td>{{ $ticket->booking_reference }} &middot; {{ $ticket->origin ? $ticket->origin.' to ' : '' }}{{ $ticket->destination }}</td></tr>
        <tr><th>Date</th><td>{{ $payment->received_at->format('F j, Y') }}</td></tr>
        <tr><th>Method</th><td>{{ $payment->methodLabel() }}</td></tr>
        @if ($payment->reference)
            <tr><th>Reference</th><td>{{ $payment->reference }}</td></tr>
        @endif
        @if ($payment->note)
            <tr><th>Note</th><td>{{ $payment->note }}</td></tr>
        @endif
        <tr><th>Booking total</th><td>{{ $peso((float) $ticket->total_amount) }}</td></tr>
        <tr><th>Received to date</th><td>{{ $peso(max(0, $receivedToDate)) }}</td></tr>
        <tr><th>Balance</th><td>{{ $peso($balanceAfter) }}</td></tr>
    </table>

    <div class="sign">
        <div>{{ $payment->receivedBy?->name ?? 'Authorised staff' }}<br>Amega Travel and Tours Services</div>
    </div>

    <p class="foot">This receipt is issued for the payment recorded above. Keep it for your records.</p>
</main>

</body>
</html>
