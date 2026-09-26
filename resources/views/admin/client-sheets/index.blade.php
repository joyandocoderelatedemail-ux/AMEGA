@extends('layouts.immigration')

@section('title', 'Client Sheets - AMEGA Admin')
@section('page_title', 'Client Sheets')

@section('content')
<div class="space-y-6">

    <!-- Passport Lookup -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <div class="max-w-xl">
            <h2 class="font-heading text-xl font-bold text-dark">Look up a client by name or passport</h2>
            <p class="text-xs text-dark/50 mt-1">Start typing the client's name, passport number, email or mobile &mdash; matches appear as you type.</p>

            <form method="GET" action="{{ route('admin.client-sheets.index') }}" class="mt-5 flex flex-col sm:flex-row gap-3"
                  x-data="clientSheetSearch({{ Js::from(route('admin.client-sheets.index')) }})" @submit.prevent="search(true)">
                <div class="flex-1 relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-dark/40">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </span>
                    <input type="search" name="passport" value="{{ $search }}" autofocus autocomplete="off"
                           x-model="term" @input.debounce.300ms="search()"
                           placeholder="Name, passport number, email or mobile"
                           aria-label="Search clients by name, passport, email or mobile"
                           class="w-full pl-12 pr-10 py-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-dark text-base focus:outline-none focus:ring-2 focus:ring-primary">
                    <span x-show="loading" style="display: none" class="absolute inset-y-0 right-0 pr-4 flex items-center text-dark/40">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                    </span>
                </div>
                <button type="submit" class="px-7 py-3.5 bg-primary text-white text-sm font-bold rounded-2xl hover:bg-primary-dark transition-all shadow-md">
                    Search
                </button>
            </form>

            <div class="flex flex-wrap items-center gap-4 mt-4">
                <a href="{{ route('admin.client-sheets.blank') }}" target="_blank" class="inline-flex items-center gap-2 text-xs font-bold text-primary hover:underline">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Print a blank form
                </a>
                <a href="{{ route('admin.client-sheets.create') }}" class="inline-flex items-center gap-2 text-xs font-bold text-dark/60 hover:text-dark">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Add a client without searching
                </a>
            </div>
        </div>
    </div>

    <div id="client-sheet-listing" class="space-y-6">
        @include('admin.client-sheets.listing')
    </div>

</div>
<script>
    // Live search: refresh the results below as staff type, and keep the URL
    // in step so a reload or a shared link shows the same list.
    function clientSheetSearch(url) {
        return {
            term: new URLSearchParams(location.search).get('passport') || '',
            loading: false,
            requestId: 0,

            async search(immediate = false) {
                const term = this.term.trim();

                // One letter matches almost everyone; wait for a second one.
                if (!immediate && term.length === 1) return;

                const query = term ? '?passport=' + encodeURIComponent(term) : '';
                const id = ++this.requestId;
                this.loading = true;

                try {
                    const response = await fetch(url + query, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        credentials: 'same-origin',
                    });
                    // Signed out meanwhile: load the page properly so the login screen shows.
                    if (response.redirected) {
                        location.href = url + query;
                        return;
                    }
                    if (!response.ok || id !== this.requestId) return;

                    document.getElementById('client-sheet-listing').innerHTML = await response.text();
                    history.replaceState(null, '', url + query);
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                } finally {
                    if (id === this.requestId) this.loading = false;
                }
            },
        };
    }
</script>
@endsection
