@extends('layouts.local-tours')

@section('title', 'Local Tours - AMEGA')

{{-- The desk's front page: one card per tour with what is in progress on it, then the bookings. --}}
@php
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $canCreate = auth()->user()->hasPermission('local_tours.create');
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Local tour bookings</h1>
        @if ($canCreate)
            <a href="{{ route('local-tours.bookings.create') }}" class="h-10 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold inline-flex items-center gap-2 self-start">
                <i data-lucide="plus" class="w-4 h-4"></i> New booking
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($tours as $card)
            <a href="{{ route('local-tours.dashboard', ['tour' => $card['key']]) }}" @if ($tour === $card['key']) aria-current="true" @endif
               class="bg-white rounded-xl border p-4 flex items-start gap-3 hover:border-navy-600 transition-colors {{ $tour === $card['key'] ? 'border-navy-700 ring-1 ring-navy-700' : 'border-slate-200' }}">
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                    <i data-lucide="{{ $card['icon'] }}" class="w-5 h-5"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900">{{ $card['label'] }}</span>
                    <span class="mt-1.5 block text-xs text-slate-600">
                        <strong class="text-slate-900">{{ $card['open'] }}</strong> in progress
                        @if ($card['waiting'] > 0)
                            <span class="text-amber-700">· {{ $card['waiting'] }} with the approver</span>
                        @endif
                    </span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <nav class="inline-flex flex-wrap gap-1 p-1 rounded-lg bg-slate-100 self-start" aria-label="Booking lists">
            @foreach ($views as $key => $name)
                <a href="{{ route('local-tours.dashboard', array_filter(['view' => $key, 'tour' => $tour])) }}" @if ($view === $key) aria-current="page" @endif
                   class="inline-flex items-center h-9 px-3 rounded-md text-sm font-semibold {{ $view === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">{{ $name }}</a>
            @endforeach
        </nav>
        <form method="GET" class="flex flex-wrap gap-2" role="search">
            <input type="hidden" name="view" value="{{ $view }}">
            <label for="tour-filter" class="sr-only">Tour</label>
            <select id="tour-filter" name="tour" class="{{ $control }}">
                <option value="">All tours</option>
                @foreach (\App\Support\LocalTours::labels() as $key => $name)
                    <option value="{{ $key }}" @selected($tour === $key)>{{ $name }}</option>
                @endforeach
            </select>
            <label for="booking-search" class="sr-only">Search bookings</label>
            <input id="booking-search" type="search" name="search" value="{{ $search }}" placeholder="Reference, guest or phone" class="{{ $control }} w-56">
            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($bookings->isEmpty())
            <div class="px-5 py-12 text-center">
                <p class="text-sm text-slate-500">
                    {{ $search !== '' || $tour ? 'No bookings match.' : ($view === 'open' ? 'No bookings in progress.' : 'Nothing here yet.') }}
                </p>
                @if ($canCreate && $view === 'open' && $search === '')
                    <a href="{{ route('local-tours.bookings.create', array_filter(['tour' => $tour])) }}" class="mt-3 inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="plus" class="w-4 h-4"></i> Open a booking
                    </a>
                @endif
            </div>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Lead guest</th>
                            <th class="px-5 py-3">Tour</th>
                            <th class="px-5 py-3">Travel date</th>
                            <th class="px-5 py-3">Step</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($bookings as $booking)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <div class="font-mono text-xs font-semibold text-slate-900">{{ $booking->reference }}</div>
                                    <div class="text-xs text-slate-500">Agent: {{ $booking->creator?->name ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-900">{{ $booking->lead_guest_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $booking->guestsLabel() }}</div>
                                </td>
                                <td class="px-5 py-3 text-slate-900">{{ $booking->tourLabel() }}</td>
                                <td class="px-5 py-3 text-slate-900 whitespace-nowrap">{{ $booking->travel_date?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-900">{{ $booking->status_label }}</div>
                                    @if ($booking->isAwaitingApproval())
                                        <div class="text-xs font-semibold text-amber-700">With the approver</div>
                                    @elseif ($booking->isReturned())
                                        <div class="text-xs font-semibold text-rose-700">Returned to correct</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap {{ $booking->outstandingBalance() > 0 ? 'font-semibold text-amber-700' : 'text-slate-400' }}">
                                    {{ (float) $booking->total_amount > 0 ? '₱'.number_format($booking->outstandingBalance(), 2) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('local-tours.bookings.show', $booking) }}" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($bookings->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">{{ $bookings->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
