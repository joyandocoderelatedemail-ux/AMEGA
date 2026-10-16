@extends('layouts.visa-assistance')

@section('title', 'Add New Client - Non-Immigrant - AMEGA')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $tile = 'group block '.$card.' p-4 hover:border-navy-700 hover:shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500';
@endphp

@section('content')
<div class="space-y-6" x-data="{ term: '' }">

    <div class="min-w-0">
        <a href="{{ route('visa.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-navy-700">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Non-Immigrant
        </a>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Add New Client</h1>
        <p class="mt-1 text-sm text-slate-500">Which visa is this for? Each country has its own form, so choose it first.</p>
    </div>

    {{-- A visa extension in the Philippines is the Immigration desk's Visa Extension service. --}}

    <!-- Everywhere else: a visit visa for the destination -->
    <section aria-labelledby="visit-heading" class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 id="visit-heading" class="text-sm font-bold text-slate-900">Visit visa to another country</h2>
                <p class="mt-0.5 text-sm text-slate-500">The destination sets the fees, the requirements and the processing time.</p>
            </div>
            @if ($countries->count() > 6)
                <div class="relative sm:w-72">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="search" x-model="term" placeholder="Search a country" aria-label="Search a country" autocomplete="off"
                           class="w-full h-10 pl-9 pr-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500">
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($countries as $country)
                <a href="{{ route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => $country->country]) }}"
                   x-show="!term.trim() || {{ Js::from(Str::lower($country->country)) }}.includes(term.trim().toLowerCase())"
                   class="{{ $tile }}">
                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                            <i data-lucide="globe" class="w-5 h-5"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-slate-900 truncate">{{ $country->country }}</div>
                            <div class="mt-0.5 text-sm text-slate-500">
                                @if ((float) $country->service_fee + (float) $country->visa_fee > 0)
                                    ₱{{ number_format((float) $country->service_fee + (float) $country->visa_fee, 0) }} per person
                                @else
                                    Visit visa
                                @endif
                                @if ($country->processing_time)
                                    &middot; {{ $country->processing_time }}
                                @endif
                            </div>
                            @if (in_array($country->country, \App\Models\VisaApplication::NO_APPEARANCE_COUNTRIES, true))
                                <div class="mt-1 text-xs text-slate-500">No embassy appearance needed</div>
                            @endif
                        </div>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0 mt-1 group-hover:text-navy-700"></i>
                    </div>
                </a>
            @endforeach

            <a href="{{ route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => '']) }}"
               x-show="!term.trim() || 'other country'.includes(term.trim().toLowerCase())"
               class="{{ $tile }} border-dashed">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                        <i data-lucide="map-pin" class="w-5 h-5"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-slate-900">Other country</div>
                        <div class="mt-0.5 text-sm text-slate-500">Not on the price list: type the destination and enter the fees by hand.</div>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 shrink-0 mt-1 group-hover:text-navy-700"></i>
                </div>
            </a>
        </div>
    </section>
</div>
@endsection
