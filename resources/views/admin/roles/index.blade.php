@extends('layouts.admin')

@section('title', 'Roles & Permissions - AMEGA Admin')
@section('page_title', 'Roles & Permissions')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Roles & Permissions</h2>
            <p class="text-sm text-slate-500 mt-0.5">A role is a named set of permissions. Give staff a role (Users &gt; Staff Accounts &gt; Edit) and add extras for one person when needed. Changing a role changes it for everyone who has it.</p>
        </div>
        <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
            <i data-lucide="plus" class="w-4 h-4"></i> New role
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100">
        @foreach ($roles as $role)
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-900">{{ $role->name }}</span>
                        @if ($role->is_system)
                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-semibold uppercase tracking-wider">Standard</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $role->description }}</p>
                    <p class="text-[11px] text-slate-400 mt-1">{{ count($role->permissionKeys()) }} permissions · {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('person', $role->users_count) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.roles.edit', $role) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                    @unless ($role->is_system || $role->users_count)
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?');" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete</button>
                        </form>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
