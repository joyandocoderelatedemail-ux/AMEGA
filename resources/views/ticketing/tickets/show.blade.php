@extends('layouts.ticketing')

@section('title', 'Ticket Details - ' . $ticket->booking_reference)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
        <a href="{{ route('ticketing.tickets.index') }}" class="inline-flex items-center gap-1.5 text-xs font-heading font-bold text-dark/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Ticket Directory</span>
        </a>

        <div class="flex items-center gap-2">
            @if($ticket->bookingAgreement)
                <a href="{{ route('ticketing.agreements.show', $ticket->bookingAgreement) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white font-heading font-bold text-xs transition-colors shadow-sm hover:bg-navy">
                    <i data-lucide="file-check-2" class="w-4 h-4 text-accent"></i>
                    <span>View Booking Agreement</span>
                </a>
            @else
                <a href="{{ route('ticketing.agreements.create', $ticket) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white font-heading font-bold text-xs transition-colors shadow-sm hover:bg-navy">
                    <i data-lucide="file-signature" class="w-4 h-4 text-accent"></i>
                    <span>Generate Booking Agreement</span>
                </a>
            @endif

            <a href="{{ route('ticketing.tickets.voucher', ['ticket' => $ticket, 'autoprint' => 1]) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Ticket Voucher</span>
            </a>
            <a href="{{ route('ticketing.tickets.consent', $ticket) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors shadow-sm">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                <span>Consent Form</span>
            </a>
            @unless ($ticket->isIssued() || $ticket->isCancelled())
                <a href="{{ route('ticketing.tickets.edit', $ticket) }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors shadow-sm">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                    <span>Edit Booking</span>
                </a>
            @endunless
            <a href="{{ route('ticketing.tickets.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent text-dark font-heading font-extrabold text-xs uppercase tracking-wider hover:bg-accent-dark transition-all shadow-md shadow-accent/20">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>New Booking</span>
            </a>
        </div>
    </div>

    {{-- Payment and issuance. Working controls, so they stay off the printed
         voucher. Full payment is required before the issue action unlocks, and
         issuing is gated behind the data privacy declaration. --}}
    @php
        $total = (float) $ticket->total_amount;
        $paid = (float) $ticket->amount_paid;
        $balance = $ticket->balanceDue();
        $paidPct = $total > 0 ? min(100, round(($paid / $total) * 100)) : 0;

        $approvalBadge = match (true) {
            $ticket->isAwaitingApproval() => ['Pending approval', 'bg-amber-50', 'text-amber-700', 'ring-amber-200', 'bg-amber-500'],
            $ticket->isRejected() => ['Returned by admin', 'bg-rose-50', 'text-rose-700', 'ring-rose-200', 'bg-rose-500'],
            default => null,
        };

        $payTone = match ($ticket->payment_status) {
            \App\Models\TicketBooking::PAYMENT_FULL => ['bg-emerald-50', 'text-emerald-700', 'ring-emerald-200', 'bg-emerald-500'],
            \App\Models\TicketBooking::PAYMENT_PARTIAL => ['bg-amber-50', 'text-amber-700', 'ring-amber-200', 'bg-amber-500'],
            default => ['bg-slate-100', 'text-slate-600', 'ring-slate-200', 'bg-slate-400'],
        };
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 print:hidden">

        {{-- Payment --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="font-heading text-base font-bold text-slate-900">Payment</h2>
                @if ($approvalBadge)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold ring-1 {{ $approvalBadge[1] }} {{ $approvalBadge[2] }} {{ $approvalBadge[3] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $approvalBadge[4] }}"></span>
                        {{ $approvalBadge[0] }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold ring-1 {{ $payTone[0] }} {{ $payTone[1] }} {{ $payTone[2] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $payTone[3] }}"></span>
                        {{ ucfirst(str_replace('_', ' ', $ticket->payment_status)) }}
                    </span>
                @endif
            </div>

            <dl class="grid grid-cols-3 gap-3 mb-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</dt>
                    <dd class="text-sm font-bold text-slate-900 tabular-nums mt-1">&#8369;{{ number_format($total, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paid</dt>
                    <dd class="text-sm font-bold text-emerald-700 tabular-nums mt-1">&#8369;{{ number_format($paid, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Balance</dt>
                    <dd class="text-sm font-bold {{ $balance > 0 ? 'text-amber-700' : 'text-slate-400' }} tabular-nums mt-1">&#8369;{{ number_format($balance, 2) }}</dd>
                </div>
            </dl>

            <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden mb-4"
                 role="img" aria-label="{{ $paidPct }} percent of the total has been paid">
                <div class="h-full rounded-full {{ $ticket->isFullyPaid() ? 'bg-emerald-500' : 'bg-amber-400' }}" style="width: {{ $paidPct }}%"></div>
            </div>

            @if ($ticket->isIssued())
                <p class="text-xs text-slate-500">Payment is locked because this ticket has been issued.</p>
            @elseif ($ticket->isCancelled())
                @if ($paid > 0)
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 mb-4">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                        <p class="text-xs text-amber-800">
                            This booking is cancelled but <span class="font-bold tabular-nums">&#8369;{{ number_format($paid, 2) }}</span>
                            is still held. Settle it with the client, then record the refund here.
                        </p>
                    </div>
                    @include('ticketing.tickets._ledger-form', [
                        'action' => route('ticketing.tickets.refund', $ticket),
                        'amountLabel' => 'Amount refunded',
                        'submitLabel' => 'Record refund',
                        'amountDefault' => number_format($paid, 2, '.', ''),
                    ])
                @else
                    <p class="text-xs text-slate-500">This booking is cancelled and nothing is held against it.</p>
                @endif
            @elseif ($total <= 0)
                <p class="text-xs text-amber-700">This booking has no total amount yet, so payment cannot be recorded against it.</p>
            @elseif ($ticket->isAwaitingApproval())
                <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200">
                    <i class="fa-solid fa-hourglass-half text-amber-600 mt-0.5" aria-hidden="true"></i>
                    <p class="text-xs text-amber-800">
                        Sent to the admin for approval{{ $ticket->approval_requested_at ? ' on '.$ticket->approval_requested_at->format('M j, Y g:i A') : '' }}.
                        Once approved, the cashier records the payment.
                    </p>
                </div>
            @elseif ($ticket->isRejected())
                <div class="p-3 rounded-lg bg-rose-50 ring-1 ring-rose-200 space-y-2">
                    <p class="text-xs font-semibold text-rose-800">
                        Returned by {{ $ticket->reviewedBy?->name ?? 'the admin' }}{{ $ticket->reviewed_at ? ' on '.$ticket->reviewed_at->format('M j, Y g:i A') : '' }}:
                    </p>
                    <p class="text-xs text-rose-800 whitespace-pre-line">{{ $ticket->review_note }}</p>
                    <a href="{{ route('ticketing.tickets.edit', $ticket) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700">
                        <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                        <span>Correct and resubmit</span>
                    </a>
                </div>
            @elseif ($balance <= 0)
                <p class="text-xs text-slate-500">Paid in full. Nothing more to record.</p>
            @elseif (auth()->user()->isAdmin())
                @include('ticketing.tickets._ledger-form', [
                    'action' => route('ticketing.tickets.payment', $ticket),
                    'amountLabel' => 'Amount received',
                    'submitLabel' => 'Record payment',
                    'amountDefault' => number_format($balance, 2, '.', ''),
                ])
            @else
                <div class="flex items-start gap-2.5 p-3 rounded-lg bg-slate-50 ring-1 ring-slate-200">
                    <i class="fa-solid fa-cash-register text-slate-500 mt-0.5" aria-hidden="true"></i>
                    <p class="text-xs text-slate-600">
                        Approved{{ $ticket->reviewedBy ? ' by '.$ticket->reviewedBy->name : '' }}. Waiting for the cashier to record the payment.
                    </p>
                </div>
            @endif
        </div>

        {{-- Issue ticket --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 flex flex-col">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="font-heading text-base font-bold text-slate-900">Issue Ticket</h2>
                @if ($ticket->isIssued())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold ring-1 bg-emerald-50 text-emerald-700 ring-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Issued
                    </span>
                @endif
            </div>

            @if ($ticket->isIssued())
                <p class="text-sm text-slate-600">
                    Issued {{ $ticket->issued_at?->format('M j, Y \a\t g:ia') }}
                    @if ($ticket->issuedBy)
                        by <span class="font-semibold text-slate-900">{{ $ticket->issuedBy->name }}</span>
                    @endif.
                </p>
                <p class="text-xs text-slate-500 mt-2">
                    Data privacy consent recorded {{ $ticket->consent_accepted_at?->format('M j, Y \a\t g:ia') }}.
                </p>
            @elseif ($ticket->isCancelled())
                <p class="text-sm text-slate-600">A cancelled booking cannot be issued.</p>
            @elseif ($ticket->isAwaitingApproval() || $ticket->isRejected())
                <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200">
                    <i class="fa-solid fa-lock text-amber-600 mt-0.5" aria-hidden="true"></i>
                    <p class="text-xs text-amber-800">
                        {{ $ticket->isRejected() ? 'Returned by the admin.' : 'Waiting for admin approval.' }}
                        The ticket can be issued once it is approved and the cashier has recorded full payment.
                    </p>
                </div>
            @elseif ($ticket->isQuotation())
                <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200">
                    <i data-lucide="file-text" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <p class="text-xs text-amber-800">
                        This is a quotation. It was saved without the travel documents, so it cannot be
                        issued as a ticket until the passenger details and required documents are completed.
                    </p>
                </div>
            @elseif (! $ticket->isFullyPaid() || $documentGaps->isNotEmpty())
                @unless ($ticket->isFullyPaid())
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200">
                        <i data-lucide="lock" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                        <p class="text-xs text-amber-800">
                            Full payment is required before this ticket can be issued.
                            @if ($total > 0)
                                Outstanding balance: <span class="font-bold tabular-nums">&#8369;{{ number_format($balance, 2) }}</span>.
                            @endif
                        </p>
                    </div>
                @endunless
                @if ($documentGaps->isNotEmpty())
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 {{ $ticket->isFullyPaid() ? '' : 'mt-3' }}">
                        <i data-lucide="file-warning" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                        <div class="text-xs text-amber-800 space-y-1">
                            <p>Required documents are still missing. Payment can go ahead, but the ticket cannot be issued until they are uploaded.</p>
                            <ul class="list-disc pl-4 space-y-0.5">
                                @foreach ($documentGaps as $gap)
                                    <li><span class="font-bold">{{ $gap['passenger']->full_name }}:</span> {{ implode(', ', $gap['missing']) }}</li>
                                @endforeach
                            </ul>
                            <a href="#missing-documents-{{ $documentGaps->first()['passenger']->id }}"
                               class="inline-flex items-center gap-1.5 mt-1 px-3 py-1.5 rounded-lg bg-amber-600 text-white font-semibold hover:bg-amber-700 transition-colors">
                                <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                <span>Upload missing documents</span>
                            </a>
                        </div>
                    </div>
                @endif
            @else
                <form method="POST" action="{{ route('ticketing.tickets.issue', $ticket) }}" class="flex flex-col gap-4 flex-1">
                    @csrf

                    <div class="p-3 rounded-lg bg-slate-50 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Data Privacy and Consent</p>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            The passenger information and uploaded documents held against this booking are
                            collected solely to process and issue this ticket, and are shared with the
                            carrier and relevant authorities only as required to complete travel.
                        </p>
                    </div>

                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" name="data_privacy_consent" value="1" required
                               class="mt-0.5 rounded border-slate-300 text-navy-700 focus:ring-navy-500">
                        <span class="text-xs text-slate-700">
                            The client has reviewed the passenger details, flight details, quotation, and
                            airline restrictions, and consents to the processing of their personal data.
                        </span>
                    </label>
                    @error('data_privacy_consent')
                        <p class="text-xs text-rose-600">{{ $message }}</p>
                    @enderror

                    <button type="submit"
                            class="mt-auto w-full px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition-colors">
                        Issue ticket
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Every payment and refund, with a receipt for each. --}}
    @if ($ticket->payments->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 print:hidden">
            <h2 class="font-heading text-base font-bold text-slate-900 mb-4">Payment History</h2>
            <div class="overflow-x-auto relative">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Entry</th>
                            <th class="py-2 pr-4">Method</th>
                            <th class="py-2 pr-4">Reference</th>
                            <th class="py-2 pr-4">Taken by</th>
                            <th class="py-2 pr-4 text-right">Amount</th>
                            <th class="py-2"><span class="sr-only">Receipt</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($ticket->payments as $entry)
                            <tr>
                                <td class="py-2.5 pr-4 whitespace-nowrap text-slate-700">{{ $entry->received_at->format('M j, Y') }}</td>
                                <td class="py-2.5 pr-4 whitespace-nowrap font-semibold text-slate-900">
                                    {{ $entry->receiptNumber() }}
                                    @if ($entry->isRefund())
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 text-xs font-semibold">Refund</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-4 whitespace-nowrap text-slate-700">{{ $entry->methodLabel() }}</td>
                                <td class="py-2.5 pr-4 text-slate-600">{{ $entry->reference ?: '—' }}@if ($entry->note)<span class="block text-xs text-slate-400">{{ $entry->note }}</span>@endif</td>
                                <td class="py-2.5 pr-4 whitespace-nowrap text-slate-600">{{ $entry->receivedBy?->name ?? '—' }}</td>
                                <td class="py-2.5 pr-4 text-right tabular-nums font-semibold whitespace-nowrap {{ $entry->isRefund() ? 'text-rose-700' : 'text-emerald-700' }}">
                                    {{ $entry->isRefund() ? '−' : '' }}&#8369;{{ number_format((float) $entry->amount, 2) }}
                                </td>
                                <td class="py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('ticketing.tickets.payments.receipt', [$ticket, $entry]) }}" target="_blank" rel="noopener"
                                       class="text-xs font-semibold text-navy-700 hover:underline">Receipt</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @include('ticketing.tickets._flight-changes')

    {{-- Cancellation: the record stays, with who cancelled it and why. --}}
    @if ($ticket->isCancelled())
        <div class="flex items-start gap-3 p-5 rounded-2xl bg-rose-50 ring-1 ring-rose-200">
            <i data-lucide="x-circle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
            <div class="text-sm text-rose-900">
                <p class="font-bold">Cancelled {{ $ticket->cancelled_at?->format('M j, Y 	 g:ia') }}@if ($ticket->cancelledBy) by {{ $ticket->cancelledBy->name }}@endif</p>
                <p class="mt-1">{{ $ticket->cancellation_reason }}</p>
            </div>
        </div>
    @else
        <div x-data="{ open: {{ $errors->has('cancellation_reason') ? 'true' : 'false' }} }" class="print:hidden">
            <button type="button" x-show="!open" @click="open = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-white border border-rose-200 text-rose-700 text-xs font-bold hover:bg-rose-50 transition-colors">
                <i data-lucide="x-circle" class="w-4 h-4"></i>
                Cancel this booking
            </button>

            <form x-show="open" x-cloak method="POST" action="{{ route('ticketing.tickets.cancel', $ticket) }}"
                  class="bg-white rounded-2xl border border-rose-200 shadow-sm p-5 sm:p-6 space-y-3">
                @csrf
                <h2 class="font-heading text-base font-bold text-slate-900">Cancel {{ $ticket->booking_reference }}</h2>

                @if ($paid > 0)
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                        <p class="text-xs text-amber-800">
                            <span class="font-bold tabular-nums">&#8369;{{ number_format($paid, 2) }}</span> has already been received.
                            Cancelling does not return it: settle it with the client afterwards and record the refund on this page.
                        </p>
                    </div>
                @endif
                @if ($ticket->isIssued())
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                        <p class="text-xs text-amber-800">This ticket has been issued. Also cancel or void it with the airline.</p>
                    </div>
                @endif

                <div>
                    <label for="cancellation_reason" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Reason for cancelling</label>
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3" maxlength="500" required
                              class="w-full rounded-lg border-slate-300 text-sm focus:border-navy-500 focus:ring-navy-500">{{ old('cancellation_reason') }}</textarea>
                    @error('cancellation_reason')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit"
                            class="px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 transition-colors">
                        Cancel booking
                    </button>
                    <button type="button" @click="open = false"
                            class="px-4 py-2.5 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors">
                        Keep booking
                    </button>
                </div>
            </form>
        </div>
    @endif

    <x-file-owner :file="$ticket" type="ticket" class="print:hidden" />

    @if ($ticket->agent_acknowledged_at)
        <p class="flex items-center gap-2 text-xs text-dark/60 print:hidden">
            <i class="fa-solid fa-user-check text-emerald-600" aria-hidden="true"></i>
            <span>Information confirmed correct by <strong class="text-dark">{{ $ticket->acknowledgedBy?->name ?? 'a former staff member' }}</strong> on {{ $ticket->agent_acknowledged_at->format('M d, Y g:i A') }}</span>
        </p>
    @endif

    @include('ticketing.tickets._confirmation', ['documentGaps' => $documentGaps])

</div>
@endsection
