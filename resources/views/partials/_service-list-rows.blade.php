{{-- The files of one section of a service list (see _service-list). $items: App\Support\ServiceList rows. --}}
<ul class="divide-y divide-slate-100">
    @foreach ($items as $item)
        <li data-state="{{ $item['hold'] ?? $item['group'] }}" class="px-5 py-3.5 flex flex-wrap items-center gap-x-4 gap-y-2 hover:bg-slate-50/70 transition-colors">
            <span class="min-w-0 flex-1 basis-56">
                <span class="block text-sm font-semibold text-slate-900 truncate">{{ $item['name'] }}</span>
                <span class="block text-xs text-slate-500 truncate">{{ $item['meta'] }}</span>
            </span>
            <span class="inline-flex px-2.5 py-1 rounded-lg border text-[11px] font-semibold {{ $item['tone'] }}">{{ $item['badge'] }}</span>
            @if ($item['url'])
                <a href="{{ $item['url'] }}" aria-label="Open {{ $item['name'] }}"
                   class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-navy-700/40 hover:text-navy-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">
                    Open
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5" aria-hidden="true"></i>
                </a>
            @endif
            @if ($item['note'])
                <p class="basis-full text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    <span class="font-semibold">Returned:</span> {{ $item['note'] }}
                </p>
            @endif
        </li>
    @endforeach
</ul>
