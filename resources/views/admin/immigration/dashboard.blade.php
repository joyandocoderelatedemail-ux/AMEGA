@extends('layouts.immigration')

@section('title', 'Counter Dashboard - AMEGA Immigration')

{{-- The hub navigates through its own links, so it skips the section tabs --}}
@section('is_hub')@endsection

@php
    /**
     * Same tokens as the ticketing dashboard: rounded-xl cards, rounded-lg
     * controls, weights 400/600/700, nothing below text-xs, slate neutrals.
     *
     * Headings deliberately omit `font-heading`: app.css forces weight 900 on
     * `h1.font-heading` / `h2.font-heading`, and the element selector already
     * gives them Montserrat.
     *
     * Layout: a header with the counter's main job (find a client), one stat
     * strip, then the work list beside the counter tools and recent activity.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';

    $tiles = [
        ['label' => 'Clients on file', 'value' => number_format($stats['clients']), 'note' => 'Searchable by name or passport', 'url' => route('admin.client-sheets.index')],
        ['label' => 'Expiring in 7 days', 'value' => number_format($stats['expiringSoon']), 'note' => 'Express window', 'url' => null],
        ['label' => 'Expired / penalty', 'value' => number_format($stats['flagged']), 'note' => 'Or other attention required', 'url' => route('admin.client-sheets.index', ['flagged' => 1])],
        ['label' => 'Extensions this month', 'value' => number_format($stats['extensionsThisMonth']), 'note' => '₱'.number_format($collectedThisMonth, 0).' recorded', 'url' => null],
    ];

    $tools = [
        ['url' => route('admin.client-sheets.index'), 'icon' => 'id-card', 'label' => 'Client Sheets', 'note' => number_format($stats['clients']).' '.Str::plural('client', $stats['clients']).' on file'],
        ['url' => route('admin.immigration-pricing.index'), 'icon' => 'receipt', 'label' => 'Immigration Pricing', 'note' => $pricing['publishedRows'].' published rates', 'alert' => $pricing['needsReview'] > 0 ? $pricing['needsReview'].' to confirm' : null],
        ['url' => route('admin.immigration-categories.index'), 'icon' => 'layers', 'label' => 'Process Categories', 'note' => $pricing['categories'].' active processes & checklists'],
    ];
@endphp

@section('content')
<div class="space-y-6">

    <!-- Header: find a client, or start one -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Immigration Counter</h1>
            <p class="mt-1 text-sm text-slate-500">{{ now()->format('l, F j, Y') }}</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 lg:w-auto">
            <!-- Find a client: matches drop down as staff type; Enter opens the full results page -->
            <form method="GET" action="{{ route('admin.client-sheets.index') }}" role="search" class="relative sm:w-96"
                  x-data="clientLookup({ lookupUrl: {{ Js::from(route('admin.client-sheets.lookup')) }}, resultsUrl: {{ Js::from(route('admin.client-sheets.index')) }}, createUrl: {{ Js::from(route('admin.client-sheets.create')) }} })"
                  @click.outside="open = false" @keydown.escape="open = false"
                  @submit="if (highlighted >= 0) { $event.preventDefault(); go(items[highlighted].url); }">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-[1.4rem] -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="passport" placeholder="Find a client: name or passport" autocomplete="off"
                       x-model="term" @input.debounce.250ms="lookup()" @focus="open = hasResults"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                       role="combobox" aria-autocomplete="list" aria-controls="client-lookup-results" :aria-expanded="open"
                       aria-label="Find a client by name or passport"
                       class="w-full pl-9 pr-9 py-2.5 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500">
                <span x-show="loading" style="display: none" class="absolute right-3 top-[1.4rem] -translate-y-1/2 text-slate-400" aria-hidden="true">
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                </span>

                <div x-show="open" x-cloak style="display: none" id="client-lookup-results" role="listbox"
                     class="absolute z-30 left-0 right-0 mt-1.5 bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden">
                    <template x-if="sheets.length">
                        <div>
                            <div class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-500">Client sheets</div>
                            <template x-for="(item, i) in sheets" :key="'s' + i">
                                <a :href="item.url" role="option" :aria-selected="highlighted === i"
                                   @mouseenter="highlighted = i"
                                   class="flex items-center justify-between gap-3 px-4 py-2.5"
                                   :class="highlighted === i ? 'bg-slate-100' : ''">
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="block text-sm font-semibold text-slate-900 truncate" x-text="item.name"></span>
                                            <span x-show="item.flagged" class="shrink-0 px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-semibold">Flagged</span>
                                        </span>
                                        <span class="block text-xs text-slate-500 truncate" x-text="[item.passport ? 'Passport ' + item.passport : 'No passport on file', item.nationality].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="text-xs font-semibold text-navy-700 shrink-0">Open</span>
                                </a>
                            </template>
                        </div>
                    </template>

                    <template x-if="clients.length">
                        <div :class="sheets.length ? 'border-t border-slate-100' : ''">
                            <div class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-500">Registered clients &mdash; no sheet yet</div>
                            <template x-for="(item, i) in clients" :key="'c' + i">
                                <a :href="item.url" role="option" :aria-selected="highlighted === sheets.length + i"
                                   @mouseenter="highlighted = sheets.length + i"
                                   class="flex items-center justify-between gap-3 px-4 py-2.5"
                                   :class="highlighted === sheets.length + i ? 'bg-slate-100' : ''">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900 truncate" x-text="item.name"></span>
                                        <span class="block text-xs text-slate-500 truncate" x-text="[item.passport ? 'Passport ' + item.passport : null, item.contact].filter(Boolean).join(' · ') || 'No contact on file'"></span>
                                    </span>
                                    <span class="text-xs font-semibold text-navy-700 shrink-0">Create sheet</span>
                                </a>
                            </template>
                        </div>
                    </template>

                    <p x-show="!hasResults" class="px-4 py-3 text-sm text-slate-500">
                        No client matches &ldquo;<span x-text="term.trim()"></span>&rdquo;.
                    </p>

                    <a :href="allResultsUrl" class="block px-4 py-2.5 border-t border-slate-100 text-xs font-semibold text-navy-700 hover:bg-slate-50"
                       x-text="hasResults ? 'See all results for “' + term.trim() + '”' : 'Start a new client sheet'"></a>
                </div>
            </form>
            <a href="{{ route('admin.client-sheets.create') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors {{ $focusRing }}">
                <i data-lucide="user-plus" class="w-4 h-4" aria-hidden="true"></i>
                Add a client
            </a>
            <a href="{{ route('admin.client-sheets.blank') }}" target="_blank"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors {{ $focusRing }}">
                <i data-lucide="printer" class="w-4 h-4" aria-hidden="true"></i>
                Print blank form
            </a>
        </div>
    </div>

    <!-- Stats: one strip rather than four loose boxes -->
    <section aria-label="Counter statistics"
             class="rounded-xl border border-slate-200 shadow-sm bg-slate-200 grid grid-cols-2 lg:grid-cols-4 gap-px overflow-hidden">
        @foreach ($tiles as $tile)
            @php $cellClass = 'block bg-white p-5'; @endphp
            @if ($tile['url'])
                <a href="{{ $tile['url'] }}" class="{{ $cellClass }} hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
            @else
                <div class="{{ $cellClass }}">
            @endif
                    <div class="text-sm font-semibold text-slate-600">{{ $tile['label'] }}</div>
                    <div class="font-heading text-2xl font-bold text-slate-900 tracking-tight tabular-nums mt-1.5">{{ $tile['value'] }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ $tile['note'] }}</div>
            @if ($tile['url'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </section>

    <!-- Work list beside the counter tools and recent activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <section class="lg:col-span-2 {{ $card }} overflow-hidden" aria-labelledby="needs-attention-heading">
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
                        {{-- On phones the status sits under the name instead of squeezing beside it --}}
                        @if ($band)
                            <span class="sm:hidden block text-xs mt-1">
                                <span class="font-semibold {{ $band['key'] === 'expired' ? 'text-rose-700' : ($band['key'] === 'express' ? 'text-amber-700' : 'text-emerald-700') }}">{{ $band['label'] }}</span>
                                <span class="text-slate-500">&middot; {{ $band['detail'] }}</span>
                            </span>
                        @endif
                    </span>
                    @if ($band)
                        <span class="hidden sm:block text-right shrink-0">
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

        <div class="space-y-6">
            <!-- Counter tools -->
            <nav aria-label="Counter tools" class="{{ $card }} overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="text-base font-semibold text-slate-900">Counter Tools</h2>
                </div>
                @foreach ($tools as $tool)
                    <a href="{{ $tool['url'] }}"
                       class="group flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                        <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                            <i data-lucide="{{ $tool['icon'] }}" class="w-4 h-4" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900">{{ $tool['label'] }}</span>
                            <span class="block text-xs text-slate-500">
                                {{ $tool['note'] }}
                                @if (! empty($tool['alert']))
                                    &middot; <span class="font-semibold text-amber-700">{{ $tool['alert'] }}</span>
                                @endif
                            </span>
                        </span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 shrink-0 group-hover:text-navy-700 transition-colors" aria-hidden="true"></i>
                    </a>
                @endforeach
            </nav>

            <!-- Recent extensions -->
            <section class="{{ $card }} overflow-hidden" aria-labelledby="recent-extensions-heading">
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
                    <div class="px-5 py-10 text-center">
                        <i data-lucide="inbox" class="w-7 h-7 text-slate-300 mx-auto mb-2" aria-hidden="true"></i>
                        <p class="text-sm text-slate-500">No extensions recorded yet.</p>
                    </div>
                @endforelse
            </section>
        </div>
    </div>

</div>
<script>
    // Type-ahead for the counter search: sheets first, then registered clients
    // without a sheet. Arrow keys move through both lists; Enter opens the
    // highlighted match, or the full results page when nothing is highlighted.
    function clientLookup(config) {
        return {
            term: '',
            sheets: [],
            clients: [],
            open: false,
            loading: false,
            highlighted: -1,
            requestId: 0,

            get items() {
                return [...this.sheets, ...this.clients];
            },

            get hasResults() {
                return this.items.length > 0;
            },

            get allResultsUrl() {
                const term = this.term.trim();
                if (this.hasResults) {
                    return config.resultsUrl + '?passport=' + encodeURIComponent(term);
                }
                // Nothing on file: start a sheet with what was typed, as a passport or a name.
                return config.createUrl + '?' + (/\d/.test(term) ? 'passport=' : 'name=') + encodeURIComponent(term);
            },

            async lookup() {
                const term = this.term.trim();
                const id = ++this.requestId;
                this.highlighted = -1;

                if (term.length < 2) {
                    this.sheets = [];
                    this.clients = [];
                    this.open = false;
                    this.loading = false;
                    return;
                }

                this.loading = true;

                try {
                    const response = await fetch(config.lookupUrl + '?q=' + encodeURIComponent(term), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });

                    // Signed out meanwhile: fall back to the full results page, which asks to log in.
                    if (response.redirected || !response.ok) {
                        if (id === this.requestId && response.redirected) this.go(config.resultsUrl + '?passport=' + encodeURIComponent(term));
                        return;
                    }

                    const data = await response.json();
                    if (id !== this.requestId) return;

                    this.sheets = data.sheets || [];
                    this.clients = data.clients || [];
                    this.open = true;
                } catch (e) {
                    // Network hiccup: leave the last results; Enter still opens the results page.
                } finally {
                    if (id === this.requestId) this.loading = false;
                }
            },

            move(step) {
                if (!this.open || !this.hasResults) return;
                // Cycle through the matches and back to the input (-1).
                const slots = this.items.length + 1;
                this.highlighted = ((this.highlighted + 1 + step) % slots + slots) % slots - 1;
            },

            go(url) {
                window.location.href = url;
            },
        };
    }
</script>
@endsection
