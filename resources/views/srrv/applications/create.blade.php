@extends('layouts.srrv')

@section('title', 'New Retiree File - AMEGA Travel and Tours')

@section('content')

    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Desk Intake</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">New Retiree File</h1>
        <p class="text-sm text-dark/50 mt-1">Open a renewal application or re-stamping job for the PRA.</p>
    </div>

    <form method="POST" action="{{ route('srrv.applications.store') }}">
        @csrf

        @include('srrv.applications._form', [
            'application' => null,
            'serviceType' => $serviceType,
            'pricing' => $pricing,
        ])
    </form>

@endsection
