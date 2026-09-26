@extends('layouts.admin')

@section('title', 'Add Client Record - AMEGA Admin')
@section('page_title', 'Input New Client Record')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Staff Manual Client Entry</h2>
            <p class="text-xs text-dark/50">Register the client's contact, travel documents and emergency contact</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-full hover:bg-gray-200 transition-all flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Client List</span>
        </a>
    </div>

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Role & Category -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="role" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Account Role *</label>
                    <select id="role" name="role" required class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="client">Client / Traveler</option>
                        @if(Auth::user()->isAdmin())
                            <option value="agent">Staff Agent</option>
                            <option value="ticketing">Ticketing Officer</option>
                            <option value="visa_assistance">Visa Assistance Officer</option>
                            <option value="srrv">SRRV Officer</option>
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

            @include('admin.users._identity')

            @include('admin.users._travel-profile')

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
