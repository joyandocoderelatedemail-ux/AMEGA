@extends('layouts.admin')

@section('title', 'Registered Accounts - AMEGA Admin')
@section('page_title', 'Registered Client Accounts')

@section('content')
<div class="space-y-6">
    
    <!-- Action Header & Filters -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Client & Registered Accounts Directory</h2>
            <p class="text-xs text-dark/50">Inspect personal info, addresses, passport records, ID photos & signatures</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-1.5 shrink-0">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>Add Client Record</span>
            </a>

            <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2 flex-1 md:flex-initial">
                <select name="category" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs font-bold text-dark focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">All Categories</option>
                    <option value="Individual" {{ request('category') === 'Individual' ? 'selected' : '' }}>Individual</option>
                    <option value="Family" {{ request('category') === 'Family' ? 'selected' : '' }}>Family</option>
                    <option value="Corporate" {{ request('category') === 'Corporate' ? 'selected' : '' }}>Corporate</option>
                    <option value="Agency" {{ request('category') === 'Agency' ? 'selected' : '' }}>Agency</option>
                </select>

                <div class="relative flex-1 md:w-64">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, passport..."
                           class="w-full pl-9 pr-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3 top-2.5"></i>
                </div>
            </form>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm overflow-hidden space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">User / Account</th>
                        <th class="pb-3 px-3">Category</th>
                        <th class="pb-3 px-3">Contact & Address</th>
                        <th class="pb-3 px-3">Passport & ID Records</th>
                        <th class="pb-3 px-3">ID Photo & Signature</th>
                        <th class="pb-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-3">
                                <div class="flex items-center gap-3">
                                    @if($user->profile_photo_url)
                                        <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full object-cover border border-primary/20 shrink-0">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-navy text-accent font-bold text-sm flex items-center justify-center shrink-0">
                                            {{ substr($user->full_name, 0, 1) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-bold text-dark text-sm">{{ $user->full_name }}</div>
                                        <div class="text-[11px] text-dark/50">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-3">
                                <span class="px-2.5 py-1 rounded-full font-bold text-[10px] uppercase tracking-wider {{ $user->account_category === 'Corporate' ? 'bg-indigo-100 text-indigo-800' : ($user->account_category === 'Agency' ? 'bg-amber-100 text-amber-800' : 'bg-primary/10 text-primary') }}">
                                    {{ $user->account_category ?? 'Individual' }}
                                </span>
                            </td>

                            <td class="py-4 px-3">
                                <div class="font-semibold text-dark">{{ $user->phone ?? 'N/A' }}</div>
                                <div class="text-[11px] text-dark/50 max-w-xs truncate">{{ $user->address ?? ($user->city ? $user->city . ', ' . $user->country : 'No Address') }}</div>
                            </td>

                            <td class="py-4 px-3">
                                <div class="font-mono text-dark">Passport: {{ $user->passport_number ?? 'Not Provided' }}</div>
                                <div class="text-[11px] text-dark/50">{{ $user->government_id_type ?? 'ID' }}: {{ $user->government_id_number ?? 'N/A' }}</div>
                            </td>

                            <td class="py-4 px-3">
                                <div class="flex items-center gap-2">
                                    @if($user->government_id_photo_url)
                                        <a href="{{ $user->government_id_photo_url }}" target="_blank" class="p-1.5 rounded bg-emerald-50 text-emerald-700 font-bold text-[10px] border border-emerald-200 hover:bg-emerald-100 flex items-center gap-1" title="View ID Document Photo">
                                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                                            <span>ID File</span>
                                        </a>
                                    @else
                                        <span class="text-[10px] text-dark/40 italic">No ID photo</span>
                                    @endif

                                    @if($user->signature)
                                        <div class="h-7 w-16 bg-gray-50 border border-gray-200 rounded p-0.5 flex items-center justify-center overflow-hidden" title="E-Signature Preview">
                                            <img src="{{ $user->signature }}" alt="E-Signature" class="max-h-full object-contain">
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <td class="py-4 px-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.users.show', $user) }}" class="px-3 py-1.5 bg-primary/10 text-primary font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors flex items-center gap-1">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        <span>View</span>
                                    </a>

                                    @if(Auth::user()->isAdmin())
                                        <a href="{{ route('admin.users.edit', $user) }}" class="p-1.5 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Edit Account & Page Permissions">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </a>
                                    @endif

                                    @if(!$user->isAdmin())
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this account permanently?');" class="inline-flex m-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Account">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-dark/40 font-medium">
                                No registered client accounts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
