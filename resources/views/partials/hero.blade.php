<section id="hero" class="relative min-h-screen flex items-center justify-center overflow-hidden">
    @php
        // Scenery only. The destination records point at tour flyers — full of
        // prices and itinerary text — which read as clutter behind the headline.
        // These are photographs of the places themselves; see /photo-credits.
        $heroCards = collect(\App\Support\PhotoCredits::deck())->pluck('path');
    @endphp

    <div class="absolute inset-0">
        <x-diagonal-marquee
            :cards="$heroCards"
            :angle="-25"
            :base-speed="140"
            height="h-full"
            card-overlay=""
            card-class="h-[220px] w-[320px] sm:h-[260px] sm:w-[360px] lg:h-[300px] lg:w-[400px]"
            fade-from="from-white"
            fade-height="h-1/6"
        />
        <div class="absolute inset-0 hero-gradient"></div>
    </div>

    <div class="hero-decorator"></div>
    <div class="hero-decorator"></div>
    <div class="hero-decorator"></div>

    <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-16">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="text-center lg:text-left">
                <div class="inline-flex items-center gap-3 px-5 py-2 bg-white/90 backdrop-blur-sm border border-gray-200/90 shadow-sm rounded-full mb-6 animate-on-scroll">
                    <span class="w-2 h-2 bg-accent rounded-full animate-pulse"></span>
                    <span class="font-subheading text-primary text-xs font-bold tracking-widest uppercase">Your Trusted Travel Partner Since 2001</span>
                </div>

                <h1 class="font-heading text-5xl sm:text-6xl md:text-7xl lg:text-8xl font-black text-primary mb-2 leading-[1.02] tracking-tight animate-on-scroll animate-on-scroll-delay-1">
                    AMEGA
                </h1>

                <p class="font-heading text-2xl sm:text-3xl md:text-4xl text-[#005ADA] font-extrabold mb-4 max-w-xl mx-auto lg:mx-0 tracking-wide drop-shadow-sm animate-on-scroll animate-on-scroll-delay-2">
                    Travel and Tours Services
                </p>

                <p class="font-body text-base sm:text-lg text-slate-900 font-semibold mb-8 max-w-2xl mx-auto lg:mx-0 leading-relaxed drop-shadow-sm animate-on-scroll animate-on-scroll-delay-3">
                    Discover Local and International Destinations with <span class="text-[#003B95] font-bold">Amega Travel and Tours Services</span>. We make every journey simple, memorable, and worry-free.
                </p>

                <div class="flex flex-col sm:flex-row items-center gap-4 animate-on-scroll animate-on-scroll-delay-4">
                    <a href="#destinations" class="inline-flex items-center px-8 py-4 bg-[#005ADA] text-white font-bold rounded-full hover:bg-[#003B95] transition-all duration-300 shadow-xl hover:shadow-2xl hover:-translate-y-1 text-base focus:outline-none focus:ring-2 focus:ring-[#005ADA] focus:ring-offset-2">
                        Plan Your Trip
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    <a href="#tours" class="inline-flex items-center px-8 py-4 bg-white/90 text-primary border-2 border-primary/30 font-bold rounded-full hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 text-base shadow-sm backdrop-blur-sm">
                        Explore Packages
                    </a>
                </div>
            </div>

            <div class="booking-card animate-on-scroll animate-on-scroll-delay-4">
                <div class="bg-white/95 backdrop-blur-md rounded-2xl p-6 sm:p-8 shadow-2xl border border-gray-200/80">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <div>
                            <h3 class="font-heading text-base font-bold text-dark">Search Your Trip</h3>
                            <p class="text-xs text-dark/40">Find your perfect destination</p>
                        </div>
                    </div>
                    <form action="{{ route('packages.index') }}" method="GET" class="space-y-4">
                        <div>
                            <label class="block text-xs text-dark/50 font-medium mb-1.5">Destination</label>
                            <select name="search" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors appearance-none">
                                <option value="">Where are you going?</option>
                                <option value="local">Local Destinations</option>
                                <option value="international">International Destinations</option>
                                <option value="boracay">Boracay</option>
                                <option value="japan">Japan</option>
                                <option value="korea">South Korea</option>
                                <option value="switzerland">Switzerland</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-dark/50 font-medium mb-1.5">Departure</label>
                                <input type="date" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors">
                            </div>
                            <div>
                                <label class="block text-xs text-dark/50 font-medium mb-1.5">Return</label>
                                <input type="date" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-dark/50 font-medium mb-1.5">Travelers</label>
                            <div class="flex items-center gap-3">
                                <button type="button" class="w-10 h-10 rounded-xl bg-gray-50 border border-gray-200 text-dark hover:bg-gray-100 transition-colors flex items-center justify-center text-lg font-bold" onclick="const input=this.nextElementSibling;if(parseInt(input.value)>1)input.value=parseInt(input.value)-1">−</button>
                                <input type="number" value="2" min="1" max="20" class="w-full text-center px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                <button type="button" class="w-10 h-10 rounded-xl bg-gray-50 border border-gray-200 text-dark hover:bg-gray-100 transition-colors flex items-center justify-center text-lg font-bold" onclick="const input=this.previousElementSibling;if(parseInt(input.value)<20)input.value=parseInt(input.value)+1">+</button>
                            </div>
                        </div>
                        <button type="submit" class="w-full px-6 py-4 bg-[#005ADA] text-white font-bold rounded-xl hover:bg-[#003B95] transition-all duration-300 text-base shadow-lg flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Inquire Now
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce z-10" aria-hidden="true">
        <div class="w-8 h-14 rounded-full border-2 border-dark/25 flex items-start justify-center p-2">
            <div class="w-1 h-3 bg-primary/60 rounded-full animate-[scrollDown_2s_ease-in-out_infinite]"></div>
        </div>
    </div>
</section>