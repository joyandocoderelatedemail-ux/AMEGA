@extends('layouts.visa-assistance')

@section('title', $label.' - AMEGA Travel and Tours')

@php $icons = ['visit_visa' => 'globe', 'e_visa' => 'zap', 'passporting' => 'book-user']; @endphp

{{-- A service's files (the shared desk list): held files first, then in progress, then released. --}}
@section('content')
    @include('partials._service-list', [
        'label' => $label,
        'icon' => $icons[$service] ?? 'folder',
        'newUrl' => $newUrl,
        'rows' => $rows,
    ])
@endsection
