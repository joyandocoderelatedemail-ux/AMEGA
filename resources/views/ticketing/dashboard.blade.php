@extends('layouts.ticketing')

@section('title', 'Ticketing Workspace - AMEGA')

@section('content')
<div class="space-y-8">
    
    <!-- Welcome Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-navy via-primary to-navy text-white p-6 sm:p-8 shadow-xl border border-white/10">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent/20 border border-accent/30 text-accent text-xs font-heading font-extrabold uppercase tracking-wider">
                    <i data-lucide="plane-takeoff" class="w-3.5 h-3.5"></i>
                    Phase 1: Local / Domestic Tour
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-heading font-black tracking-tight text-white">
                    Ticketing &amp; Reservation Workspace
                </h1>
                <p class="text-xs sm:text-sm text-white/80 max-w-2xl font-normal leading-relaxed">
                    Welcome, <strong class="text-accent">{{ Auth::user()->name }}</strong>. Issue and manage local flight &amp; domestic tour tickets, passenger documents, and travel taxes.
                </p>
            </div>

            <!-- Quick Action Button -->
            <div class="shrink-0 flex items-center gap-3">
                <a href="{{ route('ticketing.tickets.create') }}" 
                   class="inline-flex items-center justify-center gap-2.5 px-5 py-3.5 rounded-2xl bg-accent text-dark font-heading font-extrabold text-xs sm:text-sm uppercase tracking-wider shadow-lg shadow-accent/25 hover:bg-accent-dark hover:scale-[1.02] active:scale-95 transition-all">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Create New Ticket</span>
                </a>
            </div>
        </div>

        <!-- Subtle Background Decorative Graphic -->
        <div class="absolute -right-10 -bottom-10 opacity-10 pointer-events-none text-white">
            <i data-lucide="ticket" class="w-64 h-64"></i>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <i data-lucide="ticket" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-xl sm:text-2xl font-heading font-black text-dark">{{ $stats['totalTickets'] ?? 0 }}</div>
                <div class="text-[11px] font-bold text-dark/50 uppercase tracking-wider">Total Tickets</div>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i data-lucide="map-pin" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-xl sm:text-2xl font-heading font-black text-dark">{{ $stats['domesticTickets'] ?? 0 }}</div>
                <div class="text-[11px] font-bold text-dark/50 uppercase tracking-wider">Domestic Tours</div>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-xl sm:text-2xl font-heading font-black text-dark">{{ $stats['totalPassengers'] ?? 0 }}</div>
                <div class="text-[11px] font-bold text-dark/50 uppercase tracking-wider">Total Passengers</div>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i data-lucide="clock" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-xl sm:text-2xl font-heading font-black text-dark">{{ $stats['pendingTickets'] ?? 0 }}</div>
                <div class="text-[11px] font-bold text-dark/50 uppercase tracking-wider">Pending Confirmation</div>
            </div>
        </div>
    </div>

    <!-- Recent Ticket Bookings Table -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-heading font-bold text-dark">Recent Ticket Bookings</h2>
                <p class="text-xs text-dark/50">Latest tickets issued through the ticketing module</p>
            </div>
            <a href="{{ route('ticketing.tickets.index') }}" class="text-xs font-heading font-bold text-primary hover:underline flex items-center gap-1">
                View All Directory &rarr;
            </a>
        </div>

        @if(isset($recentTickets) && $recentTickets->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 border-b border-gray-100 text-dark/60 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4 sm:px-6">Ticket Reference</th>
                            <th class="py-3.5 px-4">Contact / Traveler</th>
                            <th class="py-3.5 px-4">Route &amp; Destination</th>
                            <th class="py-3.5 px-4">Travel Dates</th>
                            <th class="py-3.5 px-4">Passengers</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 sm:px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($recentTickets as $ticket)
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
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[10px] uppercase tracking-wider">
                                            Issued
                                        </span>
                                    @elseif($ticket->status === 'confirmed')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-bold text-[10px] uppercase tracking-wider">
                                            Confirmed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-bold text-[10px] uppercase tracking-wider">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 sm:px-6 text-right">
                                    <a href="{{ route('ticketing.tickets.show', $ticket) }}" 
                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-primary hover:text-white font-bold text-xs transition-colors">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        <span>Details</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <!-- Empty State -->
            <div class="p-10 sm:p-14 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-3xl bg-primary/10 flex items-center justify-center text-primary">
                    <i data-lucide="ticket-plus" class="w-8 h-8"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-heading font-bold text-dark">No ticket bookings recorded yet</h3>
                    <p class="text-xs text-dark/50 max-w-sm mx-auto">
                        Start issuing local and domestic tour tickets with passenger documents and travel tax requirements.
                    </p>
                </div>
                <div>
                    <a href="{{ route('ticketing.tickets.create') }}" 
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-all shadow-md">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Start First Booking</span>
                    </a>
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
