@extends('layouts.admin')

@section('title', 'Add Client Record - AMEGA Admin')
@section('page_title', 'Input New Client Record')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Staff Manual Client Entry</h2>
            <p class="text-xs text-dark/50">Register customer credentials, passport information, and category</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Client List</span>
        </a>
    </div>

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            <!-- Role & Category -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="role" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Role *</label>
                    <select id="role" name="role" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="client">Client / Traveler</option>
                        @if(Auth::user()->isAdmin())
                            <option value="agent">Staff Agent</option>
                            <option value="admin">Administrator</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label for="account_category" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Category *</label>
                    <select id="account_category" name="account_category" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="Individual">Individual Traveler</option>
                        <option value="Corporate">Corporate / Group Account</option>
                        <option value="Visa Processing Assistance">Visa Processing Assistance</option>
                        <option value="Philippine Retirement Visa (SRRV)">Philippine Retirement Visa (SRRV)</option>
                    </select>
                </div>
            </div>

            <!-- Full Legal Name -->
            <div class="space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider text-primary block">Full Legal Name *</span>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-4">
                        <label for="first_name" class="block text-[11px] font-bold text-dark/70 mb-1">First / Given Name *</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                               placeholder="Juan">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="middle_name" class="block text-[11px] font-bold text-dark/70 mb-1">Middle Name</label>
                        <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                               placeholder="Dela Cruz">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="last_name" class="block text-[11px] font-bold text-dark/70 mb-1">Last Name / Surname *</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                               placeholder="Santos">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="suffix" class="block text-[11px] font-bold text-dark/70 mb-1">Suffix</label>
                        <select id="suffix" name="suffix" class="w-full px-2.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="III">III</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Contact & Address -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="email" class="block text-[11px] font-bold text-dark/70 mb-1">Email Address *</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="juan@example.com">
                </div>
                <div>
                    <label for="phone" class="block text-[11px] font-bold text-dark/70 mb-1">Phone Number *</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="+63 912 345 6789">
                </div>
                <div>
                    <label for="nationality" class="block text-[11px] font-bold text-dark/70 mb-1">Citizenship / Nationality *</label>
                    <input id="nationality" type="text" name="nationality" value="{{ old('nationality', 'Filipino') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="Filipino">
                </div>
            </div>

            <div>
                <label for="address" class="block text-[11px] font-bold text-dark/70 mb-1">Residential Address *</label>
                <input id="address" type="text" name="address" value="{{ old('address') }}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                       placeholder="House/Unit No., Street, Barangay, City, Province">
            </div>

            <!-- Passport & Identification -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
                <span class="text-xs font-bold uppercase tracking-wider text-primary block">Passport & Government Credentials (Optional)</span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="passport_number" class="block text-[11px] font-bold text-dark/70 mb-1">Passport Number</label>
                        <input id="passport_number" type="text" name="passport_number" value="{{ old('passport_number') }}"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                               placeholder="P1234567A">
                    </div>
                    <div>
                        <label for="passport_expiry_date" class="block text-[11px] font-bold text-dark/70 mb-1">Passport Expiry Date</label>
                        <input id="passport_expiry_date" type="date" name="passport_expiry_date" value="{{ old('passport_expiry_date') }}"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label for="government_id_number" class="block text-[11px] font-bold text-dark/70 mb-1">Government ID No.</label>
                        <input id="government_id_number" type="text" name="government_id_number" value="{{ old('government_id_number') }}"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                               placeholder="UMID / SSS / DL">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Save Client Record</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
