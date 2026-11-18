@extends('layouts.ticketing')

@section('title', 'Ticketing Workspace - AMEGA')

@php
    /**
     * Tokens for this page: rounded-xl cards, rounded-lg controls, rounded-full
     * pills; weights 400/600/700; nothing below text-xs. Each hue means one
     * thing — emerald = done / paid, amber = awaiting action, rose = returned,
     * navy = brand / with the cashier, slate = neutral.
     *
     * Headings need no `font-heading`: the staff font class (staff-type or
     * portal-minimal) already sets every heading in Inter.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $statLabel = 'text-sm font-semibold text-slate-600';
    $statFigure = 'text-2xl font-bold text-slate-900 tracking-tight tabular-nums';
    $statMeta = 'text-xs text-slate-500';
    $primaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
    $secondaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:border-slate-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
    $th = 'px-3 xl:px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';
    $sectionHead = 'px-4 sm:px-5 py-4 border-b border-slate-200';

    // The stages a booking walks through, left to right.
    $flowCards = [
        'returned' => ['label' => 'Returned', 'hint' => 'Fix and resend to the admin', 'icon' => 'rotate-ccw', 'box' => 'bg-rose-50 border-rose-200', 'head' => 'text-rose-700', 'figure' => 'text-rose-900', 'meta' => 'text-rose-800'],
        'approval' => ['label' => 'Waiting for approval', 'hint' => 'With the admin to approve', 'icon' => 'shield-check', 'box' => 'bg-amber-50 border-amber-200', 'head' => 'text-amber-700', 'figure' => 'text-amber-900', 'meta' => 'text-amber-800'],
        'cashier' => ['label' => 'With the cashier', 'hint' => 'Approved, payment not complete', 'icon' => 'banknote', 'box' => 'bg-navy-50 border-navy-100', 'head' => 'text-navy-700', 'figure' => 'text-navy-900', 'meta' => 'text-navy-800'],
        'ready' => ['label' => 'Ready to issue', 'hint' => 'Paid in full, issue the ticket', 'icon' => 'send', 'box' => 'bg-emerald-50 border-emerald-200', 'head' => 'text-emerald-700', 'figure' => 'text-emerald-900', 'meta' => 'text-emerald-800'],
        'issued' => ['label' => 'Issued', 'hint' => 'Done, the client has the ticket', 'icon' => 'circle-check', 'box' => 'bg-slate-50 border-slate-200', 'head' => 'text-slate-600', 'figure' => 'text-slate-900', 'meta' => 'text-slate-500'],
    ];
    $collectedShare = $stats['bookedValue'] > 0 ? (int) round(min(1, $stats['collected'] / $stats['bookedValue']) * 100) : 0;
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Ticketing Workspace</h1>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('ticketing.quotations.index') }}" class="{{ $secondaryButton }}">
                <i data-lucide="file-text" class="w-4 h-4" aria-hidden="true"></i>
                <span>Quotations</span>
            </a>
            @can('ticketing.create')
                <a href="{{ route('ticketing.tickets.create') }}" class="{{ $primaryButton }}">
                    <i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i>
                    <span>New Ticket</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Where every booking stands: agent → admin approval → cashier → issue. --}}
    <section class="{{ $card }} p-4 sm:p-5" aria-labelledby="flow-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
            <h2 id="flow-heading" class="text-base font-bold text-slate-900">Where your tickets are</h2>
            <span class="text-xs text-slate-500">
                @if ($flow['returned'] + $flow['approval'] + $flow['cashier'] + $flow['ready'] === 0)
                    Nothing awaiting action &middot;
                @endif
                Agent &rarr; Admin approval &rarr; Cashier &rarr; Issue
            </span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
            @foreach ($flowCards as $stage => $c)
                <div class="flex flex-col gap-1.5 p-4 rounded-xl border {{ $c['box'] }}">
                    <span class="flex items-center justify-between gap-2 text-sm font-semibold {{ $c['head'] }}">
                        {{ $c['label'] }}
                        <i data-lucide="{{ $c['icon'] }}" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
                    </span>
                    <span class="text-2xl font-bold tabular-nums {{ $c['figure'] }}">{{ number_format($flow[$stage]) }}</span>
                    <span class="text-xs {{ $c['meta'] }}">{{ $c['hint'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Returned by an admin: the reason, and the way to fix it, before anything else. --}}
    @if ($returnedTickets->isNotEmpty())
        <section class="{{ $card }} overflow-hidden ring-1 ring-rose-200" aria-labelledby="returned-heading">
            <div class="px-4 sm:px-5 py-4 border-b border-rose-100 bg-rose-50/60">
                <h2 id="returned-heading" class="text-base font-bold text-rose-900">Returned To You <span class="ml-1 inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 rounded-full bg-rose-600 text-white text-xs font-bold align-middle">{{ $returnedTickets->count() }}</span></h2>
                <p class="mt-0.5 text-sm text-rose-800">Fix what the admin asked, then send it again; nothing is paid or issued until it is approved.</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($returnedTickets as $ticket)
                    <li class="flex flex-col gap-3 px-4 sm:px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="font-mono text-xs font-semibold text-slate-900 hover:underline">{{ $ticket->booking_reference }}</a>
                            <span class="ml-2 font-semibold text-slate-900">{{ $ticket->contact_name }}</span>
                            <span class="block text-xs text-slate-500">
                                {{ $ticket->origin }} &rarr; {{ $ticket->destination }} &middot; departs {{ $ticket->departure_date?->format('M j, Y') }}
                                &middot; returned by {{ $ticket->reviewedBy?->name ?? 'the admin' }}{{ $ticket->reviewed_at ? ' '.$ticket->reviewed_at->diffForHumans() : '' }}
                            </span>
                            <p class="mt-2 text-sm text-rose-800 whitespace-pre-line">&ldquo;{{ $ticket->review_note }}&rdquo;</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <a href="{{ route('ticketing.tickets.edit', $ticket) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700">
                                <i data-lucide="pencil" class="w-3.5 h-3.5" aria-hidden="true"></i>
                                <span>Fix and resend</span>
                            </a>
                            @include('ticketing.tickets._resend-button')
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Airline holds to ticket within 24 hours, or already lapsed. --}}
    @if ($deadlineTickets->isNotEmpty())
        <section class="{{ $card }} overflow-hidden ring-1 ring-amber-200" aria-labelledby="deadline-heading">
            <div class="{{ $sectionHead }}">
                <h2 id="deadline-heading" class="text-base font-bold text-slate-900">Airline Holds Due</h2>
                <p class="mt-0.5 text-sm text-slate-500">These must be paid and issued before the airline's ticketing deadline, or the seats are released.</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($deadlineTickets as $ticket)
                    @php $passed = $ticket->deadlineState() === 'passed'; @endphp
                    <li>
                        <a href="{{ route('ticketing.tickets.show', $ticket) }}#airline-hold" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 sm:px-5 py-3 hover:bg-slate-50 transition-colors">
                            <span class="min-w-0">
                                <span class="font-mono text-xs font-semibold text-slate-900">{{ $ticket->booking_reference }}</span>
                                <span class="ml-2 font-semibold text-slate-900">{{ $ticket->contact_name }}</span>
                                <span class="block text-xs text-slate-500">
                                    PNR {{ $ticket->airline_pnr ?: '—' }}
                                    &middot; {{ $ticket->isAwaitingApproval() ? 'waiting for approval' : ($ticket->isRejected() ? 'returned by the admin' : ($ticket->isFullyPaid() ? 'paid, ready to issue' : 'waiting for payment')) }}
                                </span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold {{ $passed ? 'text-rose-700' : 'text-amber-700' }}">
                                <i data-lucide="{{ $passed ? 'alarm-clock-off' : 'alarm-clock' }}" class="w-3.5 h-3.5" aria-hidden="true"></i>
                                {{ $ticket->deadlineLabel() }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($flightChangesToTell->isNotEmpty())
        <section class="{{ $card }} overflow-hidden ring-1 ring-amber-200" aria-labelledby="changes-heading">
            <div class="{{ $sectionHead }}">
                <h2 id="changes-heading" class="text-base font-bold text-slate-900">Flight Changes To Tell</h2>
                <p class="mt-0.5 text-sm text-slate-500">The flight changed and the client has not been told yet. Open the ticket to send the message.</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($flightChangesToTell as $change)
                    <li>
                        <a href="{{ route('ticketing.tickets.show', $change->ticket) }}#flight-changes"
                           class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 sm:px-5 py-3 hover:bg-slate-50 transition-colors">
                            <span class="min-w-0">
                                <span class="font-mono text-xs font-semibold text-slate-900">{{ $change->ticket->booking_reference }}</span>
                                <span class="ml-2 font-semibold text-slate-900">{{ $change->ticket->contact_name }}</span>
                                <span class="block text-xs text-slate-500">{{ $change->label() }} &middot; {{ $change->created_at->diffForHumans() }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700">
                                <i data-lucide="bell-ring" class="w-3.5 h-3.5" aria-hidden="true"></i>
                                Not told yet
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="{{ $card }} p-5 flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Total Tickets</span>
                <i data-lucide="ticket" class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true"></i>
            </div>
            <div class="{{ $statFigure }}">{{ number_format($stats['totalTickets']) }}</div>
            <div class="{{ $statMeta }}">
                <span class="font-semibold text-slate-700 tabular-nums">{{ number_format($stats['domesticTickets']) }}</span> domestic
                <span class="mx-1 text-slate-300" aria-hidden="true">&middot;</span>
                <span class="font-semibold text-slate-700 tabular-nums">{{ number_format($stats['internationalTickets']) }}</span> international
            </div>
        </div>

        <div class="{{ $card }} p-5 flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Passengers</span>
                <i data-lucide="users" class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true"></i>
            </div>
            <div class="{{ $statFigure }}">{{ number_format($stats['totalPassengers']) }}</div>
            <div class="{{ $statMeta }}">
                @if ($stats['departingSoon'] > 0)
                    <span class="font-semibold text-amber-700 tabular-nums">{{ $stats['departingSoon'] }}</span> departing within 7 days
                @elseif ($stats['avgPartySize'] > 0)
                    <span class="font-semibold text-slate-700 tabular-nums">{{ $stats['avgPartySize'] }}</span> average party size
                @else
                    No tickets to average yet
                @endif
            </div>
        </div>

        <div class="{{ $card }} p-5 flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Booked Value</span>
                <i data-lucide="wallet" class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true"></i>
            </div>
            <div class="{{ $statFigure }}">&#8369;{{ number_format($stats['bookedValue'], 0) }}</div>
            @if ($stats['bookedValue'] > 0)
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden" role="img" aria-label="{{ $collectedShare }}% collected">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $collectedShare }}%"></div>
                </div>
                <div class="{{ $statMeta }}"><span class="font-semibold text-emerald-700 tabular-nums">&#8369;{{ number_format($stats['collected'], 0) }}</span> collected ({{ $collectedShare }}%)</div>
            @else
                <div class="{{ $statMeta }}">Nothing booked yet</div>
            @endif
        </div>

        <a href="{{ route('ticketing.quotations.index') }}" class="{{ $card }} p-5 flex flex-col gap-1.5 hover:border-slate-300 transition-colors">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Open Quotations</span>
                <i data-lucide="file-text" class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true"></i>
            </div>
            <div class="{{ $statFigure }}">{{ number_format($quotes['count']) }}</div>
            <div class="{{ $statMeta }}">
                @if ($quotes['count'] > 0)
                    <span class="font-semibold text-slate-700 tabular-nums">&#8369;{{ number_format($quotes['value'], 0) }}</span> quoted, not yet booked
                @else
                    No quotations waiting on a client
                @endif
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

            <section class="{{ $card }} overflow-hidden" aria-labelledby="departing-heading">
                <div class="{{ $sectionHead }}">
                    <h2 id="departing-heading" class="text-base font-bold text-slate-900">Departing Soon</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Flights in the next 7 days. Remind the client.</p>
                </div>
                @forelse ($departingSoonTickets as $ticket)
                    @php $when = \App\Notifications\FlightReminderNotification::when($ticket); @endphp
                    <div class="flex items-center gap-3 px-4 sm:px-5 py-3.5 {{ $loop->last ? '' : 'border-b border-slate-100' }}">
                        <div class="w-11 shrink-0 text-center rounded-lg border py-1 {{ in_array($when, ['today', 'tomorrow', 'in 2 days'], true) ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }}">
                            <div class="text-[11px] font-semibold uppercase {{ in_array($when, ['today', 'tomorrow', 'in 2 days'], true) ? 'text-amber-700' : 'text-slate-600' }}">{{ $ticket->departure_date->format('M') }}</div>
                            <div class="text-base font-bold leading-tight text-slate-900 tabular-nums">{{ $ticket->departure_date->format('j') }}</div>
                        </div>
                        <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="flex-1 min-w-0 group">
                            <div class="text-sm font-semibold text-slate-900 truncate group-hover:underline">{{ $ticket->contact_name }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ $ticket->origin }} &rarr; {{ $ticket->destination }} &middot; <span class="font-semibold {{ in_array($when, ['today', 'tomorrow', 'in 2 days'], true) ? 'text-amber-700' : '' }}">{{ ucfirst($when) }}</span></div>
                        </a>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @include('ticketing._message-actions', ['compact' => true])
                        </div>
                    </div>
                @empty
                    <p class="px-4 sm:px-5 py-8 text-center text-sm text-slate-500">No flights in the next 7 days.</p>
                @endforelse
            </section>

            <section class="{{ $card }} overflow-hidden" aria-labelledby="quotes-heading">
                <div class="flex items-center justify-between gap-3 {{ $sectionHead }}">
                    <div>
                        <h2 id="quotes-heading" class="text-base font-bold text-slate-900">Open Quotations</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Priced, waiting for the client</p>
                    </div>
                    @if ($quotes['count'] > 0)
                        <a href="{{ route('ticketing.quotations.index') }}" class="text-sm font-semibold text-navy-700 hover:text-navy-900 shrink-0">All {{ $quotes['count'] }}</a>
                    @endif
                </div>
                @forelse ($quotes['latest'] as $quote)
                    <a href="{{ route('ticketing.tickets.show', $quote) }}" class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3.5 hover:bg-slate-50 transition-colors {{ $loop->last ? '' : 'border-b border-slate-100' }}">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900 truncate">{{ $quote->contact_name }}</span>
                            <span class="block text-xs truncate {{ $quote->quoteExpired() ? 'font-semibold text-rose-700' : 'text-slate-500' }}">
                                {{ $quote->origin }} &rarr; {{ $quote->destination }} &middot;
                                @if ($quote->quoteExpired())
                                    expired {{ $quote->quote_valid_until->format('M j') }}
                                @elseif ($quote->quote_valid_until)
                                    valid until {{ $quote->quote_valid_until->format('M j') }}
                                @else
                                    no end date
                                @endif
                            </span>
                        </span>
                        @if ((float) $quote->total_amount > 0)
                            <span class="text-sm font-semibold text-slate-900 tabular-nums whitespace-nowrap">&#8369;{{ number_format((float) $quote->total_amount, 0) }}</span>
                        @else
                            <span class="text-xs font-semibold text-amber-700 whitespace-nowrap">Not priced</span>
                        @endif
                    </a>
                @empty
                    <p class="px-4 sm:px-5 py-8 text-center text-sm text-slate-500">No open quotations.</p>
                @endforelse
            </section>

    </div>

    <section class="{{ $card }} overflow-hidden" aria-labelledby="recent-heading">
        <div class="flex items-center justify-between gap-4 {{ $sectionHead }}">
            <div class="min-w-0">
                <h2 id="recent-heading" class="text-base font-bold text-slate-900">Recent Tickets</h2>
                @if ($recentTickets->isNotEmpty())
                    <p class="mt-0.5 text-sm text-slate-500">Latest {{ $recentTickets->count() }} of {{ number_format($stats['totalTickets']) }}</p>
                @endif
            </div>
            <a href="{{ route('ticketing.tickets.index') }}"
               class="inline-flex items-center gap-1.5 shrink-0 rounded-lg text-sm font-semibold text-navy-700 hover:text-navy-900 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                View all
                <i data-lucide="arrow-right" class="w-4 h-4" aria-hidden="true"></i>
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
                            <th scope="col" class="{{ $th }}">Client</th>
                            <th scope="col" class="{{ $th }}">Route</th>
                            <th scope="col" class="{{ $th }} hidden md:table-cell">Departure</th>
                            <th scope="col" class="{{ $th }}">Status</th>
                            <th scope="col" class="{{ $th }} text-right hidden sm:table-cell">Total</th>
                            <th scope="col" class="{{ $th }} pr-4 sm:pr-5 xl:pr-5 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentTickets as $ticket)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="pl-4 sm:pl-5 pr-3 xl:pr-4 py-4">
                                    <span class="font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">{{ $ticket->booking_reference }}</span>
                                </td>
                                <td class="px-3 xl:px-4 py-4">
                                    <div class="font-semibold text-slate-900 truncate max-w-[10rem] xl:max-w-[11rem]">{{ $ticket->contact_name }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500 truncate max-w-[10rem] xl:max-w-[11rem]">{{ $ticket->contact_phone }}</div>
                                </td>
                                <td class="px-3 xl:px-4 py-4">
                                    <div class="flex items-center gap-1.5 font-semibold text-slate-900">
                                        <span class="truncate max-w-[6rem] xl:max-w-[8rem]">{{ $ticket->origin }}</span>
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400 shrink-0" aria-hidden="true"></i>
                                        <span class="truncate max-w-[6rem] xl:max-w-[8rem]">{{ $ticket->destination }}</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500 whitespace-nowrap">
                                        {{ $ticket->trip_type === 'round_trip' ? 'Round trip' : ($ticket->trip_type === 'multi_city' ? 'Multi-city' : 'One way') }}
                                        &middot; {{ $ticket->travel_type === 'international' ? 'International' : 'Domestic' }}
                                    </div>
                                </td>
                                <td class="px-3 xl:px-4 py-4 hidden md:table-cell whitespace-nowrap">
                                    <div class="text-slate-700 tabular-nums">{{ $ticket->departure_date?->format('M j, Y') ?? '—' }}</div>
                                    @if ($ticket->return_date)
                                        <div class="mt-0.5 text-xs text-slate-500 tabular-nums">Returns {{ $ticket->return_date->format('M j') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 xl:px-4 py-4">@include('ticketing._stage-pill')</td>
                                <td class="px-3 xl:px-4 py-4 hidden sm:table-cell text-right font-semibold text-slate-900 tabular-nums whitespace-nowrap">&#8369;{{ number_format((float) $ticket->total_amount, 0) }}</td>
                                <td class="pl-3 xl:pl-4 pr-4 sm:pr-5 py-4">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        @include('ticketing._message-actions', ['compact' => true])
                                        <a href="{{ route('ticketing.tickets.show', $ticket) }}"
                                           class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-1">
                                            Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="flex flex-col items-center text-center px-6 py-14">
                <i data-lucide="ticket" class="w-8 h-8 text-slate-300" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-semibold text-slate-900">No tickets issued yet</p>
                <p class="mt-1 max-w-sm text-sm text-slate-500">Book a ticket to record passenger documents, travel taxes, and departure details.</p>
                @can('ticketing.create')
                    <a href="{{ route('ticketing.tickets.create') }}" class="{{ $primaryButton }} mt-5">
                        <i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i>
                        <span>Book the first ticket</span>
                    </a>
                @endcan
            </div>
        @endif
    </section>

</div>
@endsection
