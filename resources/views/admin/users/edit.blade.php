@extends('layouts.admin')

@section('title', 'Edit User Account & Permissions - AMEGA Admin')
@section('page_title', 'Edit Account & Permissions')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Edit Account: {{ $user->name }}</h2>
            <p class="text-xs text-dark/50">Manage credentials, staff role, and page access permissions</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Client List</span>
        </a>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Role & Category -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="role" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Role *</label>
                    <select id="role" name="role" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="client" {{ old('role', $user->role) === 'client' ? 'selected' : '' }}>Client / Traveler</option>
                        <option value="agent" {{ old('role', $user->role) === 'agent' ? 'selected' : '' }}>Staff Agent</option>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrator</option>
                    </select>
                </div>
                <div>
                    <label for="account_category" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Category *</label>
                    <select id="account_category" name="account_category" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="Individual" {{ old('account_category', $user->account_category) === 'Individual' ? 'selected' : '' }}>Individual Traveler</option>
                        <option value="Corporate" {{ old('account_category', $user->account_category) === 'Corporate' ? 'selected' : '' }}>Corporate / Group Account</option>
                        <option value="Visa Processing Assistance" {{ old('account_category', $user->account_category) === 'Visa Processing Assistance' ? 'selected' : '' }}>Visa Processing Assistance</option>
                        <option value="Philippine Retirement Visa (SRRV)" {{ old('account_category', $user->account_category) === 'Philippine Retirement Visa (SRRV)' ? 'selected' : '' }}>Philippine Retirement Visa (SRRV)</option>
                    </select>
                </div>
            </div>

            <!-- Page Access Permissions (For Staff Agents) -->
            <div class="p-5 rounded-2xl bg-amber-50/60 border border-amber-200/80 space-y-3">
                <div class="flex items-center justify-between border-b border-amber-200/60 pb-2">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
                            <i data-lucide="shield-check" class="w-4 h-4 text-amber-600"></i>
                            Agent Dashboard Page Access Permissions
                        </span>
                        <p class="text-[11px] text-amber-800/70 mt-0.5">Select which specific pages and modules this staff user can view & access in the dashboard</p>
                    </div>
                </div>

                @php
                    $allowedPages = old('allowed_pages', $user->allowed_pages ?? ['dashboard', 'bookings', 'inquiries', 'users', 'packages', 'destinations']);
                    $pageOptions = [
                        'bookings' => ['Bookings Management', 'Process tour package reservations & statuses', 'calendar'],
                        'inquiries' => ['Inquiries Inbox', 'View and respond to client message inquiries', 'inbox'],
                        'users' => ['Client Directory & Entry', 'Access client profiles and add new traveler records', 'users'],
                        'packages' => ['Travel Packages', 'View and manage available travel itineraries', 'package'],
                        'destinations' => ['Destinations', 'View domestic and international destinations', 'map-pin'],
                        'services' => ['Services Management', 'Edit and manage agency core service offers', 'briefcase'],
                        'testimonials' => ['Testimonials', 'Manage client reviews and feedback', 'message-square'],
                    ];
                @endphp

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    @foreach($pageOptions as $pageKey => [$label, $desc, $icon])
                        <label class="flex items-start gap-3 p-3 rounded-xl bg-white border border-amber-200/80 hover:border-amber-400 cursor-pointer transition-all">
                            <input type="checkbox" name="allowed_pages[]" value="{{ $pageKey }}" 
                                   {{ in_array($pageKey, $allowedPages) ? 'checked' : '' }}
                                   class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                            <div>
                                <div class="text-xs font-bold text-dark flex items-center gap-1.5">
                                    <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5 text-primary"></i>
                                    <span>{{ $label }}</span>
                                </div>
                                <p class="text-[10px] text-dark/50 mt-0.5">{{ $desc }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Full Legal Name -->
            <div class="space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-primary block">Full Legal Name *</span>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-4">
                        <label for="first_name" class="block text-[11px] font-bold text-dark/70 mb-1">First / Given Name *</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="middle_name" class="block text-[11px] font-bold text-dark/70 mb-1">Middle Name</label>
                        <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="last_name" class="block text-[11px] font-bold text-dark/70 mb-1">Last Name / Surname *</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="suffix" class="block text-[11px] font-bold text-dark/70 mb-1">Suffix</label>
                        <select id="suffix" name="suffix" class="w-full px-2.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">None</option>
                            <option value="Jr." {{ old('suffix', $user->suffix) === 'Jr.' ? 'selected' : '' }}>Jr.</option>
                            <option value="Sr." {{ old('suffix', $user->suffix) === 'Sr.' ? 'selected' : '' }}>Sr.</option>
                            <option value="III" {{ old('suffix', $user->suffix) === 'III' ? 'selected' : '' }}>III</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Contact & Address -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="email" class="block text-[11px] font-bold text-dark/70 mb-1">Email Address *</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label for="phone" class="block text-[11px] font-bold text-dark/70 mb-1">Phone Number *</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label for="nationality" class="block text-[11px] font-bold text-dark/70 mb-1">Citizenship / Nationality *</label>
                    <input id="nationality" type="text" name="nationality" value="{{ old('nationality', $user->nationality ?? 'Filipino') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <div>
                <label for="address" class="block text-[11px] font-bold text-dark/70 mb-1">Residential Address</label>
                <input id="address" type="text" name="address" value="{{ old('address', $user->address) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Save Changes & Permissions</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
