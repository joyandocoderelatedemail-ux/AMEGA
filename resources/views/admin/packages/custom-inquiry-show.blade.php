@extends('layouts.admin')

@section('title', 'Custom Package Quotation - ' . $inquiry->reference_number . ' - AMEGA Admin')
@section('page_title', 'Custom Package Quotation')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">

    <!-- Top Action & Navigation Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-dark/40 mb-1">
                <a href="{{ route('admin.packages.configurator', ['tab' => 'inquiries']) }}" class="hover:text-primary transition-colors flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>Back to Configurator</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="font-heading text-2xl font-bold text-dark">{{ $inquiry->reference_number }}</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border {{ $inquiry->status_badge_class }}">
                    {{ $inquiry->status }}
                </span>
            </div>
            <p class="text-xs text-dark/50 mt-1">Configured for <strong>{{ $inquiry->client_name }}</strong> on {{ $inquiry->created_at->format('M d, Y h:i A') }}</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button" onclick="window.print()" class="px-4 py-2.5 rounded-xl border border-gray-200 text-dark font-bold text-xs hover:bg-gray-50 transition-all flex items-center gap-1.5 shadow-sm cursor-pointer">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Quotation</span>
            </button>
            <a href="{{ route('ticketing.tickets.create', ['destination' => $inquiry->destination_name, 'travel_type' => $inquiry->travel_type, 'total_passengers' => $inquiry->number_of_pax, 'contact_name' => $inquiry->client_name, 'contact_email' => $inquiry->client_email, 'contact_phone' => $inquiry->client_phone]) }}"
               class="px-5 py-2.5 rounded-xl bg-primary text-white font-bold text-xs hover:bg-navy transition-all flex items-center gap-1.5 shadow-md">
                <i data-lucide="ticket" class="w-4 h-4"></i>
                <span>Book in Ticketing Wizard</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3 shadow-sm">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Printable Quotation Card -->
    <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm space-y-8 print:border-none print:shadow-none">
        
        <!-- Header Branding -->
        <div class="flex items-start justify-between border-b border-gray-100 pb-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours" class="h-10 w-auto object-contain">
                </div>
                <p class="text-xs text-dark/60 font-semibold">Amega Travel and Tours Services</p>
                <p class="text-[11px] text-dark/40">Customized Travel &amp; Accommodation Quotation</p>
            </div>

            <div class="text-right space-y-1">
                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-dark/40 block">Quotation Reference</span>
                <span class="font-mono text-base font-extrabold text-primary">{{ $inquiry->reference_number }}</span>
                <p class="text-[11px] text-dark/50">{{ now()->format('F d, Y') }}</p>
            </div>
        </div>

        <!-- Client & Travel Overview -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 p-5 rounded-2xl bg-gray-50 border border-gray-100">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block mb-1">Client Information</span>
                <h3 class="font-heading font-bold text-base text-dark">{{ $inquiry->client_name }}</h3>
                <p class="text-xs text-dark/70 mt-0.5">{{ $inquiry->client_email }}</p>
                @if($inquiry->client_phone)
                    <p class="text-xs text-dark/70">{{ $inquiry->client_phone }}</p>
                @endif
            </div>

            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block mb-1">Destination &amp; Schedule</span>
                <h3 class="font-heading font-bold text-base text-primary">{{ $inquiry->destination_name ?: 'Custom Tour' }}</h3>
                <p class="text-xs text-dark/70 mt-0.5 capitalize">Travel Type: <strong>{{ $inquiry->travel_type }}</strong></p>
                <p class="text-xs text-dark/70">
                    Dates: <strong>{{ $inquiry->check_in_date ? $inquiry->check_in_date->format('M d, Y') : 'TBD' }}</strong> &mdash; 
                    <strong>{{ $inquiry->check_out_date ? $inquiry->check_out_date->format('M d, Y') : 'TBD' }}</strong>
                    @if($inquiry->duration)
                        ({{ $inquiry->duration }})
                    @endif
                </p>
            </div>
        </div>

        <!-- Passenger Mix (# Pax) -->
        <div class="space-y-3">
            <h4 class="font-heading font-bold text-xs uppercase tracking-wider text-dark/50 flex items-center gap-1.5">
                <i data-lucide="users" class="w-4 h-4 text-primary"></i>
                <span>Party Size &amp; Passenger Count</span>
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="text-[11px] font-bold text-dark/50 uppercase block">Total # Pax</span>
                    <span class="text-lg font-heading font-black text-primary">{{ $inquiry->number_of_pax }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="text-[11px] font-bold text-dark/50 uppercase block">Adults (18+)</span>
                    <span class="text-lg font-heading font-black text-dark">{{ $inquiry->adults_count }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="text-[11px] font-bold text-dark/50 uppercase block">Children (2-17)</span>
                    <span class="text-lg font-heading font-black text-dark">{{ $inquiry->children_count }}</span>
                </div>
                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="text-[11px] font-bold text-dark/50 uppercase block">Infants (&lt;2)</span>
                    <span class="text-lg font-heading font-black text-dark">{{ $inquiry->infants_count }}</span>
                </div>
            </div>
        </div>

        <!-- Hotel, Room & Accommodation Configuration -->
        <div class="space-y-3">
            <h4 class="font-heading font-bold text-xs uppercase tracking-wider text-dark/50 flex items-center gap-1.5">
                <i data-lucide="hotel" class="w-4 h-4 text-primary"></i>
                <span>Hotel &amp; Room Specifications</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl border border-gray-200 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-dark/50">Hotel Category:</span>
                        <span class="font-bold text-dark">{{ $inquiry->hotel_name ?: 'Standard Resort / Hotel' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-dark/50">Preferred Property / Location:</span>
                        <span class="font-bold text-dark">{{ $inquiry->preferred_hotel ?: 'No Specific Brand' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-dark/50">Bed Configuration:</span>
                        <span class="font-bold text-dark">{{ $inquiry->bed_config ?: 'Standard Bedding' }}</span>
                    </div>
                </div>

                <div class="p-4 rounded-2xl border border-gray-200 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-dark/50">Daily Breakfast:</span>
                        <span class="font-bold {{ $inquiry->has_breakfast ? 'text-emerald-700' : 'text-dark/60' }}">
                            {{ $inquiry->has_breakfast ? 'Included ✓' : 'Not Included' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-dark/50">Smoking Policy:</span>
                        <span class="font-bold uppercase text-[11px] {{ $inquiry->smoking_preference === 'non_smoking' ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $inquiry->smoking_preference === 'non_smoking' ? 'Non-Smoking Room' : 'Smoking Room' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-dark/50">Pet Friendly Accommodation:</span>
                        <span class="font-bold {{ $inquiry->pet_friendly ? 'text-emerald-700' : 'text-dark/60' }}">
                            {{ $inquiry->pet_friendly ? 'Yes (Pet-Friendly Required)' : 'No' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transportation & Logistics -->
        <div class="space-y-3">
            <h4 class="font-heading font-bold text-xs uppercase tracking-wider text-dark/50 flex items-center gap-1.5">
                <i data-lucide="car" class="w-4 h-4 text-primary"></i>
                <span>Transportation &amp; Transfers</span>
            </h4>
            <div class="p-4 rounded-2xl border border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl {{ $inquiry->has_transportation ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/40' }} flex items-center justify-center shrink-0">
                        <i data-lucide="bus" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-dark block">
                            {{ $inquiry->has_transportation ? ($inquiry->transportation_type ?: 'Transportation Included') : 'No Transportation Requested' }}
                        </span>
                        <span class="text-[11px] text-dark/50">
                            {{ $inquiry->has_transportation ? 'Airport pickup / drop-off or touring vehicle arranged by Amega' : 'Client arranges own transport' }}
                        </span>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $inquiry->has_transportation ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/60' }}">
                    {{ $inquiry->has_transportation ? 'Active' : 'N/A' }}
                </span>
            </div>
        </div>

        <!-- Special Requests -->
        @if($inquiry->special_requests)
            <div class="space-y-2">
                <h4 class="font-heading font-bold text-xs uppercase tracking-wider text-dark/50">Client Special Requests</h4>
                <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200/80 text-xs text-dark font-medium leading-relaxed">
                    {{ $inquiry->special_requests }}
                </div>
            </div>
        @endif

        <!-- Estimated Budget & Pricing Banner -->
        <div class="p-6 rounded-2xl bg-navy text-white flex items-center justify-between">
            <div>
                <span class="text-xs uppercase tracking-wider text-white/60 font-bold block">Estimated Package Price / Budget</span>
                <p class="text-[11px] text-white/50 mt-0.5">Subject to flight &amp; hotel availability at time of final booking confirmation</p>
            </div>
            <div class="text-right">
                <span class="font-mono text-2xl font-black text-accent">{{ $inquiry->formatted_budget }}</span>
            </div>
        </div>
    </div>

    <!-- Agent Status Update Panel -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4 print:hidden">
        <h3 class="font-heading font-bold text-sm text-dark">Update Quotation Status &amp; Notes</h3>
        
        <form method="POST" action="{{ route('admin.packages.custom-inquiries.status', $inquiry) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Quotation Status</label>
                    <select name="status" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-bold">
                        <option value="pending" {{ $inquiry->status === 'pending' ? 'selected' : '' }}>Pending Review</option>
                        <option value="quoted" {{ $inquiry->status === 'quoted' ? 'selected' : '' }}>Quoted / Sent to Client</option>
                        <option value="booked" {{ $inquiry->status === 'booked' ? 'selected' : '' }}>Booked &amp; Confirmed</option>
                        <option value="cancelled" {{ $inquiry->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Update Estimated Budget ({{ $inquiry->currency }})</label>
                    <input type="number" step="0.01" min="0" name="estimated_budget" value="{{ $inquiry->estimated_budget }}"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-dark/70 mb-1">Agent Internal Notes</label>
                <textarea name="agent_notes" rows="2"
                          class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                          placeholder="e.g. Sent quotation via email on Sept 23; client requested breakfast inclusion update...">{{ $inquiry->agent_notes }}</textarea>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-white font-bold text-xs hover:bg-navy transition-all shadow-md cursor-pointer">
                    Save Status Changes
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
