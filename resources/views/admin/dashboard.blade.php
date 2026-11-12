@extends('layouts.admin')

@section('title', 'Admin Dashboard - Amega Travel and Tours Services')
@section('page_title', 'Dashboard Overview')

@php
    /**
     * Shared presentation tokens for this screen.
     *
     * Cards are rounded-2xl, controls rounded-lg. Colour is bounded: emerald = settled or up,
     * amber = needs action, rose = failed, cancelled or down, navy = neutral brand, olive marks
     * airline tickets in the chart. Every figure comes from the database; where there is none,
     * the card renders an empty state rather than a plausible-looking placeholder.
     */
    $card = 'bg-white rounded-2xl border border-slate-200 shadow-card';
    $cardHead = 'flex flex-wrap items-start justify-between gap-3 px-5 pt-5';
    $title = 'font-heading text-[15px] font-bold text-slate-900';
    $sub = 'text-xs text-slate-500 mt-0.5';
    $link = 'inline-flex items-center gap-1 text-xs font-semibold text-navy-700 hover:text-navy-900 whitespace-nowrap';
    $pill = 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap';

    $statusTone = [
        'pending'   => ['pill' => 'bg-amber-50 text-amber-700',     'bar' => 'bg-amber-500'],
        'confirmed' => ['pill' => 'bg-navy-50 text-navy-700',       'bar' => 'bg-navy-700'],
        'completed' => ['pill' => 'bg-emerald-50 text-emerald-700', 'bar' => 'bg-emerald-500'],
        'cancelled' => ['pill' => 'bg-rose-50 text-rose-700',       'bar' => 'bg-rose-500'],
    ];
    $paymentTone = [
        'fully_paid'   => ['label' => 'Fully paid',   'pill' => 'bg-emerald-50 text-emerald-700', 'bar' => 'bg-emerald-500'],
        'deposit_paid' => ['label' => 'Deposit paid', 'pill' => 'bg-amber-50 text-amber-700',     'bar' => 'bg-amber-500'],
        'unpaid'       => ['label' => 'Unpaid',       'pill' => 'bg-rose-50 text-rose-700',       'bar' => 'bg-rose-500'],
    ];

    $trend = $analytics['monthly_trend'];
    $hasTrend = array_sum(array_column($trend, 'count')) > 0;
    // A long daily chart labels every nth bar so the dates stay legible.
    $labelEvery = max(1, (int) ceil(count($trend) / 12));

    // Chart geometry per metric: each bar's height against the tallest, and the packages' share of it.
    $metrics = ['pax' => 'pax', 'count' => 'count', 'revenue' => 'revenue'];
    $chartMax = [];
    foreach ($metrics as $metric) {
        $chartMax[$metric] = max(1, max(array_map(fn (array $b) => $b[$metric], $trend)));
    }
    $bars = array_map(function (array $b) use ($metrics, $chartMax) {
        $geometry = [];
        foreach ($metrics as $metric) {
            $total = $b[$metric];
            $geometry[$metric] = [
                'h' => $total > 0 ? max(2, round(($total / $chartMax[$metric]) * 100, 1)) : 0,
                'p' => $total > 0 ? round(($b['packages'][$metric] / $total) * 100, 1) : 0,
            ];
        }

        return $geometry;
    }, $trend);
    $axis = fn (string $metric, float $value): string => $metric === 'revenue'
        ? '₱'.($value >= 1000000 ? round($value / 1000000, 1).'M' : ($value >= 1000 ? round($value / 1000).'k' : number_format($value)))
        : number_format($value);

    // The last seven chart buckets, as each key figure's small trend.
    $spark = function (string $metric) use ($trend): array {
        $values = array_map(fn (array $b) => $b[$metric], array_slice($trend, -7));
        $max = max($values ?: [0]);

        // An empty bucket stays a hairline so it never reads as a small figure.
        return $max > 0 ? array_map(fn ($v) => $v > 0 ? max(12, round(($v / $max) * 100)) : 4, $values) : [];
    };

    $kpis = [
        ['key' => 'bookings', 'label' => 'Total Bookings', 'icon' => 'calendar-check', 'value' => number_format($analytics['total_bookings_all']), 'spark' => $spark('count'),
            'note' => number_format($stats['total_bookings']).' '.Str::plural('package', $stats['total_bookings']).' · '.number_format($analytics['ticket_bookings_count']).' '.Str::plural('flight', $analytics['ticket_bookings_count'])],
        ['key' => 'value', 'label' => 'Booked Value', 'icon' => 'wallet', 'value' => '₱'.number_format($analytics['revenue_total'], 0), 'spark' => $spark('revenue'),
            'note' => 'Packages and airline tickets'],
        ['key' => 'passengers', 'label' => 'Passengers', 'icon' => 'users', 'value' => number_format($analytics['total_passengers']), 'spark' => $spark('pax'),
            'note' => $analytics['avg_party_size'] > 0 ? $analytics['avg_party_size'].' per booking on average' : 'No bookings to average yet'],
        ['key' => 'inquiries', 'label' => 'Inquiries', 'icon' => 'inbox', 'value' => number_format($stats['total_inquiries']), 'spark' => [],
            'note' => $stats['total_inquiries'] > 0 ? $analytics['conversion_rate'].'% became bookings' : 'No inquiries'.($range->isAllTime() ? ' received yet' : ' in this period')],
    ];

    $statusTotal = array_sum($analytics['booking_statuses']);
    $payTotal = $analytics['payment_total'];

    $formatMoney = fn (string $currency, float $amount): string => ($currency === 'PHP' ? '₱' : $currency.' ').number_format($amount, 0);
    $initials = fn (?string $name): string => Str::upper(collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode('')) ?: '?';
    $inRange = $range->isAllTime() ? '' : ' in this period';

    // Written out in full so Tailwind builds each one.
    $deptColumns = [1 => 'xl:grid-cols-1', 2 => 'xl:grid-cols-2', 3 => 'xl:grid-cols-3', 4 => 'xl:grid-cols-4', 5 => 'xl:grid-cols-5'];
@endphp

@section('content')
<div class="space-y-5 sm:space-y-6">

    @include('admin.partials._date-range', ['range' => $range, 'action' => route('admin.dashboard'), 'keep' => request()->only('staff', 'staff_role')])

    {{-- Key figures: the number, its change against the previous period, and its last seven chart buckets. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($kpis as $kpi)
            @php $change = $comparison['changes'][$kpi['key']]; @endphp
            <div class="{{ $card }} p-5 flex flex-col gap-3 min-w-0" data-kpi="{{ $kpi['key'] }}">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                        <i data-lucide="{{ $kpi['icon'] }}" class="w-3.5 h-3.5"></i>
                    </span>
                    <span class="text-xs font-semibold text-slate-500">{{ $kpi['label'] }}</span>
                    @if ($kpi['spark'])
                        <span class="ml-auto flex items-end gap-[3px] h-7" aria-hidden="true">
                            @foreach ($kpi['spark'] as $height)
                                <span class="w-1.5 rounded-sm {{ $loop->last ? 'bg-navy-700' : 'bg-navy-200' }}" style="height: {{ $height }}%"></span>
                            @endforeach
                        </span>
                    @endif
                </div>
                <div class="text-[28px] leading-none font-bold text-slate-900 tracking-tight tabular-nums truncate">{{ $kpi['value'] }}</div>
                <div class="flex items-center gap-2 text-xs text-slate-500 min-w-0">
                    @if ($change !== null)
                        <span class="{{ $pill }} {{ $change > 0 ? 'bg-emerald-50 text-emerald-700' : ($change < 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }} tabular-nums">
                            {{ $change > 0 ? '+' : ($change < 0 ? '−' : '') }}{{ abs($change) }}%
                        </span>
                        <span class="truncate">{{ $comparison['label'] }}</span>
                    @else
                        <span class="truncate">{{ $kpi['note'] }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Trajectory and pipeline. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

        <section class="lg:col-span-8 {{ $card }} flex flex-col overflow-x-clip" x-data="{ mode: 'pax' }" aria-labelledby="volume-heading">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="volume-heading" class="{{ $title }}">Booking Volume</h2>
                    <p class="{{ $sub }}">{{ $analytics['trend_label'] }} &middot; packages and airline tickets</p>
                </div>

                @if ($hasTrend)
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex items-center gap-3 text-xs text-slate-500">
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-navy-700"></span>Packages</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-accent"></span>Tickets</span>
                        </div>
                        <div class="inline-flex p-0.5 bg-white border border-slate-200 rounded-lg" role="group" aria-label="Chart metric">
                            @foreach (['pax' => 'Passengers', 'count' => 'Bookings', 'revenue' => 'Value'] as $metric => $metricLabel)
                                <button type="button" @click="mode = '{{ $metric }}'"
                                        :aria-pressed="(mode === '{{ $metric }}').toString()"
                                        :class="mode === '{{ $metric }}' ? 'bg-navy-700 text-white' : 'text-slate-500 hover:text-slate-800'"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer">
                                    {{ $metricLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="px-5 pb-5 pt-5 flex-1">
                @if ($hasTrend)
                    <div class="flex gap-3">
                        <div class="w-10 h-60 flex flex-col justify-between text-right text-[11px] text-slate-400 tabular-nums -mt-1.5 pb-0" aria-hidden="true">
                            @foreach ([1, 0.5, 0] as $fraction)
                                <span>
                                    @foreach ($metrics as $metric)
                                        <span x-show="mode === '{{ $metric }}'" @if ($metric !== 'pax') x-cloak @endif>{{ $axis($metric, $chartMax[$metric] * $fraction) }}</span>
                                    @endforeach
                                </span>
                            @endforeach
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="h-60 flex items-end gap-1.5 sm:gap-3 relative border-b border-slate-200">
                                <div class="absolute inset-x-0 top-0 border-t border-dashed border-slate-100" aria-hidden="true"></div>
                                <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-slate-100" aria-hidden="true"></div>

                                @foreach ($trend as $i => $bucket)
                                    @php
                                        $g = $bars[$i];
                                        // Edge bars anchor their tooltip inward so it never runs off the card.
                                        $tipSide = match (true) {
                                            $loop->index < count($trend) / 3 => 'left-0',
                                            $loop->index >= count($trend) * 2 / 3 => 'right-0',
                                            default => '',
                                        };
                                    @endphp
                                    <div class="flex-1 min-w-0 h-full flex flex-col justify-end items-center group relative">
                                        <div class="absolute -top-2 {{ $tipSide }} opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none bg-slate-900 text-white text-xs font-semibold px-2.5 py-1.5 rounded-lg shadow-lg whitespace-nowrap z-20 tabular-nums">
                                            {{ $bucket['month'] }}:
                                            <span x-show="mode === 'pax'">{{ number_format($bucket['packages']['pax']) }} + {{ number_format($bucket['tickets']['pax']) }} pax</span>
                                            <span x-show="mode === 'count'" x-cloak>{{ number_format($bucket['packages']['count']) }} + {{ number_format($bucket['tickets']['count']) }} bookings</span>
                                            <span x-show="mode === 'revenue'" x-cloak>&#8369;{{ number_format($bucket['revenue'], 0) }}</span>
                                        </div>

                                        <div class="w-full max-w-[56px] flex flex-col rounded-t-md overflow-hidden transition-[height] duration-300"
                                             style="height: {{ $g['pax']['h'] }}%"
                                             :style="{{ Js::from(collect($g)->map(fn ($m) => 'height: '.$m['h'].'%')) }}[mode]">
                                            <div class="bg-accent group-hover:bg-accent-dark transition-colors"
                                                 style="height: {{ 100 - $g['pax']['p'] }}%"
                                                 :style="{{ Js::from(collect($g)->map(fn ($m) => 'height: '.(100 - $m['p']).'%')) }}[mode]"></div>
                                            <div class="bg-navy-700 group-hover:bg-navy-600 transition-colors flex-1"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex gap-1.5 sm:gap-3 mt-2">
                                @foreach ($trend as $bucket)
                                    <span class="flex-1 min-w-0 text-center text-[11px] text-slate-400 whitespace-nowrap overflow-visible">{{ $loop->index % $labelEvery === 0 ? $bucket['month'] : '' }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="h-60 flex flex-col items-center justify-center text-center gap-2 rounded-xl bg-slate-50 border border-dashed border-slate-200">
                        <i data-lucide="bar-chart-3" class="w-7 h-7 text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-500">No bookings {{ $range->isAllTime() ? 'in the last six months' : 'in this period' }}</p>
                        <p class="text-xs text-slate-400">The chart fills in as bookings come through.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="lg:col-span-4 {{ $card }} flex flex-col" aria-labelledby="pipeline-heading">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="pipeline-heading" class="{{ $title }}">Booking Pipeline</h2>
                    <p class="{{ $sub }}">
                        @if ($statusTotal > 0)
                            Where {{ $range->isAllTime() ? 'all' : 'this period\'s' }} {{ number_format($statusTotal) }} package {{ Str::plural('booking', $statusTotal) }} stand
                        @else
                            Package bookings by status &middot; {{ $range->label() }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="px-5 pb-5 pt-4 flex-1 flex flex-col gap-3.5">
                @if ($statusTotal > 0)
                    @foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $status)
                        @php $count = $analytics['booking_statuses'][$status]; @endphp
                        <div>
                            <div class="flex items-center text-xs mb-1.5">
                                <span class="font-medium text-slate-600 capitalize">{{ $status }}</span>
                                <span class="ml-auto font-bold text-slate-900 tabular-nums">{{ number_format($count) }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $statusTone[$status]['bar'] }}" style="width: {{ ($count / $statusTotal) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-center gap-2 py-8 rounded-xl bg-slate-50 border border-dashed border-slate-200">
                        <i data-lucide="list-checks" class="w-7 h-7 text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-500">No package bookings{{ $range->isAllTime() ? ' yet' : $inRange }}</p>
                    </div>
                @endif

                <div class="mt-auto pt-4 border-t border-slate-100">
                    <div class="flex items-center text-xs mb-2">
                        <span class="font-medium text-slate-600">Payments</span>
                        @if ($payTotal > 0)
                            <span class="ml-auto font-semibold text-emerald-700 tabular-nums">{{ round(($analytics['payment_statuses']['fully_paid'] / $payTotal) * 100) }}% fully paid</span>
                        @endif
                    </div>

                    @if ($payTotal > 0)
                        <div class="h-2.5 rounded-full overflow-hidden flex gap-0.5 bg-slate-100" role="img"
                             aria-label="{{ $analytics['payment_statuses']['fully_paid'] }} fully paid, {{ $analytics['payment_statuses']['deposit_paid'] }} deposit paid, {{ $analytics['payment_statuses']['unpaid'] }} unpaid">
                            @foreach ($paymentTone as $key => $tone)
                                @if ($analytics['payment_statuses'][$key] > 0)
                                    <div class="h-full {{ $tone['bar'] }}" style="width: {{ ($analytics['payment_statuses'][$key] / $payTotal) * 100 }}%"></div>
                                @endif
                            @endforeach
                        </div>
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($paymentTone as $key => $tone)
                                <li class="flex items-center gap-2 text-xs text-slate-600">
                                    <span class="w-2.5 h-2.5 rounded-sm {{ $tone['bar'] }}"></span>
                                    {{ $tone['label'] }}
                                    <span class="ml-auto font-bold text-slate-900 tabular-nums">{{ number_format($analytics['payment_statuses'][$key]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-slate-400">No payments recorded yet.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- Per-department report: one card for each desk the viewer can open. --}}
    @if (! empty($departments))
        <section class="{{ $card }}" aria-labelledby="department-reports-heading">
            <div class="{{ $cardHead }}">
                <div>
                    <h2 id="department-reports-heading" class="{{ $title }}">Department Reports</h2>
                    <p class="{{ $sub }}">
                        {{ auth()->user()->isAdmin() ? 'Files on each desk' : 'Files you opened on each desk' }}
                        @if ($range->isAllTime())
                            &middot; &ldquo;new&rdquo; means this month, {{ now()->format('F Y') }}
                        @else
                            &middot; opened {{ $range->label() }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 {{ $deptColumns[min(count($departments), 5)] }} gap-3">
                @foreach ($departments as $department)
                    @php
                        $pipeline = $department['pipeline'];
                        $second = match (true) {
                            $range->isAllTime() => ['value' => $department['newThisMonth'], 'label' => 'New this month'],
                            $pipeline !== null => ['value' => $pipeline['open'], 'label' => $department['pipelineLabels']['open']],
                            default => ['value' => $department['facts'][0]['value'] ?? 0, 'label' => $department['facts'][0]['label'] ?? ''],
                        };
                        $collected = collect($department['money'])->first();
                    @endphp
                    <a href="{{ route($department['route']) }}"
                       class="group rounded-xl border border-slate-200 p-4 flex flex-col gap-3.5 hover:border-navy-300 hover:shadow-sm transition"
                       data-department="{{ $department['key'] }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-9 h-9 rounded-lg bg-navy-700 text-white flex items-center justify-center shrink-0">
                                <i data-lucide="{{ $department['icon'] }}" class="w-4 h-4"></i>
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-slate-900 truncate">{{ $department['label'] }}</h3>
                                <p class="text-[11px] text-slate-500 truncate">{{ Str::ucfirst(Str::plural($department['unit'])) }}</p>
                            </div>
                            <i data-lucide="arrow-up-right" class="w-4 h-4 ml-auto text-slate-300 group-hover:text-navy-700 shrink-0"></i>
                        </div>

                        <dl class="grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-slate-50 px-2.5 py-2 min-w-0">
                                <dd class="text-lg font-bold text-slate-900 tabular-nums leading-tight">{{ number_format($department['total']) }}</dd>
                                <dt class="text-[11px] text-slate-500 truncate">{{ $range->isAllTime() ? 'All time' : 'In period' }}</dt>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-2.5 py-2 min-w-0">
                                <dd class="text-lg font-bold text-slate-900 tabular-nums leading-tight">{{ number_format($second['value']) }}</dd>
                                <dt class="text-[11px] text-slate-500 truncate">{{ $second['label'] }}</dt>
                            </div>
                        </dl>

                        @if ($department['total'] === 0)
                            <p class="text-xs text-slate-400">No {{ Str::plural($department['unit']) }}{{ $range->isAllTime() ? ' yet' : $inRange }}.</p>
                        @elseif ($collected)
                            <p class="flex items-baseline justify-between gap-2 text-xs">
                                <span class="text-slate-500">{{ $collected['label'] }}</span>
                                <span class="font-bold text-slate-900 tabular-nums truncate">
                                    @forelse ($collected['amounts'] as $currency => $amount)
                                        {{ $formatMoney($currency, $amount) }}@if (! $loop->last)<span class="text-slate-300 font-normal"> &middot; </span>@endif
                                    @empty
                                        <span class="font-normal text-slate-400">None</span>
                                    @endforelse
                                </span>
                            </p>
                        @endif

                        <div class="mt-auto">
                            @if ($department['attention']['count'] > 0)
                                <span class="{{ $pill }} bg-amber-50 text-amber-700">{{ number_format($department['attention']['count']) }} {{ $department['attention']['label'] }}</span>
                            @else
                                <span class="{{ $pill }} bg-emerald-50 text-emerald-700">{{ $department['attention']['clear'] }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- The latest bookings, and beside them what is running out and who has written in. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

        <section class="lg:col-span-8 {{ $card }} overflow-hidden flex flex-col" aria-labelledby="recent-bookings-heading">
            <div class="{{ $cardHead }} pb-4">
                <div>
                    <h2 id="recent-bookings-heading" class="{{ $title }}">Recent Bookings</h2>
                    <p class="{{ $sub }}">Latest {{ $recentBookings->count() }} of {{ number_format($stats['total_bookings']) }} package {{ Str::plural('booking', $stats['total_bookings']) }}</p>
                </div>
                <a href="{{ route('admin.bookings.index') }}" class="{{ $link }}">All bookings <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
            </div>

            @if ($recentBookings->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 border-y border-slate-200 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <th scope="col" class="px-5 py-2.5 whitespace-nowrap">Client</th>
                                <th scope="col" class="px-3 py-2.5 whitespace-nowrap hidden md:table-cell">Package</th>
                                <th scope="col" class="px-3 py-2.5 whitespace-nowrap hidden sm:table-cell">Travel date</th>
                                <th scope="col" class="px-3 py-2.5 whitespace-nowrap">Status</th>
                                <th scope="col" class="px-3 py-2.5 whitespace-nowrap hidden md:table-cell">Payment</th>
                                <th scope="col" class="px-5 py-2.5 whitespace-nowrap text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentBookings as $booking)
                                @php
                                    $tone = $statusTone[$booking->status] ?? ['pill' => 'bg-slate-100 text-slate-600'];
                                    $payment = $paymentTone[$booking->payment_status] ?? null;
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-8 h-8 rounded-full bg-navy-100 text-navy-700 text-[11px] font-bold flex items-center justify-center shrink-0">{{ $initials($booking->customer_name) }}</span>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-slate-900 truncate max-w-[180px]">{{ $booking->customer_name }}</div>
                                                <div class="text-[11px] text-slate-500 whitespace-nowrap"><span class="font-mono">{{ $booking->booking_reference }}</span> &middot; {{ $booking->number_of_passengers }} pax</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 hidden md:table-cell">
                                        <span class="text-sm text-slate-600 truncate block max-w-[200px]">{{ $booking->travelPackage?->title ?? '—' }}</span>
                                    </td>
                                    <td class="px-3 py-3 hidden sm:table-cell">
                                        <span class="text-sm text-slate-600 tabular-nums whitespace-nowrap">{{ $booking->travel_date?->format('M j, Y') ?? '—' }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="{{ $pill }} capitalize {{ $tone['pill'] }}">{{ $booking->status }}</span>
                                    </td>
                                    <td class="px-3 py-3 hidden md:table-cell">
                                        @if ($payment)
                                            <span class="{{ $pill }} {{ $payment['pill'] }}">{{ $payment['label'] }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-slate-900 tabular-nums whitespace-nowrap">
                                        {{ $booking->amount_due !== null ? '₱'.number_format((float) $booking->amount_due, 0) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center gap-2 py-14 px-6 border-t border-slate-100">
                    <i data-lucide="calendar-x" class="w-7 h-7 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No bookings{{ $range->isAllTime() ? ' yet' : $inRange }}</p>
                    <p class="text-xs text-slate-400">New bookings will appear here as they come in.</p>
                </div>
            @endif
        </section>

        <div class="lg:col-span-4 space-y-4">

            {{-- Client documents running out (Reports & Analytics > Expiring Documents). --}}
            <section class="{{ $card }} {{ $expiring['expired'] ? 'ring-1 ring-rose-200' : '' }}" aria-labelledby="expiring-heading">
                <div class="{{ $cardHead }}">
                    <div class="min-w-0">
                        <h2 id="expiring-heading" class="{{ $title }}">Expiring Client Documents</h2>
                        <p class="{{ $sub }}">
                            @if ($expiring['expired'] + $expiring['month'] > 0)
                                <span class="font-semibold text-amber-700">{{ $expiring['month'] }}</span> within 30 days
                                @if ($expiring['expired'])
                                    &middot; <span class="font-semibold text-rose-700">{{ $expiring['expired'] }} already expired</span>
                                @endif
                            @else
                                Visas, passports and ACR I-Cards
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('admin.expiring-documents.index') }}" class="{{ $link }}">View and remind <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
                </div>

                <div class="p-5 pt-4">
                    @if ($expiring['soonest']->isNotEmpty())
                        <ul class="space-y-2">
                            @foreach ($expiring['soonest'] as $row)
                                <li class="flex items-center gap-3 px-3 py-2 rounded-lg border border-slate-100">
                                    <span class="w-11 shrink-0 rounded-lg py-1 text-center {{ $row['days'] <= 30 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                                        <span class="block text-[15px] font-bold leading-tight tabular-nums">{{ $row['days'] }}</span>
                                        <span class="block text-[9px] font-semibold uppercase">{{ Str::plural('day', $row['days']) }}</span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900 truncate">{{ $row['name'] }}</span>
                                        <span class="block text-[11px] text-slate-500 truncate">{{ $row['label'] }} &middot; {{ $row['date']->format('M j, Y') }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-400 text-center py-6">No client documents coming up for renewal.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>

    @if ($staff)
        @include('admin.partials._staff-activity', ['staff' => $staff, 'range' => $range])
    @endif

</div>
@endsection
