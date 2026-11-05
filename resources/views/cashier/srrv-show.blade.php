@extends('layouts.cashier')

@section('title', 'Payment '.$file->reference.' - Cashier - AMEGA')

{{-- One SRRV application at the cashier: what is owed, the payment form, and the payments taken so far. --}}
@php
    $currency = $file->currency ?: 'PHP';
    $total = (float) $file->service_fee;
    $paid = (float) $file->amount_paid;
    $balance = $file->outstandingBalance();
    $control = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('cashier.srrv.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>SRRV applications</span>
        </a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900 font-mono">{{ $file->reference }}</h1>
        <p class="text-sm text-slate-500">
            {{ $file->retiree_name }} · {{ ucfirst((string) $file->visa_class).' SRRV' }}{{ $file->pra_reference ? ' · '.$file->pra_reference : '' }}
            · Agent: {{ $file->creator?->name ?? '—' }}
            @if ($file->approval_status === 'approved')
                · Approved by {{ $file->reviewedBy?->name ?? 'an admin' }}{{ $file->reviewed_at ? ' on '.$file->reviewed_at->format('M j, Y') : '' }}
            @endif
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <ul class="text-sm text-rose-900 space-y-0.5">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Payment</h2>
                @if ($balance <= 0 && $total > 0)
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">Paid in full</span>
                @elseif ($paid > 0)
                    <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-700">Down payment taken</span>
                @else
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-600">Unpaid</span>
                @endif
            </div>

            <dl class="grid grid-cols-3 gap-3">
                <div><dt class="text-xs font-semibold text-slate-500">Total</dt><dd class="text-lg font-bold text-slate-900">{{ $currency }} {{ number_format($total, 2) }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Paid</dt><dd class="text-lg font-bold text-emerald-700">{{ $currency }} {{ number_format($paid, 2) }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Balance</dt><dd class="text-lg font-bold {{ $balance > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $currency }} {{ number_format($balance, 2) }}</dd></div>
            </dl>

            @if ($blockedReason)
                <p class="rounded-lg border px-3 py-2 text-sm {{ $balance <= 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">{{ $blockedReason }}</p>
            @else
                <form method="POST" action="{{ route('cashier.srrv.store', $file->id) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount received *</label>
                            <input id="amount" type="number" step="0.01" min="0.01" max="{{ $balance }}" name="amount" required value="{{ old('amount', number_format($balance, 2, '.', '')) }}" class="{{ $control }}">
                            <p class="mt-1 text-xs text-slate-500">A down payment, or the balance of {{ $currency }} {{ number_format($balance, 2) }}.</p>
                        </div>
                        <div>
                            <label for="method" class="block text-xs font-semibold text-slate-600 mb-1">Method *</label>
                            <select id="method" name="method" required class="{{ $control }}">
                                @foreach (\App\Models\SrrvPayment::METHODS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="reference" class="block text-xs font-semibold text-slate-600 mb-1">OR / reference no.</label>
                            <input id="reference" type="text" name="reference" maxlength="100" value="{{ old('reference') }}" class="{{ $control }}">
                        </div>
                        <div>
                            <label for="note" class="block text-xs font-semibold text-slate-600 mb-1">Note</label>
                            <input id="note" type="text" name="note" maxlength="255" value="{{ old('note') }}" class="{{ $control }}">
                        </div>
                    </div>
                    <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/60 p-3 cursor-pointer">
                        <input type="checkbox" name="cashier_acknowledged" value="1" class="w-4 h-4 mt-0.5 rounded">
                        <span class="text-sm text-slate-800">I acknowledge that I have received the amount entered above and that the payment details are correct. I, <strong>{{ auth()->user()->name }}</strong>, will be held accountable for any incorrect information.</span>
                    </label>
                    <button type="submit" class="h-10 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold inline-flex items-center gap-2">
                        <i class="fa-solid fa-cash-register" aria-hidden="true"></i> Record payment
                    </button>
                </form>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-3">
            <h2 class="text-base font-semibold text-slate-900">Payments</h2>
            @if ($file->payments->isEmpty())
                <p class="text-sm text-slate-500">No payments recorded yet.</p>
            @else
                <ul class="divide-y divide-slate-200">
                    @foreach ($file->payments as $entry)
                        <li class="py-2.5">
                            <p class="text-sm font-semibold text-slate-900">{{ $currency }} {{ number_format((float) $entry->amount, 2) }} <span class="font-normal text-slate-500">· {{ $entry->methodLabel() }}</span></p>
                            <p class="text-xs text-slate-500">{{ collect([$entry->reference ? 'OR '.$entry->reference : null, $entry->received_at?->format('M j, Y g:i A'), $entry->receivedBy?->name, $entry->note])->filter()->implode(' · ') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
