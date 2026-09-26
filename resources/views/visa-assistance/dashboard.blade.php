@extends('layouts.visa-assistance')

@section('title', 'Visa Assistance Counter - AMEGA Travel and Tours')

@php
    /**
     * Same tokens as the ticketing dashboard: rounded-xl cards, rounded-lg
     * controls, rounded-full pills, weights 400/600/700, nothing below text-xs.
     *
     * Headings deliberately omit `font-heading`: app.css forces weight 900 on
     * `h1.font-heading` / `h2.font-heading`, and the element selector already
     * gives them Montserrat.
     *
     * Layout, as on the immigration counter: a header with the counter's main
     * actions, one stat strip, then the work list beside the services and fees.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $focusRing = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
    $th = 'px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';

    $tiles = [
        ['label' => 'Total files', 'value' => $stats['total'], 'note' => 'All services', 'url' => route('visa.applications.index')],
        ['label' => 'Open', 'value' => $stats['open'], 'note' => 'Not yet released or cancelled', 'url' => null],
        ['label' => 'Rush files', 'value' => $stats['rush'], 'note' => '1-5 day turnaround', 'url' => null],
        ['label' => 'Released', 'value' => $stats['total'] - $stats['open'], 'note' => 'Finished or cancelled', 'url' => null],
    ];

    // Fees grouped by service, labelled without the repeated service prefix.
    $serviceNames = collect($services)->pluck('label', 'key');
    $feeGroups = $pricing->groupBy('service_type');
@endphp

@section('content')
<div class="space-y-6">

    <!-- Header: find a file, or open a new one -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Visa Assistance Counter</h1>
            <p class="mt-1 text-sm text-slate-500">Visit visa, e-Visa and passporting &middot; {{ now()->format('l, F j, Y') }}</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <!-- Find a client or file: matches drop down as staff type; Enter opens the file directory -->
            <form method="GET" action="{{ route('visa.applications.index') }}" role="search" class="relative sm:w-96"
                  x-data="visaLookup({ lookupUrl: {{ Js::from(route('visa.lookup')) }}, resultsUrl: {{ Js::from(route('visa.applications.index')) }}, createUrl: {{ Js::from(route('visa.applications.create')) }} })"
                  @click.outside="open = false" @keydown.escape="open = false"
                  @submit="if (highlighted >= 0) { $event.preventDefault(); go(items[highlighted].url); }">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-[1.4rem] -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="search" placeholder="Find a client or file: name, passport, reference" autocomplete="off"
                       x-model="term" @input.debounce.250ms="lookup()" @focus="open = hasResults"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                       role="combobox" aria-autocomplete="list" aria-controls="visa-lookup-results" :aria-expanded="open"
                       aria-label="Find a client or counter file"
                       class="w-full pl-9 pr-3 py-2.5 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500">

                <div x-show="open" x-cloak style="display: none" id="visa-lookup-results" role="listbox"
                     class="absolute z-30 left-0 right-0 mt-1.5 bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden">
                    <template x-if="files.length">
                        <div>
                            <div class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-500">Counter files</div>
                            <template x-for="(item, i) in files" :key="'f' + i">
                                <a :href="item.url" role="option" :aria-selected="highlighted === i" @mouseenter="highlighted = i"
                                   class="flex items-center justify-between gap-3 px-4 py-2.5" :class="highlighted === i ? 'bg-slate-100' : ''">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900 truncate" x-text="item.name"></span>
                                        <span class="block text-xs text-slate-500 truncate capitalize"
                                              x-text="[item.reference, item.service, item.country, item.status].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="text-xs font-semibold text-navy-700 shrink-0">Open</span>
                                </a>
                            </template>
                        </div>
                    </template>

                    <template x-if="clients.length">
                        <div :class="files.length ? 'border-t border-slate-100' : ''">
                            <div class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-500">Registered clients</div>
                            <template x-for="(item, i) in clients" :key="'c' + i">
                                <a :href="item.url" role="option" :aria-selected="highlighted === files.length + i" @mouseenter="highlighted = files.length + i"
                                   class="flex items-center justify-between gap-3 px-4 py-2.5" :class="highlighted === files.length + i ? 'bg-slate-100' : ''">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900 truncate" x-text="item.name"></span>
                                        <span class="block text-xs text-slate-500 truncate"
                                              x-text="[item.passport ? 'Passport ' + item.passport : null, item.email, item.phone].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="text-xs font-semibold text-navy-700 shrink-0">New file</span>
                                </a>
                            </template>
                        </div>
                    </template>

                    <p x-show="!hasResults" class="px-4 py-3 text-sm text-slate-500">
                        No client or file matches &ldquo;<span x-text="term.trim()"></span>&rdquo;.
                    </p>

                    <a :href="hasResults ? resultsUrl() : createUrl" class="block px-4 py-2.5 border-t border-slate-100 text-xs font-semibold text-navy-700 hover:bg-slate-50"
                       x-text="hasResults ? 'Search all counter files for “' + term.trim() + '”' : 'Open a new counter file'"></a>
                </div>
            </form>
            <a href="{{ route('visa.applications.create') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors {{ $focusRing }}">
                <i data-lucide="file-plus" class="w-4 h-4" aria-hidden="true"></i>
                New counter file
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
                    <div class="font-heading text-2xl font-bold text-slate-900 tracking-tight tabular-nums mt-1.5">{{ number_format($tile['value']) }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ $tile['note'] }}</div>
            @if ($tile['url'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </section>

    <!-- Work list beside the services and the fee list -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Recent files -->
        <section class="lg:col-span-2 {{ $card }} overflow-hidden" aria-labelledby="recent-files-heading">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                <div>
                    <h2 id="recent-files-heading" class="text-base font-semibold text-slate-900">Recent Counter Files</h2>
                    <p class="mt-0.5 text-sm text-slate-500">The latest files opened at the counter.</p>
                </div>
                <a href="{{ route('visa.applications.index') }}"
                   class="text-sm font-semibold text-navy-700 hover:text-navy-900 shrink-0 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">View all</a>
            </div>

            @if ($recentApplications->isEmpty())
                <div class="px-5 py-14 text-center">
                    <i data-lucide="folder-open" class="w-8 h-8 text-slate-300 mx-auto mb-3" aria-hidden="true"></i>
                    <p class="text-sm font-semibold text-slate-900">No counter files yet</p>
                    <p class="mt-1 text-sm text-slate-500">Visit visa, e-Visa and passporting files will appear here.</p>
                    <a href="{{ route('visa.applications.create') }}" class="inline-block mt-4 text-sm font-semibold text-navy-700 hover:text-navy-900">Open the first file</a>
                </div>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="{{ $th }}">Reference</th>
                                <th class="{{ $th }}">Client</th>
                                <th class="{{ $th }}">Service</th>
                                <th class="{{ $th }}">Status</th>
                                <th class="{{ $th }} w-10"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentApplications as $application)
                                @php
                                    // Every cell opens the file; only the reference is a tab stop.
                                    $fileUrl = route('visa.applications.show', $application);
                                    $cellLink = 'block px-4 py-3 text-sm';
                                @endphp
                                <tr class="group cursor-pointer hover:bg-slate-50 transition-colors">
                                    <td class="p-0 whitespace-nowrap">
                                        <a href="{{ $fileUrl }}" class="{{ $cellLink }} font-semibold text-navy-700 group-hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500"
                                           aria-label="Open file {{ $application->reference }} for {{ $application->client_name }}">{{ $application->reference }}</a>
                                    </td>
                                    <td class="p-0">
                                        <a href="{{ $fileUrl }}" tabindex="-1" aria-hidden="true" class="{{ $cellLink }} text-slate-700">{{ $application->client_name }}</a>
                                    </td>
                                    <td class="p-0">
                                        <a href="{{ $fileUrl }}" tabindex="-1" aria-hidden="true" class="{{ $cellLink }}">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-navy-50 text-navy-700 ring-1 ring-inset ring-navy-700/20 text-xs font-semibold whitespace-nowrap">
                                                {{ $serviceNames[$application->service_type] ?? str_replace('_', ' ', $application->service_type) }}
                                            </span>
                                        </a>
                                    </td>
                                    <td class="p-0">
                                        <a href="{{ $fileUrl }}" tabindex="-1" aria-hidden="true" class="{{ $cellLink }} text-slate-600">{{ $application->status_label }}</a>
                                    </td>
                                    <td class="p-0">
                                        <a href="{{ $fileUrl }}" tabindex="-1" aria-hidden="true" class="{{ $cellLink }} text-slate-400 group-hover:text-navy-700">
                                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="space-y-6">
            <!-- Services: open a file straight into the right service -->
            <nav aria-label="Counter services" class="{{ $card }} overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="text-base font-semibold text-slate-900">Services</h2>
                </div>
                @foreach ($services as $service)
                    <a href="{{ route('visa.applications.create', ['service_type' => $service['key']]) }}"
                       class="group flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                        <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                            <i data-lucide="{{ $service['icon'] }}" class="w-4 h-4" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-900">{{ $service['label'] }}</span>
                                <span class="text-xs text-slate-500 tabular-nums shrink-0">{{ $service['count'] }} {{ Str::plural('file', $service['count']) }}</span>
                            </span>
                            <span class="block text-xs text-slate-500">{{ $service['notes'][0] ?? $service['blurb'] }}</span>
                        </span>
                        <i data-lucide="plus" class="w-4 h-4 text-slate-400 shrink-0 group-hover:text-navy-700 transition-colors" aria-label="New {{ $service['label'] }} file"></i>
                    </a>
                @endforeach
            </nav>

            <!-- Published fees, compact: per person, grouped by service -->
            <section class="{{ $card }} overflow-hidden" aria-labelledby="fees-heading">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 id="fees-heading" class="text-base font-semibold text-slate-900">Published Fees</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Confirmed prices, per person.</p>
                </div>

                @if ($pricing->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <i data-lucide="receipt" class="w-7 h-7 text-slate-300 mx-auto mb-2" aria-hidden="true"></i>
                        <p class="text-sm text-slate-500">No confirmed fees published yet.</p>
                    </div>
                @else
                    @foreach ($feeGroups as $serviceType => $tiers)
                        <div class="{{ $loop->first ? '' : 'border-t border-slate-100' }}">
                            <div class="px-5 pt-3 pb-1 text-xs font-semibold text-slate-500">{{ $serviceNames[$serviceType] ?? str_replace('_', ' ', $serviceType) }}</div>
                            <ul class="pb-2">
                                @foreach ($tiers as $tier)
                                    <li class="flex items-baseline justify-between gap-3 px-5 py-1.5" @if ($tier->condition_notes) title="{{ $tier->condition_notes }}" @endif>
                                        <span class="text-sm text-slate-700 min-w-0 truncate">
                                            {{ $tier->country ?? trim(Str::after($tier->label, '—')) }}
                                            @if ($tier->processing_time)
                                                <span class="text-xs text-slate-400">&middot; {{ $tier->processing_time }}</span>
                                            @endif
                                        </span>
                                        <span class="text-sm font-semibold text-slate-900 whitespace-nowrap tabular-nums">
                                            {{ $tier->label === 'Visit Visa — Rush' ? '+' : '' }}{{ number_format((float) $tier->amount, 0) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <p class="px-5 py-2.5 border-t border-slate-100 text-xs text-slate-500">Amounts in PHP. Hover a row for its notes.</p>
                @endif
            </section>
        </div>
    </div>

</div>
<script>
    // Type-ahead for the visa counter: counter files first, then registered
    // clients to open a new file for. Arrow keys move through both lists;
    // Enter opens the highlighted match, or the file directory search.
    function visaLookup(config) {
        return {
            term: '',
            files: [],
            clients: [],
            open: false,
            highlighted: -1,
            requestId: 0,
            createUrl: config.createUrl,

            get items() {
                return [...this.files, ...this.clients];
            },

            get hasResults() {
                return this.items.length > 0;
            },

            resultsUrl() {
                return config.resultsUrl + '?search=' + encodeURIComponent(this.term.trim());
            },

            async lookup() {
                const term = this.term.trim();
                const id = ++this.requestId;
                this.highlighted = -1;

                if (term.length < 2) {
                    this.files = [];
                    this.clients = [];
                    this.open = false;
                    return;
                }

                try {
                    const response = await fetch(config.lookupUrl + '?q=' + encodeURIComponent(term), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });

                    // Signed out meanwhile: the directory page asks to log in.
                    if (response.redirected) {
                        if (id === this.requestId) this.go(this.resultsUrl());
                        return;
                    }
                    if (!response.ok) return;

                    const data = await response.json();
                    if (id !== this.requestId) return;

                    this.files = data.files || [];
                    this.clients = data.clients || [];
                    this.open = true;
                } catch (e) {
                    // Network hiccup: Enter still searches the file directory.
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
