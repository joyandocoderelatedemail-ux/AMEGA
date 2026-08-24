@extends('layouts.app')

@section('content')
    <div class="pt-20 bg-primary-dark text-white text-center py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <span class="inline-block text-xs font-semibold uppercase tracking-widest text-accent mb-2">Since 2001</span>
            <h1 class="font-heading text-4xl sm:text-5xl font-bold">About Amega Travel and Tours Services</h1>
            <p class="text-blue-200/70 text-base max-w-2xl mx-auto mt-3">24+ years of trust, government accreditation, and crafting unforgettable journeys.</p>
        </div>
    </div>

    @include('partials.about')
    @include('partials.cta')
@endsection
