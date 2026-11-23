@php
    /**
     * The services of a desk as cards, each with what is open on it, opening that service's list.
     * The same grid on the Immigration, Visa Assistance and SRRV dashboards.
     *
     * A service may name a 'color' for its icon, and one marked 'soon' shows as a card that does not open yet.
     * One with 'choices' (each a label, icon, count, unit, url and waiting) opens a menu of them instead of a list.
     *
     * @var iterable<array{label: string, icon: string, count: int, unit: string, url: ?string, waiting?: int, color?: string, soon?: bool, choices?: list<array<string, mixed>>}> $services
     */
    // Written out in full so Tailwind keeps the classes.
    $iconColors = [
        'sky' => 'bg-sky-50 text-sky-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'teal' => 'bg-teal-50 text-teal-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
        'pink' => 'bg-pink-50 text-pink-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'blue' => 'bg-blue-50 text-blue-600',
        'orange' => 'bg-orange-50 text-orange-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'amber' => 'bg-amber-50 text-amber-600',
    ];
@endphp
<section aria-label="Services">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        @foreach ($services as $service)
            @php $iconClass = $iconColors[$service['color'] ?? ''] ?? 'bg-slate-100 text-slate-600'; @endphp
            @if ($service['soon'] ?? false)
                <div class="bg-white rounded-xl border border-dashed border-slate-200 p-4 flex items-center gap-3 opacity-70" data-service-card="{{ $service['label'] }}" aria-disabled="true">
                    <span class="w-10 h-10 rounded-lg {{ $iconClass }} flex items-center justify-center shrink-0">
                        <i data-lucide="{{ $service['icon'] }}" class="w-5 h-5" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900 leading-snug">{{ $service['label'] }}</span>
                        <span class="block text-xs text-slate-400 mt-0.5">Coming soon</span>
                    </span>
                </div>
                @continue
            @endif
            @if (! empty($service['choices']))
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
                    <button type="button" @click="open = !open" aria-haspopup="menu" :aria-expanded="open" class="group w-full text-left bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3 hover:border-navy-700/40 hover:shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2" data-service-card="{{ $service['label'] }}" data-open="{{ $service['count'] }}" data-waiting="{{ $service['waiting'] ?? 0 }}">
                        <span class="w-10 h-10 rounded-lg {{ $iconClass }} flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                            <i data-lucide="{{ $service['icon'] }}" class="w-5 h-5" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900 leading-snug">{{ $service['label'] }}</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                <span class="font-semibold tabular-nums {{ $service['count'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $service['count'] }}</span> {{ $service['unit'] }}
                                @if (($service['waiting'] ?? 0) > 0)
                                    <span class="text-slate-300" aria-hidden="true">&middot;</span>
                                    <span class="font-semibold text-amber-700"><span class="tabular-nums">{{ $service['waiting'] }}</span> waiting</span>
                                @endif
                            </span>
                        </span>
                        <span class="inline-flex text-slate-400 transition-transform" :class="open && 'rotate-180'" aria-hidden="true"><i data-lucide="chevron-down" class="w-4 h-4"></i></span>
                    </button>
                    <div x-show="open" x-cloak role="menu" aria-label="{{ $service['label'] }}" class="absolute left-0 right-0 top-full z-20 mt-1 bg-white rounded-xl border border-slate-200 shadow-lg p-1.5">
                        @foreach ($service['choices'] as $choice)
                            <a href="{{ $choice['url'] }}" role="menuitem" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 focus-visible:outline-none focus-visible:bg-slate-50">
                                <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                                    <i data-lucide="{{ $choice['icon'] }}" class="w-4 h-4" aria-hidden="true"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900">{{ $choice['label'] }}</span>
                                    <span class="block text-xs text-slate-500">
                                        <span class="tabular-nums">{{ $choice['count'] }}</span> {{ $choice['unit'] }}
                                        @if (($choice['waiting'] ?? 0) > 0)
                                            &middot; <span class="font-semibold text-amber-700"><span class="tabular-nums">{{ $choice['waiting'] }}</span> waiting</span>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
                @continue
            @endif
            <a href="{{ $service['url'] }}" class="group bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3 hover:border-navy-700/40 hover:shadow transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2" data-service-card="{{ $service['label'] }}" data-open="{{ $service['count'] }}" data-waiting="{{ $service['waiting'] ?? 0 }}">
                <span class="w-10 h-10 rounded-lg {{ $iconClass }} flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                    <i data-lucide="{{ $service['icon'] }}" class="w-5 h-5" aria-hidden="true"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900 leading-snug">{{ $service['label'] }}</span>
                    <span class="block text-xs text-slate-500 mt-0.5">
                        <span class="font-semibold tabular-nums {{ $service['count'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $service['count'] }}</span> {{ $service['unit'] }}
                        @if (($service['waiting'] ?? 0) > 0)
                            <span class="text-slate-300" aria-hidden="true">&middot;</span>
                            <span class="font-semibold text-amber-700"><span class="tabular-nums">{{ $service['waiting'] }}</span> waiting</span>
                        @endif
                    </span>
                </span>
            </a>
        @endforeach
    </div>
</section>
