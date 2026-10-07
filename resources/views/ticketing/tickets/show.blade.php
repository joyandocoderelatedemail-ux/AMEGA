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
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold ring-1 {{ $payTone[0] }} {{ $payTone[1] }} {{ $payTone[2] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $payTone[3] }}"></span>
                    {{ ucfirst(str_replace('_', ' ', $ticket->payment_status)) }}
                </span>
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
            @elseif ($balance <= 0)
                <p class="text-xs text-slate-500">Paid in full. Nothing more to record.</p>
            @else
                @include('ticketing.tickets._ledger-form', [
                    'action' => route('ticketing.tickets.payment', $ticket),
                    'amountLabel' => 'Amount received',
                    'submitLabel' => 'Record payment',
                    'amountDefault' => number_format($balance, 2, '.', ''),
                ])
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

    <!-- Ticket Voucher Sheet (Print Friendly) -->
    <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-sm space-y-8 print:p-0 print:border-none print:shadow-none">
        
        <!-- Voucher Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 border-b border-gray-100 pb-6">
            <div class="flex items-center gap-4">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA" class="h-10 sm:h-12 w-auto object-contain">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-primary block">Amega Travel &amp; Tours</span>
                    <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight">Official Ticket Booking Confirmation</h1>
                </div>
            </div>

            <div class="sm:text-right space-y-1">
                <div class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Ticket Reference</div>
                <div class="font-mono text-lg sm:text-xl font-black text-primary">{{ $ticket->booking_reference }}</div>
                @php
                    // The badge previously rendered emerald for every status,
                    // so a pending or cancelled booking looked confirmed.
                    $statusBadge = match ($ticket->status) {
                        \App\Models\TicketBooking::STATUS_ISSUED => 'bg-emerald-100 text-emerald-800',
                        \App\Models\TicketBooking::STATUS_CONFIRMED => 'bg-blue-100 text-blue-800',
                        \App\Models\TicketBooking::STATUS_CANCELLED => 'bg-rose-100 text-rose-800',
                        default => 'bg-amber-100 text-amber-800',
                    };
                @endphp
                <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $statusBadge }}">
                    Status: {{ ucfirst($ticket->status) }}
                </div>
                @if ($ticket->isQuotation())
                    <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 ml-1">
                        Quotation
                    </div>
                @endif
            </div>
        </div>

        <!-- Route & Schedule Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-5 rounded-2xl bg-gray-50 border border-gray-200">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Travel Type</span>
                <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                    <i data-lucide="{{ $ticket->travel_type === 'international' ? 'globe' : 'palmtree' }}" class="w-3.5 h-3.5 text-primary"></i>
                    {{ ucfirst($ticket->travel_type) }} Tour
                </span>
                @if($ticket->travel_class)
                    <span class="text-[10px] font-bold text-primary block mt-0.5 uppercase">{{ str_replace('_', ' ', $ticket->travel_class) }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Trip Route</span>
                <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                    {{ $ticket->origin }} &rarr; {{ $ticket->destination }}
                </span>
                @if($ticket->arrival_airport)
                    <span class="text-[10px] text-dark/50 block">Airport: {{ $ticket->arrival_airport }}</span>
                @endif
                @if($ticket->preferred_airline)
                    <span class="text-[10px] text-dark/50 block">Airline: {{ $ticket->preferred_airline }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Schedule</span>
                <span class="text-xs font-bold text-dark block mt-0.5">
                    Depart: {{ $ticket->departure_date->format('M d, Y') }}
                    @if($ticket->return_date)
                        <br>Return: {{ $ticket->return_date->format('M d, Y') }}
                    @endif
                </span>
                @if($ticket->preferred_flight_time && $ticket->preferred_flight_time !== 'anytime')
                    <span class="text-[10px] text-dark/50 capitalize block">Time: {{ $ticket->preferred_flight_time }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Passengers Count</span>
                <span class="text-xs font-bold text-dark block mt-0.5">
                    {{ $ticket->total_passengers }} Pax ({{ $ticket->adults_count }} Adt, {{ $ticket->children_count }} Chd, {{ $ticket->infants_count }} Inf)
                </span>
                <span class="text-[10px] text-dark/50 block mt-0.5 uppercase font-bold">{{ str_replace('_', ' ', $ticket->trip_type) }}</span>
            </div>
        </div>

        <!-- Booked Flight: what staff found on the airline's site -->
        @if($ticket->hasBookedFlight())
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-5 rounded-2xl bg-white border border-gray-200">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Booked Airline</span>
                    <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                        <i data-lucide="plane" class="w-3.5 h-3.5 text-primary"></i>
                        {{ $ticket->airline?->label() ?? 'Not recorded' }}
                    </span>
                    @if($ticket->airline_pnr)
                        <span class="text-[10px] text-dark/50 block">PNR: <span class="font-mono font-bold text-dark">{{ $ticket->airline_pnr }}</span></span>
                    @endif
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Departing Flight</span>
                    <span class="text-xs font-mono font-bold text-dark block mt-0.5">{{ $ticket->flight_number ?? '—' }}</span>
                    @if($ticket->departure_time)
                        <span class="text-[10px] text-dark/50 block">
                            {{ \App\Models\TicketBooking::formatFlightTime($ticket->departure_time) }}
                            @if($ticket->arrival_time) &rarr; {{ \App\Models\TicketBooking::formatFlightTime($ticket->arrival_time) }} @endif
                        </span>
                    @endif
                </div>
                @if($ticket->trip_type === 'round_trip')
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Return Flight</span>
                        <span class="text-xs font-mono font-bold text-dark block mt-0.5">{{ $ticket->return_flight_number ?? '—' }}</span>
                        @if($ticket->return_departure_time)
                            <span class="text-[10px] text-dark/50 block">
                                {{ \App\Models\TicketBooking::formatFlightTime($ticket->return_departure_time) }}
                                @if($ticket->return_arrival_time) &rarr; {{ \App\Models\TicketBooking::formatFlightTime($ticket->return_arrival_time) }} @endif
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Airline Restrictions: shown on the voucher, editable here at any time -->
        <div x-data="{ editing: {{ $errors->has('airline_restrictions*') ? 'true' : 'false' }}, items: @js(old('airline_restrictions', $ticket->airline_restrictions ?? [])) }"
             class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 space-y-3 {{ empty($ticket->airline_restrictions) ? 'print:hidden' : '' }}">
            <div class="flex items-center justify-between gap-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                    Airline Restrictions
                </span>
                <button type="button" x-show="!editing" @click="editing = true; if (!items.length) items.push(''); $nextTick(() => window.lucide && window.lucide.createIcons())"
                        class="print:hidden inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    <span>{{ empty($ticket->airline_restrictions) ? 'Add restrictions' : 'Edit' }}</span>
                </button>
            </div>

            <div x-show="!editing">
                @if (! empty($ticket->airline_restrictions))
                    <ul class="space-y-1">
                        @foreach ($ticket->airline_restrictions as $restriction)
                            <li class="text-xs text-dark/80 flex items-start gap-2"><span class="text-amber-600">•</span><span>{{ $restriction }}</span></li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs text-dark/50">No airline restrictions noted on this booking.</p>
                @endif
            </div>

            <form x-show="editing" x-cloak method="POST" action="{{ route('ticketing.tickets.restrictions', $ticket) }}" class="space-y-2 print:hidden">
                @csrf
                @method('PUT')
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex items-center gap-2">
                        <input type="text" name="airline_restrictions[]" x-model="items[idx]" maxlength="500"
                               placeholder="e.g. Non-refundable"
                               class="flex-1 min-w-0 px-3 py-2 rounded-lg bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="button" @click="items.splice(idx, 1)" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 shrink-0" title="Remove restriction">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </template>
                @error('airline_restrictions*')
                    <p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>
                @enderror
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <button type="button" @click="items.push(''); $nextTick(() => window.lucide && window.lucide.createIcons())"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-dashed border-gray-300 text-[11px] font-bold text-dark/70 hover:border-primary hover:text-primary">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Add a restriction</span>
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="editing = false; items = @js($ticket->airline_restrictions ?? [])" class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-[11px] font-bold text-dark/70 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-lg bg-primary text-white text-[11px] font-bold hover:bg-navy">Save Restrictions</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Package Details if selected -->
        @if($ticket->isCustomPackage() || !empty($ticket->custom_package_specs))
            <div class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-amber-200/60 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-sm shrink-0">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Customized Package Specifications</span>
                            <span class="text-sm font-bold text-amber-950">{{ $ticket->package_name ?: 'Custom Tour Package' }}</span>
                        </div>
                    </div>
                    @if(!empty($ticket->custom_package_specs['estimated_budget']))
                        <div class="sm:text-right">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800/70 block">Target Budget</span>
                            <span class="text-xs font-bold text-primary font-mono">&#8369;{{ number_format((float)$ticket->custom_package_specs['estimated_budget'], 2) }}</span>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Hotel / Location</span>
                        <span class="font-bold text-dark block">{{ $ticket->custom_package_specs['hotel_name'] ?? 'To be arranged' }}</span>
                        @if(!empty($ticket->custom_package_specs['preferred_hotel']))
                            <span class="text-[10px] text-dark/60 block">{{ $ticket->custom_package_specs['preferred_hotel'] }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Bedding &amp; Breakfast</span>
                        <span class="font-semibold text-dark block">{{ $ticket->custom_package_specs['bed_config'] ?? 'Standard' }}</span>
                        <span class="text-[10px] font-bold block mt-0.5 {{ !empty($ticket->custom_package_specs['has_breakfast']) ? 'text-emerald-700' : 'text-dark/50' }}">
                            {{ !empty($ticket->custom_package_specs['has_breakfast']) ? '✓ Breakfast Included' : 'No Breakfast' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Stay Dates</span>
                        <span class="font-semibold text-dark block">
                            {{ !empty($ticket->custom_package_specs['check_in_date']) ? \Carbon\Carbon::parse($ticket->custom_package_specs['check_in_date'])->format('M d, Y') : '—' }}
                            @if(!empty($ticket->custom_package_specs['check_out_date']))
                                &rarr; {{ \Carbon\Carbon::parse($ticket->custom_package_specs['check_out_date'])->format('M d, Y') }}
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Policies &amp; Transfer</span>
                        <span class="text-[10px] font-semibold text-dark block">
                            {{ ($ticket->custom_package_specs['smoking_preference'] ?? '') === 'smoking' ? '🚬 Smoking' : '🚭 Non-Smoking' }}
                            • {{ !empty($ticket->custom_package_specs['pet_friendly']) ? '🐾 Pets OK' : 'No Pets' }}
                        </span>
                        <span class="text-[10px] font-bold block mt-0.5 {{ !empty($ticket->custom_package_specs['has_transportation']) ? 'text-primary' : 'text-dark/50' }}">
                            {{ !empty($ticket->custom_package_specs['has_transportation']) ? '🚗 Transport: ' . ($ticket->custom_package_specs['transportation_type'] ?? 'Arranged') : 'No Transport' }}
                        </span>
                    </div>
                </div>

                @if(!empty($ticket->custom_package_specs['special_requests']))
                    <div class="pt-2 border-t border-amber-200/50 text-xs">
                        <span class="text-[10px] font-bold text-amber-900/60 uppercase block">Special Requests</span>
                        <p class="text-dark/80 text-xs mt-0.5">{{ $ticket->custom_package_specs['special_requests'] }}</p>
                    </div>
                @endif
            </div>
        @elseif($ticket->package_name)
            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center">
                        <i data-lucide="package" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Tour Package</span>
                        <span class="text-xs font-bold text-amber-950">{{ $ticket->package_name }}</span>
                    </div>
                </div>
                @if($ticket->travelPackage)
                    <span class="text-xs font-bold text-primary">{{ $ticket->travelPackage->price }}</span>
                @endif
            </div>
        @endif

        <!-- Phase 2 International Services & Emergency Contact Banner -->
        @if($ticket->travel_type === 'international' || $ticket->has_insurance || !empty($ticket->selected_services))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-5 rounded-2xl bg-blue-50/40 border border-blue-100 text-xs">
                <!-- Insurance & Add-on Services -->
                <div class="space-y-2">
                    <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Insurance &amp; Concierge Services</span>
                    @if($ticket->has_insurance)
                        <div class="flex items-center gap-1.5 text-emerald-800 font-bold">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                            <span>Travel Insurance: <span class="capitalize">{{ $ticket->insurance_plan ?? 'Standard' }}</span> Plan Active</span>
                        </div>
                    @endif
                    @if(!empty($ticket->selected_services))
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @foreach($ticket->selected_services as $srv)
                                <span class="px-2 py-0.5 rounded-lg bg-white border border-blue-200 text-dark/80 text-[10px] font-bold">
                                    ✓ {{ ucwords(str_replace('_', ' ', $srv)) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Emergency Contact -->
                @if($ticket->emergency_contact_name)
                    <div class="space-y-1">
                        <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Emergency Contact Person</span>
                        <div class="font-bold text-dark">{{ $ticket->emergency_contact_name }} ({{ $ticket->emergency_contact_relationship }})</div>
                        <div class="text-dark/60 font-semibold">{{ $ticket->emergency_contact_phone }} {{ $ticket->emergency_contact_email ? '• ' . $ticket->emergency_contact_email : '' }}</div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Passenger Manifest & Documents -->
        <div class="space-y-4">
            <h2 class="text-base font-heading font-bold text-dark flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5 text-primary"></i>
                <span>Passenger Manifest &amp; Document Credentials</span>
            </h2>

            <div class="space-y-4">
                @foreach($ticket->passengers as $p)
                    <div class="p-5 rounded-2xl border border-gray-200 bg-white space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">
                                    {{ $p->passenger_number }}
                                </span>
                                <span class="font-heading font-bold text-sm text-dark">{{ $p->full_name }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase {{ $p->nationality_type === 'filipino' ? 'bg-primary/10 text-primary' : 'bg-accent/20 text-accent-dark' }}">
                                    {{ $p->nationality_type === 'filipino' ? '🇵🇭 Filipino' : '🌐 Foreign National' }}
                                </span>
                                @if($p->gender)
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase bg-gray-100 text-dark/70 capitalize">{{ $p->gender }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-dark/60 font-medium">
                                Passenger Type: <strong class="capitalize text-dark">{{ $p->passenger_type }}</strong>
                                @if($p->travel_tax_included)
                                    • <span class="text-primary font-bold">Travel Tax Included</span>
                                @endif
                                @if($p->visa_status)
                                    • <span class="text-blue-700 font-bold uppercase text-[10px]">{{ str_replace('_', ' ', $p->visa_status) }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            @if($p->date_of_birth)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Date of Birth</span>
                                    <span class="font-semibold text-dark">{{ $p->date_of_birth->format('M d, Y') }}</span>
                                </div>
                            @endif
                            @if($p->passport_number)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Passport Number</span>
                                    <span class="font-mono font-bold text-dark">{{ $p->passport_number }}</span>
                                </div>
                            @endif
                            @if($p->passport_expiry_date)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Passport Expiry</span>
                                    <span class="font-semibold text-dark">{{ $p->passport_expiry_date->format('M d, Y') }}</span>
                                </div>
                            @endif
                            @if($p->visa_type && $p->visa_type !== 'none')
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Visa Type</span>
                                    <span class="font-semibold text-dark">{{ ucfirst(str_replace('_', ' ', $p->visa_type)) }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Attached Uploaded Documents -->
                        <div class="pt-2">
                            <span class="text-[11px] font-bold text-dark/60 uppercase tracking-wider block mb-2">Attached Documents ({{ $p->documents->count() }})</span>
                            @if($p->documents->count() > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($p->documents as $doc)
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 text-xs font-semibold">
                                            <i data-lucide="file-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                            <span class="text-dark">{{ $doc->formatted_type }}:</span>
                                            <span class="text-dark/60 truncate max-w-[150px]">{{ $doc->original_name }}</span>
                                            <a href="{{ route('ticketing.documents.download', $doc) }}" 
                                               class="text-primary hover:underline font-bold text-[11px] ml-1 flex items-center gap-0.5 print:hidden">
                                                <i data-lucide="download" class="w-3 h-3"></i> Download
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-dark/40 italic">No document scans uploaded for this passenger.</span>
                            @endif
                        </div>

                        <!-- Required documents still to come, each with its own upload -->
                        @if(($documentGaps[$p->id]['missing'] ?? []) !== [])
                            <div id="missing-documents-{{ $p->id }}" class="pt-3 border-t border-gray-100 space-y-2 print:hidden scroll-mt-24 target:ring-2 target:ring-amber-300 target:rounded-xl target:p-3">
                                <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider block">Required documents missing</span>
                                @foreach($documentGaps[$p->id]['missing'] as $type => $label)
                                    <form method="POST" action="{{ route('ticketing.tickets.passengers.documents.store', [$ticket, $p]) }}" enctype="multipart/form-data"
                                          class="flex flex-wrap items-center gap-2 p-3 rounded-xl bg-amber-50 border border-amber-200">
                                        @csrf
                                        <input type="hidden" name="document_type" value="{{ $type }}">
                                        <span class="text-xs font-bold text-amber-900 min-w-[10rem]">{{ $label }}</span>
                                        <input type="file" name="file" required accept="image/jpeg,image/png,image/webp,application/pdf"
                                               class="flex-1 min-w-[12rem] text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-white file:text-navy-700 hover:file:bg-gray-50 cursor-pointer">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-navy-700 text-white text-xs font-bold hover:bg-navy-800 transition-colors">Upload</button>
                                    </form>
                                @endforeach
                                @error('file')
                                    <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('document_type')
                                    <p class="text-xs font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="text-[10px] text-dark/40">JPG, PNG, WEBP or PDF, up to 5 MB.</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pricing & Quotation Breakdown if total amount set -->
        @if($ticket->total_amount > 0 || $ticket->estimated_fare > 0)
            <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Fare &amp; Quotation Assessment</span>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
                    <div>
                        <span class="text-dark/50 block text-[10px]">Estimated Base Fare</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->estimated_fare, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Taxes &amp; Surcharges</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->taxes_amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Visa Assistance</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->visa_assistance_fee, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Insurance Fee</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->insurance_fee, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Other Charges</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->other_charges, 2) }}</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-gray-200 flex items-center justify-between">
                    <span class="font-bold text-dark text-xs uppercase">Grand Total Quotation:</span>
                    <span class="font-mono font-black text-primary text-base">₱{{ number_format($ticket->total_amount, 2) }}</span>
                </div>
            </div>
        @endif

        <!-- Special Requests -->
        @if($ticket->special_requests || !empty($ticket->special_requests_list))
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-2 text-xs">
                <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Special Requests &amp; Seating Preferences</span>
                @if(!empty($ticket->special_requests_list))
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($ticket->special_requests_list as $req)
                            <span class="px-2 py-0.5 rounded-lg bg-white border text-dark font-bold text-[10px]">
                                • {{ ucwords(str_replace('_', ' ', $req)) }}
                            </span>
                        @endforeach
                    </div>
                @endif
                @if($ticket->special_requests)
                    <p class="text-dark/80 whitespace-pre-line mt-1">{{ $ticket->special_requests }}</p>
                @endif
            </div>
        @endif

        <!-- Contact and Officer Footer -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-gray-100 text-xs">
            <div class="space-y-1">
                <span class="font-bold text-dark/50 uppercase tracking-wider text-[10px]">Primary Booker / Contact</span>
                <div class="font-bold text-dark">{{ $ticket->contact_name }}</div>
                <div class="text-dark/60">{{ $ticket->contact_email }} • {{ $ticket->contact_phone }}</div>
            </div>
            <div class="sm:text-right space-y-1">
                <span class="font-bold text-dark/50 uppercase tracking-wider text-[10px]">Issuing Officer</span>
                <div class="font-bold text-dark">{{ $ticket->createdBy?->name ?? 'AMEGA Staff' }}</div>
                <div class="text-dark/40">Issued on {{ $ticket->created_at->format('M d, Y h:i A') }}</div>
            </div>
        </div>

    </div>

</div>
@endsection
