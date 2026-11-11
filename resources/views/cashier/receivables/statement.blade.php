@php
    /**
     * A company's Statement of Account for one month, on AMEGA's letterhead: what it owed going in, the tickets
     * charged, the payments received, and what it owes now with each ticket's due date.
     *
     * @var \App\Models\CorporateAccount $company
     * @var array $statement
     */
    $peso = fn (float $amount): string => ($amount < 0 ? '-' : '').'₱'.number_format(abs($amount), 2);
    $number = 'SOA-C'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT).'-'.$statement['start']->format('Ym');
    $overdue = round((float) $items->filter(fn ($ticket) => $ticket->daysOverdue() > 0)->sum(fn ($ticket) => $ticket->balanceDue()), 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement of Account - {{ $company->company_name }} - {{ $statement['start']->format('F Y') }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #E9EDF3; font-family: "Open Sans", "Segoe UI", Arial, Helvetica, sans-serif; color: #1F2937; font-size: 9.5pt; line-height: 1.45; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { width: 210mm; margin: 18px auto 0; display: flex; gap: 8px; font-family: Arial, Helvetica, sans-serif; }
        .toolbar a, .toolbar button { font-family: inherit; font-size: 12px; font-weight: bold; padding: 8px 14px; border-radius: 6px; border: 1px solid #003B95; background: #003B95; color: #fff; text-decoration: none; cursor: pointer; }
        .toolbar a.ghost { background: #fff; color: #003B95; }
        .sheet { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 12mm 16mm 10mm; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.18); display: flex; flex-direction: column; }
        .content { flex: 1; }
        .letterhead { display: flex; align-items: center; gap: 12px; }
        .letterhead img { height: 44px; }
        .letterhead .tagline { font-size: 8.5pt; font-style: italic; color: #003B95; border-left: 1.5px solid #003B95; padding-left: 10px; }
        .letterhead .ref { margin-left: auto; font-size: 8pt; color: #6B7280; white-space: nowrap; }
        h1 { text-align: center; font-size: 15pt; letter-spacing: 2px; color: #003B95; margin: 8mm 0 1mm; }
        .period { text-align: center; color: #4B5563; margin-bottom: 6mm; }
        .meta { display: flex; justify-content: space-between; gap: 10mm; margin-bottom: 5mm; }
        .box-title { font-size: 7pt; font-weight: bold; text-transform: uppercase; color: #6B7280; letter-spacing: .5px; margin-bottom: 1mm; }
        .name { font-size: 12pt; font-weight: bold; color: #111827; }
        table.facts td { padding: .4mm 0; }
        table.facts td.k { color: #6B7280; padding-right: 5mm; white-space: nowrap; }
        table.facts td.v { font-weight: bold; }
        .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #003B95; border-bottom: 1.5px solid #003B95; padding-bottom: 1mm; margin: 5mm 0 0; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { font-size: 7.5pt; text-transform: uppercase; text-align: left; color: #6B7280; padding: 2mm 2mm 1mm; border-bottom: 1px solid #D1D5DB; }
        table.rows td { padding: 1.6mm 2mm; border-bottom: 1px solid #E5E7EB; vertical-align: top; }
        .num, table.rows th.num { text-align: right; white-space: nowrap; }
        .empty { padding: 2mm; color: #6B7280; }
        table.summary { width: 85mm; margin: 6mm 0 0 auto; border-collapse: collapse; }
        table.summary td { padding: 1.2mm 2.5mm; }
        table.summary tr.grand td { border-top: 1.5px solid #111827; font-size: 12pt; font-weight: bold; color: #003B95; }
        .late { color: #B91C1C; font-weight: bold; }
        .terms { margin-top: 6mm; font-size: 8pt; color: #374151; }
        .footer { text-align: center; font-size: 7.5pt; color: #4B5563; border-top: 1px solid #E5E7EB; padding-top: 3mm; margin-top: 6mm; line-height: 1.5; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; box-shadow: none; min-height: 290mm; } }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ route('cashier.receivables.show', $company) }}" class="ghost">&larr; Back to the account</a>
    <button type="button" onclick="window.print()">Print statement</button>
</div>

<div class="sheet">
    <div class="content">
        <div class="letterhead">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
            <span class="tagline">Endless Possibilities in Travel and Tourism</span>
            <span class="ref">{{ $number }}</span>
        </div>

        <h1>STATEMENT OF ACCOUNT</h1>
        <p class="period">{{ $statement['start']->format('F j') }} to {{ $statement['end']->format('F j, Y') }}</p>

        <div class="meta">
            <div>
                <div class="box-title">Bill to</div>
                <div class="name">{{ $company->company_name }}</div>
                @foreach ([$company->address, $company->tin ? 'TIN '.$company->tin : null, collect([$company->contact_person, $company->contact_position])->filter()->implode(', '), $company->contact_email, $company->contact_phone] as $line)
                    @if (filled($line)) <div>{{ $line }}</div> @endif
                @endforeach
            </div>
            <table class="facts">
                <tr><td class="k">Statement No.</td><td class="v">{{ $number }}</td></tr>
                <tr><td class="k">Date</td><td class="v">{{ now()->format('F j, Y') }}</td></tr>
                <tr><td class="k">Terms</td><td class="v">Net {{ $company->credit_terms_days }} days</td></tr>
                @if ($company->credit_limit !== null)
                    <tr><td class="k">Credit limit</td><td class="v">{{ $peso((float) $company->credit_limit) }}</td></tr>
                @endif
            </table>
        </div>

        <div class="section-title">Tickets charged this period</div>
        @if ($statement['charges']->isEmpty())
            <p class="empty">No tickets were charged to the account in this period.</p>
        @else
            <table class="rows">
                <thead><tr><th>Date</th><th>Booking</th><th>Passenger / trip</th><th>Due</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    @foreach ($statement['charges'] as $ticket)
                        <tr>
                            <td>{{ $ticket->charged_to_account_at->format('M j, Y') }}</td>
                            <td>{{ $ticket->booking_reference }}</td>
                            <td>{{ $ticket->contact_name }}<br>{{ trim(($ticket->origin ? $ticket->origin.' to ' : '').$ticket->destination) }}{{ $ticket->departure_date ? ' · '.$ticket->departure_date->format('M j, Y') : '' }}</td>
                            <td>{{ $ticket->ar_due_date?->format('M j, Y') }}</td>
                            <td class="num">{{ $peso((float) $ticket->ar_amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">Payments received this period</div>
        @if ($statement['payments']->isEmpty())
            <p class="empty">No payments were received in this period.</p>
        @else
            <table class="rows">
                <thead><tr><th>Date</th><th>Booking</th><th>Paid by</th><th>Reference</th><th class="num">Amount</th></tr></thead>
                <tbody>
                    @foreach ($statement['payments'] as $entry)
                        <tr>
                            <td>{{ $entry->received_at->format('M j, Y') }}</td>
                            <td>{{ $entry->booking->booking_reference }}</td>
                            <td>{{ $entry->methodLabel() }}</td>
                            <td>{{ $entry->reference ?: '—' }}</td>
                            <td class="num">{{ $peso(-(float) $entry->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <table class="summary">
            <tr><td>Balance brought forward</td><td class="num">{{ $peso($statement['opening']) }}</td></tr>
            <tr><td>Tickets charged</td><td class="num">{{ $peso((float) $statement['charges']->sum('ar_amount')) }}</td></tr>
            <tr><td>Payments received</td><td class="num">{{ $peso(-(float) $statement['payments']->sum('amount')) }}</td></tr>
            <tr class="grand"><td>Balance, {{ $statement['end']->isFuture() ? 'to date' : $statement['end']->format('M j, Y') }}</td><td class="num">{{ $peso($statement['closing']) }}</td></tr>
        </table>

        <div class="section-title">Unpaid tickets as of {{ now()->format('F j, Y') }}</div>
        @if ($items->isEmpty())
            <p class="empty">Nothing is outstanding. Thank you.</p>
        @else
            <table class="rows">
                <thead><tr><th>Booking</th><th>Passenger</th><th>Due</th><th>Status</th><th class="num">Balance</th></tr></thead>
                <tbody>
                    @foreach ($items as $ticket)
                        <tr>
                            <td>{{ $ticket->booking_reference }}</td>
                            <td>{{ $ticket->contact_name }}</td>
                            <td>{{ $ticket->ar_due_date?->format('M j, Y') }}</td>
                            <td class="{{ $ticket->daysOverdue() > 0 ? 'late' : '' }}">{{ $ticket->daysOverdue() > 0 ? $ticket->daysOverdue().' '.\Illuminate\Support\Str::plural('day', $ticket->daysOverdue()).' overdue' : 'Not yet due' }}</td>
                            <td class="num">{{ $peso($ticket->balanceDue()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <table class="summary">
                @if ($overdue > 0)
                    <tr><td class="late">Overdue</td><td class="num late">{{ $peso($overdue) }}</td></tr>
                @endif
                <tr class="grand"><td>Total amount due</td><td class="num">{{ $peso($outstanding) }}</td></tr>
            </table>
        @endif

        <p class="terms">
            Payment terms: Net {{ $company->credit_terms_days }} days from the date each ticket is charged.
            @if ($company->credit_interest_rate !== null && (float) $company->credit_interest_rate > 0)
                Overdue amounts are subject to late interest of {{ rtrim(rtrim(number_format((float) $company->credit_interest_rate, 2), '0'), '.') }}% per month{{ $company->credit_grace_days > 0 ? ' after a grace period of '.$company->credit_grace_days.' days' : '' }}, as agreed.
            @endif
            Please make payments to Amega Travel and Tours Services and quote the statement number.
        </p>
    </div>

    <div class="footer">
        Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
        +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703 &nbsp;|&nbsp; sales@amegatravelandtour.com<br>
        www.amegatravelandtour.com &nbsp;|&nbsp; Facebook: @AmegaTravel
    </div>
</div>

</body>
</html>
