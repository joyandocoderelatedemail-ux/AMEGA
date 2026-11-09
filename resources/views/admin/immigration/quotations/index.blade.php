@extends('layouts.immigration')

@section('title', 'Quotations - AMEGA Admin')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $tones = [
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    $filters = ['' => 'All', 'open' => 'Open', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired'];
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Quotations</h1>
            <p class="mt-1 text-sm text-slate-500">Price a client's services before opening a file. Print or email it, then open each service's file once they agree.</p>
        </div>
        <a href="{{ route('admin.immigration.quotations.create') }}" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors shrink-0">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>New quotation</span>
        </a>
    </div>

    <form method="GET" action="{{ route('admin.immigration.quotations.index') }}" class="{{ $card }} p-4 flex flex-col sm:flex-row gap-3" role="search">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="search" name="q" value="{{ $term }}" placeholder="Quote number, client, passport, email or phone" aria-label="Search quotations"
               class="flex-1 h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500">
        <button type="submit" class="h-10 px-4 rounded-lg bg-slate-800 text-sm font-semibold text-white hover:bg-slate-900">Search</button>
    </form>

    <nav class="flex flex-wrap gap-2" aria-label="Quotation status">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.immigration.quotations.index', array_filter(['status' => $key, 'q' => $term])) }}"
               @if ($status === $key) aria-current="page" @endif
               class="inline-flex items-center h-9 px-4 rounded-full text-sm font-semibold border transition-colors {{ $status === $key ? 'bg-navy-700 border-navy-700 text-white' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($quotations->isEmpty())
        <div class="{{ $card }} p-10 text-center">
            <p class="text-sm font-semibold text-slate-900">No quotations {{ $term !== '' || $status !== '' ? 'match' : 'yet' }}.</p>
            <p class="mt-1 text-sm text-slate-500">Start one with New quotation.</p>
        </div>
    @else
        <div class="{{ $card }} overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Quotation</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Client</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Total</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Valid until</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Prepared by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($quotations as $quotation)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('admin.immigration.quotations.show', $quotation) }}" class="font-mono font-semibold text-navy-700 hover:underline">{{ $quotation->quote_number }}</a>
                                <div class="text-xs text-slate-500">{{ $quotation->created_at->format('M j, Y') }} &middot; {{ $quotation->items_count }} {{ \Illuminate\Support\Str::plural('line', $quotation->items_count) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $quotation->client_name }}</div>
                                <div class="text-xs text-slate-500">{{ collect([$quotation->passport_number ? 'Passport '.$quotation->passport_number : null, $quotation->client_phone])->filter()->implode(' · ') }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">₱{{ number_format((float) $quotation->total, 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ $quotation->valid_until->format('M j, Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ring-1 {{ $tones[$quotation->statusTone()] }}">{{ $quotation->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $quotation->createdBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $quotations->links() }}
    @endif
</div>
@endsection
