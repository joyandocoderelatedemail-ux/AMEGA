@extends('layouts.srrv')

@section('title', 'SRRV Desk - AMEGA Travel and Tours')

@php
    /**
     * Same tokens as the ticketing dashboard: rounded-xl cards, rounded-lg
     * controls, rounded-full pills, weights 400/600/700, nothing below text-xs.
     *
     * Headings deliberately omit `font-heading`: app.css forces weight 900 on
     * `h1.font-heading` / `h2.font-heading`, and the element selector already
     * gives them Montserrat.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $statLabel = 'text-sm font-semibold text-slate-600';
    $statFigure = 'font-heading text-2xl font-bold text-slate-900 tracking-tight tabular-nums';
    $th = 'px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';
    $td = 'px-4 py-3 text-sm text-slate-700';
    $pill = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset capitalize whitespace-nowrap';
    $classPill = fn (bool $isCourtesy) => $isCourtesy ? 'bg-amber-50 text-amber-800 ring-amber-600/20' : 'bg-navy-50 text-navy-700 ring-navy-700/20';
@endphp

@section('content')
<div class="space-y-6">

    <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">SRRV Desk</h1>
        <p class="mt-1 text-sm text-slate-500">Renewal applications, annual renewals and re-stamping.</p>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Total Files</span>
                <i data-lucide="folder-open" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ $stats['total'] }}</div>
        </div>
        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Classic / Courtesy</span>
                <i data-lucide="split" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">
                {{ $stats['classic'] }}<span class="text-slate-300">/</span>{{ $stats['courtesy'] }}
            </div>
        </div>
        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Awaiting Oath</span>
                <i data-lucide="user-check" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ $stats['awaitingOath'] }}</div>
        </div>
        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Renewals Open</span>
                <i data-lucide="calendar-check" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ $stats['renewalsDue'] }}</div>
        </div>
    </div>

    <!-- The Three Jobs -->
    <section class="space-y-3">
        <h2 class="text-base font-semibold text-slate-900">Jobs &amp; Pipelines</h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach($jobs as $job)
                <div class="{{ $card }} p-5 flex flex-col">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                            <i data-lucide="{{ $job['icon'] }}" class="w-5 h-5"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-900">{{ $job['label'] }}</h3>
                            @isset($job['count'])
                                <span class="text-xs text-slate-500">{{ $job['count'] }} open</span>
                            @endisset
                        </div>
                    </div>

                    <p class="text-sm text-slate-500 mb-4">{{ $job['blurb'] }}</p>

                    <!-- Pipeline -->
                    <div class="flex flex-wrap items-center gap-1.5 mb-4">
                        @foreach($job['stages'] as $stage)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-600 capitalize">
                                {{ str_replace('_', ' ', $stage) }}
                            </span>
                            @if(! $loop->last)
                                <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300"></i>
                            @endif
                        @endforeach
                    </div>

                    <ul class="mt-auto space-y-1.5 pt-4 border-t border-slate-100">
                        @foreach($job['notes'] as $note)
                            <li class="flex items-start gap-2 text-xs text-slate-500 leading-relaxed">
                                <i data-lucide="dot" class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5"></i>
                                <span>{{ $note }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Recent Files -->
        <div class="lg:col-span-2 {{ $card }} overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Recent Retiree Files</h2>
                <span class="text-xs text-slate-500">Latest 6</span>
            </div>

            @if($recentApplications->isEmpty())
                <div class="px-5 py-12 text-center">
                    <i data-lucide="folder-open" class="w-8 h-8 text-slate-300 mx-auto mb-3"></i>
                    <p class="text-sm font-semibold text-slate-900">No retiree files yet.</p>
                    <p class="mt-1 text-sm text-slate-500">Renewal applications and re-stamping jobs will appear here.</p>
                </div>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="{{ $th }}">Reference</th>
                                <th class="{{ $th }}">Retiree</th>
                                <th class="{{ $th }}">Class</th>
                                <th class="{{ $th }}">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentApplications as $application)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="{{ $td }}">
                                        <a href="{{ route('srrv.applications.show', $application) }}"
                                           class="font-semibold text-navy-700 hover:text-navy-900 hover:underline">{{ $application->reference }}</a>
                                    </td>
                                    <td class="{{ $td }}">{{ $application->retiree_name }}</td>
                                    <td class="{{ $td }}">
                                        <span class="{{ $pill }} {{ $classPill($application->isCourtesy()) }}">{{ $application->visa_class }}</span>
                                    </td>
                                    <td class="{{ $td }} text-slate-600 capitalize">{{ str_replace('_', ' ', $application->status) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Published Pricing -->
        <div class="{{ $card }} overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Published Fees</h2>
                <p class="mt-0.5 text-sm text-slate-500">Only confirmed desk prices</p>
            </div>

            @if($pricing->isEmpty())
                <div class="px-5 py-10 text-center">
                    <i data-lucide="receipt" class="w-7 h-7 text-slate-300 mx-auto mb-2"></i>
                    <p class="text-sm text-slate-500">No confirmed fees published yet.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($pricing as $tier)
                        <li class="px-5 py-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $tier->label }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 capitalize">{{ $tier->visa_class }}</p>
                                </div>
                                <span class="text-sm font-semibold text-slate-900 whitespace-nowrap tabular-nums">
                                    {{ $tier->currency }} {{ number_format((float) $tier->amount, 2) }}
                                </span>
                            </div>
                            @if($tier->condition_notes)
                                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ $tier->condition_notes }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <!-- Recent Renewals -->
    <div class="{{ $card }} overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Recent Renewals</h2>
                <p class="mt-0.5 text-sm text-slate-500">Due once a year, for as long as the retiree stays</p>
            </div>
            <a href="{{ route('srrv.renewals.index') }}"
               class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                <span>Renewal Ledger</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        @if($recentRenewals->isEmpty())
            <div class="px-5 py-10 text-center">
                <i data-lucide="calendar-check" class="w-7 h-7 text-slate-300 mx-auto mb-2"></i>
                <p class="text-sm text-slate-500">No renewals recorded yet.</p>
            </div>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="{{ $th }}">Reference</th>
                            <th class="{{ $th }}">Retiree</th>
                            <th class="{{ $th }}">Class</th>
                            <th class="{{ $th }}">Years</th>
                            <th class="{{ $th }} text-right">Fee</th>
                            <th class="{{ $th }}">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($recentRenewals as $renewal)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="{{ $td }}">
                                    <a href="{{ route('srrv.renewals.show', $renewal) }}"
                                       class="font-semibold text-navy-700 hover:text-navy-900 hover:underline">{{ $renewal->reference }}</a>
                                </td>
                                <td class="{{ $td }}">{{ $renewal->retiree_name }}</td>
                                <td class="{{ $td }}">
                                    <span class="{{ $pill }} {{ $classPill($renewal->visa_class === 'courtesy') }}">{{ $renewal->visa_class }}</span>
                                </td>
                                <td class="{{ $td }} tabular-nums">{{ $renewal->years_paid }}</td>
                                <td class="{{ $td }} text-right font-semibold text-slate-900 whitespace-nowrap tabular-nums">
                                    {{ $renewal->currency }} {{ number_format((float) $renewal->fee_amount, 2) }}
                                </td>
                                <td class="{{ $td }}">
                                    <span class="{{ $pill }} {{ $renewal->status === 'collected' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-amber-50 text-amber-800 ring-amber-600/20' }}">
                                        {{ str_replace('_', ' ', $renewal->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
