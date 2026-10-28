@php
    /** The FAQ section: the published questions (Contents → FAQs), hidden when there are none. */
    $faqItems = $faqs ?? \App\Models\Faq::published()->get();
@endphp

@if ($faqItems->isNotEmpty())
<section id="faq" class="py-20 sm:py-24 bg-white">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- The FAQ page has its own heading above this section. --}}
        @unless (request()->routeIs('faq'))
        <div class="text-center mb-12 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-subheading font-bold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary"></span>
                FAQ
            </span>
            <h2 class="font-heading text-4xl sm:text-5xl text-dark mt-2 tracking-tight">Frequently Asked Questions</h2>
        </div>
        @endunless

        <div class="space-y-3">
            @foreach ($faqItems as $faq)
                <details class="group rounded-2xl border border-gray-200 bg-white shadow-sm open:shadow-md transition-shadow">
                    <summary class="flex items-center justify-between gap-4 px-5 sm:px-6 py-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                        <span class="font-heading font-bold text-dark text-base">{{ $faq->question }}</span>
                        <span class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 transition-transform group-open:rotate-45">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </span>
                    </summary>
                    <div class="px-5 sm:px-6 pb-5 text-sm text-dark/70 leading-relaxed whitespace-pre-line">{{ $faq->answer }}</div>
                </details>
            @endforeach
        </div>

        <p class="text-center text-sm text-dark/60 mt-8">
            Still have a question? <a href="{{ route('contact') }}#contact" class="font-semibold text-primary hover:underline">Send us an inquiry</a> or message us.
        </p>
    </div>

    {{-- Marked up for Google once, on the FAQ page itself. --}}
    @if (request()->routeIs('faq'))
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqItems->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->answer],
            ])->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
    @endif
</section>
@endif
