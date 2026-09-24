@extends('layouts.admin')

@section('title', 'Admin Dashboard - Amega Travel and Tours Services')
@section('page_title', 'Dashboard Overview')

@php
    /**
     * Shared presentation tokens for this screen.
     *
     * Two radii (rounded-2xl for cards, rounded-lg for controls), three weights,
     * and a strictly bounded palette: emerald = settled or confirmed, amber =
     * needs action, rose = failed or cancelled, navy = neutral brand. Nothing
     * else gets a hue, so a colour on this page always means the same thing.
     *
     * Every figure below comes from the database. Where there is none, the card
     * renders an empty state rather than a plausible-looking placeholder.
     */
    $card = 'bg-white rounded-2xl border border-slate-200/80 shadow-sm';
    $cardPad = 'p-5 sm:p-6';
    $label = 'text-xs font-semibold uppercase tracking-wide text-slate-500';
    $figure = 'font-heading text-3xl font-extrabold text-slate-900 tracking-tight tabular-nums';
    $viewAll = 'inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-navy-700 bg-navy-50 hover:bg-navy-100 transition-colors';

    $statusTone = [
        'confirmed' => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'ring' => 'ring-emerald-200', 'hex' => '#10B981'],
        'pending'   => ['dot' => 'bg-amber-500',   'text' => 'text-amber-700',   'bg' => 'bg-amber-50',   'ring' => 'ring-amber-200',   'hex' => '#F59E0B'],
        'completed' => ['dot' => 'bg-navy-600',    'text' => 'text-navy-700',    'bg' => 'bg-navy-50',    'ring' => 'ring-navy-200',    'hex' => '#0049B0'],
        'cancelled' => ['dot' => 'bg-rose-500',    'text' => 'text-rose-700',    'bg' => 'bg-rose-50',    'ring' => 'ring-rose-200',    'hex' => '#F43F5E'],
    ];

    $trend = $analytics['monthly_trend'];
    $maxPax = max(array_column($trend, 'pax'));
    $maxRev = max(array_column($trend, 'revenue_k'));
    $hasTrend = ($maxPax + $maxRev) > 0;

    $statusTotal = array_sum($analytics['booking_statuses']);
    $payTotal = $analytics['payment_total'];
@endphp

@section('content')
<div class="space-y-4 sm:space-y-6">

    {{-- Key figures. One number each, with a real breakdown beneath it. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

        <div class="{{ $card }} p-5">
            <div class="flex items-start justify-between gap-3">
                <span class="{{ $label }}">Total Bookings</span>
                <span class="w-9 h-9 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                    <i data-lucide="calendar-check" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="{{ $figure }} mt-3">{{ number_format($analytics['total_bookings_all']) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-3 text-xs text-slate-600">
                <span><span class="font-bold text-slate-900 tabular-nums">{{ number_format($stats['total_bookings']) }}</span> {{ $stats['total_bookings'] === 1 ? 'package' : 'packages' }}</span>
                <span class="text-slate-300" aria-hidden="true">&bull;</span>
                <span><span class="font-bold text-slate-900 tabular-nums">{{ number_format($analytics['ticket_bookings_count']) }}</span> {{ $analytics['ticket_bookings_count'] === 1 ? 'flight' : 'flights' }}</span>
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <div class="flex items-start justify-between gap-3">
                <span class="{{ $label }}">Passengers</span>
                <span class="w-9 h-9 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="{{ $figure }} mt-3">{{ number_format($analytics['total_passengers']) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600">
                @if ($analytics['avg_party_size'] > 0)
                    <span class="font-bold text-slate-900 tabular-nums">{{ $analytics['avg_party_size'] }}</span> average party size
                @else
                    <span class="text-slate-400">No bookings to average yet</span>
                @endif
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <div class="flex items-start justify-between gap-3">
                <span class="{{ $label }}">Inquiries</span>
                <span class="w-9 h-9 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                    <i data-lucide="inbox" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="{{ $figure }} mt-3">{{ number_format($stats['total_inquiries']) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600">
                @if ($stats['total_inquiries'] > 0)
                    <span class="font-bold text-emerald-700 tabular-nums">{{ $analytics['conversion_rate'] }}%</span> converted to bookings
                @else
                    <span class="text-slate-400">No inquiries received yet</span>
                @endif
            </div>
        </div>

        <div class="{{ $card }} p-5">
            <div class="flex items-start justify-between gap-3">
                <span class="{{ $label }}">Booked Value</span>
                <span class="w-9 h-9 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                    <i data-lucide="wallet" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="{{ $figure }} mt-3">&#8369;{{ number_format($analytics['revenue_total'], 0) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600">
                <span class="font-bold text-slate-900 tabular-nums">{{ number_format($stats['total_packages']) }}</span> packages across
                <span class="font-bold text-slate-900 tabular-nums">{{ number_format($stats['total_destinations']) }}</span> destinations
            </div>
        </div>
    </div>

    {{-- Trajectory and pipeline. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

        <div class="lg:col-span-8 {{ $card }} {{ $cardPad }} flex flex-col" x-data="{ mode: 'pax' }">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h2 class="font-heading text-base font-bold text-slate-900">Booking Volume</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Last six months</p>
                </div>

                @if ($hasTrend)
                    <div class="inline-flex p-1 bg-slate-100 rounded-lg" role="group" aria-label="Chart metric">
                        <button type="button" @click="mode = 'pax'"
                                :class="mode === 'pax' ? 'bg-white text-navy-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                            Passengers
                        </button>
                        <button type="button" @click="mode = 'revenue'"
                                :class="mode === 'revenue' ? 'bg-white text-navy-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer">
                            Revenue
                        </button>
                    </div>
                @endif
            </div>

            @if ($hasTrend)
                <div class="h-56 flex items-end justify-between gap-2 sm:gap-4 relative border-b border-slate-200">
                    <div class="absolute inset-x-0 top-0 border-t border-dashed border-slate-100" aria-hidden="true"></div>
                    <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-slate-100" aria-hidden="true"></div>

                    @foreach ($trend as $m)
                        @php
                            $paxH = $maxPax > 0 ? max(2, round(($m['pax'] / $maxPax) * 100)) : 2;
                            $revH = $maxRev > 0 ? max(2, round(($m['revenue_k'] / $maxRev) * 100)) : 2;
                        @endphp
                        <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                            <div class="absolute -top-1 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none
                                        bg-slate-900 text-white text-xs font-semibold px-2.5 py-1.5 rounded-lg shadow-lg whitespace-nowrap z-20 tabular-nums">
                                <span x-show="mode === 'pax'">{{ number_format($m['pax']) }} pax &middot; {{ $m['count'] }} bookings</span>
                                <span x-show="mode === 'revenue'" x-cloak>&#8369;{{ number_format($m['revenue'], 0) }}</span>
                            </div>

                            <div class="w-full max-w-[48px] rounded-t-lg bg-navy-700 group-hover:bg-navy-500 transition-colors duration-200"
                                 :style="mode === 'pax' ? 'height: {{ $paxH }}%' : 'height: {{ $revH }}%'"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between gap-2 sm:gap-4 mt-2">
                    @foreach ($trend as $m)
                        <span class="flex-1 text-center text-xs font-semibold text-slate-500">{{ $m['month'] }}</span>
                    @endforeach
                </div>
            @else
                <div class="h-56 flex flex-col items-center justify-center text-center gap-2 rounded-2xl bg-slate-50 border border-dashed border-slate-200">
                    <i data-lucide="bar-chart-3" class="w-7 h-7 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No bookings in the last six months</p>
                    <p class="text-xs text-slate-400">The chart fills in as bookings come through.</p>
                </div>
            @endif
        </div>

        <div class="lg:col-span-4 {{ $card }} {{ $cardPad }} flex flex-col">
            <h2 class="font-heading text-base font-bold text-slate-900">Booking Pipeline</h2>
            <p class="text-xs text-slate-500 mt-0.5 mb-5">Package bookings by status</p>

            @if ($statusTotal > 0)
                @php
                    $circ = 251.327;
                    $running = 0;
                    $segments = [];
                    foreach ($analytics['booking_statuses'] as $key => $count) {
                        $len = ($count / $statusTotal) * $circ;
                        $segments[] = [
                            'key' => $key,
                            'count' => $count,
                            'len' => $len,
                            'offset' => -$running,
                            'pct' => round(($count / $statusTotal) * 100),
                        ];
                        $running += $len;
                    }
                @endphp

                <div class="flex items-center gap-5">
                    <div class="relative w-28 h-28 shrink-0">
                        <svg class="w-28 h-28 -rotate-90" viewBox="0 0 100 100" role="img" aria-label="Booking status distribution">
                            <circle cx="50" cy="50" r="40" stroke="#F1F5F9" stroke-width="12" fill="none" />
                            @foreach ($segments as $s)
                                @if ($s['count'] > 0)
                                    <circle cx="50" cy="50" r="40" stroke="{{ $statusTone[$s['key']]['hex'] }}" stroke-width="12" fill="none"
                                            stroke-dasharray="{{ $s['len'] }} {{ $circ }}" stroke-dashoffset="{{ $s['offset'] }}" />
                                @endif
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="font-heading text-xl font-extrabold text-slate-900 tabular-nums">{{ number_format($statusTotal) }}</span>
                            <span class="text-xs text-slate-500">total</span>
                        </div>
                    </div>

                    <ul class="flex-1 space-y-2 min-w-0">
                        @foreach ($segments as $s)
                            <li class="flex items-center gap-2 text-xs">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $statusTone[$s['key']]['dot'] }}"></span>
                                <span class="font-semibold text-slate-600 capitalize truncate">{{ $s['key'] }}</span>
                                <span class="ml-auto font-bold text-slate-900 tabular-nums shrink-0">{{ $s['count'] }}</span>
                                <span class="text-slate-400 tabular-nums w-9 text-right shrink-0">{{ $s['pct'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-center gap-2 py-8 rounded-2xl bg-slate-50 border border-dashed border-slate-200">
                    <i data-lucide="pie-chart" class="w-7 h-7 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No package bookings yet</p>
                </div>
            @endif

            <div class="mt-auto pt-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="{{ $label }}">Payment Settlement</span>
                    @if ($payTotal > 0)
                        <span class="text-xs font-bold text-emerald-700 tabular-nums">
                            {{ round(($analytics['payment_statuses']['fully_paid'] / $payTotal) * 100) }}% paid
                        </span>
                    @endif
                </div>

                @if ($payTotal > 0)
                    @php
                        $paidPct = ($analytics['payment_statuses']['fully_paid'] / $payTotal) * 100;
                        $depPct = ($analytics['payment_statuses']['deposit_paid'] / $payTotal) * 100;
                        $unpaidPct = ($analytics['payment_statuses']['unpaid'] / $payTotal) * 100;
                    @endphp
                    <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden flex" role="img"
                         aria-label="{{ round($paidPct) }} percent fully paid, {{ round($depPct) }} percent deposit paid, {{ round($unpaidPct) }} percent unpaid">
                        <div class="bg-emerald-500 h-full" style="width: {{ $paidPct }}%"></div>
                        <div class="bg-amber-400 h-full" style="width: {{ $depPct }}%"></div>
                        <div class="bg-rose-400 h-full" style="width: {{ $unpaidPct }}%"></div>
                    </div>
                    <div class="flex items-center justify-between mt-2 text-xs text-slate-500">
                        <span>Paid <span class="font-bold text-slate-700 tabular-nums">{{ $analytics['payment_statuses']['fully_paid'] }}</span></span>
                        <span>Deposit <span class="font-bold text-slate-700 tabular-nums">{{ $analytics['payment_statuses']['deposit_paid'] }}</span></span>
                        <span>Unpaid <span class="font-bold text-slate-700 tabular-nums">{{ $analytics['payment_statuses']['unpaid'] }}</span></span>
                    </div>
                @else
                    <p class="text-xs text-slate-400">No payments recorded yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- What needs attention: latest bookings and open inquiries. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

        <div class="lg:col-span-8 {{ $card }} overflow-hidden flex flex-col">
            <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100">
                <div>
                    <h2 class="font-heading text-base font-bold text-slate-900">Recent Bookings</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Latest {{ $recentBookings->count() }} of {{ number_format($stats['total_bookings']) }}</p>
                </div>
                <a href="{{ route('admin.bookings.index') }}" class="{{ $viewAll }}">
                    View all
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            @if ($recentBookings->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th scope="col" class="{{ $label }} px-5 sm:px-6 py-3">Reference</th>
                                <th scope="col" class="{{ $label }} px-3 py-3">Customer</th>
                                <th scope="col" class="{{ $label }} px-3 py-3 hidden md:table-cell">Package</th>
                                <th scope="col" class="{{ $label }} px-3 py-3 hidden sm:table-cell">Travel date</th>
                                <th scope="col" class="{{ $label }} px-5 sm:px-6 py-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentBookings as $booking)
                                @php $tone = $statusTone[$booking->status] ?? $statusTone['pending']; @endphp
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="px-5 sm:px-6 py-3.5">
                                        <span class="font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">{{ $booking->booking_reference }}</span>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <div class="text-sm font-semibold text-slate-900 truncate max-w-[150px]">{{ $booking->customer_name }}</div>
                                        <div class="text-xs text-slate-500">{{ $booking->number_of_passengers }} pax</div>
                                    </td>
                                    <td class="px-3 py-3.5 hidden md:table-cell">
                                        <span class="text-sm text-slate-600 truncate block max-w-[180px]">{{ $booking->travelPackage?->title ?? '—' }}</span>
                                    </td>
                                    <td class="px-3 py-3.5 hidden sm:table-cell">
                                        <span class="text-sm text-slate-600 tabular-nums">{{ $booking->travel_date?->format('M j, Y') ?? '—' }}</span>
                                    </td>
                                    <td class="px-5 sm:px-6 py-3.5 text-right">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold capitalize ring-1 {{ $tone['bg'] }} {{ $tone['text'] }} {{ $tone['ring'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $tone['dot'] }}"></span>
                                            {{ $booking->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-center gap-2 py-14 px-6">
                    <i data-lucide="calendar-x" class="w-7 h-7 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No bookings yet</p>
                    <p class="text-xs text-slate-400">New bookings will appear here as they come in.</p>
                </div>
            @endif
        </div>

        <div class="lg:col-span-4 {{ $card }} overflow-hidden flex flex-col">
            <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100">
                <div>
                    <h2 class="font-heading text-base font-bold text-slate-900">Recent Inquiries</h2>
                    <p class="text-xs text-slate-500 mt-0.5">{{ number_format($stats['total_inquiries']) }} total</p>
                </div>
                <a href="{{ route('admin.inquiries.index') }}" class="{{ $viewAll }}">
                    View all
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            @if ($recentInquiries->isNotEmpty())
                <ul class="divide-y divide-slate-100">
                    @foreach ($recentInquiries as $inquiry)
                        <li class="px-5 sm:px-6 py-3.5 hover:bg-slate-50/70 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-900 truncate">{{ $inquiry->name }}</span>
                                <span class="text-xs text-slate-400 shrink-0">{{ $inquiry->created_at?->diffForHumans(null, true) }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $inquiry->message }}</p>
                            @if ($inquiry->service_requested)
                                <span class="inline-block mt-2 px-2 py-0.5 rounded-lg bg-slate-100 text-xs font-semibold text-slate-600">
                                    {{ $inquiry->service_requested }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-center gap-2 py-14 px-6">
                    <i data-lucide="inbox" class="w-7 h-7 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No inquiries yet</p>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
