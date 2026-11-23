@extends('layouts.cruise-services')

@section('title', 'Quotations - Cruise Services - AMEGA')

{{-- Every cruise quotation prepared ("Save and print quotation" at step 2), newest first, to print again or open. --}}
@php
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Quotations</h1>
        <form method="GET" class="flex flex-wrap gap-2" role="search">
            <label for="quotation-search" class="sr-only">Search quotations</label>
            <input id="quotation-search" type="search" name="search" value="{{ $search }}" placeholder="Reference, client or destination" class="{{ $control }} w-72">
            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($quotations->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">
                {{ $search !== '' ? 'No quotations match.' : 'No quotations yet. They show here once a booking\'s quotation is saved and printed.' }}
            </p>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Destination</th>
                            <th class="px-5 py-3">Sailing</th>
                            <th class="px-5 py-3">Guests</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3">Prepared</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($quotations as $booking)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">{{ $booking->reference }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $booking->client_name }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $booking->destination ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-900 whitespace-nowrap">{{ $booking->sailingLabel() ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $booking->guestsLabel() ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">{{ $booking->money($booking->total_amount) }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-900 whitespace-nowrap">{{ $booking->quotation_at->format('M j, Y') }}</div>
                                    <div class="text-xs text-slate-500">{{ $booking->quotationBy?->name ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('cruise-services.bookings.quotation', $booking) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold whitespace-nowrap">
                                            <i data-lucide="printer" class="w-4 h-4"></i> Print
                                        </a>
                                        <a href="{{ route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'quotation']) }}" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($quotations->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">{{ $quotations->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
