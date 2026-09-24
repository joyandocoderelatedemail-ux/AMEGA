@extends('layouts.visa-assistance')

@section('title', 'New Counter File - AMEGA Travel and Tours')

@section('content')

    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Counter Intake</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">New Counter File</h1>
        <p class="text-sm text-dark/50 mt-1">Open a file for a visit visa, e-Visa or passporting job.</p>
    </div>

    <form method="POST" action="{{ route('visa.applications.store') }}">
        @csrf

        @include('visa-assistance.applications._form', [
            'application' => null,
            'serviceType' => $serviceType,
            'pricing' => $pricing,
            'rushFee' => $rushFee,
        ])
    </form>

@endsection
