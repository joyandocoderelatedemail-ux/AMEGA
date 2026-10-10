@extends('layouts.ticketing')

@section('title', 'Packages - AMEGA')

@section('content')
@php
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $categories = ['domestic' => 'Domestic', 'short_haul' => 'Short haul', 'long_haul' => 'Long haul'];
    $statuses = [
        'active' => ['Active', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'draft' => ['Draft', 'bg-slate-100 text-slate-600 border-slate-200'],
        'sold_out' => ['Sold out', 'bg-amber-50 text-amber-700 border-amber-200'],
    ];
    $filtered = request()->anyFilled(['search', 'category', 'status']);
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900">Packages</h1>
        <a href="{{ route('ticketing.packages.configurator') }}"
           class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>Add package</span>
        </a>
    </div>

    {{-- Filters apply as you type or pick: only the results below are reloaded, so the search box keeps focus. --}}
    <form method="GET" action="{{ route('ticketing.packages.index') }}" class="flex flex-col sm:flex-row gap-3" role="search"
          x-data="packageFilters()" x-ref="form" @submit.prevent="apply()">
        <label class="sr-only" for="package-search">Search packages</label>
        <input id="package-search" type="search" name="search" value="{{ request('search') }}" placeholder="Search by title or destination" autocomplete="off"
               @input.debounce.300ms="apply()" class="{{ $control }} sm:flex-1">
        <label class="sr-only" for="package-category">Category</label>
        <select id="package-category" name="category" @change="apply()" class="{{ $control }}">
            <option value="">All categories</option>
            @foreach ($categories as $value => $label)
                <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="package-status">Status</label>
        <select id="package-status" name="status" @change="apply()" class="{{ $control }}">
            <option value="">All statuses</option>
            @foreach ($statuses as $value => [$label])
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" x-show="filtered" x-cloak @click="clear()" class="h-10 px-2 inline-flex items-center text-sm font-semibold text-slate-500 hover:text-slate-900">Clear</button>
    </form>

    <div id="package-results" class="bg-white rounded-xl border border-slate-200 overflow-hidden transition-opacity" aria-live="polite">
        @if ($packages->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">
                {{ $filtered ? 'No packages match these filters.' : 'No packages yet.' }}
            </p>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Package</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3">Duration</th>
                            <th class="px-5 py-3">Flight</th>
                            <th class="px-5 py-3 text-right">Total / pax</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($packages as $package)
                            @php [$statusLabel, $statusClass] = $statuses[$package->status] ?? [ucfirst((string) $package->status), 'bg-slate-100 text-slate-600 border-slate-200']; @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <div class="font-semibold text-slate-900">{{ $package->title }}</div>
                                    <div class="text-xs text-slate-500">{{ $package->destination?->name ?? 'Standalone tour' }}</div>
                                </td>
                                <td class="px-5 py-3 text-slate-700 whitespace-nowrap">{{ $categories[$package->category] ?? $package->category }}</td>
                                <td class="px-5 py-3 text-slate-700 whitespace-nowrap">{{ $package->duration }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-700 whitespace-nowrap">{{ \App\Models\TravelPackage::TRIP_TYPES[$package->trip_type] ?? '—' }}{{ $package->airline ? ' · '.$package->airline->name : '' }}</div>
                                    @if ($package->airfare_inclusion)
                                        <div class="text-xs {{ $package->airfare_inclusion === 'included' ? 'text-emerald-700' : 'text-slate-500' }}">{{ \App\Models\TravelPackage::AIRFARE_OPTIONS[$package->airfare_inclusion] }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">{{ $package->formatted_price }}</td>
                                <td class="px-5 py-3">
                                    <span class="px-2 py-0.5 rounded-full border text-xs font-semibold whitespace-nowrap {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('ticketing.packages.edit', $package) }}"
                                       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                                        <span>Edit</span>
                                        <span class="sr-only">{{ $package->title }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($packages->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">
                    {{ $packages->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

<script>
    function packageFilters() {
        return {
            filtered: {{ Js::from($filtered) }},
            request: null,
            /** Reload only the results for the current filters, and keep them in the address bar. */
            async apply() {
                const params = new URLSearchParams(new FormData(this.$refs.form));
                for (const [key, value] of [...params]) {
                    if (!value.trim()) params.delete(key);
                }
                this.filtered = [...params.keys()].length > 0;
                const url = this.$refs.form.action + (this.filtered ? '?' + params : '');

                this.request?.abort();
                this.request = new AbortController();
                const results = document.getElementById('package-results');
                results.classList.add('opacity-60');
                try {
                    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: this.request.signal });
                    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                    results.innerHTML = page.getElementById('package-results').innerHTML;
                    history.replaceState(null, '', url);
                } catch (e) {
                    if (e.name !== 'AbortError') window.location = url;
                } finally {
                    results.classList.remove('opacity-60');
                }
            },

            clear() {
                this.$refs.form.reset();
                this.$refs.form.querySelectorAll('input, select').forEach(field => { field.value = ''; });
                this.apply();
            },
        };
    }
</script>
@endsection
