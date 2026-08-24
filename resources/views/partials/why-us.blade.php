<section id="why-us" class="py-20 sm:py-24 section-gradient-warm relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="section-accent-corner" style="top: auto; bottom: -80px; right: auto; left: -80px;"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-subheading font-bold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Why Travelers Choose Us
            </span>
            <h2 class="font-heading font-black text-4xl sm:text-5xl text-dark mt-2 tracking-tight">Why Travelers Choose Us</h2>
            <p class="font-body font-normal text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto leading-relaxed">
                We go above and beyond to ensure every journey is seamless, safe, and spectacular.
            </p>
        </div>

        @php
            $reasons = [
                ['Trusted Travel Experts', 'With over 24 years of experience, our team brings deep local knowledge and global expertise to every trip we plan.', 'globe', 'Since 2001'],
                ['Affordable Packages', 'Competitive pricing without compromising quality. We work with top partners to bring you the best value.', 'badge-dollar-sign', 'Best Value'],
                ['Personalized Itineraries', 'Every traveler is unique. We tailor every detail of your journey to match your preferences, budget, and dreams.', 'map-pin', 'Custom Plans'],
                ['24/7 Customer Support', 'Round-the-clock assistance wherever you are. We are just a call or message away, anytime, anywhere.', 'headphones', 'Always Here'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ($reasons as $index => [$title, $desc, $icon, $badge])
                <div class="text-center p-8 rounded-2xl bg-white shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 animate-on-scroll" style="transition-delay: {{ $index * 0.12 }}s">
                    <div class="w-16 h-16 mx-auto mb-5 bg-primary/10 rounded-2xl flex items-center justify-center border-2 border-accent/20">
                        <i data-lucide="{{ $icon }}" class="w-8 h-8 text-primary"></i>
                    </div>
                    <span class="inline-block font-subheading font-bold text-[10px] tracking-widest uppercase px-3 py-1 rounded-full bg-accent/15 text-accent-dark mb-3">{{ $badge }}</span>
                    <h3 class="font-heading font-bold text-lg text-dark mb-3">{{ $title }}</h3>
                    <p class="font-body font-normal text-dark/60 text-sm leading-relaxed">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>