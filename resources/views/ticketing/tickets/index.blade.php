@extends('layouts.ticketing')

@section('title', 'Ticket Directory - AMEGA')

@section('content')
<div class="space-y-6">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-heading font-extrabold uppercase tracking-wider">
                <i data-lucide="tickets" class="w-3.5 h-3.5"></i>
                Ticketing Archive
            </span>
            <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight mt-1">Ticket Directory</h1>
        </div>

        <a href="{{ route('ticketing.tickets.create') }}" 
           class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-accent text-dark font-heading font-extrabold text-xs uppercase tracking-wider shadow-lg shadow-accent/25 hover:bg-accent-dark transition-all">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>New Ticket Booking</span>
        </a>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('ticketing.tickets.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-6">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3.5 top-3.5"></i>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Search ticket ref, traveler name, email, or destination..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>
            <div class="sm:col-span-3">
                <select name="trip_type" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">All Trip Types</option>
                    <option value="round_trip" {{ request('trip_type') === 'round_trip' ? 'selected' : '' }}>Round Trip</option>
                    <option value="one_way" {{ request('trip_type') === 'one_way' ? 'selected' : '' }}>One Way</option>
                </select>
            </div>
            <div class="sm:col-span-3 flex items-center gap-2">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-primary text-white font-bold text-xs hover:bg-navy transition-colors">
                    Filter Records
                </button>
                @if(request()->hasAny(['search', 'trip_type', 'status']))
                    <a href="{{ route('ticketing.tickets.index') }}" class="px-3 py-2.5 rounded-xl bg-gray-100 text-dark/60 hover:text-dark text-xs font-bold transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Pending: saved in the wizard to continue later -->
    @if($pendingTickets->isNotEmpty())
        <div id="pending-tickets" class="bg-white rounded-3xl border border-amber-200 shadow-sm overflow-hidden scroll-mt-24">
            <div class="px-5 py-4 border-b border-amber-100 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-heading text-sm font-bold text-dark flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i>
                        Pending &mdash; continue later
                    </h2>
                    <p class="text-[11px] text-dark/50 mt-0.5">Tickets saved part-way through. Continue one to pick up on the same step with everything filled in.</p>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">{{ $pendingTickets->count() }} pending</span>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($pendingTickets as $pending)
                    <li class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-dark truncate">{{ $pending->client_name }}</p>
                            <p class="text-[11px] text-dark/50 truncate">
                                {{ $pending->summary ?: 'Trip details not entered yet' }}
                                <span class="text-dark/30">&middot;</span> saved {{ $pending->updated_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('ticketing.tickets.create', ['pending' => $pending->id]) }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-white font-bold text-xs hover:bg-navy transition-colors">
                                <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                Continue
                            </a>
                            <form method="POST" action="{{ route('ticketing.tickets.pending.destroy', $pending) }}" class="m-0"
                                  onsubmit="return confirm('Discard this pending ticket? Its saved details will be lost.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Discard pending ticket"
                                        class="p-2 rounded-xl border border-gray-200 text-rose-600 hover:border-rose-300 transition-colors">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Tickets Table -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        @if($tickets->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 border-b border-gray-100 text-dark/60 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4 sm:px-6">Reference</th>
                            <th class="py-3.5 px-4">Primary Contact</th>
                            <th class="py-3.5 px-4">Route &amp; Destination</th>
                            <th class="py-3.5 px-4">Schedule</th>
                            <th class="py-3.5 px-4">Passengers</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($tickets as $ticket)
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="py-4 px-4 sm:px-6 font-mono font-bold text-primary">
                                    {{ $ticket->booking_reference }}
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-bold text-dark">{{ $ticket->contact_name }}</div>
                                    <div class="text-[11px] text-dark/50">{{ $ticket->contact_phone }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-bold text-dark flex items-center gap-1.5">
                                        <span>{{ $ticket->origin }}</span>
                                        <i data-lucide="arrow-right" class="w-3 h-3 text-dark/40"></i>
                                        <span>{{ $ticket->destination }}</span>
                                    </div>
                                    <div class="text-[10px] text-dark/50 uppercase font-semibold">
                                        {{ $ticket->trip_type === 'round_trip' ? 'Round Trip' : 'One Way' }}
                                        @if($ticket->package_name)
                                            • <span class="text-primary">{{ $ticket->package_name }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-dark">{{ $ticket->departure_date->format('M d, Y') }}</div>
                                    @if($ticket->return_date)
                                        <div class="text-[11px] text-dark/50">Return: {{ $ticket->return_date->format('M d, Y') }}</div>
                                    @endif
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 font-bold text-[11px]">
                                        {{ $ticket->total_passengers }} Pax
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    @if($ticket->status === 'issued')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[10px] uppercase tracking-wider">
                                            Issued
                                        </span>
                                    @elseif($ticket->status === 'confirmed')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 font-bold text-[10px] uppercase tracking-wider">
                                            Confirmed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 font-bold text-[10px] uppercase tracking-wider">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 sm:px-6 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        @if($ticket->bookingAgreement)
                                            <a href="{{ route('ticketing.agreements.show', $ticket->bookingAgreement) }}" 
                                               title="View Booking Agreement"
                                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white font-bold text-xs transition-colors">
                                                <i data-lucide="file-check-2" class="w-3.5 h-3.5"></i>
                                                <span class="hidden sm:inline">Agreement</span>
                                            </a>
                                        @else
                                            <a href="{{ route('ticketing.agreements.create', $ticket) }}" 
                                               title="Create Booking Agreement"
                                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-gray-100 text-dark/70 hover:bg-primary hover:text-white font-bold text-xs transition-colors">
                                                <i data-lucide="file-signature" class="w-3.5 h-3.5"></i>
                                                <span class="hidden sm:inline">Agreement</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('ticketing.tickets.show', $ticket) }}" 
                                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-primary hover:text-white font-bold text-xs transition-colors">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            <span>View</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($tickets->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $tickets->links() }}
                </div>
            @endif
        @else
            <div class="p-12 text-center space-y-3">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gray-100 flex items-center justify-center text-dark/40">
                    <i data-lucide="ticket-x" class="w-7 h-7"></i>
                </div>
                <div class="text-sm font-heading font-bold text-dark">No ticket bookings matched your search</div>
                <p class="text-xs text-dark/50">Try refining your filter criteria or create a new ticket booking.</p>
            </div>
        @endif
    </div>

</div>
@endsection
