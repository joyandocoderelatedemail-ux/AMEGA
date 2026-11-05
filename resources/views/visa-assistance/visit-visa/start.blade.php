@extends('layouts.visa-assistance')

@section('title', $serviceLabel.' - Client folders - AMEGA')

@php
    /**
     * Before the steps, as at the Immigration counter: the clients with a Non-Immigrant file, with a search on top.
     * The search finds an existing file (it opens) or a registered client (the new file starts with their details);
     * otherwise Add a new client starts the file with the details typed in Step 1. Expects $clients (paginated).
     */
    $card = 'bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6"
     x-data="visaFolderStart({ lookupUrl: {{ Js::from(route('visa.lookup', $serviceType === 'visit_visa' ? [] : ['service' => $serviceType])) }} })">

    <p class="text-xs text-dark/50">A visit visa to another country, from the first inquiry to the release. Pick the client first, then the file opens step by step.</p>

    <!-- Whose file is this? -->
    <div class="{{ $card }} space-y-5">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider bg-primary/10 text-primary">
                <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                <span>{{ $serviceLabel }}</span>
            </span>
            <h1 class="text-xl sm:text-2xl font-black text-dark tracking-tight mt-1.5">Client folders</h1>
            <p class="text-xs text-dark/50 mt-1">Open a client's folder, or search first so nobody is added twice. Search by name, passport, email or phone.</p>
        </div>

        <!-- Search: matches drop down under the box -->
        <div class="relative" @click.outside="open = false" @keydown.escape="open = false">
            <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
            <input type="search" x-model="term" @input.debounce.250ms="search()" @focus="open = true" autocomplete="off" autofocus
                   placeholder="Search existing clients" aria-label="Search existing clients"
                   class="w-full pl-11 pr-10 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            <span x-show="loading" x-cloak class="absolute right-4 top-1/2 -translate-y-1/2 text-dark/40" aria-hidden="true">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
            </span>

            <!-- Matches: registered clients first (a new file for them), then the files already open -->
            <div x-show="open && searched" x-cloak class="absolute left-0 right-0 top-full z-30 mt-2 space-y-3 bg-white rounded-2xl border border-gray-200 shadow-lg p-3 max-h-[70vh] overflow-y-auto">
                <template x-if="clients.length">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Clients</p>
                        <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                            <template x-for="item in clients" :key="item.url">
                                <li>
                                    <a :href="item.url" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-bold text-dark truncate" x-text="item.name"></span>
                                            <span class="block text-xs text-dark/50 truncate" x-text="[item.passport ? 'Passport ' + item.passport : null, item.email, item.phone].filter(Boolean).join(' · ') || 'No contact on file'"></span>
                                        </span>
                                        <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold">Open</span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="files.length">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Files already open</p>
                        <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                            <template x-for="item in files" :key="item.url">
                                <li>
                                    <a :href="item.url" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-bold text-dark truncate" x-text="item.name"></span>
                                            <span class="block text-xs text-dark/50 truncate" x-text="[item.reference, item.service, item.country, item.status].filter(Boolean).join(' · ')"></span>
                                        </span>
                                        <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-primary/30 text-primary text-xs font-bold">View file</span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <p x-show="! clients.length && ! files.length" class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-dark/60">
                    No client matches &ldquo;<span x-text="term.trim()"></span>&rdquo;. Add them as a new client below.
                </p>
            </div>
        </div>

        <!-- Every client with a Non-Immigrant file -->
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">
                All folders <span class="text-dark/40">&middot; {{ $clients->total() }}</span>
            </p>
            @if ($clients->isEmpty())
                <p class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-6 text-center text-sm text-dark/60">
                    No {{ $serviceLabel }} clients yet. Add a new client below to open the first file.
                </p>
            @else
                <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    @foreach ($clients as $client)
                        <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0" aria-hidden="true">
                                <i data-lucide="folder" class="w-4 h-4"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-dark truncate">{{ $client->full_name }}</span>
                                <span class="block text-xs text-dark/50 truncate">{{ collect([$client->passport_number ? 'Passport '.$client->passport_number : 'No passport on file', $client->nationality])->filter()->implode(' · ') }}</span>
                            </span>
                            <a href="{{ route('visa.visit.file', array_filter(['client' => $client->id, 'service' => $serviceType === 'visit_visa' ? null : $serviceType])) }}"
                               class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-navy transition-colors">
                                <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                Open
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($clients->hasPages())
                    <div class="pt-4">{{ $clients->links() }}</div>
                @endif
            @endif
        </div>

        <a href="{{ route('visa.visit.file', array_filter(['service' => $serviceType === 'visit_visa' ? null : $serviceType])) }}"
           class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border-2 border-dashed border-gray-200 text-dark/70 hover:border-primary hover:text-primary font-heading font-bold text-xs transition-colors">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Add a new client</span>
        </a>
    </div>
</div>

<script>
    // The registered clients and the open files matching what is typed (the counter's lookup).
    function visaFolderStart(config) {
        return {
            term: '',
            clients: [],
            files: [],
            searched: false,
            open: true,
            loading: false,
            requestId: 0,

            async search() {
                const term = this.term.trim();
                const id = ++this.requestId;

                if (term.length < 2) {
                    this.clients = [];
                    this.files = [];
                    this.searched = false;
                    return;
                }

                this.loading = true;
                try {
                    const response = await fetch(config.lookupUrl + (config.lookupUrl.includes('?') ? '&q=' : '?q=') + encodeURIComponent(term), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    if (! response.ok || response.redirected) return;
                    const data = await response.json();
                    if (id !== this.requestId) return;

                    this.clients = data.clients || [];
                    this.files = data.files || [];
                    this.searched = true;
                    this.open = true;
                } catch (e) {
                    // Network hiccup: staff can still add the client below.
                } finally {
                    if (id === this.requestId) this.loading = false;
                }
            },
        };
    }
</script>
@endsection
