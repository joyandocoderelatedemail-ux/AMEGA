@extends('layouts.admin')

@section('title', 'Edit User Account & Permissions - AMEGA Admin')
@section('page_title', 'Edit Account & Permissions')

@php
    // A staff account is opened from the Agents list, a client from the Clients list.
    $fromStaff = $user->isStaff() && auth()->user()->isAdmin();
    $backUrl = $fromStaff ? route('admin.agents.index') : route('admin.users.index');
    $backLabel = $fromStaff ? 'Staff List' : 'Client List';
    // Category, nationality, address and the travel profile describe a client; a staff account has none of them.
    $isClient = $user->role === 'client';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Edit Account: {{ $user->name }}</h2>
            <p class="text-xs text-dark/50">{{ $isClient ? 'Manage the client\'s details and account role' : 'Manage credentials, staff role, and page access permissions' }}</p>
        </div>
        <a href="{{ $backUrl }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to {{ $backLabel }}</span>
        </a>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    @include('admin.partials._form-errors')

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card">
        <form novalidate data-inline-validation method="POST" action="{{ route('admin.users.update', $user) }}" enctype="multipart/form-data" class="space-y-6"
              x-data="{ role: {{ Js::from(old('role', $user->role)) }} }">
            @csrf
            @method('PUT')

            <!-- Role & Category -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if(Auth::user()->isAdmin())
                    <div>
                        <label for="role" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Role *</label>
                        <select id="role" name="role" x-model="role" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="client" {{ old('role', $user->role) === 'client' ? 'selected' : '' }}>Client / Traveler</option>
                            <option value="agent" {{ old('role', $user->role) === 'agent' ? 'selected' : '' }}>Staff Agent</option>
                            <option value="ticketing" {{ old('role', $user->role) === 'ticketing' ? 'selected' : '' }}>Ticketing Officer</option>
                            <option value="visa_assistance" {{ old('role', $user->role) === 'visa_assistance' ? 'selected' : '' }}>Visa Assistance Officer</option>
                            <option value="srrv" {{ old('role', $user->role) === 'srrv' ? 'selected' : '' }}>SRRV Officer</option>
                            <option value="cashier" {{ old('role', $user->role) === 'cashier' ? 'selected' : '' }}>Cashier</option>
                            <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrator</option>
                        </select>
                    </div>
                @else
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Role</label>
                        <input type="hidden" name="role" value="{{ $user->role }}">
                        <div class="px-3.5 py-2.5 rounded-xl bg-gray-100 border border-gray-200 text-dark/70 text-xs font-bold">
                            {{ ucfirst($user->role) }}
                        </div>
                    </div>
                @endif
                @if ($isClient)
                <div>
                    <label for="account_category" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Category *</label>
                    <select id="account_category" name="account_category" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        @php
                            $currentCategory = old('account_category', $user->account_category);
                            // An account saved under an older category keeps it until staff pick another.
                            $categories = in_array($currentCategory, \App\Models\User::ACCOUNT_CATEGORIES, true) || blank($currentCategory)
                                ? \App\Models\User::ACCOUNT_CATEGORIES
                                : [...\App\Models\User::ACCOUNT_CATEGORIES, $currentCategory];
                        @endphp
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(($currentCategory ?: 'Individual') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                    <input type="hidden" name="account_category" value="{{ $user->account_category ?: 'Individual' }}">
                @endif
            </div>

            @if($user->role === 'client')
                @include('admin.users._corporate', ['selectedCompany' => $user->corporate_account_id])
            @endif

            <!-- Page Access Permissions: only Staff Agents are limited by these; other roles have fixed access -->
            @if(Auth::user()->isAdmin())
            <fieldset x-show="role === 'agent'" :disabled="role !== 'agent'"
                      @if(old('role', $user->role) !== 'agent') style="display: none" disabled @endif
                      class="p-5 rounded-2xl bg-amber-50/60 border border-amber-200/80 space-y-3">
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
                    $pageOptions = \App\Models\User::PAGE_PERMISSIONS;
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
            </fieldset>
            @endif

            {{-- A profile picture is kept for clients only, not staff accounts. --}}
            @if ($isClient)
                @include('admin.users._profile-photo', ['user' => $user])
            @endif

            <!-- Full Legal Name -->
            <div class="space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-primary block">{{ $isClient ? 'Full Legal Name' : 'Full Name' }} *</span>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-4">
                        <label for="first_name" class="block text-[11px] font-bold text-dark/70 mb-1">First / Given Name *</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $user->first_name ?: (str_contains(trim((string) $user->name), ' ') ? \Illuminate\Support\Str::beforeLast(trim($user->name), ' ') : $user->name)) }}" required
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="middle_name" class="block text-[11px] font-bold text-dark/70 mb-1">Middle Name</label>
                        <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="last_name" class="block text-[11px] font-bold text-dark/70 mb-1">Last Name / Surname *</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user->last_name ?: (str_contains(trim((string) $user->name), ' ') ? \Illuminate\Support\Str::afterLast(trim($user->name), ' ') : '')) }}" required
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
                <div x-data="{ none: @js((bool) old('no_email', $user->isClient() && $user->hasPlaceholderEmail())) }">
                    <label for="email" class="block text-[11px] font-bold text-dark/70 mb-1">Email Address <span x-show="!none">*</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->real_email) }}" x-ref="field" :required="!none" :disabled="none"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-gray-100 disabled:text-dark/40">
                    @if($user->isClient())
                        <label class="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold text-dark/60 cursor-pointer">
                            <input type="checkbox" name="no_email" value="1" x-model="none"
                                   @change="if (none) { $refs.field.value = ''; }"
                                   class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span>No email</span>
                        </label>
                    @endif
                </div>
                <div x-data="{ none: @js((bool) old('no_phone', $user->isClient() && blank($user->phone))) }">
                    <label for="phone" class="block text-[11px] font-bold text-dark/70 mb-1">Phone Number <span x-show="!none">*</span></label>
                    <input id="phone" type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="15" autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '')" name="phone" value="{{ old('phone', preg_replace('/\D/', '', (string) $user->phone)) }}" x-ref="field" :required="!none" :disabled="none"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-gray-100 disabled:text-dark/40">
                    @if($user->isClient())
                        <label class="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold text-dark/60 cursor-pointer">
                            <input type="checkbox" name="no_phone" value="1" x-model="none"
                                   @change="if (none) { $refs.field.value = ''; }"
                                   class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span>No number</span>
                        </label>
                    @endif
                </div>
                @if ($isClient)
                <div>
                    <label for="nationality" class="block text-[11px] font-bold text-dark/70 mb-1">Citizenship / Nationality *</label>
                    <x-country-select name="nationality" :value="old('nationality', $user->nationality ?? \App\Support\Countries::DEFAULT)" required />
                </div>
                @endif
            </div>

            @if ($isClient)
            {{-- The street is kept on its own; older records show their full address here until a city is picked. --}}
            <x-ph-address :street="old('address', $user->address_line ?? $user->address)" :province="old('province', $user->province)"
                          :city="old('city', $user->city)" :postal="old('postal_code', $user->postal_code)" />

            @include('admin.users._travel-profile', ['user' => $user])
            @endif

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ $backUrl }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Save Changes & Permissions</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
