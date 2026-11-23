@extends('layouts.srrv')

@php
    $preview = $preview ?? false;
    // A new application still in its wizard (saved by Save fee, not yet past Admin Checking & Cashier) is the new file it
    // was: it reads like the New Retiree File page, not as an edit.
    $isNew = $application->isFeeFirst() && in_array($application->status, ['pending', 'agreement'], true);
    // "Edit Retiree File" only when the file was opened to change it (the preview's Edit button), not when the work on it
    // carries on (Continue, the dashboard, a step it returns to).
    $editing = ! $preview && request()->boolean('edit');
@endphp

@section('title', ($editing ? 'Edit ' : ($isNew ? 'New Retiree File ' : 'Retiree File ')).$application->reference.' - AMEGA Travel and Tours')

@section('content')

    @if ($preview)
        {{-- Nothing to type, upload or save while previewing. --}}
        <style>
            form[data-preview] label:has(input[type="file"]) { display: none !important; }
        </style>
    @endif

    @if ($preview)
        @include('srrv.partials._preview-banner', ['file' => $application, 'kind' => 'application', 'label' => 'SRRV Application'])
    @elseif ($editing)
    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <i data-lucide="pencil-line" class="w-4 h-4"></i>
            <span>{{ $application->reference }}</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">Edit Retiree File</h1>
        <p class="text-sm text-dark/50 mt-1">Amend the details recorded against this file.</p>
    </div>
    @endif

    @unless ($preview)
        <div class="mb-6 space-y-3">@include('srrv.applications._lock', ['application' => $application])</div>
    @endunless

    <form method="POST" action="{{ route('srrv.applications.update', $application) }}" enctype="multipart/form-data" novalidate @if ($preview) data-preview x-data x-init="$nextTick(() => $el.querySelectorAll('input, select, textarea').forEach(field => { if (field.type !== 'hidden' && field.name !== '_token') field.disabled = true }))" @endif>
        @csrf
        @method('PUT')

        @include('srrv.applications._form', [
            'application' => $application,
            'pricing' => $pricing,
            'preview' => $preview,
        ])
    </form>

@endsection
