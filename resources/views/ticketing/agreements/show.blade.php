@extends('layouts.ticketing')

@section('title', 'Official Booking Agreement - ' . $agreement->agreement_number)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    
    <!-- Top Action Toolbar (Hidden during Print) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
        <a href="{{ route('ticketing.tickets.show', $agreement->ticketBooking) }}" class="inline-flex items-center gap-1.5 text-xs font-heading font-bold text-dark/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Ticket #{{ $agreement->ticketBooking->booking_reference }}</span>
        </a>

        <div class="flex items-center gap-2">
            <a href="{{ route('ticketing.agreements.edit', $agreement) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark text-xs font-bold transition-colors">
                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                <span>Edit Details / Pricing</span>
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md shadow-primary/20">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Official Agreement</span>
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- OFFICIAL BOOKING AGREEMENT PAPER (Pixel-perfect replica of physical form) -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-3xl p-8 sm:p-12 border border-gray-200 shadow-sm print:p-0 print:border-0 print:shadow-none font-sans text-dark space-y-6">
        
        <!-- Header: Logo & Tagline -->
        <div class="flex items-center justify-between border-b-2 border-primary/20 pb-4">
            <div class="flex items-center gap-4">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA" class="h-12 sm:h-14 w-auto object-contain">            </div>

            <div class="text-right">
                <span class="text-[9px] font-bold uppercase tracking-widest text-dark/40 block">Agreement Code</span>
                <span class="font-mono text-sm sm:text-base font-bold text-primary">{{ $agreement->agreement_number }}</span>
                <span class="text-[10px] text-dark/50 block">Ref: {{ $agreement->ticketBooking->booking_reference }}</span>
            </div>
        </div>

        <!-- Document Title -->
        <div class="text-center pt-2">
            <h1 class="text-2xl sm:text-3xl font-heading font-black tracking-wider text-dark uppercase">
                BOOKING AGREEMENT
            </h1>
        </div>

        <!-- Client & Contact Information Block -->
        <div class="space-y-3 text-xs sm:text-sm">
            <div class="flex flex-col sm:flex-row sm:items-baseline justify-between gap-2">
                <div class="flex items-baseline gap-2 flex-1">
                    <span class="font-bold text-dark whitespace-nowrap">Name(s):</span>
                    <div class="border-b border-dark/60 flex-1 font-semibold text-dark pb-0.5 min-h-[22px]">
                        {{ $agreement->client_names }}
                    </div>
                </div>
                <div class="flex items-baseline gap-2 sm:w-64">
                    <span class="font-bold text-dark whitespace-nowrap">Date:</span>
                    <div class="border-b border-dark/60 flex-1 font-semibold text-dark pb-0.5 text-center min-h-[22px]">
                        {{ $agreement->agreement_date->format('F d, Y') }}
                    </div>
                </div>
            </div>

            <!-- Contact Information Row -->
            <div class="space-y-2 pt-1">
                <div class="font-bold text-dark">Contact Information:</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pl-4">
                    <div class="flex items-baseline gap-2">
                        <span class="text-xs font-semibold text-dark/80 whitespace-nowrap">Phone/Cell No.:</span>
                        <div class="border-b border-dark/60 flex-1 font-semibold text-dark pb-0.5 min-h-[20px]">
                            {{ $agreement->contact_phone ?? 'N/A' }}
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-xs font-semibold text-dark/80 whitespace-nowrap">Email Address:</span>
                        <div class="border-b border-dark/60 flex-1 font-semibold text-dark pb-0.5 min-h-[20px]">
                            {{ $agreement->contact_email ?? 'N/A' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Row -->
            <div class="space-y-1 pt-1">
                <div class="font-bold text-dark">Address:</div>
                <div class="flex items-baseline gap-2 pl-4">
                    <span class="text-xs font-semibold text-dark/80 whitespace-nowrap">Home/Hotel:</span>
                    <div class="border-b border-dark/60 flex-1 font-semibold text-dark pb-0.5 min-h-[20px]">
                        {{ $agreement->home_hotel_address ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- 10-Column Flight / Schedule Matrix Table -->
        <div class="pt-2">
            <table class="w-full text-[11px] border-collapse border border-dark text-center">
                <thead>
                    <tr class="bg-gray-100 font-bold uppercase text-[10px]">
                        <th class="border border-dark py-1 px-1.5">Carrier</th>
                        <th class="border border-dark py-1 px-1.5">Flt.#</th>
                        <th class="border border-dark py-1 px-1.5">Class</th>
                        <th class="border border-dark py-1 px-1.5">Day</th>
                        <th class="border border-dark py-1 px-1.5">Month</th>
                        <th class="border border-dark py-1 px-1.5">From</th>
                        <th class="border border-dark py-1 px-1.5">To</th>
                        <th class="border border-dark py-1 px-1.5">Dep.</th>
                        <th class="border border-dark py-1 px-1.5">Arr.</th>
                        <th class="border border-dark py-1 px-1.5">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $segments = $agreement->flight_segments ?? [];
                        // Ensure at least 4 rows are drawn for clean look
                        while(count($segments) < 4) {
                            $segments[] = ['carrier'=>'','flight_number'=>'','flight_class'=>'','day'=>'','month'=>'','from_location'=>'','to_location'=>'','departure_time'=>'','arrival_time'=>'','flight_status'=>''];
                        }
                    @endphp
                    @foreach($segments as $s)
                        <tr class="min-h-[26px]">
                            <td class="border border-dark py-1.5 px-1 font-semibold">{{ $s['carrier'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1 font-mono font-semibold">{{ $s['flight_number'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1">{{ $s['flight_class'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1">{{ $s['day'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1 font-semibold uppercase">{{ $s['month'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1 font-bold">{{ $s['from_location'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1 font-bold">{{ $s['to_location'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1">{{ $s['departure_time'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1">{{ $s['arrival_time'] ?? '' }}</td>
                            <td class="border border-dark py-1.5 px-1 font-bold text-primary">{{ $s['flight_status'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Inclusions & Conditions Radios / Bullets -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-y-3 gap-x-4 text-[11px] font-semibold py-2.5 px-1">
            
            <!-- 1. With Baggage -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ $agreement->has_baggage ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if($agreement->has_baggage)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ $agreement->has_baggage ? 'font-bold text-dark' : 'text-dark/70' }}">With Baggage</span>
            </div>

            <!-- 2. Non Refundable -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ $agreement->is_non_refundable ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if($agreement->is_non_refundable)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ $agreement->is_non_refundable ? 'font-bold text-dark' : 'text-dark/70' }}">Non Refundable</span>
            </div>

            <!-- 3. With Meals -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ $agreement->has_meals ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if($agreement->has_meals)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ $agreement->has_meals ? 'font-bold text-dark' : 'text-dark/70' }}">With Meals</span>
            </div>

            <!-- 4. With Rebooking Charge -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ $agreement->with_rebooking_charge ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if($agreement->with_rebooking_charge)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ $agreement->with_rebooking_charge ? 'font-bold text-dark' : 'text-dark/70' }}">With Rebooking Charge</span>
            </div>

            <!-- 5. Without Baggage -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ !$agreement->has_baggage ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if(!$agreement->has_baggage)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ !$agreement->has_baggage ? 'font-bold text-dark' : 'text-dark/70' }}">Without Baggage</span>
            </div>

            <!-- 6. Non Rebookable -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ $agreement->is_non_rebookable ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if($agreement->is_non_rebookable)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ $agreement->is_non_rebookable ? 'font-bold text-dark' : 'text-dark/70' }}">Non Rebookable</span>
            </div>

            <!-- 7. Without Meals -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ !$agreement->has_meals ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if(!$agreement->has_meals)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ !$agreement->has_meals ? 'font-bold text-dark' : 'text-dark/70' }}">Without Meals</span>
            </div>

            <!-- 8. With Airport Transfer -->
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-dark flex items-center justify-center text-xs font-black shrink-0 {{ $agreement->with_airport_transfer ? 'bg-dark text-white print:bg-black print:text-white' : 'text-transparent' }}">
                    @if($agreement->with_airport_transfer)
                        <svg class="w-3 h-3 stroke-[3] text-white print:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                </span>
                <span class="{{ $agreement->with_airport_transfer ? 'font-bold text-dark' : 'text-dark/70' }}">With Airport Transfer</span>
            </div>
        </div>

        <!-- Custom Package: Hotel Policies & Transfer -->
        @php $packageSpecs = $agreement->ticketBooking->custom_package_specs ?? []; @endphp
        @if($agreement->ticketBooking->isCustomPackage() || !empty($packageSpecs))
            <div class="border border-dark text-[11px]">
                <div class="bg-gray-100 border-b border-dark px-3 py-1 font-bold uppercase text-[10px]">Hotel Policies &amp; Transfer</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-dark print:grid-cols-3 print:divide-y-0 print:divide-x">
                    <div class="px-3 py-1.5">
                        <span class="text-dark/60 block">Smoking</span>
                        <span class="font-bold text-dark">{{ ($packageSpecs['smoking_preference'] ?? '') === 'smoking' ? 'Smoking' : 'Non-Smoking' }}</span>
                    </div>
                    <div class="px-3 py-1.5">
                        <span class="text-dark/60 block">Pets</span>
                        <span class="font-bold text-dark">{{ !empty($packageSpecs['pet_friendly']) ? 'Pets Allowed' : 'No Pets' }}</span>
                    </div>
                    <div class="px-3 py-1.5">
                        <span class="text-dark/60 block">Transport</span>
                        <span class="font-bold text-dark">{{ !empty($packageSpecs['has_transportation']) ? ($packageSpecs['transportation_type'] ?? 'Arranged') : 'No Transport' }}</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- 3-Column Pricing & Quotation Matrix -->
        <div class="pt-1">
            <table class="w-full text-xs border-collapse border border-dark">
                <thead>
                    <tr class="bg-gray-100 font-bold uppercase text-[11px] text-center">
                        <th class="border border-dark py-2 px-3 w-5/12">AIRFARE</th>
                        <th class="border border-dark py-2 px-3 w-5/12">PRICE/DETAILS</th>
                        <th class="border border-dark py-2 px-3 w-2/12">NO OF PAX</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark">
                    @php $pItems = $agreement->pricing_items ?? []; @endphp
                    @if(count($pItems) > 0)
                        @foreach($pItems as $item)
                            <tr class="min-h-[45px]">
                                <td class="border border-dark p-3 font-semibold align-top">
                                    {{ $item['airfare_description'] ?? 'Airfare Rate' }}
                                </td>
                                <td class="border border-dark p-3 align-top">
                                    <div class="font-bold text-dark">{{ $item['price_details'] ?? '' }}</div>
                                    @if(!empty($item['amount']) && (float)$item['amount'] > 0)
                                        <div class="font-mono text-primary font-bold mt-1">₱{{ number_format((float)$item['amount'], 2) }}</div>
                                    @endif
                                </td>
                                <td class="border border-dark p-3 text-center font-bold text-sm align-top">
                                    {{ $item['pax_count'] ?? $agreement->ticketBooking->total_passengers }} PAX
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr class="h-20">
                            <td class="border border-dark p-3 font-semibold align-top">
                                {{ $agreement->ticketBooking->origin }} to {{ $agreement->ticketBooking->destination }}
                            </td>
                            <td class="border border-dark p-3 align-top font-bold text-primary font-mono">
                                ₱{{ number_format($agreement->total_amount, 2) }}
                            </td>
                            <td class="border border-dark p-3 text-center font-bold align-top">
                                {{ $agreement->ticketBooking->total_passengers }} PAX
                            </td>
                        </tr>
                    @endif

                    @if($agreement->total_amount > 0)
                        <tr class="bg-gray-50/80 font-bold">
                            <td colspan="2" class="border border-dark py-2 px-3 text-right uppercase tracking-wider text-[11px]">
                                Grand Total Airfare &amp; Package Quotation:
                            </td>
                            <td class="border border-dark py-2 px-3 text-center text-sm font-mono text-primary font-black">
                                ₱{{ number_format($agreement->total_amount, 2) }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Payment Terms / Remarks if present -->
        @if($agreement->payment_terms)
            <div class="text-[11px] p-3 rounded-xl bg-gray-50 border border-gray-200">
                <span class="font-bold text-dark block uppercase tracking-wider text-[9px]">Terms &amp; Remarks:</span>
                <p class="text-dark/80 mt-0.5 whitespace-pre-line">{{ $agreement->payment_terms }}</p>
            </div>
        @endif

        <!-- Signatures Area (Agent & Client) -->
        <div class="grid grid-cols-2 gap-12 pt-8 pb-4">
            <div class="space-y-1 text-center">
                <div class="border-b border-dark mx-auto w-full sm:w-4/5 pb-1 font-semibold text-xs text-dark">
                    {{ $agreement->agent_name }}
                </div>
                <div class="font-bold text-xs uppercase tracking-wider text-dark/70">Agent</div>
            </div>

            <div class="space-y-1 text-center">
                <div class="border-b border-dark mx-auto w-full sm:w-4/5 pb-1 font-semibold text-xs text-dark">
                    {{ $agreement->passenger_client_name }}
                </div>
                <div class="font-bold text-xs uppercase tracking-wider text-dark/70">Passenger/Client</div>
            </div>
        </div>

        <!-- Company Footer matching physical layout -->
        <div class="pt-6 border-t border-gray-200 text-center text-[10px] sm:text-[11px] text-dark/70 space-y-1 leading-relaxed">
            <div class="font-bold text-dark">
                Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines
            </div>
            <div class="font-semibold">
                +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703
            </div>
            <div>
                <a href="mailto:sales@amegatravelandtours.com" class="text-primary font-semibold hover:underline">sales@amegatravelandtours.com</a>
                &nbsp;|&nbsp;
                <a href="https://www.amegatravelandtours.com" target="_blank" class="text-primary font-semibold hover:underline">www.amegatravelandtours.com</a>
                &nbsp;|&nbsp;
                <span>Facebook: <strong>@AmegaTravel</strong></span>
            </div>
        </div>

    </div>

</div>
@endsection
