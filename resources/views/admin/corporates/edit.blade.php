@extends('layouts.admin')

@section('title', 'Edit ' . $corporate->company_name . ' - AMEGA Admin')
@section('page_title', $corporate->isGroup() ? 'Edit Group' : 'Edit Corporate Account')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">{{ $corporate->company_name }}</h2>
            <p class="text-xs text-dark/50">{{ $corporate->isGroup() ? "Update the group's details" : "Update the company's details" }}</p>
        </div>
        <a href="{{ route('admin.corporates.show', $corporate) }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>{{ $corporate->isGroup() ? 'Back to Group' : 'Back to Company' }}</span>
        </a>
    </div>

    @include('admin.corporates._form-page', ['corporate' => $corporate])
</div>
@endsection
