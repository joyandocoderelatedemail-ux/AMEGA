@extends('layouts.immigration')

@section('title', 'Counter Dashboard - AMEGA Immigration')

{{-- The hub navigates through cards, so it skips the section tabs --}}
@section('is_hub')@endsection

@php
    /**
     * Same tokens as the ticketing dashboard: rounded-xl cards, rounded-lg
     * controls, weights 400/600/700, nothing below text-xs, slate neutrals.
     *
     * Headings deliberately omit `font-heading`: app.css forces weight 900 on
     * `h1.font-heading` / `h2.font-heading`, and the element selector already
     * gives them Montserrat.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $statLabel = 'text-sm font-semibold text-slate-600';
    $statFigure = 'font-heading text-2xl font-bold text-slate-900 tracking-tight tabular-nums';
    $statMeta = 'mt-1 text-sm text-slate-500';
    $cardLink = 'group '.$card.' p-5 flex items-start justify-between gap-3 hover:border-navy-300 hover:bg-navy-50/40 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';

    $tiles = [
        ['label' => 'Clients on file', 'value' => number_format($stats['clients']), 'icon' => 'users', 'note' => 'Searchable by passport'],
        ['label' => 'Expiring in 7 days', 'value' => number_format($stats['expiringSoon']), 'icon' => 'clock-alert', 'note' => 'Express window'],
        ['label' => 'Expired / penalty', 'value' => number_format($stats['flagged']), 'icon' => 'alert-octagon', 'note' => 'Marked on their sheet'],
        ['label' => 'Extensions this month', 'value' => number_format($stats['extensionsThisMonth']), 'icon' => 'stamp', 'note' => '₱'.number_format($collectedThisMonth, 0).' recorded'],
    ];

    $quickActions = [
        ['url' => route('admin.client-sheets.blank'), 'icon' => 'printer', 'label' => 'Print blank form', 'note' => 'For a walk-in to fill in', 'blank' => true],
        ['url' => route('admin.client-sheets.create'), 'icon' => 'user-plus', 'label' => 'Add a client', 'note' => 'Key in a handwritten sheet', 'blank' => false],
        ['url' => route('admin.client-sheets.index', ['flagged' => 1]), 'icon' => 'alert-octagon', 'label' => 'Flagged sheets', 'note' => $stats['flagged'].' expired or with penalty', 'blank' => false],
    ];
@endphp

@section('content')
<div class="space-y-6">

    <!-- Quick actions -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach ($quickActions as $action)
            <a href="{{ $action['url'] }}" @if ($action['blank']) target="_blank" @endif
               class="group {{ $card }} flex items-center gap-3 px-4 py-3 hover:border-navy-300 hover:bg-navy-50/40 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                    <i data-lucide="{{ $action['icon'] }}" class="w-4 h-4" aria-hidden="true"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900">{{ $action['label'] }}</span>
                    <span class="block text-xs text-slate-500 truncate">{{ $action['note'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <!-- Stats -->
    <section aria-label="Counter statistics" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($tiles as $tile)
            <div class="{{ $card }} p-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="{{ $statLabel }}">{{ $tile['label'] }}</span>
                    <i data-lucide="{{ $tile['icon'] }}" class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true"></i>
                </div>
                <div class="{{ $statFigure }} mt-2">{{ $tile['value'] }}</div>
                <div class="{{ $statMeta }}">{{ $tile['note'] }}</div>
            </div>
        @endforeach
    </section>

    <!-- Destinations -->
    <nav aria-label="Counter destinations" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <a href="{{ route('admin.client-sheets.index') }}" class="{{ $cardLink }}">
            <div class="flex items-start gap-3 min-w-0">
                <span class="w-10 h-10 rounded-lg bg-navy-700 text-white flex items-center justify-center shrink-0">
                    <i data-lucide="id-card" class="w-5 h-5" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900">Client Sheets</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Look a client up by passport, print their filled-in sheet, or start a record for a walk-in.
                    </p>
                    <p class="mt-2 text-sm text-slate-500">
                        <span class="font-semibold text-slate-700 tabular-nums">{{ number_format($stats['clients']) }}</span> clients on file
                    </p>
                </div>
            </div>
            <i data-lucide="arrow-right" class="w-4 h-4 text-slate-400 shrink-0 group-hover:text-navy-700 transition-colors" aria-hidden="true"></i>
        </a>

        <a href="{{ route('admin.immigration-pricing.index') }}" class="{{ $cardLink }}">
            <div class="flex items-start gap-3 min-w-0">
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                    <i data-lucide="receipt" class="w-5 h-5" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900">Immigration Pricing</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $pricing['publishedRows'] }} published rates
                        @if ($pricing['needsReview'] > 0)
                            &middot; <span class="font-semibold text-amber-700">{{ $pricing['needsReview'] }} to confirm</span>
                        @endif
                    </p>
                </div>
            </div>
            <i data-lucide="arrow-right" class="w-4 h-4 text-slate-400 shrink-0 group-hover:text-navy-700 transition-colors" aria-hidden="true"></i>
        </a>

        <a href="{{ route('admin.immigration-categories.index') }}" class="{{ $cardLink }}">
            <div class="flex items-start gap-3 min-w-0">
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                    <i data-lucide="layers" class="w-5 h-5" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900">Process Categories</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $pricing['categories'] }} active processes &amp; checklists</p>
                </div>
            </div>
            <i data-lucide="arrow-right" class="w-4 h-4 text-slate-400 shrink-0 group-hover:text-navy-700 transition-colors" aria-hidden="true"></i>
        </a>
    </nav>

    <!-- Reports -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

        <section class="lg:col-span-3 {{ $card }} overflow-hidden" aria-labelledby="needs-attention-heading">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                <div>
                    <h2 id="needs-attention-heading" class="text-base font-semibold text-slate-900">Needs Attention</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Lapsed visas first, then whoever runs out soonest.</p>
                </div>
                <a href="{{ route('admin.client-sheets.index', ['flagged' => 1]) }}"
                   class="text-sm font-semibold text-navy-700 hover:text-navy-900 shrink-0 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">View all</a>
            </div>

            @forelse ($needsAttention as $client)
                @php $band = $client->validity_band; @endphp
                <a href="{{ route('admin.client-sheets.edit', $client) }}"
                   class="flex items-center gap-4 px-5 py-3.5 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                    <span class="w-1.5 h-10 rounded-full shrink-0 {{ $band && $band['key'] === 'expired' ? 'bg-rose-500' : ($band && $band['key'] === 'express' ? 'bg-amber-500' : 'bg-emerald-500') }}" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-1.5">
                            <span class="font-semibold text-sm text-slate-900">{{ $client->full_name }}</span>
                            @include('admin.client-sheets.marks', ['client' => $client, 'size' => 'sm'])
                        </span>
                        <span class="block text-xs text-slate-500 font-mono mt-0.5">{{ $client->passport_number ?: 'No passport on file' }}</span>
                    </span>
                    @if ($band)
                        <span class="text-right shrink-0">
                            <span class="block text-sm font-semibold {{ $band['key'] === 'expired' ? 'text-rose-700' : ($band['key'] === 'express' ? 'text-amber-700' : 'text-emerald-700') }}">{{ $band['label'] }}</span>
                            <span class="block text-xs text-slate-500">{{ $band['detail'] }}</span>
                        </span>
                    @endif
                </a>
            @empty
                <div class="px-5 py-14 text-center">
                    <i data-lucide="check-circle-2" class="w-8 h-8 text-emerald-500 mx-auto mb-3" aria-hidden="true"></i>
                    <p class="text-sm font-semibold text-slate-900">Nothing needs chasing</p>
                    <p class="mt-1 text-sm text-slate-500">No lapsed visas, and nobody inside the 7-day express window.</p>
                </div>
            @endforelse
        </section>

        <section class="lg:col-span-2 {{ $card }} overflow-hidden" aria-labelledby="recent-extensions-heading">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 id="recent-extensions-heading" class="text-base font-semibold text-slate-900">Recent Extensions</h2>
                <p class="mt-0.5 text-sm text-slate-500">Latest rows added to client ledgers.</p>
            </div>

            @forelse ($recentExtensions as $extension)
                <div class="px-5 py-3.5 border-b border-slate-100 last:border-b-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-semibold text-sm text-slate-900 truncate">{{ $extension->client?->full_name ?? 'Deleted client' }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ $extension->ordinal }} extension &middot; {{ $extension->extension_date?->format('M j, Y') }}</div>
                            @if ($extension->details)
                                <div class="text-xs text-slate-400 mt-0.5 truncate">{{ $extension->details }}</div>
                            @endif
                        </div>
                        @if ($extension->amount_paid)
                            <div class="text-sm font-semibold text-slate-900 whitespace-nowrap tabular-nums">
                                ₱{{ number_format((float) $extension->amount_paid, 0) }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-14 text-center">
                    <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto mb-3" aria-hidden="true"></i>
                    <p class="text-sm text-slate-500">No extensions recorded yet.</p>
                </div>
            @endforelse
        </section>
    </div>

</div>
@endsection
