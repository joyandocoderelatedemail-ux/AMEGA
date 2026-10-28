@extends('layouts.app')

@section('title', 'Frequently Asked Questions | AMEGA Travel and Tours')
@section('meta_description', 'Answers to common questions about AMEGA Travel and Tours: office location and hours, how to pay, booking tour packages online, visas and immigration services.')

@section('content')
    <div class="pt-20 bg-primary-dark text-white text-center py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <span class="inline-block text-xs font-semibold uppercase tracking-widest text-accent mb-2">Help Centre</span>
            <h1 class="font-heading text-4xl sm:text-5xl font-bold">Frequently Asked Questions</h1>
            <p class="text-blue-200/70 text-base max-w-2xl mx-auto mt-3">Quick answers about booking, payment, visas and visiting our office.</p>
        </div>
    </div>

    @include('partials.faq')
@endsection
