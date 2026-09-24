@extends('layouts.admin')

@section('title', 'Lead Details: ' . $lead->reference_code . ' - AMEGA Admin')
@section('page_title', 'Lead Details')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-primary mb-1">
                <a href="{{ route('admin.crm.index') }}" class="hover:underline flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>CRM Pipeline</span>
                </a>
                <span>/</span>
                <span class="font-mono text-slate-500">{{ $lead->reference_code }}</span>
            </div>
            <h1 class="font-heading text-2xl font-bold text-dark">{{ $lead->title }}</h1>
            <p class="text-xs text-dark/60 mt-1">Client: <span class="font-bold text-dark">{{ $lead->client_name }}</span> • Created {{ $lead->created_at->format('M d, Y h:i A') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl font-bold text-xs border {{ $lead->priority === 'urgent' ? 'bg-rose-50 text-rose-700 border-rose-200' : ($lead->priority === 'high' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-700 border-slate-200') }}">
                {{ ucfirst($lead->priority) }} Priority
            </span>
            <span class="px-3 py-1.5 rounded-xl font-bold text-xs bg-navy text-white">
                Stage: {{ $lead->stage_details['label'] }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3 shadow-xs">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Lead Information & Note Logs -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Travel & Deal Information Card -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
                <h3 class="font-heading text-base font-bold text-dark flex items-center gap-2">
                    <i data-lucide="compass" class="w-5 h-5 text-primary"></i>
                    <span>Deal Specifications</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block mb-0.5">Service Type</span>
                        <span class="font-bold text-navy">{{ $lead->service_label }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block mb-0.5">Estimated Value</span>
                        <span class="font-bold text-emerald-700 font-heading text-sm">{{ $lead->formatted_value }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block mb-0.5">Destination</span>
                        <span class="font-bold text-slate-800">{{ $lead->destination ?: 'Not specified' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block mb-0.5">Target Travel Date</span>
                        <span class="font-bold text-slate-800">{{ $lead->travel_date ? $lead->travel_date->format('F d, Y') : 'Date TBD' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block mb-0.5">Party Size</span>
                        <span class="font-bold text-slate-800">{{ $lead->number_of_pax }} Pax</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-slate-400 block mb-0.5">Lead Acquisition Source</span>
                        <span class="font-bold text-slate-800">{{ $lead->source_label }}</span>
                    </div>
                </div>

                @if($lead->notes)
                    <div class="pt-2">
                        <span class="text-xs font-bold text-slate-500 block mb-1">Initial Requirements / Preferences:</span>
                        <div class="p-4 bg-amber-50/50 rounded-2xl border border-amber-100 text-xs text-amber-950 whitespace-pre-line">
                            {{ $lead->notes }}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Follow-up Activity & Notes Timeline -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
                <h3 class="font-heading text-base font-bold text-dark flex items-center gap-2">
                    <i data-lucide="history" class="w-5 h-5 text-primary"></i>
                    <span>Activity History &amp; Internal Notes</span>
                </h3>

                <!-- Add Note Form -->
                <form action="{{ route('admin.crm.leads.notes', $lead) }}" method="POST" class="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    @csrf
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold text-slate-700">Add Log / Note:</span>
                        <select name="action_type" required class="text-xs py-1 px-2.5 rounded-lg border border-slate-200 bg-white font-medium">
                            <option value="call">📞 Phone Call</option>
                            <option value="email">✉️ Email</option>
                            <option value="meeting">🤝 Meeting</option>
                            <option value="note" selected>📝 Internal Note</option>
                        </select>
                    </div>

                    <textarea name="content" required rows="2" placeholder="Record discussion with client, travel requirement updates, or quotation progress..." class="w-full text-xs px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white"></textarea>

                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary transition-all">
                            Post Activity Log
                        </button>
                    </div>
                </form>

                <!-- Activity Timeline Stream -->
                <div class="space-y-3 pt-2">
                    @forelse($lead->notes as $note)
                        <div class="p-3.5 rounded-2xl border border-slate-100 bg-white shadow-2xs flex items-start gap-3 text-xs">
                            @php
                                $iconMap = [
                                    'call' => 'phone-call',
                                    'email' => 'mail',
                                    'meeting' => 'users',
                                    'status_change' => 'arrow-right-circle',
                                    'note' => 'message-square',
                                ];
                                $colorMap = [
                                    'call' => 'bg-amber-50 text-amber-600',
                                    'email' => 'bg-blue-50 text-blue-600',
                                    'meeting' => 'bg-purple-50 text-purple-600',
                                    'status_change' => 'bg-emerald-50 text-emerald-600',
                                    'note' => 'bg-slate-100 text-slate-600',
                                ];
                            @endphp
                            <div class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center {{ $colorMap[$note->action_type] ?? 'bg-slate-100 text-slate-600' }}">
                                <i data-lucide="{{ $iconMap[$note->action_type] ?? 'message-square' }}" class="w-4 h-4"></i>
                            </div>

                            <div class="flex-1 space-y-1">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-navy">
                                        {{ $note->user ? $note->user->name : 'Staff Member' }}
                                        <span class="font-normal text-slate-400">({{ ucfirst(str_replace('_', ' ', $note->action_type)) }})</span>
                                    </span>
                                    <span class="text-slate-400">{{ $note->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="text-slate-700 whitespace-pre-line leading-relaxed">
                                    {{ $note->content }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-slate-400 text-xs">
                            No follow-up notes recorded yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Client Contact & Stage Management -->
        <div class="space-y-6">
            <!-- Client Contact Card -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
                <h3 class="font-heading text-base font-bold text-dark flex items-center gap-2">
                    <i data-lucide="user-check" class="w-5 h-5 text-primary"></i>
                    <span>Client Profile</span>
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[11px]">Full Name</span>
                        <span class="font-bold text-slate-900 text-sm">{{ $lead->client_name }}</span>
                    </div>

                    @if($lead->client_email)
                        <div>
                            <span class="text-slate-400 block text-[11px]">Email Address</span>
                            <a href="mailto:{{ $lead->client_email }}" class="font-bold text-primary hover:underline flex items-center gap-1.5 mt-0.5">
                                <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                                <span>{{ $lead->client_email }}</span>
                            </a>
                        </div>
                    @endif

                    @if($lead->client_phone)
                        <div>
                            <span class="text-slate-400 block text-[11px]">Contact Number</span>
                            <a href="tel:{{ $lead->client_phone }}" class="font-bold text-emerald-700 hover:underline flex items-center gap-1.5 mt-0.5">
                                <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                                <span>{{ $lead->client_phone }}</span>
                            </a>
                        </div>
                    @endif

                    <div>
                        <span class="text-slate-400 block text-[11px]">Assigned Consultant</span>
                        <span class="font-bold text-slate-800">{{ $lead->assignedUser ? $lead->assignedUser->name : 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            <!-- Advance Stage Card -->
            <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
                <h3 class="font-heading text-base font-bold text-dark flex items-center gap-2">
                    <i data-lucide="shuffle" class="w-5 h-5 text-primary"></i>
                    <span>Pipeline Stage</span>
                </h3>

                <form action="{{ route('admin.crm.leads.stage', $lead) }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Current Stage:</label>
                        <select name="stage" class="w-full text-xs font-bold px-3 py-2 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            @foreach(\App\Models\CrmLead::STAGES as $key => $s)
                                <option value="{{ $key }}" {{ $lead->stage === $key ? 'selected' : '' }}>
                                    {{ $s['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full py-2.5 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary transition-all flex items-center justify-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4 text-accent"></i>
                        <span>Update Stage</span>
                    </button>
                </form>

                @if($lead->source_type === \App\Models\CustomPackageInquiry::class && $lead->source_id)
                    <div class="pt-2 border-t border-slate-100">
                        <a href="{{ route('admin.packages.custom-inquiries.show', $lead->source_id) }}" class="w-full py-2 px-3 rounded-xl border border-primary/30 text-primary text-xs font-bold hover:bg-primary/5 transition-all flex items-center justify-center gap-1.5">
                            <i data-lucide="sliders" class="w-4 h-4"></i>
                            <span>Open Package Configurator</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
