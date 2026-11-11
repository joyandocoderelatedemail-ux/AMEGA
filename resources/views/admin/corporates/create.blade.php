@extends('layouts.admin')

@php $isGroup = ($kind ?? 'corporate') === 'group'; @endphp

@section('title', ($isGroup ? 'New Group' : 'New Corporate Account').' - AMEGA Admin')
@section('page_title', $isGroup ? 'New Group' : 'New Corporate Account')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">{{ $isGroup ? 'Start a Group' : 'Register a Company' }}</h2>
            <p class="text-xs text-dark/50">{{ $isGroup ? 'Name the group first, then add the people who travel in it' : "Save the company's details first, then add the people who travel under it" }}</p>
        </div>
        <a href="{{ route('admin.corporates.index', $isGroup ? ['kind' => 'group'] : []) }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>{{ $isGroup ? 'Back to Groups' : 'Back to Corporate Accounts' }}</span>
        </a>
    </div>

    @include('admin.corporates._form-page', ['kind' => $kind ?? 'corporate'])
</div>
@endsection
