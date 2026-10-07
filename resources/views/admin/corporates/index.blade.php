@extends('layouts.admin')

@section('title', 'Corporate Accounts - AMEGA Admin')
@section('page_title', 'Corporate Accounts')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Corporate Accounts</h2>
            <p class="text-xs text-dark/50">Companies that book travel for several people, and the clients under each</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('admin.corporates.index') }}" class="relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search company, contact, TIN..."
                       class="w-64 pl-9 pr-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3 top-2.5"></i>
            </form>
            <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all flex items-center gap-1.5">
                <i data-lucide="users" class="w-4 h-4"></i>
                <span>All Clients</span>
            </a>
            <a href="{{ route('admin.corporates.create') }}" class="px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-1.5">
                <i data-lucide="building-2" class="w-4 h-4"></i>
                <span>New Corporate Account</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Company</th>
                        <th class="pb-3 px-3">Contact Person</th>
                        <th class="pb-3 px-3">Registration / TIN</th>
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
                            <td class="py-4 px-3">
                                <div class="font-mono text-dark">{{ $company->registration_number ?? 'N/A' }}</div>
                                <div class="text-[11px] text-dark/50 font-mono">TIN: {{ $company->tin ?? 'N/A' }}</div>
                            </td>
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
                            <td colspan="5" class="py-10 text-center text-dark/50">
                                {{ request('search') ? 'No company matches that search.' : 'No corporate accounts yet. Create one to start adding members.' }}
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
