@php
    /** @var \App\Models\TicketBooking $ticket */
    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
    $total = round((float) $ticket->total_amount, 2);
    $payments = $ticket->payments->sortBy('id')->values();
    $schedule = $ticket->paymentSchedule();

    $credited = 0.0;      // what counts against the booking
    $charges = 0.0;       // card charges, collected on top
    foreach ($payments as $entry) {
        $sign = $entry->isRefund() ? -1 : 1;
        $credited += $sign * (float) $entry->amount;
        $charges += $entry->isRefund() ? 0 : (float) $entry->card_surcharge;
    }
    $balance = max(0, round($total - $credited, 2));
    $paidSum = (float) $payments->reject->isRefund()->sum('amount');
    $refundSum = (float) $payments->filter->isRefund()->sum('amount');
    $ledgerRunning = 0.0;
    $statementNo = 'SOA-'.$ticket->booking_reference;
    $overdue = $ticket->overdueInstallments();
    $trip = trim(($ticket->origin ? $ticket->origin.' to ' : '').$ticket->destination);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement of Account - {{ $ticket->booking_reference }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #E9EDF3;
            font-family: "Open Sans", "Segoe UI", Arial, Helvetica, sans-serif;
            color: #1F2937;
            font-size: 10pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 16mm 18mm 12mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
            display: flex;
            flex-direction: column;
        }

        .letterhead { display: flex; align-items: center; gap: 12px; }
        .letterhead img { height: 46px; display: block; }
        .letterhead .tagline { font-size: 9pt; font-style: italic; color: #003B95; border-left: 1.5px solid #003B95; padding-left: 12px; }
        .reference { margin-left: auto; font-size: 8pt; color: #6B7280; text-align: right; }

        h1 { text-align: center; font-size: 15pt; font-weight: bold; margin: 9mm 0 1mm; }
        .asof { text-align: center; font-size: 9pt; color: #4B5563; margin: 0 0 6mm; }

        table { width: 100%; border-collapse: collapse; }
        .meta { margin-bottom: 5mm; font-size: 9.5pt; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td.k { width: 30mm; color: #4B5563; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; }

        h2 { font-size: 9pt; font-weight: bold; letter-spacing: .06em; text-transform: uppercase; color: #003B95; margin: 5mm 0 1.5mm; }

        .summary { display: flex; gap: 4mm; margin: 0 0 3mm; }
        .summary div { flex: 1; border: 1px solid #111827; padding: 5px 8px; }
        .summary span { display: block; font-size: 7.5pt; font-weight: bold; letter-spacing: .05em; text-transform: uppercase; color: #4B5563; }
        .summary strong { font-size: 12pt; }
        .summary .due { background: #F3F4F6; }

        table.grid { font-size: 8.5pt; }
        table.grid th, table.grid td { border: 1px solid #111827; padding: 3px 6px; text-align: left; vertical-align: middle; }
        table.grid th { background: #F3F4F6; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .03em; }
        table.grid td.n, table.grid th.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        table.grid tr.total td { font-weight: bold; background: #F9FAFB; }
        .refund { color: #BE123C; }
        .tag { display: inline-block; font-size: 7.5pt; font-weight: bold; padding: 0 5px; border-radius: 3px; border: 1px solid; }
        .tag.paid { color: #047857; border-color: #047857; }
        .tag.partial, .tag.upcoming { color: #6B7280; border-color: #9CA3AF; }
        .tag.overdue { color: #BE123C; border-color: #BE123C; }

        .note { font-size: 8pt; color: #6B7280; margin: 2mm 0 0; }
        .alert { margin: 0 0 3mm; padding: 5px 9px; border: 1px solid #BE123C; color: #9F1239; font-weight: bold; font-size: 9pt; }

        .sign { margin-top: auto; padding-top: 14mm; display: flex; justify-content: flex-end; }
        .sign div { width: 62mm; border-top: 1px solid #111827; padding-top: 3px; text-align: center; font-size: 8.5pt; color: #4B5563; }

        .footer { margin-top: 8mm; text-align: center; font-size: 8pt; color: #4B5563; line-height: 1.45; padding-top: 4mm; border-top: 1px solid #E5E7EB; }

        .toolbar { width: 210mm; margin: 18px auto 0; display: flex; align-items: center; gap: 8px; font-family: Arial, Helvetica, sans-serif; }
        .toolbar a, .toolbar button {
            font: inherit; font-size: 12px; font-weight: bold; padding: 8px 14px; border-radius: 6px;
            border: 1px solid #003B95; background: #003B95; color: #fff; text-decoration: none; cursor: pointer;
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
            .summary { flex-direction: column; }
        }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print statement</button>
    <a class="ghost" href="{{ route('cashier.payments.show', $ticket) }}">Back to payments</a>
    <span class="hint">Up to date as of now. Print it again any time.</span>
</div>

<main class="sheet">
    <header class="letterhead">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
        <span class="tagline">Endless Possibilities in Travel and Tourism</span>
        <span class="reference">{{ $statementNo }}</span>
    </header>

    <h1>Statement of Account</h1>
    <p class="asof">As of {{ now()->format('F j, Y g:i A') }}</p>

    <table class="meta">
        <tr><td class="k">Client</td><td><strong>{{ $ticket->contact_name ?: '—' }}</strong>{{ $ticket->contact_phone ? ' · '.$ticket->contact_phone : '' }}{{ $ticket->contact_email ? ' · '.$ticket->contact_email : '' }}</td></tr>
        <tr><td class="k">Booking</td><td>{{ $ticket->booking_reference }}{{ $trip !== '' ? ' · '.$trip : '' }}</td></tr>
        @if ($ticket->departure_date)
            <tr><td class="k">Departure</td><td>{{ $ticket->departure_date->format('F j, Y') }}{{ $ticket->return_date ? ' · Return '.$ticket->return_date->format('F j, Y') : '' }}</td></tr>
        @endif
        <tr><td class="k">Booked by</td><td>{{ $ticket->createdBy?->name ?? '—' }}</td></tr>
        @if ($ticket->hasTerms())
            <tr><td class="k">Terms</td><td>{{ $ticket->terms_payments }} payments, {{ $ticket->terms_days }} {{ \Illuminate\Support\Str::plural('day', (int) $ticket->terms_days) }} apart, starting {{ $ticket->terms_start->format('F j, Y') }}</td></tr>
        @endif
    </table>

    <div class="summary">
        <div><span>Booking total</span><strong>{{ $peso($total) }}</strong></div>
        <div><span>Paid to date</span><strong>{{ $peso(max(0, $credited)) }}</strong></div>
        <div class="due"><span>Balance due</span><strong>{{ $peso($balance) }}</strong></div>
    </div>

    @if ($overdue > 0)
        <p class="alert">{{ $overdue }} scheduled {{ \Illuminate\Support\Str::plural('payment', $overdue) }} past due.</p>
    @endif

    {{-- Ledger: the booking's cost, then every OR in order, with what is still owed after each --}}
    <h2>Ledger</h2>
    <table class="grid" data-soa-ledger>
        <thead>
            <tr><th>OR #</th><th>Date</th><th class="n">Cost</th><th class="n">Amount paid</th><th class="n">A.R. balance</th><th class="n">Refund</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $ticket->booking_reference }}</td>
                <td>{{ $ticket->created_at?->format('M j, Y') }}</td>
                <td class="n">{{ $peso($total) }}</td>
                <td class="n">—</td>
                <td class="n">{{ $peso($total) }}</td>
                <td class="n">—</td>
            </tr>
            @foreach ($payments as $entry)
                @php
                    $ledgerRunning += ($entry->isRefund() ? -1 : 1) * (float) $entry->amount;
                @endphp
                <tr class="{{ $entry->isRefund() ? 'refund' : '' }}">
                    <td>{{ $entry->receiptNumber() }}</td>
                    <td>{{ $entry->received_at?->format('M j, Y') }}</td>
                    <td class="n">—</td>
                    <td class="n">{{ $entry->isRefund() ? '—' : $peso((float) $entry->amount) }}</td>
                    <td class="n">{{ $peso(max(0, round($total - $ledgerRunning, 2))) }}</td>
                    <td class="n">{{ $entry->isRefund() ? $peso((float) $entry->amount) : '—' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="n">{{ $peso($total) }}</td>
                <td class="n">{{ $peso($paidSum) }}</td>
                <td class="n">{{ $peso($balance) }}</td>
                <td class="n">{{ $refundSum > 0 ? $peso($refundSum) : '—' }}</td>
            </tr>
        </tbody>
    </table>
    <p class="note">A.R. balance is the cost less what has been paid, after refunds. A 4% charge is added to card payments; it is collected on top of the amount paid and does not reduce the balance.@if ($charges > 0) Card charges collected: {{ $peso($charges) }}.@endif</p>

    @if ($schedule !== [])
        <h2>Payment schedule</h2>
        <table class="grid">
            <thead>
                <tr><th>Payment</th><th>Due date</th><th class="n">Amount due</th><th class="n">Paid</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($schedule as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ $row['due']->format('M j, Y') }}</td>
                        <td class="n">{{ $peso($row['amount']) }}</td>
                        <td class="n">{{ $peso($row['paid']) }}</td>
                        <td><span class="tag {{ $row['status'] }}">{{ ucfirst($row['status']) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="sign">
        <div>{{ auth()->user()?->name }}<br>Prepared by, Amega Travel and Tours Services</div>
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
