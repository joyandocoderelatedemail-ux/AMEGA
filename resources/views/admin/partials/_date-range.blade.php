@php
    /**
     * The reporting period for an admin page: preset links plus custom
     * from/to dates, all as a plain GET so the range lives in the URL.
     *
     * @var \App\Support\DateRange $range
     * @var string $action  The page's own URL.
     * @var array<string, mixed> $keep  Other query values to carry along (searches).
     */
    $keep = array_filter($keep ?? [], fn ($value) => filled($value));
    $pill = 'inline-flex items-center h-9 px-3 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500';
    $dateInput = 'h-9 px-2.5 rounded-lg border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-500/30 focus:border-navy-600';
@endphp

<section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-5" aria-label="Date range">
    <div class="flex flex-col xl:flex-row xl:items-center gap-3 xl:gap-4">
        <div class="flex items-center gap-2 shrink-0">
            <i data-lucide="calendar-range" class="w-4 h-4 text-navy-700"></i>
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Showing</span>
            <span class="text-sm font-bold text-slate-900">{{ $range->label() }}</span>
        </div>

        <nav class="flex flex-wrap gap-1.5 xl:ml-auto" aria-label="Preset ranges">
            @foreach (\App\Support\DateRange::PRESETS as $key => $presetLabel)
                @php $isActive = $range->preset === $key; @endphp
                <a href="{{ $action.'?'.http_build_query(($key === 'all' ? [] : ['range' => $key]) + $keep) }}"
                   @if ($isActive) aria-current="true" @endif
                   class="{{ $pill }} {{ $isActive ? 'bg-navy-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    {{ $presetLabel }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ $action }}" class="flex flex-wrap items-center gap-2">
            @foreach ($keep as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label class="sr-only" for="range_from">From</label>
            <input id="range_from" type="date" name="from" value="{{ $range->preset === 'custom' ? $range->from->toDateString() : '' }}"
                   max="{{ now()->toDateString() }}" class="{{ $dateInput }}" required>
            <span class="text-xs text-slate-400" aria-hidden="true">to</span>
            <label class="sr-only" for="range_to">To</label>
            <input id="range_to" type="date" name="to" value="{{ $range->preset === 'custom' ? $range->to->toDateString() : '' }}"
                   class="{{ $dateInput }}" required>
            <button type="submit"
                    class="{{ $pill }} {{ $range->preset === 'custom' ? 'bg-navy-700 text-white' : 'bg-navy-50 text-navy-700 hover:bg-navy-100' }} cursor-pointer">
                Apply
            </button>
        </form>
    </div>
</section>
