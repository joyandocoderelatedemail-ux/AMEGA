@extends('layouts.srrv')

@section('title', \App\Models\SrrvServiceRequest::TYPES[$type].' - AMEGA Travel and Tours')

@section('content')
    <form method="POST" action="{{ route('srrv.requests.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('srrv.requests._form', ['type' => $type, 'file' => null, 'preview' => false])
    </form>
@endsection
