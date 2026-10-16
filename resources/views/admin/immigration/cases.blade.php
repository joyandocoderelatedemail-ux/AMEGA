@extends('layouts.immigration')

@section('title', 'Cases - AMEGA Admin')
@section('page_title', 'Cases')

@php
    use App\Support\ImmigrationCases;
    use App\Support\ImmigrationServices;

    $statusPill = [
        'released' => 'bg-emerald-100 text-emerald-700',
        'filed' => 'bg-primary/10 text-primary',
        'in_progress' => 'bg-amber-100 text-amber-800',
    ];
    $filter = fn (array $changes) => route('admin.immigration.cases', array_filter(array_merge(['service' => $kind, 'status' => $status === 'open' ? null : $status, 'q' => $search ?: null], $changes)));
    $chip = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-bold transition-colors';
@endphp

@section('content')
<div class="space-y-6">

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-5">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Service cases</h2>
            <p class="text-xs text-dark/50 mt-1">Every case opened from the Services menu, across clients. Visa Extension lives on each client's ledger, under Client Sheets.</p>
        </div>

        <form method="GET" action="{{ route('admin.immigration.cases') }}" class="flex flex-col sm:flex-row gap-3">
            @if ($kind) <input type="hidden" name="service" value="{{ $kind }}"> @endif
            @if ($status !== 'open') <input type="hidden" name="status" value="{{ $status }}"> @endif
            <div class="flex-1 relative">
                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-dark/40">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </span>
                <input type="search" name="q" value="{{ $search }}" autocomplete="off"
                       placeholder="Client name, passport, or a reference such as OS-2026-0014"
                       aria-label="Search cases"
                       class="w-full pl-12 pr-4 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <button type="submit" class="px-6 py-3 bg-primary text-white text-sm font-bold rounded-2xl hover:bg-primary-dark transition-all shadow-md">Search</button>
        </form>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/50 w-16">Service</span>
                <a href="{{ $filter(['service' => null]) }}" class="{{ $chip }} {{ $kind === null ? 'border-primary bg-primary text-white' : 'border-gray-200 text-dark/70 hover:border-primary' }}">All</a>
                @foreach ($kinds as $key => $kindInfo)
                    <a href="{{ $filter(['service' => $key]) }}" class="{{ $chip }} {{ $kind === $key ? 'border-primary bg-primary text-white' : 'border-gray-200 text-dark/70 hover:border-primary' }}">
                        <i data-lucide="{{ ImmigrationServices::find($kindInfo['service'])['icon'] }}" class="w-3.5 h-3.5"></i>
                        {{ $kindInfo['label'] }}
                        @if ($counts[$key]) <span class="{{ $kind === $key ? 'text-white/80' : 'text-dark/40' }}">{{ $counts[$key] }} open</span> @endif
                    </a>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/50 w-16">Status</span>
                @foreach (ImmigrationCases::STATUSES as $key => $statusLabel)
                    <a href="{{ $filter(['status' => $key === 'open' ? null : $key]) }}" class="{{ $chip }} {{ $status === $key ? 'border-primary bg-primary text-white' : 'border-gray-200 text-dark/70 hover:border-primary' }}">{{ $statusLabel }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-gray-200 shadow-card overflow-hidden">
        @if ($cases->isEmpty())
            <div class="p-8 text-center">
                <i data-lucide="folder-open" class="w-8 h-8 text-dark/30 mx-auto"></i>
                <p class="mt-3 text-sm font-bold text-dark">No cases match</p>
                <p class="text-xs text-dark/50 mt-1">
                    @if ($search) Nothing found for &ldquo;{{ $search }}&rdquo;. Check the spelling or the reference. @else Cases opened from the Services menu show up here. @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-5 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Reference</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Client</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Service</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Status</th>
                            <th class="px-3 py-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">Dates</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($cases as $case)
                            <tr class="hover:bg-gray-50" style="vertical-align: top">
                                <td class="px-5 py-3 font-mono text-xs font-bold text-primary whitespace-nowrap">{{ $case['reference'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="block text-xs font-bold text-dark">{{ $case['client']?->full_name ?? 'Unknown client' }}</span>
                                    <span class="block font-mono text-[11px] text-dark/50">{{ $case['client']?->passport_number }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="block text-xs font-semibold text-dark">{{ $case['label'] }}</span>
                                    <span class="block text-[11px] text-dark/50">{{ $case['summary'] }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider whitespace-nowrap {{ $statusPill[$case['status']] }}">{{ $case['status_label'] }}</span>
                                    @if ($case['status'] !== 'released' && $case['missing'])
                                        <span class="block mt-1 text-[11px] font-semibold text-amber-700">{{ $case['missing'] }} {{ \Illuminate\Support\Str::plural('requirement', $case['missing']) }} missing</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-[11px] text-dark/60 whitespace-nowrap">
                                    Opened {{ $case['opened']?->format('M d, Y') }}
                                    @if ($case['filed_on']) <br>Filed {{ $case['filed_on']->format('M d, Y') }} @endif
                                    @if ($case['released_on']) <br>Released {{ $case['released_on']->format('M d, Y') }} @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($case['url'])
                                        <a href="{{ $case['url'] }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline whitespace-nowrap">Open <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
