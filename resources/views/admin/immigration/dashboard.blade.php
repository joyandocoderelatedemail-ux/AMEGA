@extends('layouts.immigration')

@section('title', 'Counter Dashboard - AMEGA Immigration')

@section('content')
<div class="space-y-8">

    <!-- Greeting -->
    <div>
        <h1 class="font-heading text-2xl sm:text-3xl font-black text-dark tracking-tight">
            Good day, {{ Str::before(Auth::user()->name, ' ') }}
        </h1>
        <p class="text-sm text-dark/50 mt-1">{{ now()->format('l, F j, Y') }} &mdash; here's where the counter stands.</p>
    </div>

    <!-- Stat row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $tiles = [
                ['label' => 'Clients on file', 'value' => number_format($stats['clients']), 'icon' => 'users', 'tone' => 'text-primary bg-primary/10', 'note' => 'Searchable by passport'],
                ['label' => 'Expiring within 7 days', 'value' => number_format($stats['expiringSoon']), 'icon' => 'clock-alert', 'tone' => 'text-amber-700 bg-amber-100', 'note' => 'Express processing window'],
                ['label' => 'Expired or with penalty', 'value' => number_format($stats['flagged']), 'icon' => 'alert-octagon', 'tone' => 'text-rose-700 bg-rose-100', 'note' => 'Marked on their sheet'],
                ['label' => 'Extensions this month', 'value' => number_format($stats['extensionsThisMonth']), 'icon' => 'stamp', 'tone' => 'text-emerald-700 bg-emerald-100', 'note' => '₱'.number_format($collectedThisMonth, 2).' recorded'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-sm">
                <div class="w-10 h-10 rounded-xl {{ $tile['tone'] }} flex items-center justify-center mb-3">
                    <i data-lucide="{{ $tile['icon'] }}" class="w-5 h-5"></i>
                </div>
                <div class="font-heading text-3xl font-black text-dark tabular-nums leading-none">{{ $tile['value'] }}</div>
                <div class="text-xs font-bold text-dark/70 mt-2">{{ $tile['label'] }}</div>
                <div class="text-[11px] text-dark/40 mt-0.5">{{ $tile['note'] }}</div>
            </div>
        @endforeach
    </div>

    <!-- Primary actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="{{ route('admin.client-sheets.index') }}"
           class="group bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm hover:shadow-lg hover:border-primary/30 transition-all flex items-start gap-5">
            <div class="w-14 h-14 rounded-2xl bg-primary text-white flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                <i data-lucide="id-card" class="w-7 h-7"></i>
            </div>
            <div class="min-w-0">
                <h2 class="font-heading text-lg font-bold text-dark flex items-center gap-2">
                    Client Sheets
                    <i data-lucide="arrow-right" class="w-4 h-4 text-primary group-hover:translate-x-1 transition-transform"></i>
                </h2>
                <p class="text-xs text-dark/60 mt-1.5 leading-relaxed">
                    Look a client up by passport, print their filled-in Client Information Sheet, or start a record for a walk-in.
                </p>
            </div>
        </a>

        <a href="{{ route('admin.immigration-pricing.index') }}"
           class="group bg-white rounded-3xl p-6 sm:p-7 border border-gray-100 shadow-sm hover:shadow-lg hover:border-primary/30 transition-all flex items-start gap-5">
            <div class="w-14 h-14 rounded-2xl bg-accent text-dark flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                <i data-lucide="receipt" class="w-7 h-7"></i>
            </div>
            <div class="min-w-0">
                <h2 class="font-heading text-lg font-bold text-dark flex items-center gap-2">
                    Immigration Pricing
                    <i data-lucide="arrow-right" class="w-4 h-4 text-primary group-hover:translate-x-1 transition-transform"></i>
                </h2>
                <p class="text-xs text-dark/60 mt-1.5 leading-relaxed">
                    {{ $pricing['publishedRows'] }} published rates across {{ $pricing['categories'] }} processes.
                    @if ($pricing['needsReview'] > 0)
                        <span class="text-amber-700 font-bold">{{ $pricing['needsReview'] }} still need confirmation.</span>
                    @endif
                </p>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- Needs attention -->
        <div class="lg:col-span-3 bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-heading text-base font-bold text-dark">Needs attention</h2>
                    <p class="text-[11px] text-dark/50 mt-0.5">Lapsed visas first, then whoever runs out soonest.</p>
                </div>
                <a href="{{ route('admin.client-sheets.index', ['flagged' => 1]) }}" class="text-[11px] font-bold text-primary hover:underline shrink-0">View all</a>
            </div>

            @forelse ($needsAttention as $client)
                @php $band = $client->validity_band; @endphp
                <a href="{{ route('admin.client-sheets.edit', $client) }}"
                   class="flex items-center gap-4 px-6 py-4 border-b border-gray-50 last:border-b-0 hover:bg-gray-50/70 transition-colors">
                    <div class="w-1.5 h-10 rounded-full shrink-0 {{ $band && $band['key'] === 'expired' ? 'bg-rose-500' : ($band && $band['key'] === 'express' ? 'bg-amber-500' : 'bg-emerald-500') }}"></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="font-bold text-sm text-dark">{{ $client->full_name }}</span>
                            @include('admin.client-sheets.marks', ['client' => $client, 'size' => 'sm'])
                        </div>
                        <div class="text-[11px] text-dark/50 font-mono mt-0.5">{{ $client->passport_number ?: 'No passport on file' }}</div>
                    </div>
                    @if ($band)
                        <div class="text-right shrink-0">
                            <div class="text-xs font-bold {{ $band['key'] === 'expired' ? 'text-rose-600' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">{{ $band['label'] }}</div>
                            <div class="text-[11px] text-dark/40">{{ $band['detail'] }}</div>
                        </div>
                    @endif
                </a>
            @empty
                <div class="px-6 py-14 text-center">
                    <i data-lucide="check-circle-2" class="w-9 h-9 text-emerald-400 mx-auto mb-2"></i>
                    <p class="text-sm font-bold text-dark">Nothing needs chasing</p>
                    <p class="text-[11px] text-dark/50 mt-1">No lapsed visas, and nobody inside the 7-day express window.</p>
                </div>
            @endforelse
        </div>

        <!-- Recent ledger activity -->
        <div class="lg:col-span-2 bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="font-heading text-base font-bold text-dark">Recent extensions</h2>
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
                <div class="px-6 py-14 text-center">
                    <i data-lucide="inbox" class="w-9 h-9 text-dark/25 mx-auto mb-2"></i>
                    <p class="text-[11px] text-dark/50">No extensions recorded yet.</p>
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
