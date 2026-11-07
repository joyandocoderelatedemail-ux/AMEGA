@extends('layouts.cashier')

@section('title', 'Account lookup - Cashier - AMEGA')

@php
    /** @var string $term */
    /** @var \Illuminate\Support\Collection $accounts */
    $control = 'h-11 px-4 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $money = function (float $amount, ?string $currency = 'PHP'): string {
        $symbol = match ($currency ?: 'PHP') {
            'PHP' => '₱',
            'USD' => 'US$',
            default => ($currency ?: 'PHP').' ',
        };

        return $symbol.number_format($amount, 2);
    };
    $badge = fn (string $tone): string => match ($tone) {
        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
        'rose' => 'bg-rose-50 text-rose-700 border-rose-200',
        default => 'bg-slate-100 text-slate-600 border-slate-200',
    };
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Account lookup</h1>
        <p class="text-sm text-slate-500">Search a client by name, email, phone, passport or reference to see everything they were billed and paid, across ticketing, visa assistance and PRA-SRRV.</p>
    </div>

    <form method="GET" action="{{ route('cashier.accounts') }}" class="flex flex-wrap gap-2" role="search">
        <label for="account-search" class="sr-only">Search an account</label>
        <input id="account-search" type="search" name="search" value="{{ $term }}" placeholder="Name, email, phone, passport or reference" autofocus
               class="{{ $control }} w-full sm:w-96">
        <button type="submit" class="h-11 px-5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Search</button>
    </form>

    @if ($term === '')
        <p class="rounded-xl bg-white border border-slate-200 px-5 py-12 text-center text-sm text-slate-500">Type at least two characters to find an account.</p>
    @elseif (mb_strlen($term) < 2)
        <p class="rounded-xl bg-white border border-slate-200 px-5 py-12 text-center text-sm text-slate-500">Type at least two characters to find an account.</p>
    @elseif ($accounts->isEmpty())
        <p class="rounded-xl bg-white border border-slate-200 px-5 py-12 text-center text-sm text-slate-500">No account matches “{{ $term }}”.</p>
    @else
        <p class="text-sm text-slate-500">
            {{ $accounts->count() }} {{ \Illuminate\Support\Str::plural('account', $accounts->count()) }} found{{ $accounts->count() >= \App\Support\CashierAccounts::MAX_ACCOUNTS ? ' (showing the most recent; narrow the search to see others)' : '' }}.
        </p>

        @foreach ($accounts as $account)
            <section class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-900 truncate">{{ $account['name'] ?: 'Unnamed client' }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ $account['email'] ?: 'No email' }} · {{ $account['phone'] ?: 'No phone' }}
                        </p>
                    </div>
                    <dl class="flex flex-wrap gap-x-8 gap-y-2">
                        @foreach ($account['totals'] as $currency => $row)
                            <div class="text-right">
                                <dt class="text-xs font-semibold text-slate-500">{{ count($account['totals']) > 1 ? $currency.' · ' : '' }}Billed / Paid / Balance</dt>
                                <dd class="text-sm font-bold tabular-nums">
                                    <span class="text-slate-900">{{ $money($row['total'], $currency) }}</span>
                                    <span class="text-slate-300">/</span>
                                    <span class="text-emerald-700">{{ $money($row['paid'], $currency) }}</span>
                                    <span class="text-slate-300">/</span>
                                    <span class="{{ $row['balance'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $money($row['balance'], $currency) }}</span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Ticket bookings --}}
                @if ($account['tickets'])
                    <div class="space-y-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket bookings ({{ count($account['tickets']) }})</h3>
                        @foreach ($account['tickets'] as $ticket)
                            @php
                                $balance = $ticket->balanceDue();
                                $status = match (true) {
                                    $ticket->isQuotation() => ['Quotation', 'slate'],
                                    $ticket->isCancelled() => ['Cancelled', 'rose'],
                                    $ticket->isAwaitingApproval() => ['Awaiting approval', 'amber'],
                                    $ticket->isFullyPaid() => ['Paid in full', 'emerald'],
                                    (float) $ticket->amount_paid > 0 => ['Partially paid', 'amber'],
                                    default => ['Unpaid', 'slate'],
                                };
                                $payments = $ticket->payments->sortBy('id');
                            @endphp
                            <details class="group rounded-lg border border-slate-200" @if ($accounts->count() === 1) open @endif>
                                <summary class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 cursor-pointer list-none [&::-webkit-details-marker]:hidden hover:bg-slate-50">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900"><span class="font-mono">{{ $ticket->booking_reference }}</span>
                                            <span class="ml-1 px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $badge($status[1]) }}">{{ $status[0] }}</span>
                                            @if ($ticket->hasTerms() && $ticket->overdueInstallments() > 0)
                                                <span class="ml-1 px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $badge('rose') }}">{{ $ticket->overdueInstallments() }} overdue</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-slate-500">{{ trim(($ticket->origin ? $ticket->origin.' → ' : '').$ticket->destination) }}{{ $ticket->departure_date ? ' · '.$ticket->departure_date->format('M j, Y') : '' }}</p>
                                    </div>
                                    <p class="text-sm tabular-nums whitespace-nowrap">
                                        <span class="text-slate-900">{{ $money((float) $ticket->total_amount) }}</span>
                                        <span class="text-slate-300">/</span> <span class="text-emerald-700">{{ $money((float) $ticket->amount_paid) }}</span>
                                        <span class="text-slate-300">/</span> <span class="{{ $balance > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $money($balance) }}</span>
                                    </p>
                                </summary>
                                <div class="border-t border-slate-200 px-4 py-3 space-y-3">
                                    @if ($payments->isEmpty())
                                        <p class="text-sm text-slate-500">No payments recorded.</p>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                                                        <th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Receipt</th><th class="py-2 pr-3">Payment</th><th class="py-2 pr-3">Method</th>
                                                        <th class="py-2 pr-3 text-right">Amount</th><th class="py-2 pr-3 text-right">Card charge</th><th class="py-2">Received by</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach ($payments as $entry)
                                                        <tr class="{{ $entry->isRefund() ? 'text-rose-700' : '' }}">
                                                            <td class="py-2 pr-3 whitespace-nowrap">{{ $entry->received_at?->format('M j, Y') }}</td>
                                                            <td class="py-2 pr-3 font-mono text-xs">{{ $entry->receiptNumber() }}</td>
                                                            <td class="py-2 pr-3">{{ $entry->sequenceLabel() }}</td>
                                                            <td class="py-2 pr-3">{{ $entry->methodLabel() }}</td>
                                                            <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ $entry->isRefund() ? '−' : '' }}{{ $money((float) $entry->amount) }}</td>
                                                            <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ $entry->hasSurcharge() ? $money((float) $entry->card_surcharge) : '—' }}</td>
                                                            <td class="py-2">{{ $entry->receivedBy?->name ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('cashier.payments.show', $ticket) }}" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open booking</a>
                                        <a href="{{ route('cashier.payments.soa', $ticket) }}" target="_blank" rel="noopener" class="inline-flex items-center h-9 px-3 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Statement of Account</a>
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endif

                {{-- Visa Assistance and PRA-SRRV files --}}
                @foreach ([['visa', 'Visa assistance', 'cashier.visa.show', 'total_amount'], ['srrv', 'PRA-SRRV', 'cashier.srrv.show', 'service_fee']] as [$kind, $heading, $showRoute, $totalColumn])
                    @if ($account[$kind])
                        <div class="space-y-2">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $heading }} ({{ count($account[$kind]) }})</h3>
                            @foreach ($account[$kind] as $file)
                                @php
                                    $currency = $file->currency ?: 'PHP';
                                    $total = (float) $file->{$totalColumn};
                                    $paid = (float) $file->amount_paid;
                                    $due = max(0, round($total - $paid, 2));
                                    $payments = $file->payments->sortBy('id');
                                @endphp
                                <details class="group rounded-lg border border-slate-200" @if ($accounts->count() === 1) open @endif>
                                    <summary class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 cursor-pointer list-none [&::-webkit-details-marker]:hidden hover:bg-slate-50">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-900"><span class="font-mono">{{ $file->reference }}</span>
                                                <span class="ml-1 px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $badge($total > 0 && $due <= 0 ? 'emerald' : ($paid > 0 ? 'amber' : 'slate')) }}">{{ $total > 0 && $due <= 0 ? 'Paid in full' : ($paid > 0 ? 'Partially paid' : ($total > 0 ? 'Unpaid' : 'No fee yet')) }}</span>
                                            </p>
                                            <p class="text-xs text-slate-500">{{ $heading }}{{ $file->created_at ? ' · opened '.$file->created_at->format('M j, Y') : '' }}</p>
                                        </div>
                                        <p class="text-sm tabular-nums whitespace-nowrap">
                                            <span class="text-slate-900">{{ $money($total, $currency) }}</span>
                                            <span class="text-slate-300">/</span> <span class="text-emerald-700">{{ $money($paid, $currency) }}</span>
                                            <span class="text-slate-300">/</span> <span class="{{ $due > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $money($due, $currency) }}</span>
                                        </p>
                                    </summary>
                                    <div class="border-t border-slate-200 px-4 py-3 space-y-3">
                                        @if ($payments->isEmpty())
                                            <p class="text-sm text-slate-500">No payments recorded.</p>
                                        @else
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                                                            <th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Method</th><th class="py-2 pr-3">Reference</th><th class="py-2 pr-3 text-right">Amount</th><th class="py-2">Received by</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        @foreach ($payments as $entry)
                                                            <tr>
                                                                <td class="py-2 pr-3 whitespace-nowrap">{{ $entry->received_at?->format('M j, Y') }}</td>
                                                                <td class="py-2 pr-3">{{ $entry->methodLabel() }}</td>
                                                                <td class="py-2 pr-3">{{ $entry->reference ?: '—' }}</td>
                                                                <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ $money((float) $entry->amount, $currency) }}</td>
                                                                <td class="py-2">{{ $entry->receivedBy?->name ?? '—' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                        <a href="{{ route($showRoute, $file->id) }}" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open file</a>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </section>
        @endforeach
    @endif
</div>
@endsection
