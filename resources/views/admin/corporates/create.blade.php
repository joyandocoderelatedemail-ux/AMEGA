@extends('layouts.admin')

@section('title', 'New Corporate Account - AMEGA Admin')
@section('page_title', 'New Corporate Account')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Register a Company</h2>
            <p class="text-xs text-dark/50">Save the company's details first, then add the people who travel under it</p>
        </div>
        <a href="{{ route('admin.corporates.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Corporate Accounts</span>
        </a>
    </div>

    @include('admin.corporates._form-page')
</div>
@endsection
