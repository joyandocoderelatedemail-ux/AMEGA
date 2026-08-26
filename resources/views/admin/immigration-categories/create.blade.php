@extends('layouts.immigration')

@section('title', 'Add Immigration Category - AMEGA Admin')
@section('page_title', 'Create Immigration Category')

@section('content')
<div class="max-w-2xl bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Create Immigration Category</h2>
            <p class="text-xs text-dark/50">Add a new immigration process group with its requirements and process notes</p>
        </div>
        <a href="{{ route('admin.immigration-categories.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Back to List</a>
    </div>

    <form method="POST" action="{{ route('admin.immigration-categories.store') }}" class="space-y-5">
        @csrf

        @include('admin.immigration-categories.form', ['category' => null])

        <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-100">
            <a href="{{ route('admin.immigration-categories.index') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">Cancel</a>
            <button type="submit" class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md">
                Save Category
            </button>
        </div>
    </form>
</div>
@endsection
