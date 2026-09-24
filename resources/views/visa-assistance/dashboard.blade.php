@extends('layouts.visa-assistance')

@section('title', 'Visa Assistance Counter - AMEGA Travel and Tours')

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
@endphp

@section('content')
<div class="space-y-6">

    <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Visa Assistance Counter</h1>
        <p class="mt-1 text-sm text-slate-500">Visit visa, e-Visa and passporting — the counter-handled applications.</p>
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
                <span class="{{ $statLabel }}">Open</span>
                <i data-lucide="loader-circle" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ $stats['open'] }}</div>
        </div>
        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Rush Files</span>
                <i data-lucide="zap" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ $stats['rush'] }}</div>
        </div>
        <div class="{{ $card }} p-5">
            <div class="flex items-center justify-between gap-3">
                <span class="{{ $statLabel }}">Released</span>
                <i data-lucide="check-circle-2" class="w-4 h-4 text-slate-400 shrink-0"></i>
            </div>
            <div class="{{ $statFigure }} mt-2">{{ $stats['total'] - $stats['open'] }}</div>
        </div>
    </div>

    <!-- The Three Services -->
    <section class="space-y-3">
        <h2 class="text-base font-semibold text-slate-900">Services &amp; Pipelines</h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach($services as $service)
                <div class="{{ $card }} p-5 flex flex-col">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                            <i data-lucide="{{ $service['icon'] }}" class="w-5 h-5"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-900">{{ $service['label'] }}</h3>
                            <span class="text-xs text-slate-500">{{ $service['count'] }} file(s)</span>
                        </div>
                    </div>

                    <p class="text-sm text-slate-500 mb-4">{{ $service['blurb'] }}</p>

                    <!-- Pipeline -->
                    <div class="flex flex-wrap items-center gap-1.5 mb-4">
                        @foreach($service['stages'] as $stage)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-600 capitalize">
                                {{ str_replace('_', ' ', $stage) }}
                            </span>
                            @if(! $loop->last)
                                <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300"></i>
                            @endif
                        @endforeach
                    </div>

                    <ul class="mt-auto space-y-1.5 pt-4 border-t border-slate-100">
                        @foreach($service['notes'] as $note)
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
                <h2 class="text-base font-semibold text-slate-900">Recent Counter Files</h2>
                <span class="text-xs text-slate-500">Latest 6</span>
            </div>

            @if($recentApplications->isEmpty())
                <div class="px-5 py-12 text-center">
                    <i data-lucide="folder-open" class="w-8 h-8 text-slate-300 mx-auto mb-3"></i>
                    <p class="text-sm font-semibold text-slate-900">No counter files yet.</p>
                    <p class="mt-1 text-sm text-slate-500">Visit visa, e-Visa and passporting applications will appear here.</p>
                </div>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="{{ $th }}">Reference</th>
                                <th class="{{ $th }}">Client</th>
                                <th class="{{ $th }}">Service</th>
                                <th class="{{ $th }}">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentApplications as $application)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="{{ $td }} font-semibold text-slate-900">{{ $application->reference }}</td>
                                    <td class="{{ $td }}">{{ $application->client_name }}</td>
                                    <td class="{{ $td }}">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-navy-50 text-navy-700 ring-1 ring-inset ring-navy-700/20 text-xs font-semibold capitalize whitespace-nowrap">
                                            {{ str_replace('_', ' ', $application->service_type) }}
                                        </span>
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
                <p class="mt-0.5 text-sm text-slate-500">Only confirmed counter prices</p>
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
                                    @if($tier->processing_time)
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $tier->processing_time }}</p>
                                    @endif
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

</div>
@endsection
