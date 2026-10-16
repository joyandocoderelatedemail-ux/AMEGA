@extends('layouts.immigration')

@section('title', $service['title'].' - AMEGA Admin')
@section('page_title', $service['title'])

@section('content')
<div class="max-w-5xl mx-auto space-y-4">
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-dark/50">{{ $service['blurb'] }}</p>
        <a href="{{ route('admin.client-sheets.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark shrink-0">Back to counter</a>
    </div>

    <form method="POST" action="{{ route('admin.client-sheets.store') }}" enctype="multipart/form-data" novalidate>
        @csrf

        @include('admin.client-sheets.form', ['client' => $client, 'service' => $service])
    </form>
</div>
@endsection
