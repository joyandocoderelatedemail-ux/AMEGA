@extends('layouts.admin')

@section('title', 'CRM Sales Pipeline & Deals - AMEGA Admin')
@section('page_title', 'CRM Pipeline')

@section('content')
<div x-data="{
    viewMode: '{{ $viewMode }}',
    openNewLeadModal: false,
    openNoteModal: false,
    openDetailModal: false,
    activeLead: null,
    noteLeadId: null,
    noteLeadRef: '',
    noteLeadClient: '',
    isSyncing: false,

    openAddNote(leadId, leadRef, leadClient) {
        this.noteLeadId = leadId;
        this.noteLeadRef = leadRef;
        this.noteLeadClient = leadClient;
        this.openNoteModal = true;
    },

    viewLeadDetails(lead) {
        this.activeLead = lead;
        this.openDetailModal = true;
    }
}" class="space-y-6">

    <!-- Top Banner & Quick Actions Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-primary mb-1">
                <i data-lucide="kanban" class="w-4 h-4 text-accent"></i>
                <span class="uppercase tracking-wider">Customer Relationship Management</span>
            </div>
            <h1 class="font-heading text-2xl font-bold text-dark">Travel Sales Pipeline</h1>
            <p class="text-xs text-dark/60 mt-1">Unified deal flow for tour packages, flight ticketing, visa assistance, and customer inquiries.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
            <!-- Sync Inquiries Button -->
            <form action="{{ route('admin.crm.sync') }}" method="POST" @submit="isSyncing = true" class="inline">
                @csrf
                <button type="submit" 
                        :disabled="isSyncing"
                        class="px-3.5 py-2.5 rounded-xl border border-gray-200 text-dark font-bold text-xs hover:bg-gray-50 hover:border-gray-300 transition-all flex items-center gap-2 disabled:opacity-60 shadow-xs cursor-pointer"
                        title="Scan and import any unlinked inquiries or flight quotations into CRM">
                    <i data-lucide="refresh-cw" :class="isSyncing ? 'animate-spin' : ''" class="w-4 h-4 text-primary"></i>
                    <span x-text="isSyncing ? 'Syncing...' : 'Sync Leads'">Sync Leads</span>
                </button>
            </form>

            <!-- View Switcher (Kanban vs Table) -->
            <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200/60">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'kanban']) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ $viewMode === 'kanban' ? 'bg-white text-navy shadow-xs' : 'text-slate-500 hover:text-navy' }}">
                    <i data-lucide="kanban" class="w-3.5 h-3.5"></i>
                    <span>Kanban</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'table']) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ $viewMode === 'table' ? 'bg-white text-navy shadow-xs' : 'text-slate-500 hover:text-navy' }}">
                    <i data-lucide="list" class="w-3.5 h-3.5"></i>
                    <span>Table</span>
                </a>
            </div>

            <!-- New Lead Button -->
            <button @click="openNewLeadModal = true" 
                    type="button"
                    class="px-4 py-2.5 rounded-xl bg-navy text-white font-bold text-xs hover:bg-primary transition-all flex items-center gap-2 shadow-sm cursor-pointer ml-auto lg:ml-0">
                <i data-lucide="plus-circle" class="w-4 h-4 text-accent"></i>
                <span>+ New Lead / Deal</span>
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3 shadow-xs">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1.5 shadow-xs">
            <div class="font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 pl-6 text-rose-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Pipeline KPI Metrics Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Metric 1: Active Pipeline Value -->
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs relative overflow-hidden group hover:border-primary/30 transition-all">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 bg-blue-50 rounded-full opacity-60 pointer-events-none group-hover:scale-110 transition-transform"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Active Pipeline Value</span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i data-lucide="coins" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="font-heading text-2xl font-bold text-navy">
                ₱{{ number_format((float) $metrics['totalPipelineValue'], 2) }}
            </div>
            <p class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="font-bold text-blue-600">{{ $metrics['activeDealsCount'] }}</span> deals currently in active pipeline
            </p>
        </div>

        <!-- Metric 2: Active Open Deals -->
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs relative overflow-hidden group hover:border-primary/30 transition-all">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 bg-amber-50 rounded-full opacity-60 pointer-events-none group-hover:scale-110 transition-transform"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Open Inquiries &amp; Deals</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="compass" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="font-heading text-2xl font-bold text-slate-800">
                {{ $metrics['activeDealsCount'] }}
                <span class="text-xs font-normal text-slate-400">/ {{ $metrics['totalLeads'] }} total</span>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">
                Requiring response, quoting or follow-up
            </p>
        </div>

        <!-- Metric 3: Closed Won Revenue -->
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs relative overflow-hidden group hover:border-primary/30 transition-all">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 bg-emerald-50 rounded-full opacity-60 pointer-events-none group-hover:scale-110 transition-transform"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Won &amp; Booked Revenue</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="trophy" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="font-heading text-2xl font-bold text-emerald-700">
                ₱{{ number_format((float) $metrics['wonDealsValue'], 2) }}
            </div>
            <p class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                <span class="font-bold text-emerald-600">{{ $metrics['wonDealsCount'] }} deals</span> confirmed and booked
            </p>
        </div>

        <!-- Metric 4: Conversion Win Rate -->
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs relative overflow-hidden group hover:border-primary/30 transition-all">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 bg-indigo-50 rounded-full opacity-60 pointer-events-none group-hover:scale-110 transition-transform"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Win Conversion Rate</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="font-heading text-2xl font-bold text-indigo-700">
                {{ $metrics['conversionRate'] }}%
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ min(100, max(0, $metrics['conversionRate'])) }}%"></div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs">
        <form action="{{ route('admin.crm.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="hidden" name="view" value="{{ $viewMode }}">

            <!-- Search input -->
            <div class="relative lg:col-span-2">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </div>
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search client, deal title, ref, destination..." 
                       class="w-full pl-10 pr-3 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
            </div>

            <!-- Service Type Filter -->
            <div>
                <select name="service_type" 
                        onchange="this.form.submit()" 
                        class="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white text-slate-700 font-medium">
                    <option value="">All Services &amp; Portals</option>
                    @foreach(\App\Models\CrmLead::SERVICE_TYPES as $key => $label)
                        <option value="{{ $key }}" {{ request('service_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Priority Filter -->
            <div>
                <select name="priority" 
                        onchange="this.form.submit()" 
                        class="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white text-slate-700 font-medium">
                    <option value="">All Priorities</option>
                    @foreach(\App\Models\CrmLead::PRIORITIES as $key => $p)
                        <option value="{{ $key }}" {{ request('priority') === $key ? 'selected' : '' }}>{{ $p['label'] }} Priority</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary transition-all">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'service_type', 'priority', 'assigned_to']))
                    <a href="{{ route('admin.crm.index', ['view' => $viewMode]) }}" class="py-2 px-3 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-200 transition-all" title="Clear Filters">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- VIEW 1: KANBAN BOARD VIEW -->
    @if($viewMode === 'kanban')
    <div class="overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-[1280px]">
            @php
                $columns = [
                    \App\Models\CrmLead::STAGE_NEW => [
                        'title' => 'New Leads',
                        'headerBg' => 'bg-blue-50 border-blue-200 text-blue-900',
                        'badgeBg' => 'bg-blue-200/80 text-blue-800',
                        'icon' => 'sparkles',
                    ],
                    \App\Models\CrmLead::STAGE_CONTACTED => [
                        'title' => 'Contacted / Active',
                        'headerBg' => 'bg-amber-50 border-amber-200 text-amber-900',
                        'badgeBg' => 'bg-amber-200/80 text-amber-800',
                        'icon' => 'phone-call',
                    ],
                    \App\Models\CrmLead::STAGE_QUOTED => [
                        'title' => 'Proposal / Quoted',
                        'headerBg' => 'bg-indigo-50 border-indigo-200 text-indigo-900',
                        'badgeBg' => 'bg-indigo-200/80 text-indigo-800',
                        'icon' => 'file-text',
                    ],
                    \App\Models\CrmLead::STAGE_WON => [
                        'title' => 'Won / Booked',
                        'headerBg' => 'bg-emerald-50 border-emerald-200 text-emerald-900',
                        'badgeBg' => 'bg-emerald-200/80 text-emerald-800',
                        'icon' => 'trophy',
                    ],
                    \App\Models\CrmLead::STAGE_LOST => [
                        'title' => 'Lost / Cancelled',
                        'headerBg' => 'bg-slate-100 border-slate-200 text-slate-800',
                        'badgeBg' => 'bg-slate-200 text-slate-700',
                        'icon' => 'x-circle',
                    ],
                ];
            @endphp

            @foreach($columns as $stageKey => $col)
                @php
                    $leadsInCol = $stages[$stageKey] ?? collect();
                    $colTotal = $stageTotals[$stageKey] ?? 0;
                @endphp
                <div class="w-80 shrink-0 flex flex-col bg-slate-100/70 rounded-3xl p-3 border border-slate-200/60 max-h-[85vh]">
                    <!-- Column Header -->
                    <div class="p-3 rounded-2xl mb-3 border flex items-center justify-between {{ $col['headerBg'] }}">
                        <div class="flex items-center gap-2">
                            <i data-lucide="{{ $col['icon'] }}" class="w-4 h-4"></i>
                            <h2 class="font-heading font-bold text-xs">{{ $col['title'] }}</h2>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold {{ $col['badgeBg'] }}">
                            {{ $leadsInCol->count() }}
                        </span>
                    </div>

                    <!-- Column Total Amount -->
                    <div class="px-2 pb-2 mb-2 flex items-center justify-between text-[11px] text-slate-500 font-semibold border-b border-slate-200/60">
                        <span>Column Total:</span>
                        <span class="font-bold text-navy">₱{{ number_format($colTotal, 2) }}</span>
                    </div>

                    <!-- Cards Container (Scrollable) -->
                    <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                        @forelse($leadsInCol as $lead)
                            <div class="bg-white rounded-2xl p-4 border border-gray-200 shadow-xs hover:shadow-md hover:border-primary/40 transition-all flex flex-col justify-between gap-3 group relative">
                                <!-- Top Row: Ref & Priority -->
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[10px] font-mono font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-100">
                                        {{ $lead->reference_code }}
                                    </span>
                                    
                                    @php
                                        $priorityColors = [
                                            'urgent' => 'bg-rose-100 text-rose-800 border-rose-200',
                                            'high' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'medium' => 'bg-sky-100 text-sky-800 border-sky-200',
                                            'low' => 'bg-slate-100 text-slate-700 border-slate-200',
                                        ];
                                    @endphp
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border {{ $priorityColors[$lead->priority] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($lead->priority) }}
                                    </span>
                                </div>

                                <!-- Deal Title & Service Type -->
                                <div>
                                    <span class="inline-block text-[10px] font-bold text-primary mb-1 uppercase tracking-wider">
                                        {{ $lead->service_label }}
                                    </span>
                                    <h4 class="font-heading font-bold text-xs text-dark leading-snug line-clamp-2">
                                        {{ $lead->title }}
                                    </h4>
                                    @if($lead->destination)
                                        <div class="flex items-center gap-1 text-[11px] text-slate-500 mt-1">
                                            <i data-lucide="map-pin" class="w-3 h-3 text-accent shrink-0"></i>
                                            <span class="truncate">{{ $lead->destination }}</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Client Contact & Pax -->
                                <div class="bg-slate-50/80 rounded-xl p-2.5 border border-slate-100 space-y-1.5 text-[11px]">
                                    <div class="flex items-center justify-between">
                                        <div class="font-bold text-navy truncate max-w-[170px]" title="{{ $lead->client_name }}">
                                            {{ $lead->client_name }}
                                        </div>
                                        <div class="flex items-center gap-1.5 text-slate-400">
                                            @if($lead->client_phone)
                                                <a href="tel:{{ $lead->client_phone }}" class="hover:text-primary transition-colors" title="Call {{ $lead->client_phone }}">
                                                    <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                                                </a>
                                            @endif
                                            @if($lead->client_email)
                                                <a href="mailto:{{ $lead->client_email }}" class="hover:text-primary transition-colors" title="Email {{ $lead->client_email }}">
                                                    <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between text-slate-500 text-[10px]">
                                        <span>{{ $lead->number_of_pax }} Pax</span>
                                        <span>{{ $lead->travel_date ? $lead->travel_date->format('M d, Y') : 'Date TBD' }}</span>
                                    </div>
                                </div>

                                <!-- Deal Value & Source Tag -->
                                <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                                    <div class="font-heading font-extrabold text-sm text-emerald-700">
                                        {{ $lead->formatted_value }}
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-medium">
                                        via {{ $lead->source_label }}
                                    </span>
                                </div>

                                <!-- Bottom Action Bar: Move Stage & Add Note -->
                                <div class="flex items-center justify-between gap-1 pt-1">
                                    <!-- Stage Selector Form -->
                                    <form action="{{ route('admin.crm.leads.stage', $lead) }}" method="POST" class="flex-1">
                                        @csrf
                                        <select name="stage" 
                                                onchange="this.form.submit()" 
                                                class="w-full text-[10px] font-bold py-1 px-2 rounded-lg border border-slate-200 bg-white text-slate-700 hover:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer">
                                            @foreach(\App\Models\CrmLead::STAGES as $sKey => $s)
                                                <option value="{{ $sKey }}" {{ $lead->stage === $sKey ? 'selected' : '' }}>
                                                    Move: {{ $s['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>

                                    <!-- Add Note Button -->
                                    <button @click="openAddNote({{ $lead->id }}, '{{ $lead->reference_code }}', '{{ addslashes($lead->client_name) }}')"
                                            type="button" 
                                            class="p-1 rounded-lg text-slate-400 hover:text-navy hover:bg-slate-100 transition-colors"
                                            title="Add follow-up note or call log">
                                        <i data-lucide="message-square-plus" class="w-4 h-4"></i>
                                    </button>

                                    <!-- Quick Source Deep-Link -->
                                    @if($lead->source_type === \App\Models\CustomPackageInquiry::class && $lead->source_id)
                                        <a href="{{ route('admin.packages.custom-inquiries.show', $lead->source_id) }}" 
                                           class="p-1 rounded-lg text-slate-400 hover:text-primary hover:bg-slate-100 transition-colors" 
                                           title="Open Custom Package Configurator file">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center text-slate-400">
                                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p class="text-xs font-medium">No deals in this stage</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- VIEW 2: TABLE / LIST VIEW -->
    @if($viewMode === 'table')
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-gray-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="py-3.5 px-4">Deal Reference</th>
                        <th class="py-3.5 px-4">Client Contact</th>
                        <th class="py-3.5 px-4">Service &amp; Destination</th>
                        <th class="py-3.5 px-4">Est. Value</th>
                        <th class="py-3.5 px-4">Stage</th>
                        <th class="py-3.5 px-4">Priority</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-slate-700">
                    @forelse($tableLeads as $lead)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4">
                                <span class="font-mono font-bold text-navy">{{ $lead->reference_code }}</span>
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $lead->created_at->format('M d, Y') }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-dark">{{ $lead->client_name }}</div>
                                <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                    @if($lead->client_phone)
                                        <a href="tel:{{ $lead->client_phone }}" class="hover:text-primary">{{ $lead->client_phone }}</a>
                                    @endif
                                    @if($lead->client_email)
                                        <span class="text-slate-300">•</span>
                                        <a href="mailto:{{ $lead->client_email }}" class="hover:text-primary">{{ $lead->client_email }}</a>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-900">{{ $lead->title }}</div>
                                <div class="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
                                    <span class="text-primary font-bold">{{ $lead->service_label }}</span>
                                    @if($lead->destination)
                                        <span>• {{ $lead->destination }}</span>
                                    @endif
                                    <span>• {{ $lead->number_of_pax }} Pax</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-heading font-bold text-emerald-700 text-sm">
                                    {{ $lead->formatted_value }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <form action="{{ route('admin.crm.leads.stage', $lead) }}" method="POST">
                                    @csrf
                                    <select name="stage" 
                                            onchange="this.form.submit()" 
                                            class="text-xs font-bold py-1 px-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 hover:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer">
                                        @foreach(\App\Models\CrmLead::STAGES as $sKey => $s)
                                            <option value="{{ $sKey }}" {{ $lead->stage === $sKey ? 'selected' : '' }}>
                                                {{ $s['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $priorityColors = [
                                        'urgent' => 'bg-rose-100 text-rose-800 border-rose-200',
                                        'high' => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'medium' => 'bg-sky-100 text-sky-800 border-sky-200',
                                        'low' => 'bg-slate-100 text-slate-700 border-slate-200',
                                    ];
                                @endphp
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border {{ $priorityColors[$lead->priority] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ ucfirst($lead->priority) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button @click="openAddNote({{ $lead->id }}, '{{ $lead->reference_code }}', '{{ addslashes($lead->client_name) }}')"
                                            type="button" 
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-navy hover:bg-slate-100 transition-colors"
                                            title="Add Note">
                                        <i data-lucide="message-square-plus" class="w-4 h-4"></i>
                                    </button>

                                    @if($lead->source_type === \App\Models\CustomPackageInquiry::class && $lead->source_id)
                                        <a href="{{ route('admin.packages.custom-inquiries.show', $lead->source_id) }}" 
                                           class="p-1.5 rounded-lg text-slate-500 hover:text-primary hover:bg-slate-100 transition-colors"
                                           title="View Custom Package Inquiry">
                                            <i data-lucide="external-link" class="w-4 h-4"></i>
                                        </a>
                                    @endif

                                    <form action="{{ route('admin.crm.leads.destroy', $lead) }}" 
                                          method="POST" 
                                          onsubmit="return confirm('Are you sure you want to delete this CRM lead?');" 
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Lead">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                                <p class="text-sm font-semibold">No CRM leads found matching your criteria</p>
                                <p class="text-xs text-slate-400 mt-1">Try clearing filters or click "+ New Lead / Deal" to create one.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tableLeads->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $tableLeads->links() }}
            </div>
        @endif
    </div>
    @endif

    <!-- MODAL 1: CREATE NEW LEAD / DEAL -->
    <div x-show="openNewLeadModal" 
         style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
        
        <div @click.away="openNewLeadModal = false" 
             class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-gray-100 space-y-5">
            
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <div>
                    <h3 class="font-heading text-lg font-bold text-dark">Add New Lead / Deal</h3>
                    <p class="text-xs text-dark/60">Capture a new walk-in, phone call, or custom travel inquiry.</p>
                </div>
                <button @click="openNewLeadModal = false" type="button" class="text-slate-400 hover:text-dark p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="{{ route('admin.crm.leads.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Client Info Row -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Client Full Name *</label>
                        <input type="text" name="client_name" required placeholder="e.g. Maria Santos" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                        <input type="email" name="client_email" placeholder="maria@example.com" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Phone / Mobile</label>
                        <input type="text" name="client_phone" placeholder="+63 917 123 4567" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                </div>

                <!-- Deal Scope & Title -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Service Department *</label>
                        <select name="service_type" required class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                            @foreach(\App\Models\CrmLead::SERVICE_TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Lead Source *</label>
                        <select name="source" required class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                            @foreach(\App\Models\CrmLead::SOURCES as $key => $label)
                                <option value="{{ $key }}" {{ $key === 'walk_in' ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Deal Title / Travel Request *</label>
                    <input type="text" name="title" required placeholder="e.g. 5D4N Palawan Coron & El Nido Island Hopping" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>

                <!-- Destination, Date, Pax, Budget -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Destination</label>
                        <input type="text" name="destination" placeholder="e.g. Boracay" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Estimated Budget (₱)</label>
                        <input type="number" step="0.01" name="estimated_value" placeholder="45000" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Number of Pax</label>
                        <input type="number" min="1" name="number_of_pax" value="2" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Target Travel Date</label>
                        <input type="date" name="travel_date" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                </div>

                <!-- Stage, Priority, Assignee -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Initial Stage *</label>
                        <select name="stage" required class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                            @foreach(\App\Models\CrmLead::STAGES as $key => $s)
                                <option value="{{ $key }}">{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Priority *</label>
                        <select name="priority" required class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                            @foreach(\App\Models\CrmLead::PRIORITIES as $key => $p)
                                <option value="{{ $key }}" {{ $key === 'medium' ? 'selected' : '' }}>{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Assign to Agent</label>
                        <select name="assigned_to" class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                            <option value="">Unassigned</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }} ({{ ucfirst($agent->role) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Notes / Special Requests -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Client Preferences / Initial Notes</label>
                    <textarea name="notes" rows="2" placeholder="e.g. Needs beach-front hotel with breakfast, celebrating anniversary..." class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100">
                    <button @click="openNewLeadModal = false" type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary transition-all flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4 text-accent"></i>
                        <span>Save Lead</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: ADD FOLLOW-UP NOTE / CALL LOG -->
    <div x-show="openNoteModal" 
         style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
        
        <div @click.away="openNoteModal = false" 
             class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-gray-100 space-y-4">
            
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Log Follow-Up Activity</h3>
                    <p class="text-xs text-dark/60">
                        Lead: <span class="font-bold text-primary" x-text="noteLeadRef"></span> (<span x-text="noteLeadClient"></span>)
                    </p>
                </div>
                <button @click="openNoteModal = false" type="button" class="text-slate-400 hover:text-dark p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form :action="'{{ url('/admin/crm/leads') }}/' + noteLeadId + '/notes'" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Activity Type *</label>
                    <select name="action_type" required class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white font-medium">
                        <option value="call">📞 Phone Call Log</option>
                        <option value="email">✉️ Email Follow-Up</option>
                        <option value="meeting">🤝 Meeting / In-Person Discussion</option>
                        <option value="note" selected>📝 Internal Note / Update</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Details &amp; Client Feedback *</label>
                    <textarea name="content" required rows="4" placeholder="Client confirmed they prefer flight departure in the morning. Sent revised itinerary quote..." class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-gray-100">
                    <button @click="openNoteModal = false" type="button" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary transition-all flex items-center gap-1.5">
                        <i data-lucide="plus" class="w-4 h-4 text-accent"></i>
                        <span>Save Activity Log</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
