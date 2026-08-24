<section id="destinations" class="py-20 sm:py-24 bg-white relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="section-accent-corner"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-subheading font-bold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Domestic Island Packages
            </span>
            <h2 class="font-heading font-black text-4xl sm:text-5xl text-dark mt-2 tracking-tight">Domestic Island Packages</h2>
            <p class="font-body font-normal text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto leading-relaxed">
                Discover the breathtaking beauty of Philippine islands — from white sand beaches to crystal clear lagoons.
            </p>
        </div>

        <div class="mb-16">
            <div class="flex items-center gap-3 mb-8 animate-on-scroll">
                <div class="w-1.5 h-8 bg-accent rounded-full"></div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-dark">Local Destinations</h3>
                <span class="font-subheading font-semibold text-xs text-primary tracking-widest uppercase px-3 py-1 rounded-full bg-primary/5 border border-primary/20">Philippines</span>
            </div>

            @php
                $localDests = isset($localDestinations) ? $localDestinations : \App\Models\Destination::where('type', 'domestic')->where('is_featured', true)->get();
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($localDests as $index => $dest)
                    <div class="group card-lift animate-on-scroll" style="transition-delay: {{ $index * 0.1 }}s">
                        <div class="rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-sm h-full">
                            <div onclick="previewImage('{{ asset($dest->image) }}', '{{ addslashes($dest->name) }} - {{ addslashes($dest->location) }}')" class="relative h-56 img-zoom overflow-hidden cursor-pointer group/img">
                                <img src="{{ asset($dest->image) }}" alt="{{ $dest->name }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-600">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                                <div class="absolute top-4 left-4">
                                    <span class="text-xs font-bold tracking-widest uppercase px-3 py-1.5 rounded-full bg-accent text-dark">Local</span>
                                </div>
                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/img:opacity-100 transition-all duration-300 pointer-events-none">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-accent/90 text-dark font-bold text-xs shadow-lg">
                                        <i data-lucide="zoom-in" class="w-3.5 h-3.5"></i>
                                        Click to View Details
                                    </span>
                                </div>
                                <div class="absolute bottom-4 left-4 right-4">
                                    <h4 class="font-heading text-xl font-bold text-white">{{ $dest->name }}</h4>
                                    <p class="text-white/70 text-sm">{{ $dest->location }}</p>
                                </div>
                            </div>
                            <div class="p-5">
                                <p class="text-dark/50 text-sm leading-relaxed mb-4 line-clamp-2">{{ $dest->description }}</p>
                                <div class="flex items-center justify-between">
                                    <span class="font-heading text-xl font-black text-brand-red">From {{ $dest->starting_price }}</span>
                                    <a href="{{ route('packages.index') }}?search={{ urlencode($dest->name) }}" class="inline-flex items-center px-5 py-2.5 bg-[#005ADA] text-white text-xs font-bold rounded-full hover:bg-[#003B95] transition-all duration-300 shadow-md">
                                        View Packages
                                        <svg class="w-3.5 h-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <div class="flex items-center gap-3 mb-8 animate-on-scroll">
                <div class="w-1.5 h-8 bg-accent rounded-full"></div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-dark">International Destinations</h3>
                <span class="font-subheading font-semibold text-xs text-primary tracking-widest uppercase px-3 py-1 rounded-full bg-primary/5 border border-primary/20">Worldwide</span>
            </div>

            @php
                $intlDests = isset($intlDestinations) ? $intlDestinations : \App\Models\Destination::where('type', 'international')->where('is_featured', true)->get();
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($intlDests as $index => $dest)
                    <div class="group card-lift animate-on-scroll" style="transition-delay: {{ $index * 0.1 }}s">
                        <div class="rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-sm h-full">
                            <div class="relative h-56 img-zoom overflow-hidden">
                                <img src="{{ asset($dest->image) }}" alt="{{ $dest->name }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-600">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                                <div class="absolute top-4 left-4">
                                    <span class="text-xs font-bold tracking-widest uppercase px-3 py-1.5 rounded-full bg-primary/10 text-primary border border-primary/20">International</span>
                                </div>
                                <div class="absolute bottom-4 left-4 right-4">
                                    <h4 class="font-heading text-xl font-bold text-white">{{ $dest->name }}</h4>
                                    <p class="text-white/70 text-sm">{{ $dest->location }}</p>
                                </div>
                            </div>
                            <div class="p-5">
                                <p class="text-dark/50 text-sm leading-relaxed mb-4 line-clamp-2">{{ $dest->description }}</p>
                                <div class="flex items-center justify-between">
                                    <span class="font-heading text-xl font-black text-brand-red">From {{ $dest->starting_price }}</span>
                                    <a href="{{ route('packages.index') }}?search={{ urlencode($dest->name) }}" class="inline-flex items-center px-5 py-2.5 bg-[#005ADA] text-white text-xs font-bold rounded-full hover:bg-[#003B95] transition-all duration-300 shadow-md">
                                        View Packages
                                        <svg class="w-3.5 h-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>