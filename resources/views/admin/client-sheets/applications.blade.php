@extends('layouts.immigration')

@section('title', $service['label'].' - AMEGA Admin')

{{-- A service's transactions (the shared desk list): held files first, then in progress, then released. --}}
@section('content')
    @include('partials._service-list', [
        'label' => $service['label'],
        'icon' => $service['icon'],
        'blurb' => $service['blurb'],
        'newUrl' => route('admin.client-sheets.start', ['service' => $service['key']]),
        'rows' => $rows,
        'hidden' => ['service' => $service['key']],
    ])
@endsection
