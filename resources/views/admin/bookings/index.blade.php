@extends('layouts.admin')

@section('title', 'Bookings Management - AMEGA Admin')
@section('page_title', 'Customer Bookings & Reservations')

@section('content')
<div class="space-y-6">
    
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Client Bookings Directory</h2>
            <p class="text-xs text-dark/50">Website package bookings: register the customer, book the trip in Ticketing, and keep track of the follow-up</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.bookings.index') }}" class="bg-white rounded-2xl p-4 border border-gray-200 shadow-card flex flex-col sm:flex-row sm:flex-wrap gap-3">
        <div class="flex-1 relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-dark/40">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Search reference, customer name, email..." 
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <button type="submit" class="px-5 py-2.5 bg-navy text-white text-xs font-bold rounded-xl hover:bg-primary transition-all">
            Filter
        </button>
        <x-date-range label="Booked" class="w-full" inputClass="py-2 px-3 rounded-xl bg-gray-50 border border-gray-200 text-xs text-dark focus:outline-none focus:ring-2 focus:ring-primary" buttonClass="py-2 px-3 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary" />
    </form>

    <!-- Table Section -->
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Reference</th>
                        <th class="pb-3 px-3">Customer</th>
                        <th class="pb-3 px-3">Package</th>
                        <th class="pb-3 px-3">Travel Date</th>
                        <th class="pb-3 px-3">Follow-up</th>
                        <th class="pb-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($bookings as $b)
                        @php $isNew = in_array($b->id, $newIds ?? [], true); @endphp
                        <tr class="{{ $isNew ? 'bg-rose-50/40' : '' }} hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-3 font-mono font-bold text-primary">
                                {{ $b->booking_reference }}
                                @if ($isNew)
                                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[9px] font-sans font-extrabold uppercase tracking-wider">New</span>
                                @endif
                            </td>
                            <td class="py-4 px-3">
                                <div class="font-bold text-dark">{{ $b->customer_name }}</div>
                                <div class="text-[11px] text-dark/50">{{ $b->customer_email }}</div>
                                <div class="text-[11px] text-dark/40">{{ $b->customer_phone }}</div>
                            </td>
                            <td class="py-4 px-3">
                                <div class="font-bold text-dark min-w-[14rem] max-w-sm leading-snug">{{ $b->travelPackage->title ?? 'Deleted Package' }}</div>
                                <div class="text-[11px] text-dark/50">{{ $b->number_of_passengers }} Passenger(s)</div>
                                @if ($b->ticketBooking)
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <a href="{{ route('ticketing.tickets.show', $b->ticketBooking) }}" class="font-mono font-bold text-primary hover:underline">{{ $b->ticketBooking->booking_reference }}</a>
                                        @include('ticketing._stage-pill', ['ticket' => $b->ticketBooking])
                                    </div>
                                @elseif ($b->user?->isClient())
                                    @can('ticketing.create')
                                        <a href="{{ route('ticketing.tickets.create', ['client' => $b->user_id, 'website_booking' => $b->id]) }}"
                                           class="mt-1.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-navy-700 text-white text-[11px] font-bold hover:bg-navy-800">
                                            <i data-lucide="ticket" class="w-3.5 h-3.5"></i> Book in Ticketing
                                        </a>
                                    @endcan
                                @else
                                    @can('users.create')
                                        <a href="{{ route('admin.users.create', ['website_booking' => $b->id]) }}"
                                           class="mt-1.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-navy-700 text-navy-700 text-[11px] font-bold hover:bg-navy-50">
                                            <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Register this client
                                        </a>
                                    @endcan
                                @endif
                            </td>
                            <td class="py-4 px-3 font-medium text-dark/70 whitespace-nowrap">
                                {{ $b->travel_date ? $b->travel_date->format('M j, Y') : 'N/A' }}
                            </td>
                            <td class="py-4 px-3 min-w-[15rem]">
                                <form method="POST" action="{{ route('admin.bookings.update', $b) }}" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <label class="inline-flex items-center gap-2 font-bold text-dark cursor-pointer">
                                        <input type="checkbox" name="contacted" value="1" @checked($b->contacted_at) class="rounded border-gray-300 text-primary focus:ring-primary">
                                        Client contacted
                                    </label>
                                    @if ($b->contacted_at)
                                        <div class="text-[11px] text-emerald-700 font-semibold">{{ $b->contacted_at->format('M j, Y g:i A') }}{{ $b->contactedBy ? ' by '.$b->contactedBy->name : '' }}</div>
                                    @endif
                                    <textarea name="remarks" rows="2" maxlength="2000" placeholder="Remarks"
                                              class="w-full px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-xs text-dark focus:outline-none focus:ring-2 focus:ring-primary">{{ $b->remarks }}</textarea>
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-navy text-white text-[11px] font-bold hover:bg-primary">Save</button>
                                </form>
                            </td>
                            <td class="py-4 px-3 text-right space-x-1">
                                <a href="{{ route('bookings.confirmation', $b->booking_reference) }}" target="_blank" class="p-2 text-primary hover:bg-primary/5 rounded-lg inline-block" title="View Receipt">
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                                @if (auth()->user()?->isAdmin())
                                <form method="POST" action="{{ route('admin.bookings.destroy', $b) }}" onsubmit="return confirm('Delete this booking?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg" title="Delete Booking">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-dark/40 font-medium">
                                No client bookings found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-4">
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection
