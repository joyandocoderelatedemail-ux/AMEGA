@extends('layouts.ticketing')

@section('title', 'Register Client - AMEGA')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-dark tracking-tight">Register Client</h1>
            <p class="text-xs text-dark/50 mt-1">Saved once, then used to fill in this and every later booking for the client.</p>
        </div>
        <a href="{{ route('ticketing.tickets.create') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gray-100 text-dark/70 font-semibold text-xs hover:bg-gray-200 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to New Booking</span>
        </a>
    </div>

    @if($errors->any())
        <div role="alert" class="p-4 rounded-xl bg-rose-50 text-rose-800 text-sm border border-rose-200">
            <p class="font-bold">The client was not saved. Please check the following:</p>
            <ul class="mt-1 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <form method="POST" action="{{ route('ticketing.clients.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="sm:w-1/2">
                <label for="account_category" class="block text-[11px] font-bold text-dark/70 mb-1">Account Category *</label>
                <select id="account_category" name="account_category" required
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    @foreach(['Individual' => 'Individual Traveler', 'Corporate' => 'Corporate / Group Account'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('account_category', 'Individual') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @include('admin.users._identity')

            @include('admin.users._travel-profile', ['birthDateRequired' => true])

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('ticketing.tickets.create') }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-semibold text-xs rounded-lg hover:bg-gray-200 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold text-xs rounded-lg hover:bg-primary-dark transition-colors shadow-md flex items-center gap-2">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                    <span>Save &amp; Continue to Booking</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
