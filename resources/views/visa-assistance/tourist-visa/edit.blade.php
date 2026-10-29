@extends('layouts.visa-assistance')

@section('title', $application->reference.' - Visit Visa Extension - AMEGA')

@php
    $missing = $application->missingChecklist();
    $outlineButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-navy-700 transition-colors';
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('visa.tourist-visa.client', $application->passport_key) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-navy-700">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Client folder
            </a>
            <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">{{ $application->formal_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                <span class="font-mono font-semibold text-slate-700">{{ $application->reference }}</span>
                &middot; Passport {{ $application->passport_number }}
            </p>
            @if ($missing)
                <p class="mt-2 inline-flex items-start gap-1.5 text-sm font-semibold text-amber-700">
                    <i data-lucide="circle-alert" class="w-4 h-4 mt-0.5 shrink-0"></i>
                    Still missing: {{ implode('; ', array_map(fn ($item) => Str::before($item, ' with attached'), $missing)) }}
                </p>
            @else
                <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Checklist complete
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="{{ route('visa.tourist-visa.print-form', $application) }}" target="_blank" rel="noopener" class="{{ $outlineButton }}">
                <i data-lucide="printer" class="w-4 h-4"></i> Print application form
            </a>
            <a href="{{ route('visa.tourist-visa.print-checklist', $application) }}" target="_blank" rel="noopener" class="{{ $outlineButton }}">
                <i data-lucide="list-checks" class="w-4 h-4"></i> Print checklist &amp; claim slip
            </a>
            @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('visa.tourist-visa.destroy', $application) }}" onsubmit="return confirm('Delete this application? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" title="Delete application" aria-label="Delete application"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-slate-300 bg-white text-rose-600 hover:border-rose-300 hover:bg-rose-50 transition-colors">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
            @endif
        </div>
    </div>

    @include('visa-assistance._next-step', [
        'step' => $application->nextStep(),
        'links' => [
            ['label' => 'Print application form', 'url' => route('visa.tourist-visa.print-form', $application), 'icon' => 'printer'],
            ['label' => 'Print checklist & claim slip', 'url' => route('visa.tourist-visa.print-checklist', $application), 'icon' => 'list-checks'],
        ],
    ])

    <form method="POST" action="{{ route('visa.tourist-visa.update', $application) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('visa-assistance.tourist-visa._form')
    </form>

    {{-- Outside the main form, which cannot nest another; the ledger's remove buttons point here. --}}
    @if (auth()->user()->isAdmin())
        @foreach ($application->payments as $payment)
            @if (auth()->user()?->isAdmin())
            <form id="payment-delete-{{ $payment->id }}" method="POST" action="{{ route('visa.tourist-visa.payments.destroy', [$application, $payment]) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
            @endif
        @endforeach
    @endif
</div>
@endsection
