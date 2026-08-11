@extends('layouts.app')

@section('title', 'Booking Confirmation ' . $booking->booking_reference . ' - AMEGA Travel')

@section('content')
<section class="py-28 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Banner -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4 shadow-sm">
                <i data-lucide="check-circle" class="w-10 h-10"></i>
            </div>
            <span class="px-3.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-extrabold text-xs uppercase tracking-widest">
                Booking Request Received
            </span>
            <h1 class="font-heading text-3xl sm:text-4xl font-bold text-dark mt-3">Thank You for Booking with AMEGA!</h1>
            <p class="text-dark/60 text-sm mt-2">
                Your reservation request has been registered under reference code <strong class="text-primary font-bold">{{ $booking->booking_reference }}</strong>.
            </p>
        </div>

        <!-- Printable Voucher / Receipt Card -->
        <div id="booking-receipt" class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-xl space-y-8 relative overflow-hidden">
            
            <!-- Watermark / Brand Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-gray-100 pb-6 gap-4">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA Travel & Tours" class="h-12 w-auto object-contain">
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-[10px] text-dark/40 font-bold uppercase tracking-widest block">Reference Code</span>
                    <span class="font-mono font-extrabold text-lg text-primary">{{ $booking->booking_reference }}</span>
                    <span class="text-[10px] text-dark/40 block mt-0.5">{{ $booking->created_at->format('M j, Y g:i A') }}</span>
                </div>
            </div>

            <!-- Package Info Box -->
            <div class="flex flex-col sm:flex-row items-center gap-6 p-4 rounded-2xl bg-gray-50 border border-gray-100">
                @if($booking->travelPackage)
                    <img src="{{ asset($booking->travelPackage->image) }}" alt="{{ $booking->travelPackage->title }}" class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl object-cover shrink-0 border border-gray-200">
                    <div class="space-y-1 text-center sm:text-left flex-1">
                        <span class="px-2.5 py-0.5 rounded-full bg-accent/20 text-dark font-bold text-[10px] uppercase">
                            {{ str_replace('_', ' ', $booking->travelPackage->category) }}
                        </span>
                        <h3 class="font-heading font-bold text-lg text-dark">{{ $booking->travelPackage->title }}</h3>
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 text-xs text-dark/60">
                            <span>⏱️ {{ $booking->travelPackage->duration }}</span>
                            <span>💵 {{ $booking->travelPackage->price }} per person</span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Booking Details Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs border-b border-gray-100 pb-6">
                <div>
                    <span class="text-dark/40 font-bold uppercase tracking-wider block mb-1">Lead Passenger</span>
                    <span class="font-bold text-dark text-sm block">{{ $booking->customer_name }}</span>
                    <span class="text-dark/60 block">{{ $booking->customer_email }}</span>
                    <span class="text-dark/60 block">{{ $booking->customer_phone }}</span>
                </div>

                <div>
                    <span class="text-dark/40 font-bold uppercase tracking-wider block mb-1">Reservation Details</span>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span class="text-dark/60">Travel Date:</span>
                            <span class="font-bold text-dark">{{ $booking->travel_date->format('F j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-dark/60">Passengers:</span>
                            <span class="font-bold text-dark">{{ $booking->number_of_passengers }} Passenger(s)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-dark/60">Booking Status:</span>
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px] uppercase">
                                {{ strtoupper($booking->status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            @if($booking->special_requests)
                <div class="text-xs space-y-1 bg-amber-50/50 p-4 rounded-xl border border-amber-100">
                    <span class="font-bold text-amber-900 block uppercase tracking-wider text-[10px]">Special Instructions / Notes</span>
                    <p class="text-amber-900/80 leading-relaxed">{{ $booking->special_requests }}</p>
                </div>
            @endif

            <!-- Next Steps & Assistance -->
            <div class="bg-navy/5 p-5 rounded-2xl border border-navy/10 space-y-2 text-xs text-dark/70">
                <span class="font-bold text-navy text-sm block">What Happens Next?</span>
                <p>1. Our travel specialists will review your reservation dates and confirm availability.</p>
                <p>2. We will contact you via phone (<strong class="text-dark">{{ $booking->customer_phone }}</strong>) or email with your official payment voucher and flight schedule.</p>
            </div>

            <!-- Receipt Action Buttons -->
            <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-gray-100">
                <button onclick="window.print();" class="w-full sm:w-auto px-6 py-3 bg-gray-100 hover:bg-gray-200 text-dark font-bold text-xs rounded-full transition-all flex items-center justify-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Print Receipt / Save PDF</span>
                </button>

                @auth
                    <a href="{{ route('client.bookings') }}" class="w-full sm:w-auto px-6 py-3 bg-navy text-white font-bold text-xs rounded-full hover:bg-primary transition-all text-center">
                        View My Bookings
                    </a>
                @else
                    <a href="{{ route('packages.index') }}" class="w-full sm:w-auto px-6 py-3 bg-primary text-white font-bold text-xs rounded-full hover:bg-primary-dark transition-all text-center">
                        Browse More Packages
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>
@endsection
