@extends('layouts.cruise-services')

@section('title', 'Cruise Services - AMEGA')

{{-- The desk's dashboard: how many bookings there are and whether their slot is confirmed (each card
     filters the list), then the bookings. --}}
@php
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $canCreate = auth()->user()->hasPermission('cruise_services.create');
    $cards = [
        [null, 'All bookings', 'ship', $counts['all']],
        ['waiting', 'Slot not confirmed yet', 'hourglass', $counts['waiting']],
        ['confirmed', 'Slot confirmed', 'circle-check', $counts['confirmed']],
    ];
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Cruise bookings</h1>
        @if ($canCreate)
            <a href="{{ route('cruise-services.bookings.create') }}" class="h-10 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold inline-flex items-center gap-2 self-start">
                <i data-lucide="plus" class="w-4 h-4"></i> New booking
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach ($cards as [$key, $label, $icon, $count])
            <a href="{{ route('cruise-services.dashboard', array_filter(['slot' => $key])) }}" @if ($filter === $key) aria-current="true" @endif
               class="bg-white rounded-xl border p-4 flex items-start gap-3 hover:border-navy-600 transition-colors {{ $filter === $key ? 'border-navy-700 ring-1 ring-navy-700' : 'border-slate-200' }}">
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                    <i data-lucide="{{ $icon }}" class="w-5 h-5"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900">{{ $label }}</span>
                    <span class="mt-1 block text-2xl font-bold text-slate-900">{{ $count }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap justify-end gap-2" role="search">
        @if ($filter)<input type="hidden" name="slot" value="{{ $filter }}">@endif
        <label for="booking-search" class="sr-only">Search bookings</label>
        <input id="booking-search" type="search" name="search" value="{{ $search }}" placeholder="Reference, client, destination or ship" class="{{ $control }} w-72">
        <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($bookings->isEmpty())
            <div class="px-5 py-12 text-center">
                <p class="text-sm text-slate-500">{{ $search !== '' || $filter ? 'No bookings match.' : 'No cruise bookings yet.' }}</p>
                @if ($canCreate && $search === '' && ! $filter)
                    <a href="{{ route('cruise-services.bookings.create') }}" class="mt-3 inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Destination</th>
                            <th class="px-5 py-3">Dates</th>
                            <th class="px-5 py-3">Guests</th>
                            <th class="px-5 py-3">Cruise line / ship</th>
                            <th class="px-5 py-3">Step</th>
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
                                <td class="px-5 py-3 text-slate-900">{{ $booking->client_name }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $booking->destination ?: '—' }}</td>
                                <td class="px-5 py-3 text-slate-900 whitespace-nowrap">{{ $booking->sailingLabel() ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $booking->guestsLabel() ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $booking->cruiseLabel() ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-900 whitespace-nowrap mb-1">{{ $booking->stageLabel() }}</div>
                                    @if ($booking->isSlotConfirmed())
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/20">Slot confirmed</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset bg-amber-50 text-amber-800 ring-amber-600/20">Slot not confirmed</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('cruise-services.bookings.show', $booking) }}" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open</a>
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
