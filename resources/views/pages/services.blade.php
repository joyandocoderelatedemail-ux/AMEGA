@extends('layouts.app')

@section('content')
    <div class="pt-20 bg-primary-dark text-white text-center py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <span class="inline-block text-xs font-semibold uppercase tracking-widest text-accent mb-2">What We Offer</span>
            <h1 class="font-heading text-4xl sm:text-5xl font-bold">Our Core Services</h1>
            <p class="text-blue-200/70 text-base max-w-2xl mx-auto mt-3">From visa assistance to bespoke travel itineraries, we handle all details of your journey.</p>

            <a href="{{ route('immigration-pricing') }}" class="inline-flex items-center gap-2 mt-6 px-6 py-3 bg-accent text-dark font-bold text-xs sm:text-sm rounded-full hover:bg-accent-dark transition-all duration-300 shadow-md">
                <i data-lucide="receipt" class="w-4 h-4"></i>
                <span>View Immigration Pricing &amp; Process Guide</span>
            </a>
        </div>
    </div>

    @include('partials.services')
    @include('partials.cta')
@endsection
