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
        <h1 class="mt-2 text-2xl font-bold text-slate-900 font-mono">{{ $ticket->booking_reference }}</h1>
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

            @if ($blockedReason)
                <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">{{ $blockedReason }}</p>
            @elseif ($balance <= 0)
                <p class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-800">Paid in full. The agent can now issue the ticket.</p>
            @else
                @include('ticketing.tickets._ledger-form', [
                    'action' => route('cashier.payments.store', $ticket),
                    'amountLabel' => 'Amount received',
                    'submitLabel' => 'Record payment',
                    'amountDefault' => number_format($balance, 2, '.', ''),
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
                                    {{ $entry->isRefund() ? '−' : '' }}₱{{ number_format((float) $entry->amount, 2) }}
                                    <span class="font-normal text-slate-500">· {{ $entry->methodLabel() }}</span>
                                </p>
                                <p class="text-xs text-slate-500 truncate">
                                    {{ $entry->receiptNumber() }} · {{ $entry->received_at?->format('M j, Y g:i A') }}{{ $entry->receivedBy ? ' · '.$entry->receivedBy->name : '' }}
                                </p>
                            </div>
                            <a href="{{ route('cashier.payments.receipt', [$ticket, $entry]) }}" target="_blank" rel="noopener"
                               class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                <i class="fa-solid fa-receipt" aria-hidden="true"></i><span>Receipt</span>
                            </a>
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
