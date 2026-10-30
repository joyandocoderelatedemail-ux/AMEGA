@extends('layouts.visa-assistance')

@section('title', 'Visa Assistance Counter - AMEGA Travel and Tours')

@php
    /**
     * The ticketing workspace's tokens: rounded-xl cards, rounded-lg controls,
     * rounded-full pills; weights 400/600/700; nothing below text-xs. Each hue
     * means one thing: emerald = released, amber = waiting at the counter,
     * rose = cancelled, navy = in progress, slate = neutral.
     *
     * Headings deliberately omit `font-heading`: app.css forces weight 900 on
     * `h1.font-heading` / `h2.font-heading`.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $primaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
    $th = 'px-3 xl:px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';

    $tone = [
        'released'  => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-600/20', 'dot' => 'bg-emerald-500'],
        'cancelled' => ['bg' => 'bg-rose-50',    'text' => 'text-rose-700',    'ring' => 'ring-rose-600/20',    'dot' => 'bg-rose-500'],
        'pending'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-800',   'ring' => 'ring-amber-600/20',   'dot' => 'bg-amber-500'],
        'progress'  => ['bg' => 'bg-navy-50',    'text' => 'text-navy-700',    'ring' => 'ring-navy-700/20',    'dot' => 'bg-navy-600'],
    ];

    // Fees grouped by service, labelled without the repeated service prefix.
    $serviceNames = collect($services)->pluck('label', 'key');
    $feeGroups = $pricing->groupBy('service_type');
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Visa Assistance Counter</h1>
            <p class="mt-1 text-sm text-slate-500">Non-Immigrant, e-Visa and passporting &middot; {{ now()->format('l, F j, Y') }}</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <!-- Find a client or file: matches drop down as staff type; Enter opens the file directory -->
            <form method="GET" action="{{ route('visa.applications.index') }}" role="search" class="relative sm:w-96"
                  x-data="visaLookup({ lookupUrl: {{ Js::from(route('visa.lookup')) }}, resultsUrl: {{ Js::from(route('visa.applications.index')) }} })"
                  @click.outside="open = false" @keydown.escape="open = false"
                  @submit="if (highlighted >= 0) { $event.preventDefault(); go(items[highlighted].url); }">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-5 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="search" placeholder="Find a client or file: name, passport, reference" autocomplete="off"
                       x-model="term" @input.debounce.250ms="lookup()" @focus="open = hasResults"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                       role="combobox" aria-autocomplete="list" aria-controls="visa-lookup-results" :aria-expanded="open"
                       aria-label="Find a client or transaction"
                       class="w-full h-10 pl-9 pr-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500">

                <div x-show="open" x-cloak style="display: none" id="visa-lookup-results" role="listbox"
                     class="absolute z-30 left-0 right-0 mt-1.5 bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden">
                    <template x-if="files.length">
                        <div>
                            <div class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-500">Transactions</div>
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

                    {{-- New files start from a service (the cards below or the Services menu), not from here. --}}
                    <a x-show="hasResults" :href="resultsUrl()" class="block px-4 py-2.5 border-t border-slate-100 text-xs font-semibold text-navy-700 hover:bg-slate-50"
                       x-text="'Search all transactions for “' + term.trim() + '”'"></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Every service, with what is open on it (as on the Immigration and SRRV dashboards) -->
    @include('partials._service-cards', ['services' => $services])

    @include('partials._pending-forms', ['desk' => 'visa'])

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <section class="lg:col-span-2 {{ $card }} overflow-hidden" aria-labelledby="recent-files-heading">
            <div class="flex items-center justify-between gap-4 px-4 sm:px-5 py-4 border-b border-slate-200">
                <div class="min-w-0">
                    <h2 id="recent-files-heading" class="text-base font-bold text-slate-900">Recent Transactions</h2>
                    @if ($recentApplications->isNotEmpty())
                        <p class="mt-0.5 text-sm text-slate-500">Latest {{ $recentApplications->count() }} of {{ number_format($stats['total']) }} opened</p>
                    @endif
                </div>
                <a href="{{ route('visa.applications.index') }}"
                   class="inline-flex items-center gap-1.5 shrink-0 rounded-lg text-sm font-semibold text-navy-700 hover:text-navy-900 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                    View all
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            @if ($recentApplications->isEmpty())
                <div class="flex flex-col items-center text-center px-6 py-14">
                    <i data-lucide="folder-open" class="w-8 h-8 text-slate-300" aria-hidden="true"></i>
                    <p class="mt-3 text-sm font-semibold text-slate-900">No transactions yet</p>
                    <p class="mt-1 max-w-sm text-sm text-slate-500">Non-Immigrant, e-Visa and passporting files will appear here.</p>
                    <p class="mt-3 max-w-sm text-sm text-slate-500">Start one from the <strong class="font-semibold text-slate-700">Services</strong> menu.</p>
                </div>
            @else
                {{-- `relative` keeps the sr-only header label inside this scroller. --}}
                <div class="relative overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th scope="col" class="{{ $th }} pl-4 sm:pl-5 xl:pl-5">Reference</th>
                                <th scope="col" class="{{ $th }}">Client</th>
                                <th scope="col" class="{{ $th }}">Service</th>
                                <th scope="col" class="{{ $th }}">Stage</th>
                                <th scope="col" class="{{ $th }} pr-4 sm:pr-5 xl:pr-5 text-right">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentApplications as $application)
                                @php
                                    $pill = $tone[$application->status] ?? $tone['progress'];
                                    $applicants = $application->applicants->count();
                                @endphp
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="pl-4 sm:pl-5 pr-3 xl:pr-4 py-4">
                                        <div class="font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">{{ $application->reference }}</div>
                                        <div class="mt-1 text-xs text-slate-500 whitespace-nowrap" title="{{ $application->created_at?->format('M j, Y g:i A') }}">Opened {{ $application->created_at?->format('M j') }}</div>
                                    </td>

                                    <td class="px-3 xl:px-4 py-4">
                                        <div class="font-semibold text-slate-900 truncate max-w-[9rem] xl:max-w-[12rem]">{{ $application->client_name }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500 truncate max-w-[9rem] xl:max-w-[12rem]">{{ $application->client_phone ?: ($application->client_email ?: 'No contact on file') }}</div>
                                    </td>

                                    <td class="px-3 xl:px-4 py-4">
                                        <div class="font-semibold text-slate-900 whitespace-nowrap">{{ $serviceNames[$application->service_type] ?? str_replace('_', ' ', $application->service_type) }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500 whitespace-nowrap">
                                            {{ $application->destination_country ?: '—' }}
                                            @if ($applicants > 1)
                                                &middot; {{ $applicants }} applicants
                                            @endif
                                            @if ($application->processing_speed === 'rush')
                                                &middot; <span class="font-semibold text-amber-700">Rush</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-3 xl:px-4 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset {{ $pill['bg'] }} {{ $pill['text'] }} {{ $pill['ring'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}" aria-hidden="true"></span>
                                            {{ $application->status_label }}
                                        </span>
                                    </td>

                                    <td class="pl-3 xl:pl-4 pr-4 sm:pr-5 py-4">
                                        <div class="flex items-center justify-end whitespace-nowrap">
                                            <a href="{{ route('visa.applications.show', $application) }}"
                                               aria-label="Open file {{ $application->reference }} for {{ $application->client_name }}"
                                               class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-1">
                                                Details
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="space-y-6">

            <!-- Published fees, compact: per person, grouped by service -->
            <section class="{{ $card }} overflow-hidden" aria-labelledby="fees-heading">
                <div class="px-4 sm:px-5 py-4 border-b border-slate-200">
                    <h2 id="fees-heading" class="text-base font-bold text-slate-900">Published Fees</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Confirmed prices, per person.</p>
                </div>

                @if ($pricing->isEmpty())
                    <div class="flex flex-col items-center text-center px-6 py-10">
                        <i data-lucide="receipt" class="w-7 h-7 text-slate-300" aria-hidden="true"></i>
                        <p class="mt-2 text-sm text-slate-500">No confirmed fees published yet.</p>
                    </div>
                @else
                    @foreach ($feeGroups as $serviceType => $tiers)
                        <div class="{{ $loop->first ? '' : 'border-t border-slate-100' }}">
                            <div class="px-4 sm:px-5 pt-3 pb-1 text-xs font-semibold text-slate-500">{{ $serviceNames[$serviceType] ?? str_replace('_', ' ', $serviceType) }}</div>
                            <ul class="pb-2">
                                @foreach ($tiers as $tier)
                                    <li class="flex items-baseline justify-between gap-3 px-4 sm:px-5 py-1.5" @if ($tier->condition_notes) title="{{ $tier->condition_notes }}" @endif>
                                        <span class="text-sm text-slate-700 min-w-0 truncate">
                                            {{ $tier->country ?? trim(Str::after($tier->label, '—')) }}
                                            @if ($tier->processing_time)
                                                <span class="text-xs text-slate-400">&middot; {{ $tier->processing_time }}</span>
                                            @endif
                                        </span>
                                        <span class="text-sm font-semibold text-slate-900 whitespace-nowrap tabular-nums">
                                            {{ $tier->label === 'Visit Visa — Rush' ? '+' : '' }}&#8369;{{ number_format((float) $tier->amount, 0) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <p class="px-4 sm:px-5 py-2.5 border-t border-slate-100 text-xs text-slate-500">Hover a row for its notes.</p>
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
