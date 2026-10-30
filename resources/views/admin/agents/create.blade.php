@extends('layouts.admin')

@section('title', 'Add Staff Account - AMEGA Admin')
@section('page_title', 'Register New Staff Account')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Register New Staff Account</h2>
            <p class="text-xs text-dark/50">Choose the role, create the login and, if needed, add extra permissions for this person</p>
        </div>
        <a href="{{ route('admin.agents.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Agent List</span>
        </a>
    </div>

    @include('admin.partials._form-errors')

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card">
        {{-- Typed in capitals, like the client forms (the email is left as typed). --}}
        @include('admin.partials._uppercase-inputs')
        <form method="POST" data-uppercase action="{{ route('admin.agents.store') }}" class="space-y-6"
              x-data="{ accessRole: {{ Js::from((string) old('access_role', '')) }} }">
            @csrf

            <!-- Role: one choice sets the kind of account and what it can do (Users > Roles & Permissions) -->
            @include('admin.agents._role-select', ['current' => ''])

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
                    <label for="email_name" class="block text-[11px] font-bold text-dark/70 mb-1">Agent Login Email *</label>
                    <div class="flex">
                        <input id="email_name" type="text" name="email_name" data-keep-case value="{{ old('email_name', \Illuminate\Support\Str::before((string) old('email'), '@')) }}" required
                               autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="64" pattern="[A-Za-z0-9._\-]+" title="Letters, numbers, dots, dashes and underscores only"
                               class="min-w-0 flex-1 px-3.5 py-2.5 rounded-l-xl bg-white border border-r-0 border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                               placeholder="test">
                        <span class="inline-flex items-center px-3 rounded-r-xl bg-gray-100 border border-gray-200 text-dark/70 text-xs font-bold whitespace-nowrap">&#64;{{ \App\Http\Controllers\Admin\AdminAgentController::STAFF_EMAIL_DOMAIN }}</span>
                    </div>
                    <p class="text-[10px] text-dark/50 mt-1">Type only the name. They sign in with it plus &#64;{{ \App\Http\Controllers\Admin\AdminAgentController::STAFF_EMAIL_DOMAIN }}.</p>
                    @error('email') <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                    @error('email_name') <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="block text-[11px] font-bold text-dark/70 mb-1">Phone Number *</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="+63 918 888 9999">
                </div>
                <div>
                    <label for="password" class="block text-[11px] font-bold text-dark/70 mb-1">Login Password *</label>
                    <input id="password" type="password" name="password" required minlength="6"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="At least 6 characters">
                </div>
            </div>

            <!-- Extra permissions on top of the role (an administrator has everything) -->
            <div x-show="accessRole && accessRole !== 'admin'" x-cloak class="p-5 rounded-2xl bg-emerald-50/60 border border-emerald-200/80 space-y-3">
                <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-1.5">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                            Extra permissions
                        </span>
                        <p class="text-[11px] text-emerald-800/70 mt-0.5">On top of the role, for this person only (optional)</p>
                    </div>
                </div>

                @include('admin.agents._access', ['agent' => null, 'withRole' => false])
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.agents.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Save Staff Account</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
