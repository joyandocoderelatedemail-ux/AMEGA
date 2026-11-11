@extends('layouts.cashier')

@section('title', 'Payment ' . $ticket->booking_reference . ' - Cashier - AMEGA')

@php
    $total = (float) $ticket->total_amount;
    $paid = (float) $ticket->amount_paid;
    $balance = $ticket->balanceDue();
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('cashier.dashboard') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>To collect</span>
        </a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900 font-mono">{{ $ticket->booking_reference }}</h1>
            <a href="{{ route('cashier.payments.soa', $ticket) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i><span>Statement of Account</span>
            </a>
        </div>
        <p class="text-sm text-slate-500">
            {{ $ticket->contact_name }} · Agent: {{ $ticket->createdBy?->name ?? '—' }}
            @if ($ticket->isApproved())
                · Approved by {{ $ticket->reviewedBy?->name ?? 'an admin' }}{{ $ticket->reviewed_at ? ' on '.$ticket->reviewed_at->format('M j, Y') : '' }}
            @endif
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <ul class="text-sm text-rose-900 space-y-0.5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Payment --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Payment</h2>
                @if ($ticket->isFullyPaid())
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">Paid in full</span>
                @elseif ($paid > 0)
                    <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-700">Partially paid</span>
                @else
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-600">Unpaid</span>
                @endif
            </div>

            <dl class="grid grid-cols-3 gap-3">
                <div><dt class="text-xs font-semibold text-slate-500">Total</dt><dd class="text-lg font-bold text-slate-900">₱{{ number_format($total, 2) }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Paid</dt><dd class="text-lg font-bold text-emerald-700">₱{{ number_format($paid, 2) }}</dd></div>
                <div><dt class="text-xs font-semibold text-slate-500">Balance</dt><dd class="text-lg font-bold {{ $balance > 0 ? 'text-amber-700' : 'text-slate-400' }}">₱{{ number_format($balance, 2) }}</dd></div>
            </dl>

            {{-- Payment type: the whole balance in one go, or terms (several payments, a set number of days apart). --}}
            @php
                $schedule = $ticket->paymentSchedule();
                $canSetTerms = ! $blockedReason && $balance > 0;
            @endphp
            <div class="rounded-lg border border-slate-200 p-4 space-y-3" id="terms"
                 x-data="{ mode: @js(old('payment_type', $ticket->hasTerms() ? 'terms' : 'full')) }">
                @if ($canSetTerms)
                    <form method="POST" action="{{ route('cashier.payments.terms', $ticket) }}" class="space-y-3">
                        @csrf
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Payment type</span>
                            <input type="hidden" name="payment_type" :value="mode">
                            <div class="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-0.5" role="group" aria-label="Payment type">
                                <button type="button" @click="mode = 'full'" :aria-pressed="mode === 'full'"
                                        class="px-4 py-1.5 rounded-md text-sm font-semibold" :class="mode === 'full' ? 'bg-navy-700 text-white' : 'text-slate-600'">Full payment</button>
                                <button type="button" @click="mode = 'terms'" :aria-pressed="mode === 'terms'"
                                        class="px-4 py-1.5 rounded-md text-sm font-semibold" :class="mode === 'terms' ? 'bg-navy-700 text-white' : 'text-slate-600'">Terms</button>
                            </div>
                        </div>

                        <div x-show="mode === 'full'" class="space-y-2">
                            <p class="text-sm text-slate-600">The client pays the whole balance, ₱{{ number_format($balance, 2) }}, in one go.</p>
                            @if ($ticket->hasTerms())
                                <button type="submit" class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Switch to full payment (removes the terms)</button>
                            @endif
                        </div>

                        <div x-show="mode === 'terms'" x-cloak class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="terms_payments" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Number of payments</label>
                                <select id="terms_payments" name="terms_payments" class="rounded-lg border-slate-300 text-sm focus:border-navy-500 focus:ring-navy-500">
                                    @foreach (range(2, 12) as $n)
                                        <option value="{{ $n }}" @selected((int) old('terms_payments', $ticket->terms_payments ?: 3) === $n)>{{ $n }} payments</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="terms_days" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Days between payments</label>
                                <input type="number" id="terms_days" name="terms_days" min="1" max="365" value="{{ old('terms_days', $ticket->terms_days ?: 30) }}" :required="mode === 'terms'"
                                       class="w-28 rounded-lg border-slate-300 text-sm tabular-nums focus:border-navy-500 focus:ring-navy-500">
                            </div>
                            <button type="submit" class="px-4 py-2.5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                                {{ $ticket->hasTerms() ? 'Update terms' : 'Set terms' }}
                            </button>
                            <p class="basis-full text-xs text-slate-500">The total is split into equal payments: the first due today, the rest that many days apart, the last marked as the final payment.</p>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-slate-600">
                        <span class="font-semibold text-slate-900">Payment type:</span>
                        {{ $ticket->hasTerms() ? 'Terms' : 'Full payment' }}
                    </p>
                @endif

                @if ($ticket->hasTerms())
                    <p class="text-sm text-slate-600">
                        {{ $ticket->terms_payments }} payments, {{ $ticket->terms_days }} {{ \Illuminate\Support\Str::plural('day', (int) $ticket->terms_days) }} apart, from {{ $ticket->terms_start->format('M j, Y') }}@if ($ticket->termsSetBy) · set by {{ $ticket->termsSetBy->name }}@endif
                    </p>
                    @if ($ticket->overdueInstallments() > 0)
                        <p class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-800">
                            {{ $ticket->overdueInstallments() }} scheduled {{ \Illuminate\Support\Str::plural('payment', $ticket->overdueInstallments()) }} past due.
                        </p>
                    @endif
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                                    <th class="py-2 pr-3">Payment</th><th class="py-2 pr-3">Due</th><th class="py-2 pr-3 text-right">Amount</th><th class="py-2 pr-3 text-right">Paid</th><th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($schedule as $row)
                                    <tr>
                                        <td class="py-2 pr-3 font-semibold text-slate-900">{{ $row['label'] }}</td>
                                        <td class="py-2 pr-3 text-slate-600">{{ $row['due']->format('M j, Y') }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">₱{{ number_format($row['amount'], 2) }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">₱{{ number_format($row['paid'], 2) }}</td>
                                        <td class="py-2">
                                            <span class="px-2 py-0.5 rounded-md text-xs font-semibold border
                                                {{ match ($row['status']) {
                                                    'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'overdue' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                    'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                                } }}">{{ ucfirst($row['status']) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- A booking under a company with a credit account can go on the account: issued unpaid, collected later. --}}
            {{-- A group pays as it books: only a company can have a credit account. --}}
            @if (($company = $ticket->corporateAccount) && ! $company->isGroup())
                @php
                    $chargeRefusal = \App\Support\CorporateReceivables::chargeRefusal($ticket);
                    $overLimit = $chargeRefusal ? 0 : \App\Support\CorporateReceivables::overLimitBy($ticket);
                    $creditLeft = \App\Support\CorporateReceivables::available($company);
                @endphp
                @if ($ticket->isChargedToAccount())
                    <div class="rounded-lg bg-navy-50 border border-navy-100 px-3 py-2.5 text-sm text-navy-900">
                        <p class="font-semibold">On {{ $company->company_name }}'s account</p>
                        <p class="mt-0.5">₱{{ number_format((float) $ticket->ar_amount, 2) }} charged {{ $ticket->charged_to_account_at->format('M j, Y') }}{{ $ticket->chargedToAccountBy ? ' by '.$ticket->chargedToAccountBy->name : '' }}, due {{ $ticket->ar_due_date?->format('M j, Y') }}. The ticket can be issued; the company pays through its account.</p>
                        <a href="{{ route('cashier.receivables.show', $company) }}" class="mt-1 inline-block font-semibold underline">Open the company's account</a>
                    </div>
                @elseif ($chargeRefusal === null)
                    <form method="POST" action="{{ route('cashier.payments.charge', $ticket) }}" class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-3 space-y-2"
                          onsubmit="return confirm('Charge this balance to the company account? The ticket can then be issued unpaid.');">
                        @csrf
                        <p class="text-sm font-semibold text-slate-900">Corporate account: {{ $company->company_name }}</p>
                        <p class="text-xs text-slate-600">
                            Net {{ $company->credit_terms_days }} days
                            · {{ $creditLeft === null ? 'no credit limit' : '₱'.number_format(max($creditLeft, 0), 2).' of ₱'.number_format((float) $company->credit_limit, 2).' credit left' }}
                        </p>
                        @if ($overLimit > 0)
                            <p class="text-xs font-semibold text-rose-700">Charging this would put the company ₱{{ number_format($overLimit, 2) }} over its credit limit.</p>
                            @if (auth()->user()->isAdmin())
                                <label class="flex items-start gap-2 text-xs text-slate-700">
                                    <input type="checkbox" name="override_limit" value="1" class="mt-0.5 rounded border-slate-300">
                                    <span>I approve going over the credit limit for this booking. I, <strong>{{ auth()->user()->name }}</strong>, am accountable for it.</span>
                                </label>
                            @else
                                <p class="text-xs text-slate-600">Only an admin can approve going over the limit.</p>
                            @endif
                        @endif
                        @if ($overLimit <= 0 || auth()->user()->isAdmin())
                            <button type="submit" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-white border border-navy-700 text-navy-700 text-sm font-semibold hover:bg-navy-50">
                                <i class="fa-solid fa-building" aria-hidden="true"></i><span>Charge ₱{{ number_format($balance, 2) }} to company account</span>
                            </button>
                        @endif
                    </form>
                @endif
            @endif

            @if ($blockedReason)
                <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">{{ $blockedReason }}</p>
            @elseif ($balance <= 0)
                <p class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-800">Paid in full. The agent can now issue the ticket.</p>
            @else
                @if ($next = $ticket->nextInstallment())
                    <p class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        Next due: <strong>{{ $next['label'] }}</strong>, ₱{{ number_format($next['amount'] - $next['paid'], 2) }}
                        by {{ $next['due']->format('M j, Y') }}{{ $next['status'] === 'overdue' ? ' (overdue)' : '' }}.
                    </p>
                @endif
                @include('ticketing.tickets._ledger-form', [
                    'action' => route('cashier.payments.store', $ticket),
                    'amountLabel' => 'Amount received',
                    'submitLabel' => 'Record payment',
                    'amountDefault' => number_format($ticket->suggestedPayment(), 2, '.', ''),
                    'receiptUpload' => true,
                    'currency' => true,
                    'acknowledgement' => 'I acknowledge that I have received the amount entered above and that the payment details are correct. I, <strong>'.e(auth()->user()->name).'</strong>, will be held accountable for any incorrect information.',
                ])
            @endif
        </div>

        {{-- Payments and receipts --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-3">
            <h2 class="text-base font-semibold text-slate-900">Payments</h2>
            @if ($ticket->payments->isEmpty())
                <p class="text-sm text-slate-500">No payments recorded yet.</p>
            @else
                <ul class="divide-y divide-slate-200">
                    @foreach ($ticket->payments as $entry)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold {{ $entry->isRefund() ? 'text-rose-700' : 'text-slate-900' }}">
                                    {{ $entry->isRefund() ? '−' : '' }}{{ $entry->amountLabel() }}
                                    <span class="font-normal text-slate-500">· {{ $entry->methodLabel() }}</span>
                                    <span class="ml-1 inline-block px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $entry->is_final ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">{{ $entry->sequenceLabel() }}</span>
                                </p>
                                @if ($entry->hasSurcharge())
                                    <p class="text-xs text-amber-700 font-semibold">+ ₱{{ number_format((float) $entry->card_surcharge, 2) }} card charge (4%) · collected ₱{{ number_format($entry->totalCollected(), 2) }}</p>
                                @endif
                                <p class="text-xs text-slate-500 truncate">
                                    {{ $entry->receiptNumber() }} · {{ $entry->received_at?->format('M j, Y g:i A') }}{{ $entry->receivedBy ? ' · '.$entry->receivedBy->name : '' }}
                                </p>
                            </div>
                            <div class="shrink-0 flex flex-wrap items-center justify-end gap-2">
                                @if ($entry->hasReceiptFile())
                                    <a href="{{ route('cashier.payments.receipt-file', [$ticket, $entry]) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-emerald-300 bg-emerald-50 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                                       title="{{ $entry->receipt_file_name }}">
                                        <i class="fa-solid fa-file-image" aria-hidden="true"></i><span>Actual receipt</span>
                                    </a>
                                @endif
                                {{-- Attach the actual receipt (or replace it). --}}
                                <form method="POST" action="{{ route('cashier.payments.receipt-file.store', [$ticket, $entry]) }}" enctype="multipart/form-data" class="m-0"
                                      x-data x-ref="form">
                                    @csrf
                                    <label class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                        <span>{{ $entry->hasReceiptFile() ? 'Replace' : 'Attach receipt' }}</span>
                                        <input type="file" name="receipt_file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                                               aria-label="Attach the actual receipt for {{ $entry->receiptNumber() }}" @change="$refs.form.submit()">
                                    </label>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- The approved booking --}}
    @include('ticketing.tickets._confirmation', ['readonly' => true, 'documentLinks' => false])
</div>
@endsection
