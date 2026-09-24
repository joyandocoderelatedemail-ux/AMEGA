@extends('layouts.ticketing')

@section('title', 'Ticketing Workspace - AMEGA')

@php
    /**
     * Tokens for this page: rounded-xl cards, rounded-lg controls, rounded-full
     * pills; weights 400/600/700; nothing below text-xs. Each hue means one
     * thing — emerald = issued, amber = awaiting action, rose = cancelled,
     * navy = brand/confirmed, slate = neutral.
     *
     * Headings deliberately omit `font-heading`: app.css forces weight 900 on
     * `h1.font-heading` / `h2.font-heading`, and the element selector already
     * gives them Montserrat.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $statLabel = 'text-sm font-semibold text-slate-600';
    $statFigure = 'font-heading text-2xl font-bold text-slate-900 tracking-tight tabular-nums';
    $statMeta = 'mt-1 text-sm text-slate-500';
    $primaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
    $th = 'px-3 xl:px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';

    $statusTone = [
        'issued'    => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-600/20', 'dot' => 'bg-emerald-500'],
        'confirmed' => ['bg' => 'bg-navy-50',    'text' => 'text-navy-700',    'ring' => 'ring-navy-700/20',    'dot' => 'bg-navy-600'],
        'cancelled' => ['bg' => 'bg-rose-50',    'text' => 'text-rose-700',    'ring' => 'ring-rose-600/20',    'dot' => 'bg-rose-500'],
        'pending'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-800',   'ring' => 'ring-amber-600/20',   'dot' => 'bg-amber-500'],
    ];
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Ticketing Workspace</h1>
            <p class="mt-1 text-sm text-slate-500">
                Issue and manage flight and tour tickets, passenger documents, and travel taxes.
            </p>
        </div>

        <a href="{{ route('ticketing.tickets.create') }}" class="{{ $primaryButton }} self-start sm:self-auto shrink-0">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>New Ticket</span>
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Total Tickets</span>
                <i data-lucide="ticket" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ number_format($stats['totalTickets']) }}</div>
            <div class="{{ $statMeta }}">
                <span class="font-semibold text-slate-700 tabular-nums">{{ number_format($stats['domesticTickets']) }}</span> domestic
                <span class="mx-1 text-slate-300" aria-hidden="true">&middot;</span>
                <span class="font-semibold text-slate-700 tabular-nums">{{ number_format($stats['internationalTickets']) }}</span> international
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Passengers</span>
                <i data-lucide="users" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ number_format($stats['totalPassengers']) }}</div>
            <div class="{{ $statMeta }}">
                @if ($stats['avgPartySize'] > 0)
                    <span class="font-semibold text-slate-700 tabular-nums">{{ $stats['avgPartySize'] }}</span> average party size
                @else
                    No tickets to average yet
                @endif
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Pending Confirmation</span>
                <i data-lucide="clock" class="w-4 h-4 shrink-0 {{ $stats['pendingTickets'] > 0 ? 'text-amber-500' : 'text-slate-400' }}"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ number_format($stats['pendingTickets']) }}</div>
            <div class="{{ $statMeta }}">
                @if ($stats['departingSoon'] > 0)
                    <span class="font-semibold text-amber-700 tabular-nums">{{ $stats['departingSoon'] }}</span> departing within 7 days
                @elseif ($stats['settledTickets'] > 0)
                    <span class="font-semibold text-emerald-700 tabular-nums">{{ $stats['settledTickets'] }}</span> confirmed or issued
                @elseif ($stats['pendingTickets'] > 0)
                    None departing in the next 7 days
                @else
                    Nothing awaiting action
                @endif
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Booked Value</span>
                <i data-lucide="wallet" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">&#8369;{{ number_format($stats['bookedValue'], 0) }}</div>
            <div class="{{ $statMeta }}">
                @if ($stats['upcomingDepartures'] > 0)
                    <span class="font-semibold text-slate-700 tabular-nums">{{ $stats['upcomingDepartures'] }}</span>
                    upcoming {{ $stats['upcomingDepartures'] === 1 ? 'departure' : 'departures' }}
                @else
                    No upcoming departures
                @endif
            </div>
        </div>
    </div>

    <section class="{{ $card }} overflow-hidden">
        <div class="flex items-center justify-between gap-4 px-4 sm:px-5 py-4 border-b border-slate-200">
            <div class="min-w-0">
                <h2 class="text-base font-bold text-slate-900">Recent Tickets</h2>
                @if ($recentTickets->isNotEmpty())
                    <p class="mt-0.5 text-sm text-slate-500">
                        Latest {{ $recentTickets->count() }} of {{ number_format($stats['totalTickets']) }} issued
                    </p>
                @endif
            </div>
            <a href="{{ route('ticketing.tickets.index') }}"
               class="inline-flex items-center gap-1.5 shrink-0 rounded-lg text-sm font-semibold text-navy-700 hover:text-navy-900 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                View all
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        @if ($recentTickets->isNotEmpty())
            {{-- `relative` keeps the sr-only header label inside this scroller;
                 without it the label escapes and widens the whole page. --}}
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="{{ $th }} pl-4 sm:pl-5 xl:pl-5">Reference</th>
                            <th scope="col" class="{{ $th }}">Contact</th>
                            <th scope="col" class="{{ $th }}">Route</th>
                            <th scope="col" class="{{ $th }} hidden md:table-cell">Departure</th>
                            <th scope="col" class="{{ $th }} hidden sm:table-cell text-right">Pax</th>
                            <th scope="col" class="{{ $th }}">Status</th>
                            <th scope="col" class="{{ $th }} pr-4 sm:pr-5 xl:pr-5 text-right">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentTickets as $ticket)
                            @php $tone = $statusTone[$ticket->status] ?? $statusTone['pending']; @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="pl-4 sm:pl-5 pr-3 xl:pr-4 py-4">
                                    <span class="font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">{{ $ticket->booking_reference }}</span>
                                </td>

                                <td class="px-3 xl:px-4 py-4">
                                    <div class="font-semibold text-slate-900 truncate max-w-[10rem] xl:max-w-[13rem]">{{ $ticket->contact_name }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500 truncate max-w-[10rem] xl:max-w-[13rem]">{{ $ticket->contact_phone }}</div>
                                </td>

                                <td class="px-3 xl:px-4 py-4">
                                    <div class="flex items-center gap-1.5 font-semibold text-slate-900">
                                        <span class="truncate max-w-[6rem] xl:max-w-[8rem]">{{ $ticket->origin }}</span>
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                        <span class="truncate max-w-[6rem] xl:max-w-[8rem]">{{ $ticket->destination }}</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500 whitespace-nowrap">
                                        {{ $ticket->trip_type === 'round_trip' ? 'Round trip' : 'One way' }}
                                        @if ($ticket->travel_type === 'international')
                                            &middot; International
                                        @endif
                                    </div>
                                </td>

                                <td class="px-3 xl:px-4 py-4 hidden md:table-cell whitespace-nowrap">
                                    <div class="text-slate-700 tabular-nums">{{ $ticket->departure_date?->format('M j, Y') ?? '—' }}</div>
                                    @if ($ticket->return_date)
                                        <div class="mt-0.5 text-xs text-slate-500 tabular-nums">Returns {{ $ticket->return_date->format('M j') }}</div>
                                    @endif
                                </td>

                                <td class="px-3 xl:px-4 py-4 hidden sm:table-cell text-right text-slate-700 tabular-nums">
                                    {{ $ticket->total_passengers }}
                                </td>

                                <td class="px-3 xl:px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold capitalize whitespace-nowrap ring-1 ring-inset {{ $tone['bg'] }} {{ $tone['text'] }} {{ $tone['ring'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $tone['dot'] }}" aria-hidden="true"></span>
                                        {{ $ticket->status }}
                                    </span>
                                </td>

                                <td class="pl-3 xl:pl-4 pr-4 sm:pr-5 py-4 text-right">
                                    <a href="{{ route('ticketing.tickets.show', $ticket) }}"
                                       class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-1">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="flex flex-col items-center text-center px-6 py-14">
                <i data-lucide="ticket" class="w-8 h-8 text-slate-300"></i>
                <p class="mt-3 text-sm font-semibold text-slate-900">No tickets issued yet</p>
                <p class="mt-1 max-w-sm text-sm text-slate-500">
                    Issue a ticket to record passenger documents, travel taxes, and departure details.
                </p>
                <a href="{{ route('ticketing.tickets.create') }}" class="{{ $primaryButton }} mt-5">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Issue first ticket</span>
                </a>
            </div>
        @endif
    </section>

</div>
@endsection
