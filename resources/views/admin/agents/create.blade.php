@extends('layouts.admin')

@section('title', 'Add Travel Agent - AMEGA Admin')
@section('page_title', 'Register New Travel Agent')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Register New Travel Agent</h2>
            <p class="text-xs text-dark/50">Create agent credentials and select allowed dashboard page access</p>
        </div>
        <a href="{{ route('admin.agents.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Agent List</span>
        </a>
    </div>

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <form method="POST" action="{{ route('admin.agents.store') }}" class="space-y-6">
            @csrf

            <!-- Agent Credentials -->
            <div class="space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 block">Agent Staff Credentials *</span>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-4">
                        <label for="first_name" class="block text-[11px] font-bold text-dark/70 mb-1">First Name *</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="middle_name" class="block text-[11px] font-bold text-dark/70 mb-1">Middle Name</label>
                        <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="last_name" class="block text-[11px] font-bold text-dark/70 mb-1">Last Name *</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="suffix" class="block text-[11px] font-bold text-dark/70 mb-1">Suffix</label>
                        <select id="suffix" name="suffix" class="w-full px-2.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Email, Phone & Initial Password -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="email" class="block text-[11px] font-bold text-dark/70 mb-1">Agent Login Email *</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="agent@amegatravel.com">
                </div>
                <div>
                    <label for="phone" class="block text-[11px] font-bold text-dark/70 mb-1">Phone Number *</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="+63 918 888 9999">
                </div>
                <div>
                    <label for="password" class="block text-[11px] font-bold text-dark/70 mb-1">Login Password *</label>
                    <input id="password" type="password" name="password" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="••••••••">
                </div>
            </div>

            <!-- Page Access Permissions -->
            <div class="p-5 rounded-2xl bg-emerald-50/60 border border-emerald-200/80 space-y-3">
                <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-1.5">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                            Allowed Dashboard Pages & Modules
                        </span>
                        <p class="text-[11px] text-emerald-800/70 mt-0.5">Select which specific pages this Agent staff user is permitted to access</p>
                    </div>
                </div>

                @php
                    $defaultAllowed = ['bookings', 'inquiries', 'users', 'packages', 'destinations'];
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
                        <label class="flex items-start gap-3 p-3 rounded-xl bg-white border border-emerald-200/80 hover:border-emerald-400 cursor-pointer transition-all">
                            <input type="checkbox" name="allowed_pages[]" value="{{ $pageKey }}" 
                                   {{ in_array($pageKey, old('allowed_pages', $defaultAllowed)) ? 'checked' : '' }}
                                   class="mt-0.5 w-4 h-4 rounded text-emerald-600 border-gray-300 focus:ring-emerald-500">
                            <div>
                                <div class="text-xs font-bold text-dark flex items-center gap-1.5">
                                    <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    <span>{{ $label }}</span>
                                </div>
                                <p class="text-[10px] text-dark/50 mt-0.5">{{ $desc }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.agents.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Save Agent Account</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
