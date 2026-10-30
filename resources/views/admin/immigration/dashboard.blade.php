@extends('layouts.immigration')

@section('title', 'Counter Dashboard - AMEGA Immigration')

@php
    /**
     * An overview of every service the counter runs: what is open on each, what is filed and
     * waiting on the BI, and which visas need chasing. Ticketing portal tokens: rounded-xl
     * cards, rounded-lg controls, slate neutrals. Headings omit `font-heading` because app.css
     * forces weight 900 on `h1.font-heading`.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';

    $tiles = [
        ['label' => 'Clients', 'value' => $stats['clients'], 'url' => route('admin.immigration.cases', ['service' => 'folders'])],
        ['label' => 'Open cases', 'value' => $stats['open'], 'url' => route('admin.immigration.cases', ['status' => 'open'])],
        ['label' => 'Submitted', 'value' => $stats['submitted'], 'url' => route('admin.immigration.cases', ['status' => 'submitted'])],
        ['label' => 'Visas to chase', 'value' => $stats['attention'], 'url' => route('admin.immigration.cases', ['service' => 'folders', 'flagged' => 1])],
    ];

    // Blank forms to hand over at the counter.
    $blankForms = [
        ['Client information sheet', route('admin.client-sheets.blank')],
        ['Visa extension (CGAF)', route('admin.client-sheets.bi-blank', 'cgaf')],
        ['Extension checklist', route('admin.client-sheets.bi-blank', 'checklist')],
        ['Request for reconsideration', route('admin.client-sheets.reconsideration-blank')],
        ['Re-stamping form (IRD)', route('admin.client-sheets.restamping-blank', 'form')],
        ['Immigrant visa CGAF (13A)', route('admin.client-sheets.petition-blank', ['spouse', 'form'])],
        ['ACR I-Card application', route('admin.client-sheets.petition-blank', ['spouse', 'acr'])],
        ['ACR I-Card renewal form', route('admin.client-sheets.petition-blank', ['renewal', 'form'])],
        ['R.A. 9225 petition', route('admin.client-sheets.petition-blank', ['dual', 'form'])],
        ['Downgrading checklist', route('admin.client-sheets.petition-blank', ['downgrade', 'checklist'])],
        ['ECC application form', route('admin.client-sheets.petition-blank', ['ecc', 'form'])],
    ];
@endphp

@section('content')
<div class="space-y-6">

    <!-- Header: find a client, or start a service -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Immigration Counter</h1>

        <div class="flex flex-col sm:flex-row gap-2 lg:w-auto">
            <!-- Find a client: matches drop down as staff type; Enter opens the full results page -->
            <form method="GET" action="{{ route('admin.immigration.cases') }}" role="search" class="relative sm:w-96"
                  x-data="clientLookup({ lookupUrl: {{ Js::from(route('admin.client-sheets.lookup')) }}, resultsUrl: {{ Js::from(route('admin.immigration.cases')) }}, createUrl: {{ Js::from(route('admin.client-sheets.create')) }} })"
                  @click.outside="open = false" @keydown.escape="open = false"
                  @submit="if (highlighted >= 0) { $event.preventDefault(); go(items[highlighted].url); }">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-[1.4rem] -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="q" placeholder="Find a client: name or passport" autocomplete="off"
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

            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors {{ $focusRing }}">
                    <i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i>
                    Start a service
                    <i data-lucide="chevron-down" class="w-4 h-4" aria-hidden="true"></i>
                </button>
                <div x-show="open" x-cloak style="display: none" role="menu"
                     class="absolute right-0 z-30 mt-1.5 w-72 max-h-[70vh] overflow-y-auto rounded-xl bg-white border border-slate-200 shadow-lg p-1.5">
                    @foreach ($startable as $service)
                        <a href="{{ $service['url'] }}" role="menuitem"
                           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 focus-visible:outline-none focus-visible:bg-slate-50">
                            <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                                <i data-lucide="{{ $service['icon'] }}" class="w-4 h-4" aria-hidden="true"></i>
                            </span>
                            <span class="text-sm font-semibold text-slate-900">{{ $service['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Totals -->
    <section aria-label="Totals" class="rounded-xl border border-slate-200 shadow-sm bg-slate-200 grid grid-cols-2 lg:grid-cols-4 gap-px overflow-hidden">
        @foreach ($tiles as $tile)
            <a href="{{ $tile['url'] }}" class="block bg-white p-5 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                <div class="text-sm font-semibold text-slate-600">{{ $tile['label'] }}</div>
                <div class="font-heading text-2xl font-bold text-slate-900 tracking-tight tabular-nums mt-1.5">{{ number_format($tile['value']) }}</div>
            </a>
        @endforeach
    </section>

    <!-- Every service, with what is open on it -->
    <section aria-label="Services">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach ($services as $service)
                <a href="{{ $service['url'] }}" class="group {{ $card }} p-4 flex items-center gap-3 hover:border-navy-700/40 hover:shadow transition {{ $focusRing }}">
                    <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                        <i data-lucide="{{ $service['icon'] }}" class="w-5 h-5" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900 leading-snug">{{ $service['label'] }}</span>
                        <span class="block text-xs text-slate-500 mt-0.5">
                            <span class="font-semibold tabular-nums {{ $service['count'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $service['count'] }}</span> {{ $service['unit'] }}
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Open cases -->
        <section class="lg:col-span-2 {{ $card }} overflow-hidden" aria-labelledby="open-cases-heading">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                <h2 id="open-cases-heading" class="text-base font-semibold text-slate-900">Open Cases</h2>
                <a href="{{ route('admin.immigration.cases') }}" class="text-sm font-semibold text-navy-700 hover:text-navy-900 rounded {{ $focusRing }}">View all</a>
            </div>
            @forelse ($recentCases as $case)
                <a href="{{ $case['url'] }}" class="flex items-center gap-4 px-5 py-3.5 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-900 truncate">{{ $case['client']?->full_name ?? 'Deleted client' }}</span>
                        <span class="block text-xs text-slate-500 truncate">{{ $case['label'] }} &middot; <span class="font-mono">{{ $case['reference'] }}</span></span>
                    </span>
                    <span class="{{ \App\Support\CaseProgress::tone($case['progress']) }} inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border whitespace-nowrap">{{ $case['progress_label'] }}</span>
                </a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-500">No open cases.</p>
            @endforelse
        </section>

        <div class="space-y-6">
            <!-- Visas to chase -->
            <section class="{{ $card }} overflow-hidden" aria-labelledby="chase-heading">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                    <h2 id="chase-heading" class="text-base font-semibold text-slate-900">Visas to Chase</h2>
                    <a href="{{ route('admin.immigration.cases', ['service' => 'folders', 'flagged' => 1]) }}" class="text-sm font-semibold text-navy-700 hover:text-navy-900 rounded {{ $focusRing }}">View all</a>
                </div>
                @forelse ($needsAttention as $client)
                    @php $band = $client->validity_band; @endphp
                    <a href="{{ route('admin.client-sheets.edit', $client) }}" class="flex items-center gap-3 px-5 py-3 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                        <span class="w-1.5 h-8 rounded-full shrink-0 {{ $band && $band['key'] === 'expired' ? 'bg-rose-500' : 'bg-amber-500' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900 truncate">{{ $client->full_name }}</span>
                            @if ($band)
                                <span class="block text-xs {{ $band['key'] === 'expired' ? 'text-rose-700' : 'text-amber-700' }}">{{ $band['label'] }} &middot; {{ $band['detail'] }}</span>
                            @endif
                        </span>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">None.</p>
                @endforelse
            </section>

            <!-- Counter tools -->
            <nav aria-label="Counter tools" class="{{ $card }} overflow-hidden">
                @foreach ([
                    ['Extension Rates', 'receipt', route('admin.immigration-pricing.index'), $pricing['needsReview'] > 0 ? $pricing['needsReview'].' to confirm' : null],
                ] as [$toolLabel, $toolIcon, $toolUrl, $toolAlert])
                    <a href="{{ $toolUrl }}" class="group flex items-center gap-3 px-5 py-3 border-b border-slate-100 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                        <i data-lucide="{{ $toolIcon }}" class="w-4 h-4 text-slate-500 group-hover:text-navy-700" aria-hidden="true"></i>
                        <span class="flex-1 text-sm font-semibold text-slate-900">{{ $toolLabel }}</span>
                        @if ($toolAlert)<span class="text-xs font-semibold text-amber-700">{{ $toolAlert }}</span>@endif
                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400" aria-hidden="true"></i>
                    </a>
                @endforeach
                <details class="group">
                    <summary class="flex items-center gap-3 px-5 py-3 cursor-pointer list-none hover:bg-slate-50 transition-colors [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                        <i data-lucide="printer" class="w-4 h-4 text-slate-500" aria-hidden="true"></i>
                        <span class="flex-1 text-sm font-semibold text-slate-900">Blank forms</span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                    </summary>
                    <div class="border-t border-slate-100 py-1">
                        @foreach ($blankForms as [$formLabel, $formUrl])
                            <a href="{{ $formUrl }}" target="_blank" rel="noopener" class="block px-5 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-navy-700 transition-colors">{{ $formLabel }}</a>
                        @endforeach
                    </div>
                </details>
            </nav>
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
                    return config.resultsUrl + '?q=' + encodeURIComponent(term);
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
                        if (id === this.requestId && response.redirected) this.go(config.resultsUrl + '?q=' + encodeURIComponent(term));
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
