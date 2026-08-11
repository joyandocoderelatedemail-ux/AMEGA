<section id="about" class="py-20 sm:py-24 bg-white relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="section-accent-corner"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div class="animate-on-scroll">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    Our Legacy & History
                </span>
                <h2 class="font-heading text-4xl sm:text-5xl font-bold text-dark mt-2 mb-6">Our Legacy & History</h2>

                <div class="space-y-6">
                    <div>
                        <h3 class="font-heading text-lg font-bold text-primary mb-2">24+ Years of Excellence</h3>
                        <p class="text-dark/60 leading-relaxed">
                            Founded in 2001, AMEGA Travel & Tours has been crafting unforgettable journeys for over 50,000 happy travelers. With official accreditation from the Department of Tourism, Bureau of Immigration, and PRA, we are your most trusted travel partner in the Philippines.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-heading text-lg font-bold text-primary mb-2">Our Mission</h3>
                        <p class="text-dark/60 leading-relaxed">
                            To make extraordinary travel accessible to everyone by providing personalized service, expert guidance, and exceptional value — creating memories that last a lifetime.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-heading text-lg font-bold text-primary mb-2">Our Vision</h3>
                        <p class="text-dark/60 leading-relaxed">
                            To be the world's most trusted travel partner, connecting people with unforgettable experiences while promoting sustainable and responsible tourism across the globe.
                        </p>
                    </div>
                </div>
            </div>

            <div class="animate-on-scroll space-y-6">
                <div class="rounded-2xl overflow-hidden shadow-md border border-gray-100">
                    <div class="aspect-[16/10]">
                        <img src="{{ asset('images/services/visa.jpg') }}" alt="AMEGA Travel" loading="lazy" class="w-full h-full object-cover">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    @php
                        $stats = [
                            ['50000', 'Happy Travelers', '+', '#0A4D8C'],
                            ['60', 'Countries', '+', '#F4B400'],
                            ['24', 'Years Experience', '+', '#0A4D8C'],
                            ['98', 'Satisfaction', '%', '#F4B400'],
                        ];
                    @endphp
                    @foreach ($stats as [$value, $label, $suffix, $color])
                        <div class="counter-card bg-gray-50 border border-gray-100 rounded-xl p-5 text-center">
                            <p class="font-heading text-2xl sm:text-3xl font-bold" style="color: {{ $color }}">
                                <span class="counter-value" data-target="{{ $value }}" data-suffix="{{ $suffix }}" data-duration="2000">0{{ $suffix }}</span>
                            </p>
                            <p class="text-dark/40 text-sm mt-1">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-20 animate-on-scroll">
            <div class="text-center mb-12">
                <span class="text-primary font-semibold text-sm tracking-widest uppercase">Accreditations</span>
                <h3 class="font-heading text-3xl font-bold text-dark mt-3">Fully Licensed & Regulatory Compliant</h3>
                <p class="text-dark/50 text-sm mt-3 max-w-2xl mx-auto">AMEGA Travel & Tours operates with full official accreditation from government agencies, ensuring complete safety and legal authorization.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @php
                    $accreditations = [
                        [
                            'logo' => asset('images/logoapproved/Department_of_Tourism_(DOT).svg.webp'),
                            'badge' => 'DOT Certified',
                            'title' => 'Department of Tourism',
                            'desc' => 'Official accreditation for domestic & international tour operations.'
                        ],
                        [
                            'logo' => asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png'),
                            'badge' => 'BI Accredited',
                            'title' => 'Bureau of Immigration',
                            'desc' => 'Authorized entity for travel processing & document assistance.'
                        ],
                        [
                            'logo' => asset('images/logoapproved/PhilipineretirementAutorityLogo.jpg'),
                            'badge' => 'PRA Authorized',
                            'title' => 'Philippine Retirement Authority',
                            'desc' => 'Specialized assistance for retirement travel & residency programs.'
                        ],
                        [
                            'logo' => asset('images/logoapproved/clarkdevelopmentlogo.webp'),
                            'badge' => 'CDC Registered',
                            'title' => 'Clark Development Corp.',
                            'desc' => 'Registered commercial partner in Central Luzon travel hub.'
                        ],
                    ];
                @endphp
                @foreach ($accreditations as $item)
                    <div class="p-6 rounded-2xl bg-white border border-gray-100 shadow-sm hover:border-primary/30 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col items-center text-center group">
                        <div class="w-24 h-24 mb-5 flex items-center justify-center p-3 rounded-2xl bg-gray-50 group-hover:bg-primary/5 border border-gray-100 group-hover:border-primary/20 transition-all duration-300">
                            <img src="{{ $item['logo'] }}" alt="{{ $item['title'] }}" class="max-h-full max-w-full object-contain filter group-hover:scale-105 transition-transform duration-300">
                        </div>
                        <span class="text-primary font-bold text-xs uppercase tracking-wider mb-1.5 px-3 py-1 rounded-full bg-primary/10">{{ $item['badge'] }}</span>
                        <h4 class="text-dark font-bold text-base mt-1">{{ $item['title'] }}</h4>
                        <p class="text-dark/50 text-xs mt-2 leading-relaxed">{{ $item['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
