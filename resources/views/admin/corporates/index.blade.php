@extends('layouts.admin')

{{-- Companies and groups: the accounts several clients travel under. One list each, switched by ?kind. --}}
@php
    $isGroup = $kind === \App\Models\CorporateAccount::KIND_GROUP;
    $title = $isGroup ? 'Groups' : 'Corporate Accounts';
    $kindQuery = $isGroup ? ['kind' => $kind] : [];
@endphp

@section('title', $title.' - AMEGA Admin')
@section('page_title', $title)

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">{{ $title }}</h2>
            <p class="text-xs text-dark/50">{{ $isGroup ? 'Families, friends and tour parties who travel together, and the clients in each' : 'Companies that book travel for several people, and the clients under each' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('admin.corporates.index') }}" class="flex flex-wrap items-center gap-2">
                @if ($isGroup)<input type="hidden" name="kind" value="{{ $kind }}">@endif
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ $isGroup ? 'Search group or leader...' : 'Search company, contact, TIN...' }}"
                           class="w-64 pl-9 pr-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3 top-2.5"></i>
                </div>
                <x-date-range label="Added" inputClass="py-2 px-3 rounded-xl bg-gray-50 border border-gray-200 text-xs text-dark focus:outline-none focus:ring-2 focus:ring-primary" buttonClass="py-2 px-3 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary" />
            </form>
            <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all flex items-center gap-1.5">
                <i data-lucide="users" class="w-4 h-4"></i>
                <span>All Clients</span>
            </a>
            <a href="{{ route('admin.corporates.create', $kindQuery) }}" class="px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-1.5">
                <i data-lucide="{{ $isGroup ? 'users-round' : 'building-2' }}" class="w-4 h-4"></i>
                <span>{{ $isGroup ? 'New Group' : 'New Corporate Account' }}</span>
            </a>
        </div>
    </div>

    <nav class="inline-flex gap-1 p-1 rounded-xl bg-gray-100" aria-label="Companies or groups">
        @foreach (['corporate' => 'Companies', 'group' => 'Groups'] as $key => $label)
            <a href="{{ route('admin.corporates.index', $key === 'group' ? ['kind' => 'group'] : []) }}" @if ($kind === $key) aria-current="page" @endif
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold {{ $kind === $key ? 'bg-white text-dark shadow-sm' : 'text-dark/60 hover:text-dark' }}">
                <span>{{ $label }}</span>
                <span class="text-dark/40">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">{{ $isGroup ? 'Group' : 'Company' }}</th>
                        <th class="pb-3 px-3">{{ $isGroup ? 'Group Leader' : 'Contact Person' }}</th>
                        @unless ($isGroup)<th class="pb-3 px-3">Registration / TIN</th>@endunless
                        <th class="pb-3 px-3">Members</th>
                        <th class="pb-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($companies as $company)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-3">
                                <a href="{{ route('admin.corporates.show', $company) }}" class="font-bold text-dark text-sm hover:text-primary">{{ $company->company_name }}</a>
                                @if($company->industry)<div class="text-[11px] text-dark/50">{{ $company->industry }}</div>@endif
                            </td>
                            <td class="py-4 px-3">
                                <div class="font-semibold text-dark">{{ $company->contact_person ?? 'N/A' }}</div>
                                <div class="text-[11px] text-dark/50">{{ collect([$company->contact_email, $company->contact_phone])->filter()->implode(' · ') }}</div>
                            </td>
                            @unless ($isGroup)
                                <td class="py-4 px-3">
                                    <div class="font-mono text-dark">{{ $company->registration_number ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-dark/50 font-mono">TIN: {{ $company->tin ?? 'N/A' }}</div>
                                </td>
                            @endunless
                            <td class="py-4 px-3">
                                <span class="px-2.5 py-1 rounded-full bg-primary/10 text-primary font-bold text-[11px]">{{ $company->members_count }} {{ Str::plural('member', $company->members_count) }}</span>
                            </td>
                            <td class="py-4 px-3 text-right">
                                <a href="{{ route('admin.corporates.show', $company) }}" class="px-3 py-1.5 bg-primary/10 text-primary font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors inline-flex items-center gap-1">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    <span>Open</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isGroup ? 4 : 5 }}" class="py-10 text-center text-dark/50">
                                @if (request('search'))
                                    {{ $isGroup ? 'No group matches that search.' : 'No company matches that search.' }}
                                @else
                                    {{ $isGroup ? 'No groups yet. Create one to start adding its members.' : 'No corporate accounts yet. Create one to start adding members.' }}
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $companies->links() }}
    </div>
</div>
@endsection
