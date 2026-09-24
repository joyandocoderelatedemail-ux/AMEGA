@extends('layouts.srrv')

@section('title', 'Record Renewal - AMEGA Travel and Tours')

@section('content')

    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Annual Renewal</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">Record Renewal</h1>
        <p class="text-sm text-dark/50 mt-1">Due once a year, for as long as the retiree stays.</p>
    </div>

    <form method="POST" action="{{ route('srrv.renewals.store') }}">
        @csrf

        @include('srrv.renewals._form', [
            'renewal' => null,
            'application' => $application,
            'classFees' => $classFees,
            'maxYears' => $maxYears,
        ])
    </form>

@endsection
