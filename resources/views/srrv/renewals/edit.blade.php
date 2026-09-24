@extends('layouts.srrv')

@section('title', 'Edit '.$renewal->reference.' - AMEGA Travel and Tours')

@section('content')

    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="pencil-line" class="w-4 h-4"></i>
            <span>{{ $renewal->reference }}</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">Edit Renewal</h1>
        <p class="text-sm text-dark/50 mt-1">Changing the class or the years recomputes the fee.</p>
    </div>

    <form method="POST" action="{{ route('srrv.renewals.update', $renewal) }}">
        @csrf
        @method('PUT')

        @include('srrv.renewals._form', [
            'renewal' => $renewal,
            'application' => $renewal->application,
            'classFees' => $classFees,
            'maxYears' => $maxYears,
        ])
    </form>

@endsection
