@extends('layouts.admin')

@section('title', 'Client 360° Profile: ' . $user->full_name . ' - AMEGA Admin')
@section('page_title', 'Client Profile & Travel Dossier')

@section('content')
<div x-data="{ activeTab: 'overview' }" class="max-w-6xl mx-auto space-y-6">
    
    <!-- Profile Banner Card -->
    <div class="bg-navy rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-xl border border-white/10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            @if($user->profile_photo_url)
                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-full object-cover border-4 border-white/20 shadow-lg shrink-0">
            @else
                <div class="w-20 h-20 rounded-full bg-accent text-dark font-heading font-extrabold text-3xl flex items-center justify-center border-4 border-white/20 shadow-lg shrink-0">
                    {{ substr($user->full_name, 0, 1) }}
                </div>
            @endif

            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="px-3 py-1 rounded-full bg-accent text-dark font-extrabold text-[10px] uppercase tracking-wider">
                        {{ $user->account_category ?? 'Individual' }} Category
                    </span>
                    <span class="px-3 py-1 rounded-full bg-white/10 text-white font-bold text-[10px] uppercase tracking-wider border border-white/20">
                        {{ ucfirst($user->role) }}
                    </span>
                    @if($user->passport_number)
                        <span class="px-3 py-1 rounded-full bg-blue-500/20 text-blue-200 font-mono font-bold text-[10px] border border-blue-400/30">
                            Passport: {{ $user->passport_number }}
                        </span>
                    @endif
                </div>
                <h1 class="font-heading text-2xl font-bold text-white">{{ $user->full_name }}</h1>
                <p class="text-xs text-white/70 mt-0.5">
                    {{ $user->email }} • {{ $user->phone ?? 'No phone' }} • Registered {{ $user->created_at ? $user->created_at->format('M j, Y') : 'Recently' }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.users.edit', $user) }}" class="px-4 py-2.5 bg-accent text-dark font-bold text-xs rounded-xl hover:bg-accent-dark transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
                <span>Edit Account</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="px-4 py-2.5 bg-white/10 text-white font-bold text-xs rounded-xl hover:bg-white/20 transition-all border border-white/20 flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back to Directory</span>
            </a>
        </div>
    </div>

    <!-- 360° Service Counter Stats Pill Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <!-- Flight Tickets -->
        <button @click="activeTab = 'ticketing'" 
                type="button"
                :class="activeTab === 'ticketing' ? 'ring-2 ring-primary border-primary bg-blue-50/70' : 'bg-white hover:bg-slate-50 border-gray-100'"
                class="p-4 rounded-2xl border shadow-xs text-left transition-all cursor-pointer">
            <div class="flex items-center justify-between text-blue-600 mb-1">
                <i data-lucide="ticket" class="w-4 h-4"></i>
                <span class="font-heading font-extrabold text-lg text-dark">{{ $ticketBookings->count() }}</span>
            </div>
            <span class="text-[11px] font-bold text-slate-700 block">Flight Tickets</span>
            <span class="text-[10px] text-slate-400">Domestic &amp; Int'l</span>
        </button>

        <!-- Visa Applications -->
        <button @click="activeTab = 'visa'" 
                type="button"
                :class="activeTab === 'visa' ? 'ring-2 ring-primary border-primary bg-blue-50/70' : 'bg-white hover:bg-slate-50 border-gray-100'"
                class="p-4 rounded-2xl border shadow-xs text-left transition-all cursor-pointer">
            <div class="flex items-center justify-between text-indigo-600 mb-1">
                <i data-lucide="globe" class="w-4 h-4"></i>
                <span class="font-heading font-extrabold text-lg text-dark">{{ $visaApplications->count() }}</span>
            </div>
            <span class="text-[11px] font-bold text-slate-700 block">Visa Applications</span>
            <span class="text-[10px] text-slate-400">Visit, e-Visa, Passport</span>
        </button>

        <!-- Immigration Sheets -->
        <button @click="activeTab = 'immigration'" 
                type="button"
                :class="activeTab === 'immigration' ? 'ring-2 ring-primary border-primary bg-blue-50/70' : 'bg-white hover:bg-slate-50 border-gray-100'"
                class="p-4 rounded-2xl border shadow-xs text-left transition-all cursor-pointer">
            <div class="flex items-center justify-between text-amber-600 mb-1">
                <i data-lucide="stamp" class="w-4 h-4"></i>
                <span class="font-heading font-extrabold text-lg text-dark">{{ $immigrationRecords->count() }}</span>
            </div>
            <span class="text-[11px] font-bold text-slate-700 block">Immigration Records</span>
            <span class="text-[10px] text-slate-400">BI Ledgers &amp; Extensions</span>
        </button>

        <!-- Tour Packages -->
        <button @click="activeTab = 'packages'" 
                type="button"
                :class="activeTab === 'packages' ? 'ring-2 ring-primary border-primary bg-blue-50/70' : 'bg-white hover:bg-slate-50 border-gray-100'"
                class="p-4 rounded-2xl border shadow-xs text-left transition-all cursor-pointer">
            <div class="flex items-center justify-between text-emerald-600 mb-1">
                <i data-lucide="package" class="w-4 h-4"></i>
                <span class="font-heading font-extrabold text-lg text-dark">{{ $user->bookings->count() + $customInquiries->count() }}</span>
            </div>
            <span class="text-[11px] font-bold text-slate-700 block">Tour Packages</span>
            <span class="text-[10px] text-slate-400">Standard &amp; Custom</span>
        </button>

        <!-- Profile & Stored IDs -->
        <button @click="activeTab = 'profile'" 
                type="button"
                :class="activeTab === 'profile' ? 'ring-2 ring-primary border-primary bg-blue-50/70' : 'bg-white hover:bg-slate-50 border-gray-100'"
                class="p-4 rounded-2xl border shadow-xs text-left transition-all cursor-pointer col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between text-slate-600 mb-1">
                <i data-lucide="user-check" class="w-4 h-4"></i>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Profile</span>
            </div>
            <span class="text-[11px] font-bold text-slate-700 block">Gov't ID &amp; KYC</span>
            <span class="text-[10px] text-slate-400">Signature &amp; Address</span>
        </button>
    </div>

    <!-- Interactive Navigation Tabs Header -->
    <div class="border-b border-gray-200 bg-white rounded-2xl p-1.5 shadow-xs flex flex-wrap gap-1">
        <button @click="activeTab = 'overview'" 
                type="button"
                :class="activeTab === 'overview' ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:text-navy hover:bg-slate-50'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="layout-grid" class="w-4 h-4"></i>
            <span>Overview &amp; Statuses</span>
        </button>

        <button @click="activeTab = 'ticketing'" 
                type="button"
                :class="activeTab === 'ticketing' ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:text-navy hover:bg-slate-50'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="ticket" class="w-4 h-4 text-blue-400"></i>
            <span>Flight Ticketing History ({{ $ticketBookings->count() }})</span>
        </button>

        <button @click="activeTab = 'visa'" 
                type="button"
                :class="activeTab === 'visa' ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:text-navy hover:bg-slate-50'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="globe" class="w-4 h-4 text-indigo-400"></i>
            <span>Visa Assistance ({{ $visaApplications->count() }})</span>
        </button>

        <button @click="activeTab = 'immigration'" 
                type="button"
                :class="activeTab === 'immigration' ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:text-navy hover:bg-slate-50'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="stamp" class="w-4 h-4 text-amber-400"></i>
            <span>Immigration Records ({{ $immigrationRecords->count() }})</span>
        </button>

        <button @click="activeTab = 'packages'" 
                type="button"
                :class="activeTab === 'packages' ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:text-navy hover:bg-slate-50'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="palmtree" class="w-4 h-4 text-emerald-400"></i>
            <span>Tour Packages ({{ $user->bookings->count() + $customInquiries->count() }})</span>
        </button>

        <button @click="activeTab = 'profile'" 
                type="button"
                :class="activeTab === 'profile' ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:text-navy hover:bg-slate-50'"
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer ml-auto">
            <i data-lucide="user" class="w-4 h-4 text-slate-300"></i>
            <span>Full Profile Details</span>
        </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: OVERVIEW & ACTIVE STATUS SUMMARY    -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'overview'" class="space-y-6">
        
        <!-- Active Status Highlights Bar -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            
            <!-- 1. Latest Flight Status Card -->
            @php $latestTicket = $ticketBookings->first(); @endphp
            <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-xs flex flex-col justify-between gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600 flex items-center gap-1.5">
                        <i data-lucide="plane-takeoff" class="w-4 h-4"></i>
                        <span>Latest Flight Ticket</span>
                    </span>
                    @if($latestTicket)
                        @php
                            $ticketStatusColors = [
                                'issued' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'confirmed' => 'bg-blue-100 text-blue-800 border-blue-200',
                                'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
                            ];
                        @endphp
                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border {{ $ticketStatusColors[$latestTicket->status] ?? 'bg-slate-100 text-slate-700' }}">
                            {{ ucfirst($latestTicket->status) }}
                        </span>
                    @endif
                </div>

                @if($latestTicket)
                    <div>
                        <div class="font-heading text-base font-bold text-dark flex items-center gap-2">
                            <span>{{ $latestTicket->origin ?? 'MNL' }}</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>{{ $latestTicket->destination }}</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-2">
                            <span>Ref: <strong class="text-navy font-mono">{{ $latestTicket->booking_reference }}</strong></span>
                            <span>•</span>
                            <span>{{ $latestTicket->departure_date ? $latestTicket->departure_date->format('M d, Y') : 'Date TBD' }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span class="font-bold text-emerald-700 font-heading">₱{{ number_format((float) $latestTicket->total_amount, 2) }}</span>
                        <a href="{{ route('ticketing.tickets.show', $latestTicket) }}" class="text-primary hover:underline font-bold text-[11px] flex items-center gap-1">
                            <span>Open Ticket</span>
                            <i data-lucide="external-link" class="w-3 h-3"></i>
                        </a>
                    </div>
                @else
                    <div class="py-6 text-center text-slate-400 text-xs">
                        <i data-lucide="ticket" class="w-6 h-6 mx-auto mb-1 opacity-40"></i>
                        <p>No flight tickets on file</p>
                    </div>
                @endif
            </div>

            <!-- 2. Latest Visa Status Card -->
            @php $latestVisa = $visaApplications->first(); @endphp
            <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-xs flex flex-col justify-between gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 flex items-center gap-1.5">
                        <i data-lucide="globe" class="w-4 h-4"></i>
                        <span>Latest Visa File</span>
                    </span>
                    @if($latestVisa)
                        @php
                            $visaBadgeColors = [
                                'released' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
                                'lodged' => 'bg-purple-100 text-purple-800 border-purple-200',
                                'appointment' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                                'requirements' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
                            ];
                        @endphp
                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border {{ $visaBadgeColors[$latestVisa->status] ?? 'bg-slate-100 text-slate-700' }}">
                            {{ ucfirst(str_replace('_', ' ', $latestVisa->status)) }}
                        </span>
                    @endif
                </div>

                @if($latestVisa)
                    <div>
                        <div class="font-heading text-base font-bold text-dark">
                            {{ $latestVisa->destination_country ?? 'Travel Visa' }}
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-2">
                            <span>Ref: <strong class="text-navy font-mono">{{ $latestVisa->reference }}</strong></span>
                            <span>•</span>
                            <span>{{ ucfirst(str_replace('_', ' ', $latestVisa->service_type)) }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-700 text-[11px]">
                            {{ $latestVisa->processing_speed === 'rush' ? '⚡ Rush Processing' : 'Standard Speed' }}
                        </span>
                        <a href="{{ route('visa.applications.show', $latestVisa) }}" class="text-indigo-600 hover:underline font-bold text-[11px] flex items-center gap-1">
                            <span>Open Visa File</span>
                            <i data-lucide="external-link" class="w-3 h-3"></i>
                        </a>
                    </div>
                @else
                    <div class="py-6 text-center text-slate-400 text-xs">
                        <i data-lucide="globe" class="w-6 h-6 mx-auto mb-1 opacity-40"></i>
                        <p>No visa applications on file</p>
                    </div>
                @endif
            </div>

            <!-- 3. Latest Immigration Status Card -->
            @php $latestImm = $immigrationRecords->first(); @endphp
            <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-xs flex flex-col justify-between gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 flex items-center gap-1.5">
                        <i data-lucide="stamp" class="w-4 h-4"></i>
                        <span>Immigration &amp; Visa Expiry</span>
                    </span>
                    @if($latestImm)
                        @if($latestImm->is_expired || ($latestImm->visa_expiry_date && $latestImm->visa_expiry_date->isPast()))
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 border border-rose-200">
                                Expired / Overstay
                            </span>
                        @elseif($latestImm->has_penalty)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                                Penalty Flagged
                            </span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                In Good Standing
                            </span>
                        @endif
                    @endif
                </div>

                @if($latestImm)
                    <div>
                        <div class="font-heading text-base font-bold text-dark">
                            Visa Valid Until: 
                            <span class="{{ ($latestImm->visa_expiry_date && $latestImm->visa_expiry_date->isPast()) ? 'text-rose-600' : 'text-navy' }}">
                                {{ $latestImm->visa_expiry_date ? $latestImm->visa_expiry_date->format('M d, Y') : 'N/A' }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1">
                            Passport: <strong class="font-mono text-dark">{{ $latestImm->passport_number }}</strong> • {{ $latestImm->extensions->count() }} Extension(s) Filed
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                        <a href="{{ route('admin.client-sheets.print', $latestImm) }}" target="_blank" class="text-amber-700 hover:underline font-bold text-[11px] flex items-center gap-1">
                            <i data-lucide="printer" class="w-3 h-3"></i>
                            <span>Print Client Sheet</span>
                        </a>
                        <a href="{{ route('admin.client-sheets.edit', $latestImm) }}" class="text-primary hover:underline font-bold text-[11px] flex items-center gap-1">
                            <span>Open Record</span>
                            <i data-lucide="external-link" class="w-3 h-3"></i>
                        </a>
                    </div>
                @else
                    <div class="py-6 text-center text-slate-400 text-xs">
                        <i data-lucide="stamp" class="w-6 h-6 mx-auto mb-1 opacity-40"></i>
                        <p>No Bureau of Immigration file</p>
                    </div>
                @endif
            </div>

        </div>

        <!-- Recent Activity Feed Across Services -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-heading text-base font-bold text-dark flex items-center gap-2">
                    <i data-lucide="history" class="w-5 h-5 text-primary"></i>
                    <span>All Combined Client Transactions</span>
                </h3>
                <span class="text-xs text-slate-400 font-semibold">Chronological Dossier</span>
            </div>

            <div class="divide-y divide-gray-100 text-xs">
                @php
                    $allActivities = collect();

                    foreach($ticketBookings as $ticket) {
                        $allActivities->push([
                            'type' => 'ticket',
                            'title' => 'Flight Ticket: ' . ($ticket->origin ?? 'MNL') . ' → ' . $ticket->destination,
                            'ref' => $ticket->booking_reference,
                            'status' => $ticket->status,
                            'date' => $ticket->created_at,
                            'amount' => $ticket->total_amount,
                            'url' => route('ticketing.tickets.show', $ticket),
                            'icon' => 'ticket',
                            'color' => 'bg-blue-50 text-blue-600',
                        ]);
                    }

                    foreach($visaApplications as $visa) {
                        $allActivities->push([
                            'type' => 'visa',
                            'title' => 'Visa Assistance: ' . ($visa->destination_country ?? 'Destination') . ' (' . ucfirst(str_replace('_', ' ', $visa->service_type)) . ')',
                            'ref' => $visa->reference,
                            'status' => $visa->status,
                            'date' => $visa->created_at,
                            'amount' => $visa->total_amount,
                            'url' => route('visa.applications.show', $visa),
                            'icon' => 'globe',
                            'color' => 'bg-indigo-50 text-indigo-600',
                        ]);
                    }

                    foreach($immigrationRecords as $imm) {
                        $allActivities->push([
                            'type' => 'immigration',
                            'title' => 'Bureau of Immigration Client Sheet (Passport: ' . $imm->passport_number . ')',
                            'ref' => 'IMM-' . $imm->id,
                            'status' => $imm->is_expired ? 'Expired' : 'Active',
                            'date' => $imm->created_at,
                            'amount' => null,
                            'url' => route('admin.client-sheets.edit', $imm),
                            'icon' => 'stamp',
                            'color' => 'bg-amber-50 text-amber-600',
                        ]);
                    }

                    foreach($user->bookings as $b) {
                        $allActivities->push([
                            'type' => 'booking',
                            'title' => 'Tour Package: ' . ($b->travelPackage ? $b->travelPackage->title : 'Travel Tour'),
                            'ref' => $b->booking_reference,
                            'status' => $b->status,
                            'date' => $b->created_at,
                            'amount' => $b->amount_due,
                            'url' => null,
                            'icon' => 'package',
                            'color' => 'bg-emerald-50 text-emerald-600',
                        ]);
                    }

                    foreach($customInquiries as $ci) {
                        $allActivities->push([
                            'type' => 'custom',
                            'title' => 'Custom Tour Request: ' . ($ci->destination_name ?: 'Custom') . ' (' . $ci->number_of_pax . ' Pax)',
                            'ref' => $ci->reference_number,
                            'status' => $ci->status,
                            'date' => $ci->created_at,
                            'amount' => $ci->estimated_budget,
                            'url' => route('admin.packages.custom-inquiries.show', $ci),
                            'icon' => 'sliders',
                            'color' => 'bg-purple-50 text-purple-600',
                        ]);
                    }

                    $sortedActivities = $allActivities->sortByDesc('date');
                @endphp

                @forelse($sortedActivities as $act)
                    <div class="py-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/50 px-2 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $act['color'] }}">
                                <i data-lucide="{{ $act['icon'] }}" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-dark">{{ $act['title'] }}</h4>
                                <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-2">
                                    <span class="font-mono font-bold text-navy">{{ $act['ref'] }}</span>
                                    <span>•</span>
                                    <span>{{ $act['date'] ? $act['date']->format('M d, Y h:i A') : 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            @if($act['amount'])
                                <span class="font-heading font-bold text-slate-800">₱{{ number_format((float) $act['amount'], 2) }}</span>
                            @endif

                            <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 uppercase">
                                {{ ucfirst(str_replace('_', ' ', $act['status'])) }}
                            </span>

                            @if($act['url'])
                                <a href="{{ $act['url'] }}" class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-slate-100 transition-colors" title="Open record">
                                    <i data-lucide="external-link" class="w-4 h-4"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                        <p class="font-medium text-xs">No transactions or bookings recorded yet for this client</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 2: FLIGHT TICKETING HISTORY            -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'ticketing'" style="display: none;" class="space-y-4">
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-heading text-lg font-bold text-dark flex items-center gap-2">
                        <i data-lucide="ticket" class="w-5 h-5 text-blue-600"></i>
                        <span>Flight Ticketing History &amp; Quotations</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">All airline bookings, route itineraries, and payment issuance states for this traveler.</p>
                </div>
                <a href="{{ route('ticketing.tickets.create') }}" class="px-4 py-2 bg-navy text-white text-xs font-bold rounded-xl hover:bg-primary transition-all flex items-center gap-1.5 shadow-xs">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-accent"></i>
                    <span>New Ticket Booking</span>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-gray-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-3 px-4">Booking Ref</th>
                            <th class="py-3 px-4">Flight Route</th>
                            <th class="py-3 px-4">Travel Dates</th>
                            <th class="py-3 px-4">Passengers</th>
                            <th class="py-3 px-4">Total Amount</th>
                            <th class="py-3 px-4">Payment</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-slate-700">
                        @forelse($ticketBookings as $ticket)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4">
                                    <span class="font-mono font-bold text-navy">{{ $ticket->booking_reference }}</span>
                                    @if($ticket->is_quotation)
                                        <span class="block text-[9px] font-bold text-amber-600 uppercase">Quotation Only</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-dark flex items-center gap-1.5">
                                        <span>{{ $ticket->origin ?? 'MNL' }}</span>
                                        <i data-lucide="arrow-right" class="w-3 h-3 text-slate-400"></i>
                                        <span>{{ $ticket->destination }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ ucfirst($ticket->trip_type ?? 'roundtrip') }} • {{ $ticket->travel_class ?? 'Economy' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold">{{ $ticket->departure_date ? $ticket->departure_date->format('M d, Y') : 'TBD' }}</div>
                                    @if($ticket->return_date)
                                        <div class="text-[11px] text-slate-400">Ret: {{ $ticket->return_date->format('M d, Y') }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-semibold">
                                    {{ $ticket->total_passengers ?? 1 }} Pax
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-heading font-bold text-emerald-700">
                                        ₱{{ number_format((float) $ticket->total_amount, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $paymentColors = [
                                            'fully_paid' => 'bg-emerald-100 text-emerald-800',
                                            'partially_paid' => 'bg-blue-100 text-blue-800',
                                            'unpaid' => 'bg-rose-100 text-rose-800',
                                        ];
                                    @endphp
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md {{ $paymentColors[$ticket->payment_status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst(str_replace('_', ' ', $ticket->payment_status)) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $ticketColors = [
                                            'issued' => 'bg-emerald-100 text-emerald-800',
                                            'confirmed' => 'bg-blue-100 text-blue-800',
                                            'pending' => 'bg-amber-100 text-amber-800',
                                            'cancelled' => 'bg-rose-100 text-rose-800',
                                        ];
                                    @endphp
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md {{ $ticketColors[$ticket->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($ticket->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition-colors">
                                        <span>Open</span>
                                        <i data-lucide="external-link" class="w-3 h-3"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400">
                                    <i data-lucide="ticket" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                                    <p class="font-semibold text-xs">No flight ticketing records found for this client</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 3: VISA ASSISTANCE & APPLICATIONS      -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'visa'" style="display: none;" class="space-y-4">
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-heading text-lg font-bold text-dark flex items-center gap-2">
                        <i data-lucide="globe" class="w-5 h-5 text-indigo-600"></i>
                        <span>Visa Assistance Files &amp; Status</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Visit visa, e-Visa, and foreign/local passporting counter applications.</p>
                </div>
                <a href="{{ route('visa.dashboard') }}" class="px-4 py-2 bg-navy text-white text-xs font-bold rounded-xl hover:bg-primary transition-all flex items-center gap-1.5 shadow-xs">
                    <i data-lucide="layout-dashboard" class="w-4 h-4 text-accent"></i>
                    <span>Visa Counter Dashboard</span>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-gray-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-3 px-4">Counter File</th>
                            <th class="py-3 px-4">Service</th>
                            <th class="py-3 px-4">Destination Country</th>
                            <th class="py-3 px-4">Processing Speed</th>
                            <th class="py-3 px-4">Stage / Status</th>
                            <th class="py-3 px-4">Total Amount</th>
                            <th class="py-3 px-4">Amount Paid</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-slate-700">
                        @forelse($visaApplications as $app)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4">
                                    <span class="font-mono font-bold text-navy">{{ $app->reference }}</span>
                                    <div class="text-[10px] text-slate-400">{{ $app->created_at->format('M d, Y') }}</div>
                                </td>
                                <td class="py-3 px-4 font-semibold">
                                    {{ ucfirst(str_replace('_', ' ', $app->service_type)) }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-dark">{{ $app->destination_country ?? 'N/A' }}</div>
                                    <div class="text-[10px] text-slate-400 capitalize">{{ $app->purpose ?? 'General' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md {{ $app->processing_speed === 'rush' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($app->processing_speed ?? 'regular') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $visaColors = [
                                            'released' => 'bg-emerald-100 text-emerald-800',
                                            'acknowledged' => 'bg-emerald-100 text-emerald-800',
                                            'approved' => 'bg-blue-100 text-blue-800',
                                            'lodged' => 'bg-purple-100 text-purple-800',
                                            'appointment' => 'bg-indigo-100 text-indigo-800',
                                            'requirements' => 'bg-amber-100 text-amber-800',
                                            'pending' => 'bg-slate-100 text-slate-700',
                                            'cancelled' => 'bg-rose-100 text-rose-800',
                                        ];
                                    @endphp
                                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full {{ $visaColors[$app->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst(str_replace('_', ' ', $app->status)) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    ₱{{ number_format((float) $app->total_amount, 2) }}
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-700">
                                    ₱{{ number_format((float) $app->amount_paid, 2) }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('visa.applications.show', $app) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition-colors">
                                        <span>View File</span>
                                        <i data-lucide="external-link" class="w-3 h-3"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400">
                                    <i data-lucide="globe" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                                    <p class="font-semibold text-xs">No visa assistance applications on file for this client</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 4: IMMIGRATION RECORDS & EXTENSION LEDGER -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'immigration'" style="display: none;" class="space-y-6">
        @forelse($immigrationRecords as $sheet)
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-heading text-lg font-bold text-dark">
                                Bureau of Immigration Client Information Sheet
                            </h3>
                            @if($sheet->is_expired || ($sheet->visa_expiry_date && $sheet->visa_expiry_date->isPast()))
                                <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold text-[10px]">
                                    Visa Expired
                                </span>
                            @endif
                            @if($sheet->has_penalty)
                                <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px]">
                                    Overstay Penalty Flagged
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Passport: <strong class="font-mono text-dark">{{ $sheet->passport_number }}</strong> • Nationality: <strong>{{ $sheet->nationality ?? 'Foreign National' }}</strong></p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.client-sheets.print', $sheet) }}" target="_blank" class="px-3.5 py-2 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl hover:bg-slate-200 transition-all flex items-center gap-1.5 shadow-xs">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                            <span>Print Sheet</span>
                        </a>
                        <a href="{{ route('admin.client-sheets.edit', $sheet) }}" class="px-3.5 py-2 bg-navy text-white font-bold text-xs rounded-xl hover:bg-primary transition-all flex items-center gap-1.5 shadow-xs">
                            <i data-lucide="edit" class="w-3.5 h-3.5 text-accent"></i>
                            <span>Edit Extensions</span>
                        </a>
                    </div>
                </div>

                <!-- Ledger Specs -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">Current Visa Expiry</span>
                        <span class="font-bold text-sm {{ ($sheet->visa_expiry_date && $sheet->visa_expiry_date->isPast()) ? 'text-rose-600' : 'text-navy' }}">
                            {{ $sheet->visa_expiry_date ? $sheet->visa_expiry_date->format('F d, Y') : 'Not Set' }}
                        </span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">Date of Birth</span>
                        <span class="font-bold text-sm text-dark">{{ $sheet->date_of_birth ? $sheet->date_of_birth->format('M d, Y') : 'N/A' }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">Civil Status</span>
                        <span class="font-bold text-sm text-dark">{{ ucfirst($sheet->civil_status ?? 'Single') }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">Height / Weight</span>
                        <span class="font-bold text-sm text-dark">{{ $sheet->height ?? 'N/A' }} / {{ $sheet->weight ?? 'N/A' }}</span>
                    </div>
                </div>

                @if($sheet->status_note || $sheet->notes)
                    <div class="p-3 bg-amber-50/60 rounded-2xl border border-amber-100 text-xs text-amber-950">
                        <strong>Counter Notes:</strong> {{ $sheet->status_note ?? $sheet->notes }}
                    </div>
                @endif

                <!-- Extension Ledger Table -->
                <div>
                    <h4 class="font-heading font-bold text-xs uppercase tracking-wider text-slate-500 mb-2">
                        Extension History Ledger ({{ $sheet->extensions->count() }} Entries)
                    </h4>
                    <div class="overflow-x-auto rounded-2xl border border-gray-100">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-gray-200/80 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="py-2.5 px-3">#</th>
                                    <th class="py-2.5 px-3">Entry Date</th>
                                    <th class="py-2.5 px-3">Granted Until</th>
                                    <th class="py-2.5 px-3">Visa Category</th>
                                    <th class="py-2.5 px-3">Official Receipt #</th>
                                    <th class="py-2.5 px-3">Amount (₱)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-slate-700">
                                @forelse($sheet->extensions as $ext)
                                    <tr>
                                        <td class="py-2.5 px-3 font-bold text-slate-400">{{ $ext->sequence }}</td>
                                        <td class="py-2.5 px-3">{{ $ext->entry_date ? $ext->entry_date->format('M d, Y') : '-' }}</td>
                                        <td class="py-2.5 px-3 font-bold text-navy">{{ $ext->granted_until ? $ext->granted_until->format('M d, Y') : '-' }}</td>
                                        <td class="py-2.5 px-3">{{ $ext->visa_category ?? 'Tourist Visa (9A)' }}</td>
                                        <td class="py-2.5 px-3 font-mono font-bold">{{ $ext->or_number ?? '-' }}</td>
                                        <td class="py-2.5 px-3 font-bold text-emerald-700">
                                            {{ $ext->amount_paid ? '₱' . number_format((float) $ext->amount_paid, 2) : '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-4 text-center text-slate-400 text-xs italic">
                                            No extensions filed in this ledger yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-3xl p-12 text-center text-slate-400 border border-gray-100 shadow-sm space-y-3">
                <i data-lucide="stamp" class="w-12 h-12 mx-auto opacity-30 text-amber-500"></i>
                <h4 class="font-heading font-bold text-base text-dark">No Bureau of Immigration File</h4>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">This client does not currently have a recorded immigration client information sheet or extension ledger.</p>
                <a href="{{ route('admin.immigration.dashboard') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-navy text-white text-xs font-bold rounded-xl hover:bg-primary transition-all">
                    <span>Open Immigration Counter</span>
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        @endforelse
    </div>

    <!-- ========================================== -->
    <!-- TAB 5: TOUR PACKAGES & CUSTOM ITINERARIES  -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'packages'" style="display: none;" class="space-y-6">
        <!-- Standard Tour Bookings -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
            <h3 class="font-heading text-lg font-bold text-dark flex items-center gap-2">
                <i data-lucide="package" class="w-5 h-5 text-emerald-600"></i>
                <span>Ready-Made Travel Package Bookings</span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-gray-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-3 px-4">Booking Ref</th>
                            <th class="py-3 px-4">Package</th>
                            <th class="py-3 px-4">Travel Date</th>
                            <th class="py-3 px-4">Passengers</th>
                            <th class="py-3 px-4">Amount Due</th>
                            <th class="py-3 px-4">Payment</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-slate-700">
                        @forelse($user->bookings as $b)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-navy">{{ $b->booking_reference }}</td>
                                <td class="py-3 px-4 font-bold text-dark">{{ $b->travelPackage ? $b->travelPackage->title : 'Package Tour' }}</td>
                                <td class="py-3 px-4">{{ $b->travel_date ? $b->travel_date->format('M d, Y') : 'TBD' }}</td>
                                <td class="py-3 px-4 font-semibold">{{ $b->number_of_passengers }} Pax</td>
                                <td class="py-3 px-4 font-heading font-bold text-emerald-700">₱{{ number_format((float) $b->amount_due, 2) }}</td>
                                <td class="py-3 px-4">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">
                                        {{ ucfirst($b->payment_status ?? 'unpaid') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full {{ $b->status === 'confirmed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($b->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                                    No standard package bookings on file.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Custom Package Inquiries -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
            <h3 class="font-heading text-lg font-bold text-dark flex items-center gap-2">
                <i data-lucide="sliders" class="w-5 h-5 text-purple-600"></i>
                <span>Custom Configured Itinerary Requests</span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-gray-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-3 px-4">Reference</th>
                            <th class="py-3 px-4">Destination</th>
                            <th class="py-3 px-4">Hotel &amp; Room Bed</th>
                            <th class="py-3 px-4">Dates &amp; Pax</th>
                            <th class="py-3 px-4">Transportation</th>
                            <th class="py-3 px-4">Budget</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-slate-700">
                        @forelse($customInquiries as $ci)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-navy">{{ $ci->reference_number }}</td>
                                <td class="py-3 px-4 font-bold text-dark">{{ $ci->destination_name ?: 'Custom Tour' }}</td>
                                <td class="py-3 px-4">
                                    <div>{{ $ci->hotel_name ?? 'Preferred Hotel' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $ci->bed_config ?? 'Double Bed' }} • {{ $ci->has_breakfast ? 'w/ Breakfast' : 'Room Only' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div>{{ $ci->number_of_pax }} Pax</div>
                                    <div class="text-[10px] text-slate-400">{{ $ci->check_in_date ? $ci->check_in_date->format('M d') : '' }} - {{ $ci->check_out_date ? $ci->check_out_date->format('M d, Y') : '' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md {{ $ci->has_transportation ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $ci->has_transportation ? ($ci->transportation_type ?: 'Yes') : 'None' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-heading font-bold text-emerald-700">
                                    {{ $ci->estimated_budget ? '₱' . number_format((float) $ci->estimated_budget, 2) : 'TBD' }}
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $ciColors = [
                                            'booked' => 'bg-emerald-100 text-emerald-800',
                                            'quoted' => 'bg-indigo-100 text-indigo-800',
                                            'pending' => 'bg-amber-100 text-amber-800',
                                            'cancelled' => 'bg-rose-100 text-rose-800',
                                        ];
                                    @endphp
                                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full {{ $ciColors[$ci->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($ci->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('admin.packages.custom-inquiries.show', $ci) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition-colors">
                                        <span>View</span>
                                        <i data-lucide="external-link" class="w-3 h-3"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                                    No custom tour package inquiries on file.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 6: FULL CLIENT REGISTRATION PROFILE    -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'profile'" style="display: none;" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Part 1: Personal, Name Breakdown, Address & Emergency Contact -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">1</div>
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Personal, Address &amp; Contact Details</h3>
                    <p class="text-[11px] text-dark/50">Split name records, residential address &amp; emergency contact</p>
                </div>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Name Breakdown Card -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Full Name Breakdown</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Given Name:</span>
                            <span class="font-bold text-dark">{{ $user->first_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Middle Name:</span>
                            <span class="font-bold text-dark">{{ $user->middle_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Surname:</span>
                            <span class="font-bold text-dark">{{ $user->last_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Suffix:</span>
                            <span class="font-bold text-dark">{{ $user->suffix ?? 'None' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Email Address</span>
                        <span class="font-semibold text-dark">{{ $user->email }}</span>
                    </div>
                    <div>
                        <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Phone Number</span>
                        <span class="font-semibold text-dark">{{ $user->phone ?? 'Not provided' }}</span>
                    </div>
                </div>

                <!-- Complete Address Details Card -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Residential Address Information</span>
                    <div class="space-y-1.5 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Street / House No:</span>
                            <span class="font-bold text-dark">{{ $user->address_line ?? $user->address ?? 'Not provided' }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <span class="text-dark/40 block">City / Municipality:</span>
                                <span class="font-semibold text-dark">{{ $user->city ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-dark/40 block">Province / Region:</span>
                                <span class="font-semibold text-dark">{{ $user->province ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-dark/40 block">Postal Code:</span>
                                <span class="font-semibold text-dark">{{ $user->postal_code ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Country:</span>
                            <span class="font-semibold text-dark">{{ $user->country ?? 'Philippines' }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Nationality &amp; Citizenship</span>
                    <span class="font-semibold text-dark">{{ $user->nationality ?? 'Filipino' }}</span>
                </div>

                <!-- Emergency Contact Person -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Emergency Contact Person</span>
                    <div class="grid grid-cols-3 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Name:</span>
                            <span class="font-bold text-dark">{{ $user->emergency_contact_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Phone:</span>
                            <span class="font-bold text-dark">{{ $user->emergency_contact_phone ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Relationship:</span>
                            <span class="font-bold text-dark">{{ $user->emergency_contact_relationship ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Part 2: Passport Information & Government ID Records & ID Photo -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-accent text-dark flex items-center justify-center font-bold">2</div>
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Passport &amp; Government ID Records</h3>
                    <p class="text-[11px] text-dark/50">Passport, government identification and uploaded ID photo</p>
                </div>
            </div>

            <div class="space-y-3.5 text-xs">
                <div>
                    <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px]">Account Category</span>
                    <span class="inline-block px-3 py-1 rounded-full bg-primary/10 text-primary font-bold text-xs mt-0.5">
                        {{ $user->account_category ?? 'Individual' }}
                    </span>
                </div>

                <!-- Passport Records -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Passport Details</span>
                    <div class="grid grid-cols-3 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">Passport #:</span>
                            <span class="font-mono font-bold text-dark">{{ $user->passport_number ?? 'Not Provided' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Expiry Date:</span>
                            <span class="font-semibold text-dark">{{ $user->passport_expiry ? $user->passport_expiry->format('M j, Y') : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">Issuing Country:</span>
                            <span class="font-semibold text-dark">{{ $user->passport_country ?? 'Philippines' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Government ID Records & Photo -->
                <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 space-y-3">
                    <span class="text-primary font-bold uppercase tracking-wider text-[10px] block">Government-Issued Identification</span>
                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                        <div>
                            <span class="text-dark/40 block">ID Type:</span>
                            <span class="font-bold text-dark">{{ $user->government_id_type ?? 'Not Provided' }}</span>
                        </div>
                        <div>
                            <span class="text-dark/40 block">ID Number:</span>
                            <span class="font-mono font-bold text-dark">{{ $user->government_id_number ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Government ID Uploaded Photo Preview -->
                    <div class="pt-2 border-t border-gray-200">
                        <span class="text-dark/40 font-bold uppercase tracking-wider block text-[10px] mb-1.5">Uploaded ID Photo / Document</span>
                        @if($user->government_id_photo_url)
                            <div class="space-y-2">
                                <div class="rounded-xl border border-gray-200 overflow-hidden bg-white p-2 text-center max-w-xs">
                                    @if(str_contains(strtolower($user->government_id_photo), '.pdf'))
                                        <div class="p-4 bg-rose-50 text-rose-700 font-bold text-xs rounded-lg flex items-center justify-center gap-2">
                                            <i data-lucide="file-text" class="w-5 h-5"></i>
                                            <span>PDF Document Uploaded</span>
                                        </div>
                                    @else
                                        <img src="{{ $user->government_id_photo_url }}" alt="Government ID Photo" class="max-h-48 rounded-lg object-contain mx-auto">
                                    @endif
                                </div>
                                <a href="{{ $user->government_id_photo_url }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white font-bold text-[11px] rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                    <span>Open Original ID Document</span>
                                </a>
                            </div>
                        @else
                            <span class="text-[11px] text-dark/40 italic">No government ID photo uploaded</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Part 3: Profile Photo & E-Signature Card (Full Width) -->
        <div class="md:col-span-2 bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm space-y-5">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">3</div>
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Profile Photo &amp; Digital Signature</h3>
                    <p class="text-[11px] text-dark/50">Stored account photo and recorded authorization signature</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Profile Avatar Display -->
                <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 flex items-center gap-4">
                    @if($user->profile_photo_url)
                        <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-2xl object-cover border border-gray-200 shrink-0">
                    @else
                        <div class="w-20 h-20 rounded-2xl bg-navy text-accent font-bold text-2xl flex items-center justify-center shrink-0">
                            {{ substr($user->full_name, 0, 1) }}
                        </div>
                    @endif
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Uploaded Avatar</span>
                        <div class="text-xs font-bold text-dark mt-0.5">{{ $user->profile_photo_url ? 'Custom Image Uploaded' : 'Default Avatar' }}</div>
                    </div>
                </div>

                <!-- Digital Signature Display -->
                <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 flex flex-col justify-center">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block mb-2">Recorded Digital E-Signature</span>
                    @if($user->signature)
                        <div class="bg-white rounded-xl p-3 border border-gray-200 max-w-sm flex items-center justify-center shadow-inner">
                            <img src="{{ $user->signature }}" alt="E-Signature" class="max-h-20 object-contain">
                        </div>
                    @else
                        <div class="text-xs text-dark/40 italic p-3 bg-white rounded-xl border border-gray-200 text-center">
                            No e-signature provided during registration
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
