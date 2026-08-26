@extends('layouts.immigration')

@section('title', 'Counter Dashboard - AMEGA Immigration')

{{-- The hub navigates through cards, so it skips the section switcher --}}
@section('is_hub')@endsection

@section('content')
<div class="space-y-10">

    <!-- Greeting -->
    <header class="pt-2">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary/70">{{ now()->format('l, F j, Y') }}</p>
        <h1 class="font-heading text-3xl sm:text-4xl font-black text-dark tracking-tight mt-2">
            Good day, {{ Str::before(Auth::user()->name, ' ') }}
        </h1>
        <p class="text-sm text-dark/50 mt-2 max-w-lg leading-relaxed">
            Here's where the counter stands today. Everything you need is one tap away.
        </p>
    </header>

    <!-- Stat strip -->
    <section aria-label="Counter statistics">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            @php
                $tiles = [
                    ['label' => 'Clients on file', 'value' => number_format($stats['clients']), 'icon' => 'users', 'accent' => 'bg-primary', 'note' => 'Searchable by passport'],
                    ['label' => 'Expiring in 7 days', 'value' => number_format($stats['expiringSoon']), 'icon' => 'clock-alert', 'accent' => 'bg-amber-500', 'note' => 'Express window'],
                    ['label' => 'Expired / penalty', 'value' => number_format($stats['flagged']), 'icon' => 'alert-octagon', 'accent' => 'bg-brand-red', 'note' => 'Marked on their sheet'],
                    ['label' => 'Extensions this month', 'value' => number_format($stats['extensionsThisMonth']), 'icon' => 'stamp', 'accent' => 'bg-accent-dark', 'note' => '₱'.number_format($collectedThisMonth, 0).' recorded'],
                ];
            @endphp

            @foreach ($tiles as $tile)
                <div class="relative bg-white rounded-2xl p-5 border border-gray-200/80 overflow-hidden">
                    <span class="absolute inset-x-0 top-0 h-1 {{ $tile['accent'] }}" aria-hidden="true"></span>
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-heading text-3xl sm:text-4xl font-black text-dark tabular-nums leading-none">{{ $tile['value'] }}</div>
                        <i data-lucide="{{ $tile['icon'] }}" class="w-4 h-4 text-dark/25 shrink-0 mt-1" aria-hidden="true"></i>
                    </div>
                    <div class="text-xs font-bold text-dark/80 mt-3 leading-snug">{{ $tile['label'] }}</div>
                    <div class="text-[11px] text-dark/40 mt-0.5">{{ $tile['note'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Navigation: bento grid replaces the nav bar -->
    <nav aria-label="Counter destinations">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <!-- Primary destination -->
            <a href="{{ route('admin.client-sheets.index') }}"
               class="group relative lg:col-span-2 overflow-hidden rounded-3xl bg-gradient-to-br from-primary via-primary to-primary-light text-white p-7 sm:p-9 min-h-[220px] flex flex-col justify-between
                      shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 transition-shadow duration-200 cursor-pointer
                      focus:outline-none focus-visible:ring-4 focus-visible:ring-accent focus-visible:ring-offset-2">

                <!-- Decorative watermark -->
                <i data-lucide="id-card" aria-hidden="true"
                   class="absolute -right-8 -bottom-10 w-56 h-56 text-white/10 pointer-events-none"></i>
                <span class="absolute -right-16 -top-24 w-64 h-64 rounded-full bg-accent/20 blur-3xl pointer-events-none" aria-hidden="true"></span>

                <div class="relative">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-sm text-[10px] font-bold uppercase tracking-widest">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                        Start here
                    </span>
                    <h2 class="font-heading text-2xl sm:text-3xl font-black tracking-tight mt-4">Client Sheets</h2>
                    <p class="text-sm text-white/70 mt-2 max-w-md leading-relaxed">
                        Look a client up by passport, print their filled-in sheet, or start a record for a walk-in.
                    </p>
                </div>

                <div class="relative flex items-end justify-between gap-4 mt-6">
                    <div>
                        <div class="font-heading text-2xl font-black tabular-nums leading-none">{{ number_format($stats['clients']) }}</div>
                        <div class="text-[11px] text-white/60 mt-1">clients on file</div>
                    </div>
                    <span class="w-12 h-12 rounded-full bg-white text-primary flex items-center justify-center shrink-0
                                 group-hover:bg-accent transition-colors duration-200">
                        <i data-lucide="arrow-right" class="w-5 h-5 transition-transform duration-200 group-hover:translate-x-0.5" aria-hidden="true"></i>
                    </span>
                </div>
            </a>

            <!-- Secondary destinations -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4">

                <a href="{{ route('admin.immigration-pricing.index') }}"
                   class="group relative overflow-hidden rounded-3xl bg-white border border-gray-200/80 p-6 flex flex-col justify-between min-h-[102px]
                          hover:border-accent hover:bg-accent/5 transition-colors duration-200 cursor-pointer
                          focus:outline-none focus-visible:ring-4 focus-visible:ring-accent focus-visible:ring-offset-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-accent text-dark flex items-center justify-center mb-3">
                                <i data-lucide="receipt" class="w-5 h-5" aria-hidden="true"></i>
                            </div>
                            <h2 class="font-heading text-base font-bold text-dark">Immigration Pricing</h2>
                            <p class="text-[11px] text-dark/50 mt-1 leading-relaxed">
                                {{ $pricing['publishedRows'] }} published rates
                                @if ($pricing['needsReview'] > 0)
                                    · <span class="text-amber-700 font-bold">{{ $pricing['needsReview'] }} to confirm</span>
                                @endif
                            </p>
                        </div>
                        <i data-lucide="arrow-up-right" aria-hidden="true"
                           class="w-4 h-4 text-dark/25 shrink-0 group-hover:text-dark transition-colors duration-200"></i>
                    </div>
                </a>

                <a href="{{ route('admin.immigration-categories.index') }}"
                   class="group relative overflow-hidden rounded-3xl bg-white border border-gray-200/80 p-6 flex flex-col justify-between min-h-[102px]
                          hover:border-primary hover:bg-primary/5 transition-colors duration-200 cursor-pointer
                          focus:outline-none focus-visible:ring-4 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-3">
                                <i data-lucide="layers" class="w-5 h-5" aria-hidden="true"></i>
                            </div>
                            <h2 class="font-heading text-base font-bold text-dark">Process Categories</h2>
                            <p class="text-[11px] text-dark/50 mt-1 leading-relaxed">
                                {{ $pricing['categories'] }} active processes &amp; checklists
                            </p>
                        </div>
                        <i data-lucide="arrow-up-right" aria-hidden="true"
                           class="w-4 h-4 text-dark/25 shrink-0 group-hover:text-dark transition-colors duration-200"></i>
                    </div>
                </a>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
            @php
                $quickActions = [
                    ['url' => route('admin.client-sheets.blank'), 'icon' => 'printer', 'label' => 'Print blank form', 'note' => 'For a walk-in to fill in', 'blank' => true],
                    ['url' => route('admin.client-sheets.create'), 'icon' => 'user-plus', 'label' => 'Add a client', 'note' => 'Key in a handwritten sheet', 'blank' => false],
                    ['url' => route('admin.client-sheets.index', ['flagged' => 1]), 'icon' => 'alert-octagon', 'label' => 'Flagged sheets', 'note' => $stats['flagged'].' expired or with penalty', 'blank' => false],
                ];
            @endphp

            @foreach ($quickActions as $action)
                <a href="{{ $action['url'] }}" @if ($action['blank']) target="_blank" @endif
                   class="group flex items-center gap-3.5 px-5 py-4 rounded-2xl bg-white border border-gray-200/80
                          hover:border-primary/40 hover:bg-primary/5 transition-colors duration-200 cursor-pointer
                          focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <span class="w-9 h-9 rounded-xl bg-gray-100 text-dark/60 flex items-center justify-center shrink-0
                                 group-hover:bg-primary group-hover:text-white transition-colors duration-200">
                        <i data-lucide="{{ $action['icon'] }}" class="w-4 h-4" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-bold text-dark">{{ $action['label'] }}</span>
                        <span class="block text-[11px] text-dark/45 truncate">{{ $action['note'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </nav>

    <!-- Reports -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

        <!-- Needs attention -->
        <section class="lg:col-span-3 bg-white rounded-3xl border border-gray-200/80 overflow-hidden" aria-labelledby="needs-attention-heading">
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <h2 id="needs-attention-heading" class="font-heading text-base font-bold text-dark">Needs attention</h2>
                    <p class="text-[11px] text-dark/50 mt-0.5">Lapsed visas first, then whoever runs out soonest.</p>
                </div>
                <a href="{{ route('admin.client-sheets.index', ['flagged' => 1]) }}"
                   class="text-[11px] font-bold text-primary hover:underline shrink-0 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded">View all</a>
            </div>

            @forelse ($needsAttention as $client)
                @php $band = $client->validity_band; @endphp
                <a href="{{ route('admin.client-sheets.edit', $client) }}"
                   class="flex items-center gap-4 px-6 py-4 border-b border-gray-50 last:border-b-0 hover:bg-gray-50 transition-colors duration-200 cursor-pointer
                          focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                    <span class="w-1.5 h-10 rounded-full shrink-0 {{ $band && $band['key'] === 'expired' ? 'bg-brand-red' : ($band && $band['key'] === 'express' ? 'bg-amber-500' : 'bg-emerald-500') }}" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-1.5">
                            <span class="font-bold text-sm text-dark">{{ $client->full_name }}</span>
                            @include('admin.client-sheets.marks', ['client' => $client, 'size' => 'sm'])
                        </span>
                        <span class="block text-[11px] text-dark/50 font-mono mt-0.5">{{ $client->passport_number ?: 'No passport on file' }}</span>
                    </span>
                    @if ($band)
                        <span class="text-right shrink-0">
                            <span class="block text-xs font-bold {{ $band['key'] === 'expired' ? 'text-brand-red' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">{{ $band['label'] }}</span>
                            <span class="block text-[11px] text-dark/40">{{ $band['detail'] }}</span>
                        </span>
                    @endif
                </a>
            @empty
                <div class="px-6 py-16 text-center">
                    <i data-lucide="check-circle-2" class="w-10 h-10 text-emerald-400 mx-auto mb-3" aria-hidden="true"></i>
                    <p class="text-sm font-bold text-dark">Nothing needs chasing</p>
                    <p class="text-[11px] text-dark/50 mt-1">No lapsed visas, and nobody inside the 7-day express window.</p>
                </div>
            @endforelse
        </section>

        <!-- Recent ledger activity -->
        <section class="lg:col-span-2 bg-white rounded-3xl border border-gray-200/80 overflow-hidden" aria-labelledby="recent-extensions-heading">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 id="recent-extensions-heading" class="font-heading text-base font-bold text-dark">Recent extensions</h2>
                <p class="text-[11px] text-dark/50 mt-0.5">Latest rows added to client ledgers.</p>
            </div>

            @forelse ($recentExtensions as $extension)
                <div class="px-6 py-4 border-b border-gray-50 last:border-b-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-bold text-sm text-dark truncate">{{ $extension->client?->full_name ?? 'Deleted client' }}</div>
                            <div class="text-[11px] text-dark/50 mt-0.5">{{ $extension->ordinal }} extension &middot; {{ $extension->extension_date?->format('M j, Y') }}</div>
                            @if ($extension->details)
                                <div class="text-[11px] text-dark/40 mt-0.5 truncate">{{ $extension->details }}</div>
                            @endif
                        </div>
                        @if ($extension->amount_paid)
                            <div class="font-heading font-black text-sm text-dark whitespace-nowrap tabular-nums">
                                ₱{{ number_format((float) $extension->amount_paid, 0) }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-6 py-16 text-center">
                    <i data-lucide="inbox" class="w-10 h-10 text-dark/20 mx-auto mb-3" aria-hidden="true"></i>
                    <p class="text-[11px] text-dark/50">No extensions recorded yet.</p>
                </div>
            @endforelse
        </section>
    </div>

</div>
@endsection
