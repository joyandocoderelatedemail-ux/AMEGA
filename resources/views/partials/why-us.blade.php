<section id="why-us" class="py-20 sm:py-24 section-gradient-warm relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="section-accent-corner" style="top: auto; bottom: -80px; right: auto; left: -80px;"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="font-heading font-black text-4xl sm:text-5xl text-dark mt-2 tracking-tight">Why Travelers Choose Us</h2>
            <p class="font-body font-normal text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto leading-relaxed">
                Reliable service, fair prices and trips planned around you, from the first inquiry to your return home.
            </p>
        </div>

        @php
            $reasons = [
                ['Trusted Travel Experts', 'For more than two decades, our team has arranged flights, tours, visas and travel documents for families, groups and companies.', 'globe', 'Since 2001'],
                ['Affordable Packages', 'We work directly with airlines, hotels and tour partners to offer competitive rates without cutting corners on quality.', 'badge-dollar-sign', 'Best Value'],
                ['Personalized Itineraries', 'Share your dates, budget and interests, and we will plan a trip that fits them, from flights and hotels to tours and transfers.', 'map-pin', 'Custom Plans'],
                ['Customer Support', 'Reach us by phone, email or chat for help before you leave, while you are away and after you return.', 'headphones', 'Here to Help'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ($reasons as $index => [$title, $desc, $icon, $badge])
                <div class="text-center p-8 rounded-2xl bg-white shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 animate-on-scroll" style="transition-delay: {{ $index * 0.12 }}s">
                    <div class="w-16 h-16 mx-auto mb-5 bg-primary rounded-2xl flex items-center justify-center shadow-md shadow-primary/20">
                        <i data-lucide="{{ $icon }}" class="w-8 h-8 text-white"></i>
                    </div>
                    <span class="inline-block font-subheading font-bold text-[10px] tracking-widest uppercase px-3 py-1 rounded-full bg-accent/15 text-accent-dark mb-3">{{ $badge }}</span>
                    <h3 class="font-heading font-bold text-lg text-dark mb-3">{{ $title }}</h3>
                    <p class="font-body font-normal text-dark/60 text-sm leading-relaxed">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>