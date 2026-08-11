@extends('layouts.admin')

@section('title', 'Bookings Management - AMEGA Admin')
@section('page_title', 'Customer Bookings & Reservations')

@section('content')
<div class="space-y-6">
    
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Client Bookings Directory</h2>
            <p class="text-xs text-dark/50">Manage customer package reservations, travel dates, and payment statuses</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.bookings.index') }}" class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-dark/40">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Search reference, customer name, email..." 
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <select name="status" class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="">All Statuses</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>

        <button type="submit" class="px-5 py-2.5 bg-navy text-white text-xs font-bold rounded-xl hover:bg-primary transition-all">
            Filter
        </button>
    </form>

    <!-- Table Section -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Reference</th>
                        <th class="pb-3 px-3">Customer</th>
                        <th class="pb-3 px-3">Package</th>
                        <th class="pb-3 px-3">Travel Date</th>
                        <th class="pb-3 px-3">Status</th>
                        <th class="pb-3 px-3">Payment</th>
                        <th class="pb-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($bookings as $b)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-3 font-mono font-bold text-primary">
                                {{ $b->booking_reference }}
                            </td>
                            <td class="py-4 px-3">
                                <div class="font-bold text-dark">{{ $b->customer_name }}</div>
                                <div class="text-[11px] text-dark/50">{{ $b->customer_email }}</div>
                                <div class="text-[11px] text-dark/40">{{ $b->customer_phone }}</div>
                            </td>
                            <td class="py-4 px-3">
                                <div class="font-bold text-dark max-w-xs truncate">{{ $b->travelPackage->title ?? 'Deleted Package' }}</div>
                                <div class="text-[11px] text-dark/50">{{ $b->number_of_passengers }} Passenger(s)</div>
                            </td>
                            <td class="py-4 px-3 font-medium text-dark/70 whitespace-nowrap">
                                {{ $b->travel_date ? $b->travel_date->format('M j, Y') : 'N/A' }}
                            </td>
                            <td class="py-4 px-3">
                                <form method="POST" action="{{ route('admin.bookings.update-status', $b) }}" class="inline-block">
                                    @csrf
                                    <select name="status" onchange="this.form.submit()" class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase cursor-pointer border {{ $b->status === 'confirmed' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : ($b->status === 'cancelled' ? 'bg-rose-100 text-rose-800 border-rose-300' : 'bg-amber-100 text-amber-800 border-amber-300') }}">
                                        <option value="pending" {{ $b->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="confirmed" {{ $b->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                        <option value="completed" {{ $b->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ $b->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="payment_status" value="{{ $b->payment_status }}">
                                </form>
                            </td>
                            <td class="py-4 px-3">
                                <form method="POST" action="{{ route('admin.bookings.update-status', $b) }}" class="inline-block">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $b->status }}">
                                    <select name="payment_status" onchange="this.form.submit()" class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase cursor-pointer border {{ $b->payment_status === 'fully_paid' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : ($b->payment_status === 'deposit_paid' ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-gray-100 text-gray-700 border-gray-300') }}">
                                        <option value="unpaid" {{ $b->payment_status === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                        <option value="deposit_paid" {{ $b->payment_status === 'deposit_paid' ? 'selected' : '' }}>Deposit Paid</option>
                                        <option value="fully_paid" {{ $b->payment_status === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
                                    </select>
                                </form>
                            </td>
                            <td class="py-4 px-3 text-right space-x-1">
                                <a href="{{ route('bookings.confirmation', $b->booking_reference) }}" target="_blank" class="p-2 text-primary hover:bg-primary/5 rounded-lg inline-block" title="View Receipt">
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.bookings.destroy', $b) }}" onsubmit="return confirm('Delete this booking?');" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg" title="Delete Booking">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-dark/40 font-medium">
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
