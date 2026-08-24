<section class="py-20 sm:py-24 relative overflow-hidden">
    <div class="absolute inset-0">
        <img src="{{ asset('images/cta/cta-bg.jpg') }}" alt="Your next adventure" class="w-full h-full object-cover">
        <div class="absolute inset-0 cta-gradient"></div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="animate-on-scroll">
            <div class="inline-flex items-center gap-3 px-5 py-2 bg-accent/15 backdrop-blur-sm border border-accent/40 rounded-full mb-6">
                <span class="w-2 h-2 bg-accent rounded-full animate-pulse"></span>
                <span class="font-subheading text-accent text-xs font-bold tracking-widest uppercase">Start Your Journey</span>
            </div>

            <h2 class="font-heading font-black text-4xl sm:text-5xl md:text-6xl text-white mb-6 leading-tight tracking-tight">
                Your Next Adventure<br>
                <span class="text-accent">Starts with AMEGA</span>
            </h2>

            <p class="font-body font-normal text-white/85 text-base sm:text-lg mb-10 max-w-2xl mx-auto leading-relaxed">
                Whether you dream of pristine beaches, majestic mountains, or vibrant cities — let us make it happen. Your perfect trip is just a click away.
            </p>

            <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}" class="inline-flex items-center px-10 py-5 bg-[#005ADA] text-white font-bold rounded-full hover:bg-[#003B95] transition-all duration-300 shadow-xl hover:shadow-2xl hover:-translate-y-1 text-lg focus:outline-none focus:ring-2 focus:ring-[#005ADA] focus:ring-offset-2 focus:ring-offset-transparent border border-white/20">
                Book Your Dream Vacation
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    </div>
</section>