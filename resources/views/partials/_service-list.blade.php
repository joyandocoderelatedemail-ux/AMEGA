@php
    /**
     * A desk's service list (Immigration, Visa Assistance, SRRV): what is on a service, before anything is
     * started. Files the admin is holding come first (waiting for approval, or returned with the reason),
     * then the work in progress, then the finished files in a closed section. Open shows a file read-only
     * on one page; New goes on to start one.
     *
     * @var string $label  the service's name
     * @var string $icon  its icon
     * @var ?string $blurb  one line on what it is
     * @var string $newUrl  where New goes
     * @var \Illuminate\Support\Collection $rows  App\Support\ServiceList rows
     * @var array $hidden  query values the date filter keeps (e.g. service)
     */
    $sections = \App\Support\ServiceList::sections($rows);
    $range = \App\Support\DateRange::fromRequest(request());
    $input = 'px-3 py-2 rounded-lg bg-white border border-slate-200 text-slate-900 text-xs focus:outline-none focus:ring-2 focus:ring-navy-500';
    $total = $rows->count();
@endphp
<div class="space-y-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-start gap-3 min-w-0 xl:flex-1">
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0" aria-hidden="true">
                    <i data-lucide="{{ $icon }}" class="w-5 h-5"></i>
                </span>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-slate-900 leading-tight">{{ $label }}</h1>
                    <p class="text-xs text-slate-500 mt-0.5 max-w-2xl line-clamp-2">{{ $blurb ?? 'Open one to see the whole file, or start a new one.' }}</p>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center xl:justify-end xl:shrink-0">
                <form method="GET">
                    @foreach ($hidden ?? [] as $hiddenName => $hiddenValue)
                        <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
                    @endforeach
                    <x-date-range label="Opened" :inputClass="$input" buttonClass="px-4 py-2 rounded-lg bg-navy-700 text-white text-xs font-semibold hover:bg-navy-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2" />
                </form>
                <a href="{{ $newUrl }}"
                   class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                    <i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i>
                    New {{ $label }}
                </a>
            </div>
        </div>
    </div>

    @if ($total === 0)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-10 text-center">
            @if ($range->isAllTime())
                <p class="text-sm font-semibold text-slate-700">No {{ $label }} transactions yet.</p>
                <p class="text-xs text-slate-500 mt-1">Start one with New {{ $label }}.</p>
            @else
                <p class="text-sm font-semibold text-slate-700">Nothing was opened in this date range.</p>
                <a href="{{ request()->fullUrlWithoutQuery(['from', 'to', 'range']) }}" class="inline-block mt-2 text-xs font-semibold text-navy-700 hover:underline">Show all dates</a>
            @endif
        </div>
    @else
        @php
            $sectionStyle = [
                \App\Support\ServiceList::ACTION => ['Needs action', 'Waiting for the admin, or returned to you.', 'text-amber-700'],
                \App\Support\ServiceList::PROGRESS => ['In progress', null, 'text-slate-700'],
                \App\Support\ServiceList::RELEASED => ['Released', null, 'text-slate-500'],
            ];
        @endphp
        @foreach ($sections as $group => $items)
            @continue($items->isEmpty())
            @php [$title, $hint, $titleTone] = $sectionStyle[$group]; $folded = $group === \App\Support\ServiceList::RELEASED; @endphp
            <section class="bg-white rounded-xl border {{ $group === \App\Support\ServiceList::ACTION ? 'border-amber-200' : 'border-slate-200' }} shadow-sm overflow-hidden" aria-label="{{ $title }}">
                @if ($folded)
                    <details class="group">
                        <summary class="flex items-center justify-between gap-3 px-5 py-3.5 cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-500">
                            <h2 class="text-sm font-bold {{ $titleTone }}">{{ $title }}</h2>
                            <span class="flex items-center gap-2 text-xs text-slate-500">
                                <span class="tabular-nums font-semibold">{{ $items->count() }}</span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                            </span>
                        </summary>
                        @include('partials._service-list-rows', ['items' => $items])
                    </details>
                @else
                    <header class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-slate-100">
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold {{ $titleTone }}">{{ $title }}</h2>
                            @if ($hint)
                                <p class="text-[11px] text-slate-500 mt-0.5">{{ $hint }}</p>
                            @endif
                        </div>
                        <span class="text-xs font-semibold tabular-nums {{ $group === \App\Support\ServiceList::ACTION ? 'px-2 py-0.5 rounded-full bg-amber-50 text-amber-800' : 'text-slate-500' }}">{{ $items->count() }}</span>
                    </header>
                    @include('partials._service-list-rows', ['items' => $items])
                @endif
            </section>
        @endforeach
    @endif
</div>
