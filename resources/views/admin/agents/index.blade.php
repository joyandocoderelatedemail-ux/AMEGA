@extends('layouts.admin')

@section('title', 'Travel Agents Directory - AMEGA Admin')
@section('page_title', 'Travel Agent Staff Accounts')

@section('content')
<div class="space-y-6">
    
    <!-- Action Header & Filters -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <span>Travel Agents Directory</span>
            </h2>
            <p class="text-xs text-dark/50">Manage staff agent accounts, credentials, and allowed dashboard page permissions</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <a href="{{ route('admin.agents.create') }}" class="px-4 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-all shadow-md flex items-center gap-1.5 shrink-0">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>Add Travel Agent</span>
            </a>

            <form method="GET" action="{{ route('admin.agents.index') }}" class="flex flex-wrap items-center gap-2 flex-1 md:flex-initial">
                <div class="relative flex-1 md:w-64">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search agent name, email..."
                           class="w-full pl-9 pr-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3 top-2.5"></i>
                </div>
            </form>
        </div>
    </div>

    <!-- Agent Accounts Table -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm overflow-hidden space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Agent Staff Member</th>
                        <th class="pb-3 px-3">Role</th>
                        <th class="pb-3 px-3">Contact Number</th>
                        <th class="pb-3 px-3">Allowed Dashboard Pages</th>
                        <th class="pb-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($agents as $agent)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-emerald-600 text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-sm">
                                        {{ substr($agent->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-dark text-sm">{{ $agent->name }}</div>
                                        <div class="text-[11px] text-dark/50">{{ $agent->email }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-3">
                                <span class="px-2.5 py-1 rounded-full font-bold text-[10px] uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    Travel Agent
                                </span>
                            </td>

                            <td class="py-4 px-3">
                                <div class="font-semibold text-dark">{{ $agent->phone ?? 'N/A' }}</div>
                            </td>

                            <td class="py-4 px-3">
                                <div class="flex flex-wrap gap-1 max-w-md">
                                    @php
                                        $pages = $agent->allowed_pages ?? ['dashboard', 'bookings', 'inquiries', 'users', 'packages', 'destinations'];
                                    @endphp
                                    @foreach($pages as $p)
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-dark/70 text-[10px] font-semibold border border-gray-200 uppercase tracking-wider">
                                            {{ $p }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <td class="py-4 px-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.agents.edit', $agent) }}" class="px-3 py-1.5 bg-emerald-100 text-emerald-800 font-bold text-[11px] rounded-lg hover:bg-emerald-600 hover:text-white transition-colors flex items-center gap-1" title="Configure Agent Permissions">
                                        <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                        <span>Permissions</span>
                                    </a>

                                    <form method="POST" action="{{ route('admin.agents.destroy', $agent) }}" onsubmit="return confirm('Delete this agent account permanently?');" class="inline-flex m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Agent Account">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-dark/40 font-medium">
                                No travel agent staff accounts found. Click "Add Travel Agent" to register staff.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $agents->links() }}
        </div>
    </div>
</div>
@endsection
