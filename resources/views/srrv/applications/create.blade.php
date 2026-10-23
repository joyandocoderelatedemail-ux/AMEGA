@extends('layouts.srrv')

@section('title', 'New Retiree File - AMEGA Travel and Tours')

@section('content')


    <form method="POST" action="{{ route('srrv.applications.store') }}" enctype="multipart/form-data" novalidate>
        @csrf

        @include('srrv.applications._form', [
            'application' => null,
            'serviceType' => $serviceType,
            'pricing' => $pricing,
        ])
    </form>

@endsection
