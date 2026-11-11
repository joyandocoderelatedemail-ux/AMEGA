@extends('layouts.cashier')

@section('title', 'Corporate A/R - Cashier')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $buckets = \App\Support\CorporateReceivables::BUCKETS;
    $peso = fn (float $amount): string => $amount > 0 ? '₱'.number_format($amount, 2) : '—';
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Corporate A/R</h1>
        <p class="mt-1 text-sm text-slate-500">What each company with a credit account owes on its ticket bookings, by how late it is. Open a company to record a payment or print its statement.</p>
    </div>

    @if ($rows->isEmpty())
        <div class="{{ $card }} p-10 text-center">
            <p class="text-sm font-semibold text-slate-900">No company has a credit account yet.</p>
            <p class="mt-1 text-sm text-slate-500">An admin turns one on from the company's page (Users, then Corporates).</p>
        </div>
    @else
        <div class="{{ $card }} overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Company</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Terms</th>
                        @foreach ($buckets as $label)
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right whitespace-nowrap">{{ $label }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Total owed</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Credit limit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as ['company' => $company, 'aging' => $aging])
                        @php $late = $aging['total'] - $aging['current']; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('cashier.receivables.show', $company) }}" class="font-semibold text-navy-700 hover:underline">{{ $company->company_name }}</a>
                                @unless ($company->credit_enabled)
                                    <span class="block text-xs text-slate-500">Credit turned off</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3 text-slate-700 whitespace-nowrap">Net {{ $company->credit_terms_days }}</td>
                            @foreach (array_keys($buckets) as $key)
                                <td class="px-4 py-3 text-right whitespace-nowrap {{ $key !== 'current' && $aging[$key] > 0 ? 'font-semibold text-rose-700' : 'text-slate-700' }}">{{ $peso($aging[$key]) }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-bold text-slate-900 whitespace-nowrap">{{ $peso($aging['total']) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700 whitespace-nowrap">{{ $company->credit_limit !== null ? '₱'.number_format((float) $company->credit_limit, 2) : 'No limit' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900" colspan="2">All companies</td>
                        @foreach (array_keys($buckets) as $key)
                            <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">{{ $peso($totals[$key]) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right font-bold text-slate-900 whitespace-nowrap">{{ $peso($totals['total']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
@endsection
