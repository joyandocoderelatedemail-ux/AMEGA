@extends('layouts.srrv')

@section('title', $service['label'].' - AMEGA Travel and Tours')

{{-- A service's files (the shared desk list): held files first, then in progress, then finished. --}}
@section('content')
    @include('partials._service-list', [
        'label' => $service['label'],
        'icon' => $service['icon'],
        'blurb' => $service['blurb'],
        'newUrl' => route('srrv.start', $service['key']),
        'rows' => $rows,
    ])
@endsection
