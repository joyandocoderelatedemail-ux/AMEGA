@extends('layouts.visa-assistance')

@section('title', 'Edit '.$application->reference.' - AMEGA Travel and Tours')

@section('content')

    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="pencil-line" class="w-4 h-4"></i>
            <span>{{ $application->reference }}</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">Edit Counter File</h1>
        <p class="text-sm text-dark/50 mt-1">Amend the details recorded against this file.</p>
    </div>

    <form method="POST" action="{{ route('visa.applications.update', $application) }}">
        @csrf
        @method('PUT')

        @include('visa-assistance.applications._form', [
            'application' => $application,
            'pricing' => $pricing,
        ])
    </form>

@endsection
