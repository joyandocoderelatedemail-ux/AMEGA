@extends('layouts.immigration')

@section('title', 'New Client Sheet - AMEGA Admin')
@section('page_title', 'New Client Information Sheet')

@section('content')
<div class="max-w-4xl bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">New Client Information Sheet</h2>
            <p class="text-xs text-dark/50">Key in the form the client filled in by hand, so their next visit is a lookup.</p>
        </div>
        <a href="{{ route('admin.client-sheets.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Back to counter</a>
    </div>

    <form method="POST" action="{{ route('admin.client-sheets.store') }}" novalidate>
        @csrf

        @include('admin.client-sheets.form', ['client' => $client])
    </form>
</div>
@endsection
