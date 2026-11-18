<section id="testimonials" class="py-20 sm:py-24 section-gradient-cool overflow-hidden relative">
    <div class="section-line"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="font-heading font-black text-4xl sm:text-5xl text-dark mt-2 tracking-tight">What Our Clients Say</h2>
            <p class="font-body font-normal text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto leading-relaxed">
                Real stories from real travelers who trusted AMEGA with their dream vacations and official document requests.
            </p>
        </div>

        @php
            // Newest first: the review date, then when it was added.
            $testimonialItems = $testimonials ?? \App\Models\Testimonial::where('is_published', true)->orderByDesc('reviewed_on')->latest()->get();
            $star = 'M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z';
        @endphp

        @if ($testimonialItems->isEmpty())
            <p class="text-center font-body text-dark/50">Client reviews will appear here soon.</p>
        @else
            <div class="relative group">
                {{-- Moves on its own (resources/js/app.js) and pauses while hovered or touched. --}}
                <div id="testimonial-track" class="flex gap-6 overflow-x-auto pb-4 snap-x snap-mandatory testimonial-scroll -mx-4 px-4 scroll-smooth">
                    @foreach ($testimonialItems as $index => $t)
                        <div class="min-w-[320px] sm:min-w-[400px] lg:min-w-[420px] max-w-[420px] snap-start animate-on-scroll" style="transition-delay: {{ min($index, 3) * 0.1 }}s">
                            <article class="bg-white border border-gray-200 rounded-2xl p-7 h-full flex flex-col shadow-card hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                                <div class="flex items-center justify-between gap-3 mb-5">
                                    @if ($t->rating)
                                        <div class="flex items-center gap-0.5" role="img" aria-label="Rated {{ $t->rating }} out of 5">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <svg class="w-5 h-5 {{ $i <= $t->rating ? 'text-yellow-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="{{ $star }}"/></svg>
                                            @endfor
                                        </div>
                                    @else
                                        <span></span>
                                    @endif
                                    @if ($t->reviewed_on)
                                        <time datetime="{{ $t->reviewed_on->toDateString() }}" class="font-body text-xs text-dark/45 whitespace-nowrap">{{ $t->reviewed_on->format('F j, Y') }}</time>
                                    @endif
                                </div>

                                @if (filled($t->comment))
                                    {{-- Long reviews show five lines and a Read more, so one long review does not stretch every card. --}}
                                    @php $longReview = mb_strlen($t->comment) > 220; @endphp
                                    <div class="relative mb-6" @if ($longReview) x-data="{ open: false }" @endif>
                                        <svg class="absolute -top-1 -left-1 w-8 h-8 text-primary/10" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151C7.546 6.068 5.983 8.789 5.983 11H10v10H0z"/></svg>
                                        <p id="review-{{ $t->id }}" class="relative font-body font-normal text-dark/75 leading-relaxed pl-3 {{ $longReview ? 'line-clamp-5' : '' }}"
                                           @if ($longReview) :class="open ? '!line-clamp-none' : ''" @endif>{{ $t->comment }}</p>
                                        @if ($longReview)
                                            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="review-{{ $t->id }}"
                                                    class="mt-2 pl-3 font-body text-sm font-semibold text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded"
                                                    x-text="open ? 'Show less' : 'Read more'">Read more</button>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-auto flex items-center gap-4 pt-5 border-t border-gray-100">
                                    @if ($t->avatar)
                                        <img src="{{ \App\Support\WebImage::url($t->avatar, \App\Support\WebImage::LOGO) }}" alt="{{ $t->name }}" loading="lazy" class="w-12 h-12 rounded-full object-cover shrink-0 ring-2 ring-yellow-400/40">
                                    @else
                                        <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold text-lg shrink-0" aria-hidden="true">
                                            {{ mb_strtoupper(mb_substr($t->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-heading font-bold text-dark truncate">{{ $t->name }}</p>
                                        @if (filled($t->location))
                                            <p class="font-subheading font-medium text-sm text-dark/50 truncate">{{ $t->location }}</p>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-center gap-4 mt-8">
                    <button id="testimonial-prev" type="button" class="w-10 h-10 rounded-full bg-white border border-gray-200 text-dark hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 flex items-center justify-center shadow-sm focus:outline-none focus:ring-2 focus:ring-primary" aria-label="Previous testimonial">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button id="testimonial-next" type="button" class="w-10 h-10 rounded-full bg-white border border-gray-200 text-dark hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 flex items-center justify-center shadow-sm focus:outline-none focus:ring-2 focus:ring-primary" aria-label="Next testimonial">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        @endif
    </div>
</section>
