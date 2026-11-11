@extends('layouts.cashier')

@section('title', $company->company_name.' - Corporate A/R')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
    $field = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600';
    $buckets = \App\Support\CorporateReceivables::BUCKETS;
    $owed = $aging['total'];
@endphp

@section('content')
<div class="space-y-6">
    <a href="{{ route('cashier.receivables.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">&larr; Corporate A/R</a>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $company->company_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $company->credit_enabled ? 'Credit account' : 'Credit turned off' }} · Net {{ $company->credit_terms_days }} days
                · {{ $company->credit_limit !== null ? 'limit ₱'.number_format((float) $company->credit_limit, 2) : 'no credit limit' }}
                @if ($company->credit_interest_rate !== null) · late interest {{ rtrim(rtrim(number_format((float) $company->credit_interest_rate, 2), '0'), '.') }}% per month @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('cashier.receivables.history', $company) }}" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Full history</span>
        </a>
        <form method="GET" action="{{ route('cashier.receivables.statement', $company) }}" target="_blank" class="flex items-center gap-2">
            <label for="month" class="sr-only">Statement month</label>
            <input id="month" type="month" name="month" value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" class="h-9 px-2 rounded-lg border border-slate-300 text-sm">
            <button type="submit" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i><span>Statement of Account</span>
            </button>
        </form>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-900" role="alert">
            @foreach ($errors->all() as $message) <p>{{ $message }}</p> @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="{{ $card }} p-4 col-span-2 lg:col-span-1">
            <div class="text-xs font-semibold text-slate-500">Total owed</div>
            <div class="mt-1 text-xl font-bold text-slate-900">₱{{ number_format($owed, 2) }}</div>
            @if ($available !== null)
                <div class="text-xs {{ $available < 0 ? 'text-rose-700 font-semibold' : 'text-slate-500' }}">{{ $available < 0 ? '₱'.number_format(abs($available), 2).' over the limit' : '₱'.number_format($available, 2).' credit left' }}</div>
            @endif
        </div>
        @foreach ($buckets as $key => $name)
            <div class="{{ $card }} p-4">
                <div class="text-xs font-semibold text-slate-500">{{ $name }}</div>
                <div class="mt-1 text-base font-bold {{ $key !== 'current' && $aging[$key] > 0 ? 'text-rose-700' : 'text-slate-900' }}">₱{{ number_format($aging[$key], 2) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="{{ $card }} lg:col-span-2 overflow-x-auto">
            <h2 class="px-5 pt-5 text-base font-semibold text-slate-900">Unpaid tickets</h2>
            @if ($items->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">Nothing outstanding on this account.</p>
            @else
                <table class="mt-3 min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-5 py-2.5 text-xs font-semibold text-slate-500">Booking</th>
                            <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Charged</th>
                            <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Due</th>
                            <th class="px-3 py-2.5 text-xs font-semibold text-slate-500 text-right">Charged</th>
                            <th class="px-5 py-2.5 text-xs font-semibold text-slate-500 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($items as $ticket)
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('cashier.payments.show', $ticket) }}" class="font-mono font-semibold text-navy-700 hover:underline">{{ $ticket->booking_reference }}</a>
                                    <span class="block text-xs text-slate-500">{{ $ticket->contact_name }} · {{ $ticket->destination }}</span>
                                </td>
                                <td class="px-3 py-3 text-slate-700 whitespace-nowrap">{{ $ticket->charged_to_account_at->format('M j, Y') }}</td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="text-slate-700">{{ $ticket->ar_due_date?->format('M j, Y') }}</span>
                                    @if ($ticket->daysOverdue() > 0)
                                        <span class="block text-xs font-semibold text-rose-700">{{ $ticket->daysOverdue() }} {{ \Illuminate\Support\Str::plural('day', $ticket->daysOverdue()) }} overdue</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right text-slate-700 whitespace-nowrap">₱{{ number_format((float) $ticket->ar_amount, 2) }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">₱{{ number_format($ticket->balanceDue(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="{{ $card }} p-5 space-y-4 h-fit">
            <h2 class="text-base font-semibold text-slate-900">Record a company payment</h2>
            @if ($owed <= 0)
                <p class="text-sm text-slate-500">There is nothing to collect on this account.</p>
            @else
                <p class="text-xs text-slate-500">The payment clears the unpaid tickets oldest due first. It can be part of what is owed.</p>
                <form method="POST" action="{{ route('cashier.receivables.store', $company) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="amount" class="{{ $label }}">Amount received (₱)</label>
                        <input id="amount" type="number" name="amount" step="0.01" min="0.01" max="{{ $owed }}" required value="{{ old('amount', number_format($owed, 2, '.', '')) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="method" class="{{ $label }}">Paid by</label>
                        <select id="method" name="method" required class="{{ $field }}">
                            @foreach (\App\Models\CorporatePayment::METHODS as $key => $name)
                                <option value="{{ $key }}" @selected(old('method') === $key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reference" class="{{ $label }}">Reference <span class="font-normal text-slate-400">(cheque or transfer no.)</span></label>
                        <input id="reference" type="text" name="reference" maxlength="100" value="{{ old('reference') }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="received_at" class="{{ $label }}">Date received</label>
                        <input id="received_at" type="date" name="received_at" max="{{ today()->toDateString() }}" value="{{ old('received_at', today()->toDateString()) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="note" class="{{ $label }}">Note <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="note" type="text" name="note" maxlength="255" value="{{ old('note') }}" class="{{ $field }}">
                    </div>
                    <label class="flex items-start gap-2 text-xs text-slate-700">
                        <input type="checkbox" name="acknowledged" value="1" required class="mt-0.5 rounded border-slate-300">
                        <span>I acknowledge that I have received the amount entered above. I, <strong>{{ auth()->user()->name }}</strong>, will be held accountable for any incorrect information.</span>
                    </label>
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Record payment</button>
                </form>
            @endif
        </div>
    </div>

    <div class="{{ $card }} overflow-x-auto">
        <div class="px-5 pt-5 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-900">Payments received <span class="text-xs font-normal text-slate-500">(latest 20)</span></h2>
            <a href="{{ route('cashier.receivables.history', $company) }}" class="text-sm font-semibold text-navy-700 hover:underline">See the full history</a>
        </div>
        @if ($payments->isEmpty())
            <p class="px-5 py-6 text-sm text-slate-500">No company payments recorded yet.</p>
        @else
            <table class="mt-3 min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 py-2.5 text-xs font-semibold text-slate-500">Payment</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Paid by</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Applied to</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Received by</th>
                        <th class="px-5 py-2.5 text-xs font-semibold text-slate-500 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="font-mono font-semibold text-slate-900">{{ $payment->number() }}</span>
                                <span class="block text-xs text-slate-500">{{ $payment->received_at->format('M j, Y') }}</span>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $payment->methodLabel() }}@if ($payment->reference) <span class="block text-xs text-slate-500">{{ $payment->reference }}</span>@endif</td>
                            <td class="px-3 py-3 text-xs text-slate-600">
                                @foreach ($payment->entries as $entry)
                                    <div>{{ $entry->booking?->booking_reference }}: ₱{{ number_format((float) $entry->amount, 2) }}</div>
                                @endforeach
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $payment->receivedBy?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">₱{{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
