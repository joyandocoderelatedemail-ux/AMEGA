@extends('layouts.visa-assistance')

@section('title', 'New Visit Visa Extension - AMEGA')

@section('content')
<div class="space-y-6">
    <div class="min-w-0">
        <a href="{{ route('visa.tourist-visa.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-navy-700">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Non-Immigrant
        </a>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">{{ $forClient ? 'New Application for '.$forClient->formal_name : 'New Client · Philippines' }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            Extension and updating of stay of temporary visitors
            @if ($forClient)
                &middot; the client's details are filled from their folder
            @else
                <a href="{{ route('visa.visit.new') }}" class="font-semibold text-navy-700 hover:underline">Change country</a>
            @endif
        </p>
    </div>

    @unless ($forClient)
        @include('visa-assistance.tourist-visa._about')
    @endunless

    <form method="POST" action="{{ route('visa.tourist-visa.store') }}" enctype="multipart/form-data">
        @csrf
        @include('visa-assistance.tourist-visa._form')
    </form>
</div>
@endsection
