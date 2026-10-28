@extends('layouts.app')

@section('title', 'Contact AMEGA Travel and Tours | Angeles City, Pampanga')
@section('meta_description', 'Visit, call or message AMEGA Travel and Tours at Unit 1 & 2 Astrofield Bldg, Mitchell Avenue, Balibago, Angeles City. Department numbers, office hours and an inquiry form.')

@section('content')
    <div class="pt-20 bg-primary-dark text-white text-center py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <span class="inline-block text-xs font-semibold uppercase tracking-widest text-accent mb-2">We Are Here For You</span>
            <h1 class="font-heading text-4xl sm:text-5xl font-bold">Contact Us</h1>
            <p class="text-blue-200/70 text-base max-w-2xl mx-auto mt-3">Get in touch with our expert travel agents to book your trip or inquire about visa processing.</p>
        </div>
    </div>

    @include('partials.contact')
@endsection
