@extends('layouts.admin')

@section('title', ($role->exists ? 'Edit '.$role->name : 'New role').' - AMEGA Admin')
@section('page_title', 'Roles & Permissions')

@section('content')
@php $holders = $role->exists ? $role->users()->count() : 0; @endphp
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <a href="{{ route('admin.roles.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800">&larr; Roles & Permissions</a>
        <h2 class="text-xl font-bold text-slate-900 mt-1">{{ $role->exists ? $role->name : 'New role' }}</h2>
        @if ($holders)
            <p class="text-sm text-amber-700 mt-0.5">{{ $holders }} {{ \Illuminate\Support\Str::plural('person', $holders) }} {{ $holders === 1 ? 'has' : 'have' }} this role: a change applies to them at once.</p>
        @endif
    </div>

    @include('admin.partials._form-errors')

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="space-y-5">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="block">
                <span class="block text-xs font-semibold text-slate-700 mb-1">Name</span>
                <input type="text" name="name" required maxlength="80" value="{{ old('name', $role->name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="block text-xs font-semibold text-slate-700 mb-1">What the role is for</span>
                <input type="text" name="description" maxlength="255" value="{{ old('description', $role->description) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
            <h3 class="text-sm font-semibold text-slate-900">Permissions</h3>
            @include('admin.roles._grid', ['selected' => old('permissions', $role->permissionKeys())])
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.roles.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-semibold text-slate-700 hover:bg-slate-200">Cancel</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Save role</button>
        </div>
    </form>
</div>
@endsection
