{{--
    The "next step" banner at the top of a file: what the counter does now, why it
    cannot move on yet, and a button straight to the place to do it. Give it the
    result of the file's nextStep(); $advanceUrl turns a ready file's button into
    the advance action, and $links adds buttons (label, url, icon) once it is ready.
--}}
@php
    $tones = [
        'todo' => ['wrap' => 'bg-amber-50 border-amber-200', 'icon' => 'bg-amber-100 text-amber-700', 'glyph' => 'clock', 'label' => 'text-amber-800'],
        'ready' => ['wrap' => 'bg-emerald-50 border-emerald-200', 'icon' => 'bg-emerald-100 text-emerald-700', 'glyph' => 'check', 'label' => 'text-emerald-800'],
        'done' => ['wrap' => 'bg-emerald-50 border-emerald-200', 'icon' => 'bg-emerald-100 text-emerald-700', 'glyph' => 'check-circle', 'label' => 'text-emerald-800'],
        'cancelled' => ['wrap' => 'bg-rose-50 border-rose-200', 'icon' => 'bg-rose-100 text-rose-700', 'glyph' => 'ban', 'label' => 'text-rose-800'],
    ];
    $tone = $tones[$step['state']];
    $button = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2';
    $outline = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-navy-700 transition-colors';
@endphp

<section class="rounded-xl border px-4 sm:px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4 {{ $tone['wrap'] }}" aria-label="Next step" data-next-step="{{ $step['state'] }}">
    <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tone['icon'] }}">
        <i data-lucide="{{ $tone['glyph'] }}" class="w-5 h-5"></i>
    </span>

    <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold {{ $tone['label'] }}">
            Next step
            @if ($step['steps'] > 1 && $step['state'] !== 'cancelled')
                &middot; Step {{ $step['step'] }} of {{ $step['steps'] }}
            @endif
        </p>
        <p class="mt-0.5 text-base font-bold text-slate-900">{{ $step['title'] }}</p>
        @if ($step['detail'])
            <p class="mt-0.5 text-sm text-slate-700">{{ $step['detail'] }}</p>
        @endif
        @if (! empty($step['items']))
            @php $shown = array_slice($step['items'], 0, 6); @endphp
            <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1">
                @foreach ($shown as $item)
                    <li class="flex items-start gap-2 text-xs text-slate-700 min-w-0">
                        <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                        <span class="truncate" title="{{ $item }}">{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
            @if (count($step['items']) > count($shown))
                <p class="mt-1 text-xs font-semibold text-amber-800">+ {{ count($step['items']) - count($shown) }} more {{ $step['more'] ?? 'in the checklist below' }}</p>
            @endif
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2 shrink-0">
        @if ($step['state'] === 'todo' && $step['anchor'])
            <a href="#{{ $step['anchor'] }}" class="{{ $button }}">
                <span>{{ $step['action'] }}</span>
                <i data-lucide="arrow-down" class="w-4 h-4"></i>
            </a>
        @endif

        @if ($step['state'] === 'ready' && ($advanceUrl ?? null) && $step['next'])
            <form method="POST" action="{{ $advanceUrl }}" class="m-0">
                @csrf
                <button type="submit" class="{{ $button }}">
                    <span>Advance to {{ $step['next'] }}</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        @endif

        @foreach ($step['state'] === 'ready' ? ($links ?? []) : [] as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="{{ $loop->first ? $button : $outline }}">
                <i data-lucide="{{ $link['icon'] }}" class="w-4 h-4"></i>
                <span>{{ $link['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>
