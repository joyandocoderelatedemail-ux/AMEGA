@extends('layouts.srrv')

@section('title', 'Record Renewal - AMEGA Travel and Tours')

@section('content')


    <form method="POST" action="{{ route('srrv.renewals.store') }}" novalidate>
        @csrf

        @include('srrv.renewals._form', [
            'renewal' => null,
            'application' => $application,
            'classFees' => $classFees,
            'maxYears' => $maxYears,
        ])
    </form>

@endsection
