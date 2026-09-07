@extends('layouts.ticketing')

@section('title', 'Ticket Details - ' . $ticket->booking_reference)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
        <a href="{{ route('ticketing.tickets.index') }}" class="inline-flex items-center gap-1.5 text-xs font-heading font-bold text-dark/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Ticket Directory</span>
        </a>

        <div class="flex items-center gap-2">
            @if($ticket->bookingAgreement)
                <a href="{{ route('ticketing.agreements.show', $ticket->bookingAgreement) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white font-heading font-bold text-xs transition-colors shadow-sm hover:bg-navy">
                    <i data-lucide="file-check-2" class="w-4 h-4 text-accent"></i>
                    <span>View Booking Agreement</span>
                </a>
            @else
                <a href="{{ route('ticketing.agreements.create', $ticket) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white font-heading font-bold text-xs transition-colors shadow-sm hover:bg-navy">
                    <i data-lucide="file-signature" class="w-4 h-4 text-accent"></i>
                    <span>Generate Booking Agreement</span>
                </a>
            @endif

            <button type="button" onclick="window.print()" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Ticket Voucher</span>
            </button>
            <a href="{{ route('ticketing.tickets.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent text-dark font-heading font-extrabold text-xs uppercase tracking-wider hover:bg-accent-dark transition-all shadow-md shadow-accent/20">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>New Booking</span>
            </a>
        </div>
    </div>

    <!-- Ticket Voucher Sheet (Print Friendly) -->
    <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-sm space-y-8 print:p-0 print:border-none print:shadow-none">
        
        <!-- Voucher Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 border-b border-gray-100 pb-6">
            <div class="flex items-center gap-4">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA" class="h-10 sm:h-12 w-auto object-contain">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-primary block">Amega Travel &amp; Tours</span>
                    <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight">Official Ticket Booking Confirmation</h1>
                </div>
            </div>

            <div class="sm:text-right space-y-1">
                <div class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Ticket Reference</div>
                <div class="font-mono text-lg sm:text-xl font-black text-primary">{{ $ticket->booking_reference }}</div>
                <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                    Status: {{ ucfirst($ticket->status) }}
                </div>
            </div>
        </div>

        <!-- Route & Schedule Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-5 rounded-2xl bg-gray-50 border border-gray-200">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Travel Type</span>
                <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                    <i data-lucide="{{ $ticket->travel_type === 'international' ? 'globe' : 'palmtree' }}" class="w-3.5 h-3.5 text-primary"></i>
                    {{ ucfirst($ticket->travel_type) }} Tour
                </span>
                @if($ticket->travel_class)
                    <span class="text-[10px] font-bold text-primary block mt-0.5 uppercase">{{ str_replace('_', ' ', $ticket->travel_class) }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Trip Route</span>
                <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                    {{ $ticket->origin }} &rarr; {{ $ticket->destination }}
                </span>
                @if($ticket->arrival_airport)
                    <span class="text-[10px] text-dark/50 block">Airport: {{ $ticket->arrival_airport }}</span>
                @endif
                @if($ticket->preferred_airline)
                    <span class="text-[10px] text-dark/50 block">Airline: {{ $ticket->preferred_airline }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Schedule</span>
                <span class="text-xs font-bold text-dark block mt-0.5">
                    Depart: {{ $ticket->departure_date->format('M d, Y') }}
                    @if($ticket->return_date)
                        <br>Return: {{ $ticket->return_date->format('M d, Y') }}
                    @endif
                </span>
                @if($ticket->preferred_flight_time && $ticket->preferred_flight_time !== 'anytime')
                    <span class="text-[10px] text-dark/50 capitalize block">Time: {{ $ticket->preferred_flight_time }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Passengers Count</span>
                <span class="text-xs font-bold text-dark block mt-0.5">
                    {{ $ticket->total_passengers }} Pax ({{ $ticket->adults_count }} Adt, {{ $ticket->children_count }} Chd, {{ $ticket->infants_count }} Inf)
                </span>
                <span class="text-[10px] text-dark/50 block mt-0.5 uppercase font-bold">{{ str_replace('_', ' ', $ticket->trip_type) }}</span>
            </div>
        </div>

        <!-- Package Details if selected -->
        @if($ticket->package_name)
            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center">
                        <i data-lucide="package" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Tour Package</span>
                        <span class="text-xs font-bold text-amber-950">{{ $ticket->package_name }}</span>
                    </div>
                </div>
                @if($ticket->travelPackage)
                    <span class="text-xs font-bold text-primary">{{ $ticket->travelPackage->price }}</span>
                @endif
            </div>
        @endif

        <!-- Phase 2 International Services & Emergency Contact Banner -->
        @if($ticket->travel_type === 'international' || $ticket->has_insurance || !empty($ticket->selected_services))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-5 rounded-2xl bg-blue-50/40 border border-blue-100 text-xs">
                <!-- Insurance & Add-on Services -->
                <div class="space-y-2">
                    <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Insurance &amp; Concierge Services</span>
                    @if($ticket->has_insurance)
                        <div class="flex items-center gap-1.5 text-emerald-800 font-bold">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                            <span>Travel Insurance: <span class="capitalize">{{ $ticket->insurance_plan ?? 'Standard' }}</span> Plan Active</span>
                        </div>
                    @endif
                    @if(!empty($ticket->selected_services))
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @foreach($ticket->selected_services as $srv)
                                <span class="px-2 py-0.5 rounded-lg bg-white border border-blue-200 text-dark/80 text-[10px] font-bold">
                                    ✓ {{ ucwords(str_replace('_', ' ', $srv)) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Emergency Contact -->
                @if($ticket->emergency_contact_name)
                    <div class="space-y-1">
                        <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Emergency Contact Person</span>
                        <div class="font-bold text-dark">{{ $ticket->emergency_contact_name }} ({{ $ticket->emergency_contact_relationship }})</div>
                        <div class="text-dark/60 font-semibold">{{ $ticket->emergency_contact_phone }} {{ $ticket->emergency_contact_email ? '• ' . $ticket->emergency_contact_email : '' }}</div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Passenger Manifest & Documents -->
        <div class="space-y-4">
            <h2 class="text-base font-heading font-bold text-dark flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5 text-primary"></i>
                <span>Passenger Manifest &amp; Document Credentials</span>
            </h2>

            <div class="space-y-4">
                @foreach($ticket->passengers as $p)
                    <div class="p-5 rounded-2xl border border-gray-200 bg-white space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">
                                    {{ $p->passenger_number }}
                                </span>
                                <span class="font-heading font-bold text-sm text-dark">{{ $p->full_name }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase {{ $p->nationality_type === 'filipino' ? 'bg-primary/10 text-primary' : 'bg-accent/20 text-accent-dark' }}">
                                    {{ $p->nationality_type === 'filipino' ? '🇵🇭 Filipino' : '🌐 Foreign National' }}
                                </span>
                                @if($p->gender)
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase bg-gray-100 text-dark/70 capitalize">{{ $p->gender }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-dark/60 font-medium">
                                Passenger Type: <strong class="capitalize text-dark">{{ $p->passenger_type }}</strong>
                                @if($p->travel_tax_included)
                                    • <span class="text-primary font-bold">Travel Tax Included</span>
                                @endif
                                @if($p->visa_status)
                                    • <span class="text-blue-700 font-bold uppercase text-[10px]">{{ str_replace('_', ' ', $p->visa_status) }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            @if($p->date_of_birth)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Date of Birth</span>
                                    <span class="font-semibold text-dark">{{ $p->date_of_birth->format('M d, Y') }}</span>
                                </div>
                            @endif
                            @if($p->passport_number)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Passport Number</span>
                                    <span class="font-mono font-bold text-dark">{{ $p->passport_number }}</span>
                                </div>
                            @endif
                            @if($p->passport_expiry_date)
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Passport Expiry</span>
                                    <span class="font-semibold text-dark">{{ $p->passport_expiry_date->format('M d, Y') }}</span>
                                </div>
                            @endif
                            @if($p->visa_type && $p->visa_type !== 'none')
                                <div>
                                    <span class="text-[10px] text-dark/40 font-bold uppercase block">Visa Type</span>
                                    <span class="font-semibold text-dark">{{ ucfirst(str_replace('_', ' ', $p->visa_type)) }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Attached Uploaded Documents -->
                        <div class="pt-2">
                            <span class="text-[11px] font-bold text-dark/60 uppercase tracking-wider block mb-2">Attached Documents ({{ $p->documents->count() }})</span>
                            @if($p->documents->count() > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($p->documents as $doc)
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 text-xs font-semibold">
                                            <i data-lucide="file-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                            <span class="text-dark">{{ $doc->formatted_type }}:</span>
                                            <span class="text-dark/60 truncate max-w-[150px]">{{ $doc->original_name }}</span>
                                            <a href="{{ route('ticketing.documents.download', $doc) }}" 
                                               class="text-primary hover:underline font-bold text-[11px] ml-1 flex items-center gap-0.5 print:hidden">
                                                <i data-lucide="download" class="w-3 h-3"></i> Download
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-dark/40 italic">No document scans uploaded for this passenger.</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pricing & Quotation Breakdown if total amount set -->
        @if($ticket->total_amount > 0 || $ticket->estimated_fare > 0)
            <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Fare &amp; Quotation Assessment</span>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
                    <div>
                        <span class="text-dark/50 block text-[10px]">Estimated Base Fare</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->estimated_fare, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Taxes &amp; Surcharges</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->taxes_amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Visa Assistance</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->visa_assistance_fee, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Insurance Fee</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->insurance_fee, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Other Charges</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->other_charges, 2) }}</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-gray-200 flex items-center justify-between">
                    <span class="font-bold text-dark text-xs uppercase">Grand Total Quotation:</span>
                    <span class="font-mono font-black text-primary text-base">₱{{ number_format($ticket->total_amount, 2) }}</span>
                </div>
            </div>
        @endif

        <!-- Special Requests -->
        @if($ticket->special_requests || !empty($ticket->special_requests_list))
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-2 text-xs">
                <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Special Requests &amp; Seating Preferences</span>
                @if(!empty($ticket->special_requests_list))
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($ticket->special_requests_list as $req)
                            <span class="px-2 py-0.5 rounded-lg bg-white border text-dark font-bold text-[10px]">
                                • {{ ucwords(str_replace('_', ' ', $req)) }}
                            </span>
                        @endforeach
                    </div>
                @endif
                @if($ticket->special_requests)
                    <p class="text-dark/80 whitespace-pre-line mt-1">{{ $ticket->special_requests }}</p>
                @endif
            </div>
        @endif

        <!-- Contact and Officer Footer -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-gray-100 text-xs">
            <div class="space-y-1">
                <span class="font-bold text-dark/50 uppercase tracking-wider text-[10px]">Primary Booker / Contact</span>
                <div class="font-bold text-dark">{{ $ticket->contact_name }}</div>
                <div class="text-dark/60">{{ $ticket->contact_email }} • {{ $ticket->contact_phone }}</div>
            </div>
            <div class="sm:text-right space-y-1">
                <span class="font-bold text-dark/50 uppercase tracking-wider text-[10px]">Issuing Officer</span>
                <div class="font-bold text-dark">{{ $ticket->createdBy?->name ?? 'AMEGA Staff' }}</div>
                <div class="text-dark/40">Issued on {{ $ticket->created_at->format('M d, Y h:i A') }}</div>
            </div>
        </div>

    </div>

</div>
@endsection
