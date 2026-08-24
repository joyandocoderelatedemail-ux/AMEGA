<section id="about" class="py-20 sm:py-24 bg-white relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="section-accent-corner"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div class="animate-on-scroll">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-subheading font-bold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    Our Legacy &amp; History
                </span>
                <h2 class="font-heading font-black text-4xl sm:text-5xl text-dark mt-2 mb-6 tracking-tight">Our Legacy &amp; History</h2>

                <div class="space-y-6">
                    <div>
                        <h3 class="font-heading font-bold text-lg text-primary mb-2">24 Years of Unmatched Travel Excellence</h3>
                        <p class="font-body font-normal text-dark/70 text-sm leading-relaxed">
                            Established on May 26, 2001, AMEGA Travel &amp; Tours Services started its journey providing domestic and international ticketing and passport assistance. Over the years, we grew into a premier one-stop travel agency specializing in Domestic &amp; International Tour Packages, Airline Ticketing, Visa Documentation, and Immigration Services (Bureau of Immigration &amp; PRA).
                        </p>
                    </div>
                    <div>
                        <h3 class="font-heading font-bold text-lg text-primary mb-2">Major Summit Partner &amp; Award-Winning Agency</h3>
                        <p class="font-body font-normal text-dark/70 text-sm leading-relaxed">
                            In official partnership with the Department of Tourism (Region III) and Clark Development Corporation, we handled land arrangements and tours for major milestones including MICECON, APEC 2015, ASEAN Summit 2017, and Junior Summit 2017. Earned prestigious honors like the Top Sales Agent Award from IATA Amity Travel and Las Palmas Tours.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-heading font-bold text-lg text-primary mb-2">Committed, Compassionate &amp; Competent</h3>
                        <p class="font-body font-normal text-dark/70 text-sm leading-relaxed">
                            Our team is dedicated to overall client satisfaction, personalized attention, and high quality standards. We continuously enhance our industry knowledge to deliver creative travel ideas that exceed expectations.
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
                            ['50000', 'Happy Travelers', '+', '#003B95'],
                            ['60', 'Countries', '+', '#A9BD00'],
                            ['24', 'Years Experience', '+', '#005ADA'],
                            ['98', 'Satisfaction', '%', '#859500'],
                        ];
                    @endphp
                    @foreach ($stats as [$value, $label, $suffix, $color])
                        <div class="counter-card bg-gray-50 border border-gray-100 rounded-xl p-5 text-center">
                            <p class="font-heading font-black text-2xl sm:text-3xl" style="color: {{ $color }}">
                                <span class="counter-value" data-target="{{ $value }}" data-suffix="{{ $suffix }}" data-duration="2000">0{{ $suffix }}</span>
                            </p>
                            <p class="font-subheading font-semibold text-dark/50 text-sm mt-1">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-20 animate-on-scroll">
            <div class="text-center mb-12">
                <span class="font-subheading font-bold text-primary text-xs tracking-widest uppercase">Accreditations</span>
                <h3 class="font-heading font-black text-3xl text-dark mt-2 tracking-tight">Fully Licensed &amp; Regulatory Compliant</h3>
                <p class="font-body font-normal text-dark/60 text-sm mt-2 max-w-2xl mx-auto">Amega Travel and Tours Services operates with full official accreditation from government agencies, ensuring complete safety and legal authorization.</p>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const counterElements = document.querySelectorAll('.counter-value');
        if (counterElements.length > 0) {
            const animateCounter = (el) => {
                if (el.dataset.animated) return;
                el.dataset.animated = 'true';

                const target = parseInt(el.getAttribute('data-target'), 10) || 0;
                const suffix = el.getAttribute('data-suffix') || '';
                const duration = parseInt(el.getAttribute('data-duration'), 10) || 2000;
                const frameDuration = 1000 / 60;
                const totalFrames = Math.round(duration / frameDuration);
                let frame = 0;

                const counterTimer = setInterval(() => {
                    frame++;
                    const progress = frame / totalFrames;
                    const easeProgress = 1 - Math.pow(1 - progress, 3);
                    const currentCount = Math.round(target * easeProgress);

                    el.textContent = currentCount.toLocaleString() + suffix;

                    if (frame >= totalFrames) {
                        el.textContent = target.toLocaleString() + suffix;
                        clearInterval(counterTimer);
                    }
                }, frameDuration);
            };

            const counterObserver = new IntersectionObserver((entries, observerInstance) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        observerInstance.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            counterElements.forEach(el => counterObserver.observe(el));
        }
    });
</script>
