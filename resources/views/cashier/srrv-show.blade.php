@extends('layouts.cashier')

@section('title', 'Payment '.$file->reference.' - Cashier - AMEGA')

{{-- One SRRV application or ID renewal at the cashier: what is owed, the payment form, and the payments taken so far. --}}
@php
    $isRenewal = $file instanceof \App\Models\SrrvRenewal;
    $currency = $file->currency ?: 'PHP';
    $total = $isRenewal ? $file->totalDue() : (float) $file->service_fee;
    $paid = (float) $file->amount_paid;
    $balance = $file->outstandingBalance();
    $feeBalance = $isRenewal ? $file->feeBalance() : 0;
    $serviceBalance = $isRenewal ? $file->serviceBalance() : $balance;
    $serviceCurrency = $isRenewal ? ($file->service_currency ?: 'PHP') : $currency;
    $control = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('cashier.srrv.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>SRRV applications</span>
        </a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900 font-mono">{{ $file->reference }}</h1>
            <a href="{{ route($isRenewal ? 'cashier.srrv.renewals.soa' : 'cashier.srrv.soa', $file->id) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i><span>Statement of Account</span>
            </a>
        </div>
        <p class="text-sm text-slate-500">
            {{ $file->retiree_name }} · {{ ucfirst((string) $file->visa_class).($isRenewal ? ' ID Renewal' : ' SRRV') }}{{ ! $isRenewal && $file->pra_reference ? ' · '.$file->pra_reference : '' }}
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

            @if ($isRenewal)
                <dl class="grid grid-cols-3 gap-3">
                    <div><dt class="text-xs font-semibold text-slate-500">PRA fee (USD)</dt><dd class="text-lg font-bold text-slate-900">USD {{ number_format((float) $file->fee_amount, 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Fee paid</dt><dd class="text-lg font-bold text-emerald-700">USD {{ number_format((float) $file->fee_paid, 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Fee balance</dt><dd class="text-lg font-bold {{ $feeBalance > 0 ? 'text-amber-700' : 'text-slate-400' }}">USD {{ number_format($feeBalance, 2) }}</dd></div>
                </dl>
                <dl class="grid grid-cols-3 gap-3">
                    <div><dt class="text-xs font-semibold text-slate-500">Service fee ({{ $serviceCurrency }})</dt><dd class="text-lg font-bold text-slate-900">{{ $serviceCurrency }} {{ number_format($file->serviceDue(), 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Service paid</dt><dd class="text-lg font-bold text-emerald-700">{{ $serviceCurrency }} {{ number_format($paid, 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Service balance</dt><dd class="text-lg font-bold {{ $serviceBalance > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $serviceCurrency }} {{ number_format($serviceBalance, 2) }}</dd></div>
                </dl>
            @else
                <dl class="grid grid-cols-3 gap-3">
                    <div><dt class="text-xs font-semibold text-slate-500">Total</dt><dd class="text-lg font-bold text-slate-900">{{ $currency }} {{ number_format($total, 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Paid</dt><dd class="text-lg font-bold text-emerald-700">{{ $currency }} {{ number_format($paid, 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold text-slate-500">Balance</dt><dd class="text-lg font-bold {{ $balance > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $currency }} {{ number_format($balance, 2) }}</dd></div>
                </dl>
            @endif

            @if ($isRenewal)
                {{-- Split dues: the PRA fee in USD, and AMEGA’s service fee in PHP less the discount. --}}
                <ul class="text-sm text-slate-600 space-y-1">
                    <li class="flex justify-between gap-3"><span>PRA ID renewal fee{{ $file->years_paid > 1 ? ' ('.$file->years_paid.' years)' : '' }}</span><span>USD {{ number_format((float) $file->fee_amount, 2) }}</span></li>
                    <li class="flex justify-between gap-3"><span>Service fee</span><span>{{ $serviceCurrency }} {{ number_format((float) $file->service_fee, 2) }}</span></li>
                    @if ((float) $file->discount_amount > 0)
                        <li class="flex justify-between gap-3"><span>Discount{{ $file->discount_type === 'percent' ? ' ('.(float) $file->discount_value.'%)' : '' }}</span><span>&minus; {{ $serviceCurrency }} {{ number_format((float) $file->discount_amount, 2) }}</span></li>
                    @endif
                </ul>
            @endif

            @if ($blockedReason)
                <p class="rounded-lg border px-3 py-2 text-sm {{ $balance <= 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">{{ $blockedReason }}</p>
            @else
                <form method="POST" action="{{ route($isRenewal ? 'cashier.srrv.renewals.store' : 'cashier.srrv.store', $file->id) }}" class="space-y-3">
                    @csrf
                    @if ($isRenewal)
                        <div>
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Apply to *</span>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Apply payment to">
                                <label class="inline-flex items-center gap-1.5 h-10 px-3 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 has-checked:border-navy-600 has-checked:bg-navy-50 cursor-pointer">
                                    <input type="radio" name="apply_to" value="fee" @checked(old('apply_to', $feeBalance > 0 ? 'fee' : 'service') === 'fee') class="accent-navy-700"> PRA fee (USD {{ number_format($feeBalance, 2) }} due)
                                </label>
                                <label class="inline-flex items-center gap-1.5 h-10 px-3 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 has-checked:border-navy-600 has-checked:bg-navy-50 cursor-pointer">
                                    <input type="radio" name="apply_to" value="service" @checked(old('apply_to') === 'service' || ($feeBalance <= 0 && $serviceBalance > 0)) class="accent-navy-700"> Service fee ({{ $serviceCurrency }} {{ number_format($serviceBalance, 2) }} due)
                                </label>
                            </div>
                            @error('apply_to') <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount received *</label>
                            <input id="amount" type="number" step="0.01" min="0.01" max="{{ $isRenewal ? max($feeBalance, $serviceBalance) : $balance }}" name="amount" required value="{{ old('amount', number_format($isRenewal ? max($feeBalance, $serviceBalance) : $balance, 2, '.', '')) }}" class="{{ $control }}">
                            <p class="mt-1 text-xs text-slate-500">@if ($isRenewal) A down payment, or the selected due: USD {{ number_format($feeBalance, 2) }} / {{ $serviceCurrency }} {{ number_format($serviceBalance, 2) }}.@else A down payment, or the balance of {{ $currency }} {{ number_format($balance, 2) }}.@endif</p>
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
                            <p class="text-sm font-semibold text-slate-900">{{ $entry->currency ?? $currency }} {{ number_format((float) $entry->amount, 2) }} <span class="font-normal text-slate-500">· {{ $entry->methodLabel() }}</span></p>
                            <p class="text-xs text-slate-500">{{ collect([$entry->reference ? 'OR '.$entry->reference : null, $entry->received_at?->format('M j, Y g:i A'), $entry->receivedBy?->name, $entry->note])->filter()->implode(' · ') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
