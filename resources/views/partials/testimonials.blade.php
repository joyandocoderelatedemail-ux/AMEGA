<section id="testimonials" class="py-20 sm:py-24 section-gradient-cool overflow-hidden relative">
    <div class="section-line"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                What Our Clients Say
            </span>
            <h2 class="font-heading text-4xl sm:text-5xl font-bold text-dark mt-2">What Our Clients Say</h2>
            <p class="text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto font-normal leading-relaxed">
                Real stories from real travelers who trusted AMEGA with their dream vacations and official document requests.
            </p>
        </div>

        @php
            $testimonialItems = $testimonials ?? \App\Models\Testimonial::where('is_published', true)->get();
        @endphp

        <div class="relative group">
            <div id="testimonial-track" class="flex gap-6 overflow-x-auto pb-4 snap-x snap-mandatory testimonial-scroll -mx-4 px-4 scroll-smooth">
                @foreach ($testimonialItems as $index => $t)
                    <div class="min-w-[340px] sm:min-w-[400px] lg:min-w-[420px] snap-start animate-on-scroll" style="transition-delay: {{ $index * 0.1 }}s">
                        <div class="bg-white border border-gray-100 rounded-2xl p-8 h-full shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            <div class="flex items-center gap-1 mb-4">
                                @for ($i = 0; $i < 5; $i++)
                                    <svg class="w-5 h-5 {{ $i < $t->rating ? 'text-accent' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endfor
                            </div>
                            <div class="mb-6">
                                <svg class="w-8 h-8 text-primary/10 mb-2" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151C7.546 6.068 5.983 8.789 5.983 11H10v10H0z"/></svg>
                                <p class="text-dark/70 leading-relaxed italic">"{{ $t->comment }}"</p>
                            </div>
                            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-lg">
                                    {{ substr($t->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-heading font-semibold text-dark">{{ $t->name }}</p>
                                    <p class="text-sm text-dark/40">{{ $t->location }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-center gap-4 mt-8">
                <button id="testimonial-prev" class="w-10 h-10 rounded-full bg-white border border-gray-200 text-dark hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 flex items-center justify-center shadow-sm focus:outline-none focus:ring-2 focus:ring-primary" aria-label="Previous testimonial">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button id="testimonial-next" class="w-10 h-10 rounded-full bg-white border border-gray-200 text-dark hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 flex items-center justify-center shadow-sm focus:outline-none focus:ring-2 focus:ring-primary" aria-label="Next testimonial">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>
</section>
