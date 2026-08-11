<section id="tours" class="py-20 sm:py-24 section-gradient-cool relative overflow-hidden">
    <div class="section-line"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Tour Packages
            </span>
            <h2 class="font-heading text-4xl sm:text-5xl font-bold text-dark mt-2">Tour Packages</h2>
            <p class="text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto font-normal leading-relaxed">
                All-inclusive world tour packages designed to give you unforgettable experiences across Asia, Europe, America, and beyond.
            </p>
        </div>

        @php
            $packagesList = $travelPackages ?? \App\Models\TravelPackage::all();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($packagesList as $index => $pkg)
                <div class="group card-lift animate-on-scroll rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-md flex flex-col justify-between" style="transition-delay: {{ $index * 0.1 }}s">
                    <div>
                        <div onclick="previewImage('{{ asset($pkg->image) }}', '{{ addslashes($pkg->title) }} (Official Promo Poster)')" class="relative h-56 img-zoom overflow-hidden cursor-pointer group/img">
                            <img src="{{ asset($pkg->image) }}" alt="{{ $pkg->title }}" loading="lazy" decoding="async" class="w-full h-full object-cover transition-transform duration-600">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                            
                            <!-- Hover Zoom Hint -->
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/img:opacity-100 transition-all duration-300 pointer-events-none">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-accent/90 text-dark font-bold text-xs shadow-lg">
                                    <i data-lucide="zoom-in" class="w-3.5 h-3.5"></i>
                                    Click to View Details
                                </span>
                            </div>

                            <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between">
                                <span class="inline-block bg-accent text-dark text-xs font-bold px-3 py-1.5 rounded-full shadow-sm">{{ $pkg->duration }}</span>
                                <span class="bg-navy/80 backdrop-blur-md text-white text-xs px-2.5 py-1 rounded-full font-semibold">2026-2027 Package</span>
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="font-heading text-base font-bold text-dark mb-1">{{ $pkg->title }}</h3>
                            <div class="flex items-center gap-1 mb-3">
                                @for ($i = 0; $i < 5; $i++)
                                    <svg class="w-3.5 h-3.5 {{ $i < $pkg->rating ? 'text-accent' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endfor
                            </div>
                            <p class="text-dark/60 text-xs sm:text-sm leading-relaxed mb-4 line-clamp-2">{{ $pkg->description }}</p>
                        </div>
                    </div>

                    <div class="p-5 pt-0">
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100 gap-2">
                            <div>
                                <span class="text-[10px] text-dark/40 uppercase tracking-widest block">Starting Rate</span>
                                <span class="font-heading text-lg font-bold text-primary">{{ $pkg->price }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('packages.show', $pkg) }}" class="inline-flex items-center px-4 py-2 bg-primary text-white text-xs font-bold rounded-full hover:bg-primary-dark transition-all duration-300 shadow-md">
                                    <span>Book / Inquire</span>
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>