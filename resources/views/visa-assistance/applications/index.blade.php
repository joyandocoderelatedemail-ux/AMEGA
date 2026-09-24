@extends('layouts.visa-assistance')

@section('title', 'Counter File Directory - AMEGA Travel and Tours')

@section('content')

    <!-- Page Heading -->
    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
                <i data-lucide="folder-open" class="w-4 h-4"></i>
                <span>Counter Files</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">File Directory</h1>
            <p class="text-sm text-dark/50 mt-1">Every visit visa, e-Visa and passporting file at the counter.</p>
        </div>
        <a href="{{ route('visa.applications.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>New Counter File</span>
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        @foreach([
            ['label' => 'Total Files', 'value' => $stats['total'], 'icon' => 'folder-open'],
            ['label' => 'Visit Visa', 'value' => $stats['visitVisas'], 'icon' => 'globe'],
            ['label' => 'e-Visa', 'value' => $stats['eVisas'], 'icon' => 'zap'],
            ['label' => 'Passporting', 'value' => $stats['passporting'], 'icon' => 'book-user'],
            ['label' => 'Open', 'value' => $stats['open'], 'icon' => 'loader-circle'],
        ] as $card)
            <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">{{ $card['label'] }}</span>
                    <i data-lucide="{{ $card['icon'] }}" class="w-4 h-4 text-primary"></i>
                </div>
                <div class="font-heading text-xl font-extrabold text-dark">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('visa.applications.index') }}"
          class="rounded-2xl bg-white border border-gray-100 shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2">
                <label for="search" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Search</label>
                <input id="search" type="text" name="search" value="{{ request('search') }}"
                       placeholder="Reference, client, email or destination"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="service_type" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Service</label>
                <select id="service_type" name="service_type"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">All services</option>
                    @foreach(\App\Models\VisaApplication::SERVICE_TYPES as $type)
                        <option value="{{ $type }}" {{ request('service_type') === $type ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Status</label>
                <select id="status" name="status"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Any status</option>
                    @foreach(\App\Models\VisaApplication::STATUSES as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 mt-3 pt-3 border-t border-gray-100">
            @if(request()->hasAny(['search', 'service_type', 'status']))
                <a href="{{ route('visa.applications.index') }}"
                   class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Clear</a>
            @endif
            <button type="submit"
                    class="px-5 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                Apply Filters
            </button>
        </div>
    </form>

    <!-- Results -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
        @if($applications->isEmpty())
            <div class="px-6 py-16 text-center">
                <i data-lucide="folder-open" class="w-9 h-9 text-dark/20 mx-auto mb-3"></i>
                <p class="text-sm font-bold text-dark/50">No counter files match.</p>
                <p class="text-xs text-dark/40 mt-1">Open a new file to get started.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-dark/50">
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Service</th>
                            <th class="px-5 py-3">Detail</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($applications as $application)
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-5 py-3">
                                    <a href="{{ route('visa.applications.show', $application) }}"
                                       class="text-xs font-bold text-primary hover:underline">{{ $application->reference }}</a>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="text-xs font-semibold text-dark">{{ $application->client_name }}</div>
                                    @if($application->client_phone)
                                        <div class="text-[10px] text-dark/45">{{ $application->client_phone }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">
                                        {{ str_replace('_', ' ', $application->service_type) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-[11px] text-dark/60">
                                    @if($application->service_type === 'visit_visa')
                                        {{ $application->destination_country ?? '—' }}
                                        <span class="text-dark/35">&middot;</span> {{ ucfirst((string) $application->purpose) }}
                                        @if($application->isRush())
                                            <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[9px] font-bold uppercase">Rush</span>
                                        @endif
                                        @if(! $application->needsAppearance())
                                            <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[9px] font-bold uppercase">No appearance</span>
                                        @endif
                                    @elseif($application->service_type === 'e_visa')
                                        {{ ucfirst($application->applicant_type) }}
                                        <span class="text-dark/35">&middot;</span> {{ $application->applicants_count }} pax
                                    @else
                                        {{ ucfirst((string) $application->passport_type) }} passport
                                        @if($application->embassy_country)
                                            <span class="text-dark/35">&middot;</span> {{ $application->embassy_country }}
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider whitespace-nowrap {{ $application->status === 'released' ? 'bg-emerald-100 text-emerald-800' : ($application->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                                        {{ str_replace('_', ' ', $application->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="text-xs font-heading font-extrabold text-dark whitespace-nowrap">
                                        {{ $application->currency }} {{ number_format($application->outstandingBalance(), 2) }}
                                    </div>
                                    <div class="text-[10px] text-dark/40 whitespace-nowrap">
                                        of {{ number_format((float) $application->total_amount, 2) }}
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('visa.applications.show', $application) }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors">
                                        <span>Open</span>
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($applications->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $applications->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection
