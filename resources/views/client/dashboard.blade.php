@extends('layouts.app')

@section('title', 'My Account & Bookings - Amega Travel and Tours Services')

@section('content')
<!-- Hero Client Header -->
<section class="relative pt-32 pb-16 bg-navy text-white overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/30 via-navy to-navy"></div>
    <div class="section-dots opacity-10"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                @if($user->profile_photo_url)
                    <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full object-cover border-2 border-white/20 shadow-lg shrink-0">
                @else
                    <div class="w-16 h-16 rounded-full bg-accent text-dark font-heading font-extrabold text-2xl flex items-center justify-center shadow-lg border-2 border-white/20 shrink-0">
                        {{ substr($user->full_name, 0, 1) }}
                    </div>
                @endif
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-accent font-bold text-xs uppercase tracking-wider mb-1 border border-white/20">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        AMEGA Client Portal • {{ $user->account_category ?? 'Individual' }} Category
                    </span>
                    <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">Welcome back, {{ $user->full_name }}!</h1>
                    <p class="text-xs text-white/60 mt-0.5">{{ $user->email }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('client.profile') }}" class="px-5 py-3 bg-accent text-dark font-heading font-extrabold text-xs rounded-full hover:bg-accent-dark transition-all duration-300 shadow-lg flex items-center gap-2">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                    <span>View Profile</span>
                </a>
                <a href="#inquire-section" class="px-5 py-3 bg-white/10 text-white font-heading font-extrabold text-xs rounded-full hover:bg-white/20 transition-all border border-white/20 flex items-center gap-2">
                    <i data-lucide="message-square" class="w-4 h-4 text-accent"></i>
                    <span>Inquire Now</span>
                </a>
                <a href="{{ route('packages.index') }}" class="px-5 py-3 bg-white/10 text-white font-heading font-extrabold text-xs rounded-full hover:bg-white/20 transition-all border border-white/20 flex items-center gap-2">
                    <i data-lucide="compass" class="w-4 h-4"></i>
                    <span>Explore Packages</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-5 py-3 bg-rose-500/20 text-rose-200 font-heading font-extrabold text-xs rounded-full hover:bg-rose-500/30 transition-all border border-rose-400/30 flex items-center gap-2">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Client Portal Body Canvas -->
<section class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="space-y-8">
                
                <!-- Quick Summary Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-dark/40 block">Total Bookings</span>
                            <div class="font-heading text-3xl font-bold text-dark mt-1">{{ $bookings->count() }}</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
                            <i data-lucide="calendar" class="w-6 h-6"></i>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-dark/40 block">Active / Confirmed</span>
                            <div class="font-heading text-3xl font-bold text-emerald-600 mt-1">
                                {{ $bookings->whereIn('status', ['pending', 'confirmed'])->count() }}
                            </div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i data-lucide="clock" class="w-6 h-6"></i>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-dark/40 block">Completed Tours</span>
                            <div class="font-heading text-3xl font-bold text-indigo-600 mt-1">
                                {{ $bookings->where('status', 'completed')->count() }}
                            </div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <i data-lucide="check-check" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>

                <!-- My Bookings Table -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                        <div>
                            <h2 class="font-heading text-xl font-bold text-dark">My Package Reservations</h2>
                            <p class="text-xs text-dark/50">Track reservation status, payment details, and travel dates</p>
                        </div>
                        <a href="{{ route('packages.index') }}" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                            <span>Book New Tour</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-gray-100 text-dark/40 font-bold uppercase tracking-wider">
                                    <th class="pb-3 px-3">Reference</th>
                                    <th class="pb-3 px-3">Package</th>
                                    <th class="pb-3 px-3">Travel Date</th>
                                    <th class="pb-3 px-3">Status</th>
                                    <th class="pb-3 px-3 text-right">Voucher</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse ($bookings as $b)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-4 px-3 font-mono font-bold text-primary">
                                            {{ $b->booking_reference }}
                                        </td>
                                        <td class="py-4 px-3">
                                            <div class="font-bold text-dark max-w-xs truncate">{{ $b->travelPackage->title ?? 'Travel Package' }}</div>
                                            <div class="text-[11px] text-dark/50">{{ $b->number_of_passengers }} Passenger(s) • {{ $b->total_amount }}</div>
                                        </td>
                                        <td class="py-4 px-3 text-dark/70 font-medium whitespace-nowrap">
                                            {{ $b->travel_date ? $b->travel_date->format('M j, Y') : 'N/A' }}
                                        </td>
                                        <td class="py-4 px-3">
                                            @if($b->status === 'confirmed')
                                                <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold text-[10px] uppercase">Confirmed</span>
                                            @elseif($b->status === 'cancelled')
                                                <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold text-[10px] uppercase">Cancelled</span>
                                            @else
                                                <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px] uppercase">Pending</span>
                                            @endif
                                        </td>
                                        <td class="py-4 px-3 text-right">
                                            <a href="{{ route('bookings.confirmation', $b->booking_reference) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors">
                                                <span>View Receipt</span>
                                                <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-12 text-center text-dark/40 font-medium">
                                            You have no active tour bookings yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Inquiry Form Card -->
                <div id="inquire-section" class="bg-gradient-to-br from-navy to-primary rounded-3xl p-6 sm:p-8 text-white shadow-xl space-y-5">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-accent">Direct Contact</span>
                        <h3 class="font-heading text-xl font-bold text-white mt-0.5">Inquire Now</h3>
                        <p class="text-xs text-white/70 mt-1">Send a message to our travel agents for custom tours, visa assistance, or flight inquiries.</p>
                    </div>

                    <form method="POST" action="{{ route('contact.submit') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="name" value="{{ $user->full_name }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">
                        <input type="hidden" name="phone" value="{{ $user->phone ?? 'N/A' }}">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-white/80 mb-1">Service Type</label>
                                <select name="service" required class="w-full px-3.5 py-2.5 rounded-xl bg-white/10 border border-white/20 text-white text-xs focus:outline-none focus:ring-2 focus:ring-accent">
                                    <option value="General Travel Inquiry" class="text-dark">General Travel Inquiry</option>
                                    <option value="Visa Processing Assistance" class="text-dark">Visa Processing Assistance</option>
                                    <option value="Custom Tour Package Request" class="text-dark">Custom Tour Package Request</option>
                                    <option value="Flight & Hotel Booking" class="text-dark">Flight & Hotel Booking</option>
                                    <option value="Philippine Retirement Visa (SRRV)" class="text-dark">Philippine Retirement Visa (SRRV)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-white/80 mb-1">Target Destination (Optional)</label>
                                <input type="text" name="destination" placeholder="e.g. Japan, Batanes, Boracay" class="w-full px-3.5 py-2.5 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/40 text-xs focus:outline-none focus:ring-2 focus:ring-accent">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-white/80 mb-1">Your Message / Request</label>
                            <textarea name="message" rows="3" required
                                      class="w-full px-3.5 py-2.5 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/40 text-xs focus:outline-none focus:ring-2 focus:ring-accent"
                                      placeholder="Describe your travel dates, preferred destinations, or requirements..."></textarea>
                        </div>

                        <button type="submit" class="w-full py-3.5 bg-accent text-dark font-heading font-extrabold text-xs rounded-xl hover:bg-accent-dark transition-all shadow-lg flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            <span>Submit Inquiry Now</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
</section>
@endsection
