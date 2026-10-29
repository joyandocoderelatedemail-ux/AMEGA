@extends('layouts.srrv')

@section('title', 'SRRV Desk - AMEGA Travel and Tours')

@php
    /**
     * Same tokens as the ticketing dashboard: rounded-xl cards, rounded-lg
     * controls, rounded-full pills, weights 400/600/700, nothing below text-xs.
     *
     * Headings need no `font-heading`: the staff font class (staff-type or
     * portal-minimal) already sets every heading in Inter.
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

    <div>

        <!-- Recent Files -->
        <div class="{{ $card }} overflow-hidden">
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
                                    <td class="{{ $td }} text-slate-600">{{ $application->status_label }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
