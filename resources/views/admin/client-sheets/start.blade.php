@extends('layouts.immigration')

@section('title', $service['title'].' - AMEGA Admin')
@section('page_title', $service['title'])

@php
    /**
     * Before the steps: every client folder, with a search on top. Search finds an
     * existing client (their sheet opens, or a registered client's profile fills
     * step 1); otherwise the new client is added to the client list first, then
     * their folder opens.
     */
    $card = 'bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm';
    $newClientOpen = $errors->any();
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6"
     x-data="folderStart({
         lookupUrl: {{ Js::from(route('admin.client-sheets.lookup', ['service' => $service['key']])) }},
         adding: {{ $newClientOpen ? 'true' : 'false' }},
         term: {{ Js::from((string) request('q')) }},
     })">

    <p class="text-xs text-dark/50">{{ $service['blurb'] }}</p>

    <!-- Step 0: whose folder is this? -->
    <div class="{{ $card }} space-y-5">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider bg-primary/10 text-primary">
                <i data-lucide="{{ $service['icon'] }}" class="w-3.5 h-3.5"></i>
                <span>{{ $service['label'] }}</span>
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

        <!-- Matches: existing folders first, then registered clients without one -->
        <div x-show="open && searched" x-cloak class="absolute left-0 right-0 top-full z-30 mt-2 space-y-3 bg-white rounded-2xl border border-gray-200 shadow-lg p-3 max-h-[70vh] overflow-y-auto">
            <template x-if="sheets.length">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Existing folders</p>
                    <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                        <template x-for="item in sheets" :key="item.url">
                            <li>
                                <a :href="item.url" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-dark truncate" x-text="item.name"></span>
                                        <span class="block text-xs text-dark/50 truncate" x-text="[item.passport ? 'Passport ' + item.passport : 'No passport on file', item.nationality].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold">Open folder</span>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            <template x-if="clients.length">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Registered clients &mdash; no folder yet</p>
                    <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                        <template x-for="item in clients" :key="item.url">
                            <li>
                                <a :href="item.url" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-dark truncate" x-text="item.name"></span>
                                        <span class="block text-xs text-dark/50 truncate" x-text="[item.passport ? 'Passport ' + item.passport : null, item.contact].filter(Boolean).join(' · ') || 'No contact on file'"></span>
                                    </span>
                                    <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-primary/30 text-primary text-xs font-bold">Create folder</span>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            <p x-show="!sheets.length && !clients.length" class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-dark/60">
                No client matches &ldquo;<span x-text="term.trim()"></span>&rdquo;. Add them as a new client below.
            </p>
        </div>
        </div>

        <!-- Every folder on file -->
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">
                All folders <span class="text-dark/40">&middot; {{ $folders->total() }}</span>
            </p>
            @if ($folders->isEmpty())
                <p class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-6 text-center text-sm text-dark/60">
                    No client folders yet. Add a new client below to open the first one.
                </p>
            @else
                <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    @foreach ($folders as $folder)
                        <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0" aria-hidden="true">
                                <i data-lucide="folder" class="w-4 h-4"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-dark truncate">{{ $folder->full_name }}</span>
                                <span class="block text-xs text-dark/50 truncate">{{ collect([$folder->passport_number ? 'Passport '.$folder->passport_number : 'No passport on file', $folder->nationality])->filter()->implode(' · ') }}</span>
                            </span>
                            <a href="{{ \App\Support\ImmigrationServices::folderUrl($folder, $service['key']) }}"
                               class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-navy transition-colors">
                                <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                Open
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if ($folders->hasPages())
                    <div class="pt-4">{{ $folders->links() }}</div>
                @endif
            @endif
        </div>

        <button type="button" @click="adding = !adding"
                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border-2 border-dashed font-heading font-bold text-xs transition-colors"
                :class="adding ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-dark/70 hover:border-primary hover:text-primary'">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span x-text="adding ? 'Adding a new client' : 'Add a new client'">Add a new client</span>
        </button>
    </div>

    <!-- A new client: added to the client list, then their folder opens with step 1 filled -->
    <div x-show="adding" x-cloak class="{{ $card }}">
        <div class="mb-5">
            <h2 class="text-base font-bold text-dark">New client</h2>
            <p class="text-xs text-dark/50 mt-0.5">They are added to the client list. The system warns you as you type if the name, email or number is already on file.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-5 p-4 rounded-xl bg-rose-50 text-rose-800 text-sm border border-rose-200">
                <p class="font-bold">The client was not added. Please check the following:</p>
                <ul class="mt-1 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form novalidate data-inline-validation method="POST" action="{{ route('admin.client-sheets.start.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="service" value="{{ $service['key'] }}">

            @include('admin.users._identity', [
                'duplicateCheckUrl' => route('admin.client-sheets.check-duplicate'),
                'withAddress' => false,
                'prefill' => [],
            ])

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" @click="adding = false" class="px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md">
                    <span>Add client &amp; create folder</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Name the folder: client sheets and registered clients matching what is typed.
    function folderStart(config) {
        return {
            // A name searched on the client list carries over (?q=), and its matches show at once.
            term: config.term || '',
            sheets: [],
            clients: [],
            searched: false,
            open: true,
            loading: false,
            adding: config.adding,
            requestId: 0,

            init() {
                if (this.term.trim().length >= 2) this.search();
            },

            async search() {
                const term = this.term.trim();
                const id = ++this.requestId;

                if (term.length < 2) {
                    this.sheets = [];
                    this.clients = [];
                    this.searched = false;
                    return;
                }

                this.loading = true;
                try {
                    const url = config.lookupUrl + (config.lookupUrl.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(term);
                    const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    if (!response.ok || response.redirected) return;
                    const data = await response.json();
                    if (id !== this.requestId) return;

                    this.sheets = data.sheets || [];
                    this.clients = data.clients || [];
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
