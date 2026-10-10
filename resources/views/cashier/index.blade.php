@extends('layouts.cashier')

@section('title', $title . ' - Cashier - AMEGA')

@php
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">{{ $title }}</h1>
        <form method="GET" class="flex gap-2" role="search">
            <label for="cashier-search" class="sr-only">Search bookings</label>
            <input id="cashier-search" type="search" name="search" value="{{ request('search') }}" placeholder="Reference or client" class="{{ $control }} w-64">
            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($tickets->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">{{ request('search') ? 'No bookings match your search.' : $empty }}</p>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Trip</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Paid</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($tickets as $ticket)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <div class="font-mono text-xs font-semibold text-slate-900">{{ $ticket->booking_reference }}</div>
                                    <div class="text-xs text-slate-500">Agent: {{ $ticket->createdBy?->name ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3 text-slate-900">{{ $ticket->contact_name }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-900">{{ $ticket->origin }} → {{ $ticket->destination }}</div>
                                    <div class="text-xs text-slate-500">{{ $ticket->departure_date?->format('M j, Y') }}</div>
                                </td>
                                <td class="px-5 py-3 text-right text-slate-900 whitespace-nowrap">₱{{ number_format((float) $ticket->total_amount, 2) }}</td>
                                <td class="px-5 py-3 text-right text-emerald-700 whitespace-nowrap">₱{{ number_format((float) $ticket->amount_paid, 2) }}</td>
                                <td class="px-5 py-3 text-right font-semibold whitespace-nowrap {{ $ticket->balanceDue() > 0 ? 'text-amber-700' : 'text-slate-400' }}">₱{{ number_format($ticket->balanceDue(), 2) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('cashier.payments.show', $ticket) }}"
                                       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg {{ $ticket->balanceDue() > 0 ? 'bg-navy-700 text-white hover:bg-navy-800' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }} text-sm font-semibold whitespace-nowrap">
                                        <i class="fa-solid {{ $ticket->balanceDue() > 0 ? 'fa-cash-register' : 'fa-receipt' }}" aria-hidden="true"></i>
                                        <span>{{ $ticket->balanceDue() > 0 ? 'Record payment' : 'View' }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($tickets->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">{{ $tickets->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
