@extends('layouts.visa-assistance')

@section('title', 'New Transaction - AMEGA Travel and Tours')

@section('content')

    @php
        $serviceTitles = [
            'e_visa' => ['e-Visa', 'No appearance, no embassy queue.', 'zap'],
            'passporting' => ['Passporting', 'Foreign or local passport application.', 'book-user'],
        ];
        [$serviceTitle, $serviceBlurb, $serviceIcon] = $serviceTitles[$serviceType];
    @endphp

    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="{{ $serviceLocked ? $serviceIcon : 'plus-circle' }}" class="w-4 h-4"></i>
            <span>Counter Intake</span>
        </div>
        @if ($serviceLocked)
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">New {{ $serviceTitle }} File</h1>
            <p class="text-sm text-dark/50 mt-1">{{ $serviceBlurb }}</p>
        @else
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">New Transaction</h1>
            <p class="text-sm text-dark/50 mt-1">Open a file for a passporting job. Non-Immigrant and E-Visa files have their own steps: <a href="{{ route('visa.visit.new', array_filter(['client' => $clientId ?? null])) }}" class="font-semibold text-primary hover:underline">New Non-Immigrant File</a> or <a href="{{ route('visa.visit.new', array_filter(['client' => $clientId ?? null, 'service' => 'e_visa'])) }}" class="font-semibold text-primary hover:underline">New E-Visa File</a>.</p>
        @endif
    </div>

    <form method="POST" action="{{ route('visa.applications.store') }}">
        @csrf

        @include('visa-assistance.applications._form', [
            'application' => null,
            'serviceType' => $serviceType,
            'serviceLocked' => $serviceLocked,
            'prefillCountry' => $prefillCountry,
            'pricing' => $pricing,
            'rushFee' => $rushFee,
        ])
    </form>

@endsection
