@extends('layouts.srrv')

@section('title', 'Edit '.$renewal->reference.' - AMEGA Travel and Tours')

@php
    $preview = $preview ?? false;
@endphp

@section('content')

    @if ($preview)
        {{-- Nothing to type, upload or save while previewing. --}}
        <style>
            form[data-preview] label:has(input[type="file"]) { display: none !important; }
        </style>
    @endif

    @if ($preview)
        @include('srrv.partials._preview-banner', ['file' => $renewal, 'kind' => 'renewal', 'label' => 'ID Renewal'])
    @else
    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="pencil-line" class="w-4 h-4"></i>
            <span>{{ $renewal->reference }}</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">Edit Renewal</h1>
        <p class="text-sm text-dark/50 mt-1">Changing the class or the years recomputes the fee.</p>
    </div>
    @endif

    <form method="POST" action="{{ route('srrv.renewals.update', $renewal) }}" novalidate @if ($preview) data-preview x-data x-init="$nextTick(() => $el.querySelectorAll('input, select, textarea').forEach(field => { if (field.type !== 'hidden' && field.name !== '_token') field.disabled = true }))" @endif>
        @csrf
        @method('PUT')

        @include('srrv.renewals._form', [
            'renewal' => $renewal,
            'application' => $renewal->application,
            'classFees' => $classFees,
            'maxYears' => $maxYears,
            'preview' => $preview,
        ])
    </form>

@endsection
