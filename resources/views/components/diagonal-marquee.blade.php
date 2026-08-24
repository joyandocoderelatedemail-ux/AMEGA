@props([
    // Array/Collection of cards. Each item may be:
    //   - a string path/URL          → 'images/gallery/beach-1.jpg'
    //   - an array                   → ['image' => '...', 'title' => '...']
    //   - an Eloquent model          → uses ->image / ->url and ->title / ->name
    'cards' => null,
    // Rotation of the whole marquee stack, in degrees.
    'angle' => -25,
    // Seconds for one full loop of the base row. Higher = slower.
    'baseSpeed' => 120,
    // Alternate scroll direction between rows.
    'alternateDirections' => true,
    // Height utility for the wrapper. Passed as a prop (not a class) so it can be
    // overridden cleanly — Blade has no tailwind-merge to resolve class conflicts.
    'height' => 'h-screen',
    // Extra classes for the individual cards and the top/bottom fades.
    'cardClass' => '',
    'fadeClass' => '',
    // Tint laid over each card. Lighten it when text sits on top of the marquee;
    // pass an empty string for full-colour images with no tint at all.
    'cardOverlay' => 'bg-black/40',
    // Depth of the top/bottom fades. Shrink it to let more of the motion show.
    'fadeHeight' => 'h-1/4',
    // Colour the fades blend into. Matches the section behind the marquee.
    'fadeFrom' => 'from-white dark:from-neutral-950',
    // How many times to repeat the deck per row. null = work it out from the deck
    // size. Bump it manually if a small deck leaves a gap on an ultra-wide screen.
    'repeat' => null,
])

@php
    // Credited scenery, not tour flyers — see App\Support\PhotoCredits.
    $defaultCards = [
        ['image' => 'images/marquee/boracay.jpg',   'title' => 'Boracay'],
        ['image' => 'images/marquee/fuji.jpg',      'title' => 'Mount Fuji'],
        ['image' => 'images/marquee/el-nido.jpg',   'title' => 'El Nido'],
        ['image' => 'images/marquee/santorini.jpg', 'title' => 'Santorini'],
        ['image' => 'images/marquee/banaue.jpg',    'title' => 'Banaue'],
        ['image' => 'images/marquee/halong.jpg',    'title' => 'Ha Long Bay'],
    ];

    $items = collect($cards ?: $defaultCards)
        ->map(function ($card) {
            if (is_string($card)) {
                return ['image' => $card, 'title' => ''];
            }

            $card = is_array($card) ? (object) $card : $card;
            $image = $card->image ?? $card->url ?? $card->image_url ?? null;
            $title = $card->title ?? $card->name ?? '';

            return $image ? ['image' => $image, 'title' => $title] : null;
        })
        ->filter()
        ->values();

    // Each row must be wider than the rotated 200vw container. The React original
    // hardcoded three passes; scaling to the deck size keeps big decks from
    // emitting hundreds of <img> tags while still covering small ones.
    $passes = $repeat ?? ($items->isEmpty() ? 1 : max(2, (int) ceil(12 / $items->count())));

    $rowCards = collect()->pad($passes, null)->flatMap(fn () => $items)->values();
    $rowCardsReverse = $rowCards->reverse()->values();

    $rows = [
        ['cards' => $rowCards,        'speed' => $baseSpeed,                  'reverse' => false],
        ['cards' => $rowCardsReverse, 'speed' => max($baseSpeed - 15, 30),    'reverse' => true],
        ['cards' => $rowCards,        'speed' => $baseSpeed + 15,             'reverse' => false],
        ['cards' => $rowCardsReverse, 'speed' => max($baseSpeed - 6, 35),     'reverse' => true],
        ['cards' => $rowCards,        'speed' => $baseSpeed + 24,             'reverse' => false],
    ];
@endphp

<div {{ $attributes->merge(['class' => "relative flex w-full items-center justify-center overflow-hidden {$height}"]) }}>
    <div class="absolute z-0 flex w-[200vw] flex-col gap-8" style="transform: rotate({{ $angle }}deg);" aria-hidden="true">
        @foreach ($rows as $row)
            @php
                $direction = $row['reverse'] && $alternateDirections ? 'right' : 'left';
            @endphp
            <div class="flex w-full overflow-hidden">
                <div class="flex shrink-0 animate-marquee-{{ $direction }} hover:[animation-play-state:paused]"
                     style="--marquee-speed: {{ $row['speed'] }}s;">
                    {{-- Two identical halves: the keyframes translate exactly -50%, so the
                         second half lands where the first started and the loop is seamless. --}}
                    @for ($half = 0; $half < 2; $half++)
                        <div class="flex shrink-0">
                            @foreach ($row['cards'] as $index => $card)
                                <div class="shrink-0 pr-8">
                                    <div @class([
                                        'group relative h-[300px] w-[400px] shrink-0 overflow-hidden rounded-xl shadow-2xl',
                                        $cardClass => $cardClass,
                                    ])>
                                        <img
                                            src="{{ \Illuminate\Support\Str::startsWith($card['image'], ['http://', 'https://', '//']) ? $card['image'] : asset($card['image']) }}"
                                            alt=""
                                            {{-- The second half only scrolls into view a full loop later,
                                                 so it can load lazily; the first half is on screen at once. --}}
                                            loading="{{ $half === 0 ? 'eager' : 'lazy' }}"
                                            decoding="async"
                                            class="h-full w-full object-cover"
                                        >
                                        @if ($cardOverlay)
                                            <div class="absolute inset-0 {{ $cardOverlay }}"></div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endfor
                </div>
            </div>
        @endforeach
    </div>

    <div @class(["pointer-events-none absolute inset-x-0 top-0 z-10 {$fadeHeight} bg-gradient-to-b to-transparent {$fadeFrom}", $fadeClass => $fadeClass])></div>
    <div @class(["pointer-events-none absolute inset-x-0 bottom-0 z-10 {$fadeHeight} bg-gradient-to-t to-transparent {$fadeFrom}", $fadeClass => $fadeClass])></div>

    {{ $slot }}
</div>
