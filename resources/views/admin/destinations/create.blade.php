@extends('layouts.admin')

@section('title', 'Add New Destination - AMEGA Admin')
@section('page_title', 'Add Destination')

@section('content')
<div class="max-w-2xl bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Add New Destination</h2>
            <p class="text-xs text-dark/50">Create a new local or international destination card</p>
        </div>
        <a href="{{ route('admin.destinations.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Back to List</a>
    </div>

    <form method="POST" action="{{ route('admin.destinations.store') }}" class="space-y-5">
        @csrf

        @include('admin.destinations._form')

        <div class="pt-4 flex items-center justify-end gap-3">
            <a href="{{ route('admin.destinations.index') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">Cancel</a>
            <button type="submit" class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md">
                Save Destination
            </button>
        </div>
    </form>
</div>
@endsection
