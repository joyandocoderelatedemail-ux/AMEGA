@extends('layouts.srrv')

@section('title', 'Retiree Files - AMEGA Travel and Tours')

@section('content')

    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
                <i data-lucide="folder-open" class="w-4 h-4"></i>
                <span>Retiree Files</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">SRRV Files</h1>
            <p class="text-sm text-dark/50 mt-1">Renewal applications and re-stamping jobs at the PRA.</p>
        </div>
        <a href="{{ route('srrv.applications.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>New Retiree File</span>
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        @foreach([
            ['label' => 'Total Files', 'value' => $stats['total'], 'icon' => 'folder-open'],
            ['label' => 'Classic', 'value' => $stats['classic'], 'icon' => 'badge-check'],
            ['label' => 'Courtesy', 'value' => $stats['courtesy'], 'icon' => 'shield-check'],
            ['label' => 'Open', 'value' => $stats['open'], 'icon' => 'loader-circle'],
            ['label' => 'Awaiting Oath', 'value' => $stats['awaitingOath'], 'icon' => 'user-check'],
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

    <form method="GET" action="{{ route('srrv.applications.index') }}"
          class="rounded-2xl bg-white border border-gray-100 shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2">
                <label for="search" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Search</label>
                <input id="search" type="text" name="search" value="{{ request('search') }}"
                       placeholder="Reference, retiree, email or SRRV card"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="visa_class" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Class</label>
                <select id="visa_class" name="visa_class"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Both classes</option>
                    @foreach(\App\Models\SrrvApplication::VISA_CLASSES as $class)
                        <option value="{{ $class }}" {{ request('visa_class') === $class ? 'selected' : '' }}>{{ ucfirst($class) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Status</label>
                <select id="status" name="status"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Any status</option>
                    @foreach([...\App\Models\SrrvApplication::SERVICE_STAGES['renewal_application'], 'cancelled'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ \App\Models\SrrvApplication::STAGE_LABELS[$status] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 mt-3 pt-3 border-t border-gray-100">
            @if(request()->hasAny(['search', 'visa_class', 'status']))
                <a href="{{ route('srrv.applications.index') }}"
                   class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Clear</a>
            @endif
            <button type="submit"
                    class="px-5 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                Apply Filters
            </button>
        </div>
    </form>

    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
        @if($applications->isEmpty())
            <div class="px-6 py-16 text-center">
                <i data-lucide="folder-open" class="w-9 h-9 text-dark/20 mx-auto mb-3"></i>
                <p class="text-sm font-bold text-dark/50">No retiree files match.</p>
                <p class="text-xs text-dark/40 mt-1">Open a new file to get started.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-dark/50">
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Retiree</th>
                            <th class="px-5 py-3">Job</th>
                            <th class="px-5 py-3">Class</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($applications as $application)
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-5 py-3">
                                    <a href="{{ route('srrv.applications.show', $application) }}"
                                       class="text-xs font-bold text-primary hover:underline">{{ $application->reference }}</a>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="text-xs font-semibold text-dark">{{ $application->retiree_name }}</div>
                                    @if($application->srrv_card_number)
                                        <div class="text-[10px] text-dark/45">Card {{ $application->srrv_card_number }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-[11px] text-dark/60">
                                    {{ $application->service_label }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->isCourtesy() ? 'bg-amber-100 text-amber-800' : 'bg-primary/10 text-primary' }}">
                                        {{ $application->visa_class }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider whitespace-nowrap {{ $application->status === 'released' ? 'bg-emerald-100 text-emerald-800' : ($application->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $application->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="text-xs font-heading font-extrabold text-dark whitespace-nowrap">
                                        {{ $application->currency }} {{ number_format($application->outstandingBalance(), 2) }}
                                    </div>
                                    <div class="text-[10px] text-dark/40 whitespace-nowrap">
                                        of {{ number_format((float) $application->service_fee, 2) }}
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('srrv.applications.show', $application) }}"
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
                <div class="px-5 py-4 border-t border-gray-100">{{ $applications->links() }}</div>
            @endif
        @endif
    </div>

@endsection
