@php
    /**
     * Statement of Account for an SRRV application or ID renewal, in the ticket statement's look (cashier/soa).
     * Expects $file, $lines (label, currency, amount, paid: one per due, each in its own currency) and $backUrl.
     */
    $money = fn (string $currency, float $amount): string => ($currency === 'PHP' ? '₱' : $currency.' ').number_format($amount, 2);
    $isRenewal = $file instanceof \App\Models\SrrvRenewal;
    $service = ucfirst((string) $file->visa_class).($isRenewal ? ' ID Renewal' : ' SRRV Application');
    // One total, paid and balance per currency: a renewal owes USD to the PRA and pesos to AMEGA.
    $byCurrency = collect($lines)->groupBy(1)->map(fn ($rows) => [
        'total' => $rows->sum(2), 'paid' => $rows->sum(3), 'balance' => $rows->sum(fn ($row) => max(0, $row[2] - $row[3])),
    ]);
    $payments = $file->payments->sortBy('id')->values();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement of Account - {{ $file->reference }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #E9EDF3; font-family: "Open Sans", "Segoe UI", Arial, Helvetica, sans-serif; color: #1F2937; font-size: 10pt; line-height: 1.4; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 16mm 18mm 12mm; background: #fff; box-shadow: 0 4px 24px rgba(0, 0, 0, .18); display: flex; flex-direction: column; }
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
        .note { font-size: 8pt; color: #6B7280; margin: 2mm 0 0; }
        .sign { margin-top: auto; padding-top: 14mm; display: flex; justify-content: flex-end; }
        .sign div { width: 62mm; border-top: 1px solid #111827; padding-top: 3px; text-align: center; font-size: 8.5pt; color: #4B5563; }
        .footer { margin-top: 8mm; text-align: center; font-size: 8pt; color: #4B5563; line-height: 1.45; padding-top: 4mm; border-top: 1px solid #E5E7EB; }
        .toolbar { width: 210mm; margin: 18px auto 0; display: flex; align-items: center; gap: 8px; font-family: Arial, Helvetica, sans-serif; }
        .toolbar a, .toolbar button { font: inherit; font-size: 12px; font-weight: bold; padding: 8px 14px; border-radius: 6px; border: 1px solid #003B95; background: #003B95; color: #fff; text-decoration: none; cursor: pointer; }
        .toolbar a.ghost { background: #fff; color: #003B95; }
        .toolbar .hint { font-size: 12px; color: #40506B; margin-left: auto; }
        @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; min-height: 297mm; } .toolbar { display: none; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print statement</button>
    <a class="ghost" href="{{ $backUrl }}">Back to payments</a>
    <span class="hint">Up to date as of now. Print it again any time.</span>
</div>

<main class="sheet">
    <header class="letterhead">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
        <span class="tagline">Endless Possibilities in Travel and Tourism</span>
        <span class="reference">SOA-{{ $file->reference }}</span>
    </header>

    <h1>Statement of Account</h1>
    <p class="asof">As of {{ now()->format('F j, Y g:i A') }}</p>

    <table class="meta">
        <tr><td class="k">Client</td><td><strong>{{ $file->retiree_name ?: '—' }}</strong>{{ $file->retiree_phone ? ' · '.$file->retiree_phone : '' }}{{ $file->retiree_email ? ' · '.$file->retiree_email : '' }}</td></tr>
        <tr><td class="k">Service</td><td>{{ $service }} · {{ $file->reference }}</td></tr>
        @if ($file->srrv_card_number)
            <tr><td class="k">SRRV no.</td><td>{{ $file->srrv_card_number }}</td></tr>
        @endif
        <tr><td class="k">Prepared by</td><td>{{ $file->creator?->name ?? '—' }}</td></tr>
    </table>

    @foreach ($byCurrency as $currency => $sum)
        <div class="summary">
            <div><span>Total ({{ $currency }})</span><strong>{{ $money($currency, $sum['total']) }}</strong></div>
            <div><span>Paid ({{ $currency }})</span><strong>{{ $money($currency, $sum['paid']) }}</strong></div>
            <div class="due"><span>Balance ({{ $currency }})</span><strong>{{ $money($currency, $sum['balance']) }}</strong></div>
        </div>
    @endforeach

    <h2>Charges</h2>
    <table class="grid">
        <tr><th>Description</th><th>Currency</th><th class="n">Amount</th><th class="n">Paid</th><th class="n">Balance</th></tr>
        @foreach ($lines as [$label, $currency, $amount, $paid])
            <tr>
                <td>{{ $label }}</td><td>{{ $currency }}</td>
                <td class="n">{{ $money($currency, $amount) }}</td><td class="n">{{ $money($currency, $paid) }}</td><td class="n">{{ $money($currency, max(0, $amount - $paid)) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Payments received</h2>
    <table class="grid">
        <tr><th>Date</th><th>OR / reference</th><th>Method</th><th>Received by</th><th class="n">Amount</th></tr>
        @forelse ($payments as $entry)
            <tr>
                <td>{{ $entry->received_at?->format('M j, Y g:i A') }}</td>
                <td>{{ $entry->reference ?: '—' }}</td>
                <td>{{ $entry->methodLabel() }}</td>
                <td>{{ $entry->receivedBy?->name ?? '—' }}</td>
                <td class="n">{{ $money($entry->currency ?: ($file->currency ?: 'PHP'), (float) $entry->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No payments recorded yet.</td></tr>
        @endforelse
    </table>
    @if ($isRenewal)
        <p class="note">The PRA fee is payable in US dollars and AMEGA's service fee in pesos; each is settled in its own currency.</p>
    @endif

    <div class="sign"><div>Cashier</div></div>

    <footer class="footer">
        Amega Travel and Tours Services · This statement shows the account as of the date above.
    </footer>
</main>

</body>
</html>
