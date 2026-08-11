@extends('layouts.admin')

@section('title', 'Admin Dashboard - AMEGA Travel & Tours')
@section('page_title', 'Dashboard Overview')

@section('content')
<div class="space-y-8">
    
    <!-- Welcome Banner -->
    <div class="rounded-3xl bg-navy text-white p-6 sm:p-8 relative overflow-hidden shadow-xl border border-white/10">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-primary/20 blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent/20 text-accent font-bold text-xs uppercase tracking-widest mb-3 border border-accent/30">
                    <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                    Administrator Control Panel
                </span>
                <h2 class="font-heading text-2xl sm:text-3xl font-bold text-white">Welcome back, {{ Auth::user()->name }}!</h2>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('admin.bookings.index') }}" class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                    <span>Manage Bookings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Stat Card 1: Total Bookings -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-extrabold uppercase tracking-wider text-dark">Package Bookings</span>
                <div class="w-10 h-10 rounded-xl bg-accent/20 text-dark flex items-center justify-center font-bold">
                    <i data-lucide="calendar" class="w-5 h-5 text-primary"></i>
                </div>
            </div>
            <div class="font-heading text-3xl font-bold text-dark mb-1">{{ $stats['total_bookings'] }}</div>
            <p class="text-xs text-dark/50 font-medium">Customer reservations</p>
        </div>

        <!-- Stat Card 2: Inquiries -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-extrabold uppercase tracking-wider text-dark">Inquiries Received</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="inbox" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="font-heading text-3xl font-bold text-dark mb-1">{{ $stats['total_inquiries'] }}</div>
            <p class="text-xs text-dark/50 font-medium">Submitted leads & messages</p>
        </div>

        <!-- Stat Card 3: Packages -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-extrabold uppercase tracking-wider text-dark">Travel Packages</span>
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                    <i data-lucide="package" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="font-heading text-3xl font-bold text-dark mb-1">{{ $stats['total_packages'] }}</div>
            <p class="text-xs text-dark/50 font-medium">Active promotional offers</p>
        </div>

        <!-- Stat Card 4: System Users -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-extrabold uppercase tracking-wider text-dark">System Users</span>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
            </div>
            <div class="font-heading text-3xl font-bold text-dark mb-1">{{ $stats['total_users'] }}</div>
            <p class="text-xs text-dark/50 font-medium">Registered accounts</p>
        </div>
    </div>

    <!-- Recent Bookings Table Section -->
    <div class="bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-heading text-lg font-bold text-dark">Recent Customer Bookings</h3>
            </div>
            <a href="{{ route('admin.bookings.index') }}" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                <span>View All Bookings</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-2">Reference</th>
                        <th class="pb-3 px-2">Customer</th>
                        <th class="pb-3 px-2">Package</th>
                        <th class="pb-3 px-2">Travel Date</th>
                        <th class="pb-3 px-2">Status</th>
                        <th class="pb-3 px-2 text-right">Receipt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($recentBookings as $b)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3 px-2 font-mono font-bold text-primary">
                                {{ $b->booking_reference }}
                            </td>
                            <td class="py-3 px-2 font-bold text-dark">
                                <div>{{ $b->customer_name }}</div>
                                <div class="text-[10px] text-dark/50 font-normal">{{ $b->customer_phone }}</div>
                            </td>
                            <td class="py-3 px-2">
                                <div class="font-bold text-dark max-w-xs truncate">{{ $b->travelPackage->title ?? 'Package' }}</div>
                                <div class="text-[10px] text-dark/50 font-normal">{{ $b->number_of_passengers }} Pax • {{ $b->total_amount }}</div>
                            </td>
                            <td class="py-3 px-2 text-dark/60 font-medium whitespace-nowrap">
                                {{ $b->travel_date ? $b->travel_date->format('M j, Y') : 'N/A' }}
                            </td>
                            <td class="py-3 px-2">
                                @if($b->status === 'confirmed')
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase">Confirmed</span>
                                @elseif($b->status === 'cancelled')
                                    <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold text-[10px] uppercase">Cancelled</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px] uppercase">Pending</span>
                                @endif
                            </td>
                            <td class="py-3 px-2 text-right">
                                <a href="{{ route('bookings.confirmation', $b->booking_reference) }}" target="_blank" class="p-1.5 text-primary hover:bg-primary/5 rounded-lg inline-block">
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-dark/40 font-medium">
                                No package bookings recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Inquiries & Quick Management Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Recent Inquiries (2 Columns) -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-heading text-lg font-bold text-dark">Recent Customer Inquiries</h3>
                </div>
                <a href="{{ route('admin.inquiries.index') }}" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                    <span>View All</span>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                            <th class="pb-3 px-2">Customer</th>
                            <th class="pb-3 px-2">Service / Request</th>
                            <th class="pb-3 px-2">Contact</th>
                            <th class="pb-3 px-2">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($recentInquiries as $inquiry)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-3 px-2 font-bold text-dark">
                                    {{ $inquiry->name }}
                                </td>
                                <td class="py-3 px-2">
                                    <span class="px-2.5 py-1 rounded-full bg-primary/10 text-primary font-semibold text-[11px]">
                                        {{ $inquiry->service ?? 'General Inquiry' }}
                                    </span>
                                </td>
                                <td class="py-3 px-2 text-dark/70">
                                    <div>{{ $inquiry->email }}</div>
                                    <div class="text-[10px] text-dark/40">{{ $inquiry->phone }}</div>
                                </td>
                                <td class="py-3 px-2 text-dark/50 whitespace-nowrap">
                                    {{ $inquiry->created_at ? $inquiry->created_at->diffForHumans() : 'Recently' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-dark/40 font-medium">
                                    No customer inquiries recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Shortcuts Sidebar (1 Column) -->
        <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
                <h3 class="font-heading text-lg font-bold text-dark">Quick Management</h3>

                <div class="space-y-2.5 pt-2">
                    <a href="{{ route('admin.bookings.index') }}" class="flex items-center justify-between p-3.5 rounded-2xl bg-gray-50 hover:bg-accent/10 border border-gray-100 hover:border-accent/30 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-accent text-dark flex items-center justify-center font-bold">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                            </div>
                            <span class="text-xs font-bold text-dark group-hover:text-primary">All Bookings</span>
                        </div>
                        <i data-lucide="arrow-right" class="w-4 h-4 text-dark/30 group-hover:text-primary group-hover:translate-x-1 transition-transform"></i>
                    </a>

                    <a href="{{ route('admin.packages.create') }}" class="flex items-center justify-between p-3.5 rounded-2xl bg-gray-50 hover:bg-primary/5 border border-gray-100 hover:border-primary/20 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-primary text-white flex items-center justify-center font-bold">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                            </div>
                            <span class="text-xs font-bold text-dark group-hover:text-primary">Add Travel Package</span>
                        </div>
                        <i data-lucide="arrow-right" class="w-4 h-4 text-dark/30 group-hover:text-primary group-hover:translate-x-1 transition-transform"></i>
                    </a>

                    <a href="{{ route('admin.inquiries.index') }}" class="flex items-center justify-between p-3.5 rounded-2xl bg-gray-50 hover:bg-emerald-50 border border-gray-100 hover:border-emerald-200 transition-all group">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold">
                                <i data-lucide="inbox" class="w-4 h-4"></i>
                            </div>
                            <span class="text-xs font-bold text-dark group-hover:text-emerald-700">Manage Inquiries</span>
                        </div>
                        <i data-lucide="arrow-right" class="w-4 h-4 text-dark/30 group-hover:text-emerald-600 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
