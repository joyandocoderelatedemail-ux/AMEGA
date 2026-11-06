{{-- The files of one section of a service list (see _service-list). $items: App\Support\ServiceList rows. --}}
@php
    // Rows with a Continue button next to Open need the wider last column.
    $cols = $items->contains(fn ($item) => $item['continue'] ?? null)
        ? 'lg:grid lg:grid-cols-[minmax(0,2fr)_minmax(0,2.5fr)_6.5rem_13rem_10.5rem] lg:gap-x-4 lg:items-center'
        : 'lg:grid lg:grid-cols-[minmax(0,2fr)_minmax(0,2.5fr)_6.5rem_13rem_5.5rem] lg:gap-x-4 lg:items-center';
@endphp
<div class="{{ $cols }} hidden px-5 py-2 border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wide text-slate-500" aria-hidden="true">
    <span>Client</span>
    <span>Reference</span>
    <span>Opened</span>
    <span>Status</span>
    <span class="text-right">&nbsp;</span>
</div>
<ul class="divide-y divide-slate-100">
    @foreach ($items as $item)
        <li data-state="{{ $item['hold'] ?? $item['group'] }}" class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-2 {{ $cols }} hover:bg-slate-50/70 transition-colors">
            <span class="min-w-0 basis-full sm:basis-56 sm:flex-1 lg:basis-auto">
                <span class="block text-sm font-bold text-slate-900 truncate">{{ $item['name'] }}</span>
                <span class="block text-xs text-slate-500 truncate lg:hidden">{{ $item['meta'] }}</span>
            </span>
            <span class="hidden lg:block min-w-0 text-xs text-slate-600 truncate" title="{{ $item['meta'] }}">{{ $item['meta'] }}</span>
            <span class="text-xs text-slate-500 tabular-nums whitespace-nowrap">
                <span class="lg:hidden">Opened </span>{{ ($item['opened'] ?? null)?->format('M j, Y') ?? '—' }}
            </span>
            <span class="min-w-0">
                @if ($item['statusFor'] ?? null)
                    {{-- An SRRV retiree file: its status follows the step it is on. --}}
                    @include('srrv.applications._status', ['application' => $item['statusFor']])
                @else
                    <span class="inline-flex px-2.5 py-1 rounded-lg border text-[11px] font-semibold {{ $item['tone'] }}">{{ $item['badge'] }}</span>
                @endif
            </span>
            <span class="ml-auto lg:ml-0 flex items-center justify-end gap-2">
                @if ($item['continue'] ?? null)
                    <a href="{{ $item['continue'] }}" aria-label="Continue the file of {{ $item['name'] }}"
                       class="inline-flex items-center justify-center h-8 px-3 rounded-lg bg-navy-700 text-xs font-semibold text-white hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                        Continue
                    </a>
                @endif
                @if ($item['url'])
                    <a href="{{ $item['url'] }}" aria-label="Open {{ $item['name'] }}"
                       class="inline-flex items-center justify-center gap-1.5 h-8 min-w-[4.5rem] px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-navy-700/40 hover:text-navy-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">
                        Open
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5" aria-hidden="true"></i>
                    </a>
                @endif
            </span>
            @if ($item['note'])
                <p class="basis-full lg:col-span-full text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    <span class="font-semibold">Returned:</span> {{ $item['note'] }}
                </p>
            @endif
        </li>
    @endforeach
</ul>
