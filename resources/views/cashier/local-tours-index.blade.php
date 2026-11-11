@extends('layouts.cashier')

@section('title', 'Local tours - Cashier - AMEGA')

{{-- Local tour bookings an approver approved, with money to collect (or, ?paid=1, paid in full). --}}
@php
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $money = fn ($file, $amount) => '₱'.number_format((float) $amount, 2);
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Local tours</h1>
            <div class="mt-2 inline-flex rounded-lg bg-slate-100 p-1">
                <a href="{{ route('cashier.local-tours.index') }}" class="h-8 px-3 inline-flex items-center rounded-md text-sm font-semibold {{ $paid ? 'text-slate-500 hover:text-slate-900' : 'bg-white text-slate-900 shadow-sm' }}" @unless ($paid) aria-current="page" @endunless>To collect</a>
                <a href="{{ route('cashier.local-tours.index', ['paid' => 1]) }}" class="h-8 px-3 inline-flex items-center rounded-md text-sm font-semibold {{ $paid ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}" @if ($paid) aria-current="page" @endif>Paid</a>
            </div>
        </div>
        <form method="GET" class="flex flex-wrap gap-2" role="search">
            @if ($paid)<input type="hidden" name="paid" value="1">@endif
            <label for="cashier-local-tours-search" class="sr-only">Search local tour bookings</label>
            <input id="cashier-local-tours-search" type="search" name="search" value="{{ request('search') }}" placeholder="Reference or lead guest" class="{{ $control }} w-64">
            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($files->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">{{ request('search') ? 'No local tour bookings match your search.' : ($paid ? 'No paid local tour bookings yet.' : 'No approved local tour bookings are waiting for payment.') }}</p>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Lead guest</th>
                            <th class="px-5 py-3">Tour</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-right">Paid</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($files as $file)
                            @php $owed = $file->outstandingBalance(); @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <div class="font-mono text-xs font-semibold text-slate-900">{{ $file->reference }}</div>
                                    <div class="text-xs text-slate-500">Agent: {{ $file->creator?->name ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3 text-slate-900">{{ $file->lead_guest_name }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-900">{{ $file->tourLabel() }}</div>
                                    <div class="text-xs text-slate-500">{{ collect([$file->travel_date?->format('M j, Y'), $file->status_label])->filter()->implode(' · ') }}</div>
                                </td>
                                <td class="px-5 py-3 text-right text-slate-900 whitespace-nowrap">{{ $money($file, $file->total_amount) }}</td>
                                <td class="px-5 py-3 text-right text-emerald-700 whitespace-nowrap">{{ $money($file, $file->amount_paid) }}</td>
                                <td class="px-5 py-3 text-right font-semibold whitespace-nowrap {{ $owed > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $money($file, $owed) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('cashier.local-tours.show', $file->id) }}"
                                       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg {{ $owed > 0 ? 'bg-navy-700 text-white hover:bg-navy-800' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }} text-sm font-semibold whitespace-nowrap">
                                        <i class="fa-solid {{ $owed > 0 ? 'fa-cash-register' : 'fa-receipt' }}" aria-hidden="true"></i>
                                        <span>{{ $owed > 0 ? 'Record payment' : 'View' }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($files->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">{{ $files->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
