@php
    /**
     * The services of a desk as cards, each with what is open on it, opening that service's list.
     * The same grid on the Immigration, Visa Assistance and SRRV dashboards.
     *
     * @var iterable<array{label: string, icon: string, count: int, unit: string, url: string}> $services
     */
@endphp
<section aria-label="Services">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        @foreach ($services as $service)
            <a href="{{ $service['url'] }}" class="group bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3 hover:border-navy-700/40 hover:shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                    <i data-lucide="{{ $service['icon'] }}" class="w-5 h-5" aria-hidden="true"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900 leading-snug">{{ $service['label'] }}</span>
                    <span class="block text-xs text-slate-500 mt-0.5">
                        <span class="font-semibold tabular-nums {{ $service['count'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $service['count'] }}</span> {{ $service['unit'] }}
                    </span>
                </span>
            </a>
        @endforeach
    </div>
</section>
