@extends('layouts.cashier')

@section('title', $company->company_name.' - A/R history')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $kinds = [
        'charge' => ['Charged', 'bg-amber-50 text-amber-800 ring-amber-200'],
        'payment' => ['Payment', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
        'terms' => ['Terms', 'bg-slate-100 text-slate-700 ring-slate-200'],
    ];
@endphp

@section('content')
<div class="space-y-6">
    <a href="{{ route('cashier.receivables.show', $company) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">&larr; {{ $company->company_name }}</a>

    <div>
        <h1 class="text-2xl font-bold text-slate-900">A/R history</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $company->company_name }}: every ticket charged to the account, every payment, and every change to the credit terms, newest first.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="{{ $card }} p-4">
            <div class="text-xs font-semibold text-slate-500">Charged to the account</div>
            <div class="mt-1 text-xl font-bold text-slate-900">₱{{ number_format($charged, 2) }}</div>
        </div>
        <div class="{{ $card }} p-4">
            <div class="text-xs font-semibold text-slate-500">Paid</div>
            <div class="mt-1 text-xl font-bold text-slate-900">₱{{ number_format($paid, 2) }}</div>
        </div>
        <div class="{{ $card }} p-4">
            <div class="text-xs font-semibold text-slate-500">Owed now</div>
            <div class="mt-1 text-xl font-bold {{ $outstanding > 0 ? 'text-slate-900' : 'text-emerald-700' }}">₱{{ number_format($outstanding, 2) }}</div>
        </div>
    </div>

    <div class="{{ $card }} overflow-x-auto">
        @if ($history->isEmpty())
            <p class="px-5 py-6 text-sm text-slate-500">Nothing has gone through this account yet.</p>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-5 py-2.5 text-xs font-semibold text-slate-500">Date</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Entry</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Details</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">By</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500 text-right">Charged</th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-slate-500 text-right">Paid</th>
                        <th class="px-5 py-2.5 text-xs font-semibold text-slate-500 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($history as $row)
                        @php [$kindLabel, $kindClass] = $kinds[$row['kind']]; @endphp
                        <tr class="align-top">
                            <td class="px-5 py-3 whitespace-nowrap text-slate-700">
                                {{ $row['date']->format('M j, Y') }}
                                <span class="block text-xs text-slate-400">{{ $row['date']->format('g:i A') }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold ring-1 {{ $kindClass }}">{{ $kindLabel }}</span>
                                @if ($row['ticket'])
                                    <a href="{{ route('cashier.payments.show', $row['ticket']) }}" class="block mt-1 font-mono font-semibold text-navy-700 hover:underline">{{ $row['reference'] }}</a>
                                @else
                                    <span class="block mt-1 font-mono font-semibold text-slate-900">{{ $row['reference'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-slate-700">
                                {{ $row['detail'] }}
                                @if ($row['applied'])
                                    <div class="mt-1 text-xs text-slate-500">
                                        Applied to:
                                        @foreach ($row['applied'] as $part)
                                            <a href="{{ route('cashier.payments.show', $part['ticket']) }}" class="font-mono text-navy-700 hover:underline">{{ $part['reference'] }}</a> ₱{{ number_format($part['amount'], 2) }}@if (! $loop->last), @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-slate-700 whitespace-nowrap">{{ $row['by'] ?? '—' }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap text-slate-900">{{ $row['charge'] !== null ? '₱'.number_format($row['charge'], 2) : '' }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap text-slate-900">{{ $row['payment'] !== null ? '₱'.number_format($row['payment'], 2) : '' }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap font-semibold text-slate-900">{{ $row['balance'] !== null ? '₱'.number_format($row['balance'], 2) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
