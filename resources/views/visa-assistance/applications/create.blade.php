@extends('layouts.visa-assistance')

@section('title', 'New Counter File - AMEGA Travel and Tours')

@section('content')

    @php
        $serviceTitles = [
            'visit_visa' => ['Non-Immigrant', 'Counter-handled non-immigrant visa for foreign nationals.', 'globe'],
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
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">New {{ $serviceTitle }} File{{ $prefillCountry !== '' ? ' · '.$prefillCountry : '' }}</h1>
            <p class="text-sm text-dark/50 mt-1">
                {{ $serviceBlurb }}
                @if ($serviceType === 'visit_visa')
                    <a href="{{ route('visa.visit.new') }}" class="font-semibold text-primary hover:underline">Change country</a>
                @endif
            </p>
            {{-- The country's checklists, printable straight away: no need to fill the form first. --}}
            @php
                $printable = $serviceType === 'visit_visa' ? \App\Support\VisaChecklist::variants($prefillCountry) : [];
                $blankWorksheet = $serviceType === 'visit_visa' ? \App\Support\VisaWorksheets::forCountry($prefillCountry) : null;
            @endphp
            @if ($printable !== [])
                <div class="mt-4 rounded-2xl bg-white border border-gray-200 shadow-card p-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-dark/50 mr-1">
                        <i data-lucide="clipboard-list" class="w-4 h-4"></i> {{ $prefillCountry }} checklists
                    </span>
                    @foreach ($printable as $key => $variant)
                        <a href="{{ route('visa.checklists.blank', $key) }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-200 text-dark/80 font-bold text-xs rounded-xl hover:border-primary hover:text-primary transition-all">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> {{ $variant['label'] }}
                        </a>
                    @endforeach
                    @if ($blankWorksheet)
                        <a href="{{ route('visa.worksheets.blank', $blankWorksheet) }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-200 text-dark/80 font-bold text-xs rounded-xl hover:border-primary hover:text-primary transition-all">
                            <i data-lucide="file-pen-line" class="w-3.5 h-3.5"></i> Blank worksheet
                        </a>
                    @endif
                </div>
            @endif
        @else
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">New Counter File</h1>
            <p class="text-sm text-dark/50 mt-1">Open a file for a non-immigrant, e-Visa or passporting job.</p>
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
