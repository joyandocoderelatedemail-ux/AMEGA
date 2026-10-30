@extends('layouts.visa-assistance')

@section('title', 'Non-Immigrant - AMEGA')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $primaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Non-Immigrant</h1>
            <p class="mt-1 text-sm text-slate-500">Clients with saved forms, by country. Open one to see their details and forms.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <form method="GET" action="{{ route('visa.tourist-visa.index') }}" role="search" class="relative sm:w-80">
                @foreach (['range', 'from', 'to'] as $kept)
                    @if (request()->filled($kept))<input type="hidden" name="{{ $kept }}" value="{{ request($kept) }}">@endif
                @endforeach
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search name or passport" aria-label="Search clients"
                       class="w-full h-10 pl-9 pr-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500">
            </form>
            <a href="{{ route('visa.visit.new') }}" class="{{ $primaryButton }} shrink-0">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>Add New Client</span>
            </a>
        </div>
    </div>
    <form method="GET" action="{{ route('visa.tourist-visa.index') }}">
        @if ($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
        <x-date-range label="Filed" />
    </form>

    @if ($folders->isEmpty() && $otherFiles->isEmpty())
        <div class="{{ $card }} flex flex-col items-center text-center px-6 py-16">
            <i data-lucide="folder-open" class="w-8 h-8 text-slate-300"></i>
            <p class="mt-3 text-sm font-semibold text-slate-900">{{ $search !== '' ? 'No client matches your search' : 'No clients yet' }}</p>
            <p class="mt-1 max-w-sm text-sm text-slate-500">
                {{ $search !== '' ? 'Try the passport number, or part of the name.' : 'Add a client to fill in their application form and checklist. Each client gets a folder.' }}
            </p>
            @if ($search === '')
                <a href="{{ route('visa.visit.new') }}" class="{{ $primaryButton }} mt-5">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Add the first client</span>
                </a>
            @endif
        </div>
    @else
        @if ($folders->isNotEmpty())
        <section class="space-y-3" aria-labelledby="stay-heading">
        <h2 id="stay-heading" class="text-sm font-bold text-slate-900">
            Philippines &middot; extension of stay
            <span class="font-normal text-slate-500">&middot; {{ number_format($folders->total()) }} {{ Str::plural('client', $folders->total()) }}{{ $search !== '' ? ' matching “'.$search.'”' : '' }}</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($folders as $folder)
                @php
                    $latest = $folder->latest;
                    $missing = count($latest->missingChecklist());
                @endphp
                <a href="{{ route('visa.tourist-visa.client', $folder->key) }}"
                   class="group {{ $card }} p-5 block hover:border-slate-300 hover:shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">
                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                            <i data-lucide="folder" class="w-5 h-5"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-slate-900 truncate">{{ $latest->formal_name }}</div>
                            <div class="mt-0.5 text-xs text-slate-500 truncate">{{ $latest->citizenship ?: '—' }} &middot; <span class="font-mono">{{ $latest->passport_number }}</span></div>
                        </div>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0 mt-1 group-hover:text-navy-700"></i>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-500">
                            <span class="font-semibold text-slate-700 tabular-nums">{{ $folder->count }}</span> {{ Str::plural('form', $folder->count) }}
                            &middot; filed {{ $latest->filed_on?->format('M j, Y') ?? '—' }}
                        </span>
                        @if ($missing === 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span> Complete
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset bg-amber-50 text-amber-800 ring-amber-600/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500" aria-hidden="true"></span> {{ $missing }} missing
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        @if ($folders->hasPages())
            <div>{{ $folders->links() }}</div>
        @endif
        </section>
        @endif

        @if ($otherFiles->isNotEmpty())
            <section class="space-y-3" aria-labelledby="other-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="other-heading" class="text-sm font-bold text-slate-900">
                        Other countries
                        <span class="font-normal text-slate-500">&middot; visit visa files</span>
                    </h2>
                    <a href="{{ route('visa.applications.index', ['service_type' => 'visit_visa']) }}" class="text-sm font-semibold text-navy-700 hover:text-navy-900">All visit visa files</a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($otherFiles as $file)
                        <a href="{{ route('visa.applications.show', $file) }}"
                           class="group {{ $card }} p-5 block hover:border-slate-300 hover:shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">
                            <div class="flex items-start gap-3">
                                <span class="w-11 h-11 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                                    <i data-lucide="folder" class="w-5 h-5"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-slate-900 truncate">{{ $file->client_name }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500 truncate">{{ $file->destination_country ?: 'No destination' }} &middot; <span class="font-mono">{{ $file->reference }}</span></div>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0 mt-1 group-hover:text-navy-700"></i>
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                                <span class="text-xs text-slate-500">Opened {{ $file->created_at?->format('M j, Y') }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset bg-navy-50 text-navy-700 ring-navy-700/20">{{ $file->status_label }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
