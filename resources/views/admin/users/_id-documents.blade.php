@php
    /*
     * The client's uploaded identity documents side by side: passport, government ID and
     * any foreigner stamps. Each image sits in the same framed preview; a click opens it
     * full size over the page. A PDF shows as a file tile that opens in a new tab.
     */
    $isPdf = fn (?string $path) => str_ends_with(strtolower((string) $path), '.pdf');
    $documents = array_values(array_filter([
        [
            'title' => 'Passport',
            'url' => $user->passport_photo_url,
            'pdf' => $isPdf($user->passport_photo),
            'lines' => array_filter([
                $user->passport_number ? 'No. '.$user->passport_number : null,
                $user->passport_country,
                $user->passport_expiry ? 'Expires '.$user->passport_expiry->format('M j, Y') : null,
            ]),
            'empty' => 'No passport scan uploaded',
        ],
        [
            'title' => $user->government_id_type ?: 'Government ID',
            'url' => $user->government_id_photo_url,
            'pdf' => $isPdf($user->government_id_photo),
            'lines' => array_filter([
                $user->government_id_number ? 'No. '.$user->government_id_number : null,
                $user->government_id_type ? 'Government-issued ID' : null,
            ]),
            'empty' => 'No government ID uploaded',
        ],
        $user->stamps_photo_url ? [
            'title' => 'Multiple Stamps',
            'url' => $user->stamps_photo_url,
            'pdf' => $isPdf($user->stamps_photo),
            'lines' => ['Foreigner passport stamps'],
            'empty' => '',
        ] : null,
        $user->arrival_stamp_photo_url ? [
            'title' => 'Exception / Arrival Stamp',
            'url' => $user->arrival_stamp_photo_url,
            'pdf' => $isPdf($user->arrival_stamp_photo),
            'lines' => ['Foreigner arrival stamp'],
            'empty' => '',
        ] : null,
    ]));
@endphp

<section class="md:col-span-2 bg-white rounded-3xl p-6 sm:p-7 border border-gray-200 shadow-card space-y-5"
         x-data="{ viewer: null }" @keydown.escape.window="viewer = null" aria-labelledby="id-documents">
    <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
        <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
            <i data-lucide="id-card" class="w-4 h-4"></i>
        </div>
        <div>
            <h3 id="id-documents" class="font-heading text-base font-bold text-dark">ID Documents</h3>
            <p class="text-[11px] text-dark/50">The client's uploaded passport and government ID. Click a document to see it full size.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        @foreach ($documents as $document)
            <figure class="rounded-2xl border border-gray-200 overflow-hidden bg-white">
                @if (! $document['url'])
                    <div class="flex flex-col items-center justify-center gap-2 bg-gray-50 text-dark/40" style="aspect-ratio: 3 / 2">
                        <i data-lucide="image-off" class="w-7 h-7"></i>
                        <span class="text-xs font-semibold">{{ $document['empty'] }}</span>
                    </div>
                @elseif ($document['pdf'])
                    <a href="{{ $document['url'] }}" target="_blank" rel="noopener"
                       class="flex flex-col items-center justify-center gap-2 bg-gray-50 text-dark/60 hover:text-primary transition-colors" style="aspect-ratio: 3 / 2">
                        <i data-lucide="file-text" class="w-9 h-9"></i>
                        <span class="text-xs font-bold">PDF document &middot; open in a new tab</span>
                    </a>
                @else
                    <button type="button" @click="viewer = {{ Js::from(['url' => $document['url'], 'title' => $document['title']]) }}"
                            class="block w-full bg-gray-50" style="aspect-ratio: 3 / 2; cursor: zoom-in" aria-label="View {{ $document['title'] }} full size">
                        <img src="{{ $document['url'] }}" alt="{{ $document['title'] }}" loading="lazy" class="w-full h-full object-contain p-2">
                    </button>
                @endif
                <figcaption class="flex items-center justify-between gap-3 px-4 py-3 border-t border-gray-100">
                    <div class="min-w-0">
                        <span class="block text-xs font-bold text-dark">{{ $document['title'] }}</span>
                        <span class="block text-[11px] text-dark/50 truncate">{{ implode(' · ', $document['lines']) ?: ' ' }}</span>
                    </div>
                    @if ($document['url'])
                        <a href="{{ $document['url'] }}" target="_blank" rel="noopener"
                           class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline">
                            Open <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                        </a>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>

    <!-- Full-size viewer -->
    <div x-show="viewer" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8" style="background: rgba(15, 23, 42, 0.85)"
         @click.self="viewer = null" role="dialog" aria-modal="true" :aria-label="viewer?.title">
        <div class="relative w-full max-w-4xl">
            <div class="flex items-center justify-between gap-3 mb-3 text-white">
                <span class="font-bold text-sm" x-text="viewer?.title"></span>
                <div class="flex items-center gap-2">
                    <a :href="viewer?.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">
                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Open original
                    </a>
                    <button type="button" @click="viewer = null" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i> Close
                    </button>
                </div>
            </div>
            <img :src="viewer?.url" :alt="viewer?.title" class="w-full rounded-2xl bg-white object-contain" style="max-height: 80vh">
        </div>
    </div>
</section>
