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
    @endif

    <form method="POST" action="{{ route('srrv.renewals.update', $renewal) }}" enctype="multipart/form-data" novalidate @if ($preview) data-preview x-data x-init="$nextTick(() => $el.querySelectorAll('input, select, textarea').forEach(field => { if (field.type !== 'hidden' && field.name !== '_token') field.disabled = true }))" @endif>
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

    @unless ($preview)
        {{-- One standalone form per filed document (nested forms are dropped by browsers). --}}
        @foreach ($renewal->documents as $document)
            <form id="renewal-remove-{{ $document->id }}" method="POST" action="{{ route('srrv.renewals.documents.destroy', $document) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endunless

@endsection
