<section id="services" class="py-20 sm:py-24 section-gradient-light relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="section-line"></div>
    <div class="section-accent-corner"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Header -->
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Our Core Services
            </span>
            <h2 class="font-heading text-4xl sm:text-5xl font-bold text-dark mt-2">Our Core Services</h2>
        </div>

        @php
            $dbServices = $services ?? \App\Models\Service::orderBy('order', 'asc')->get();
            $coreServices = [];
            foreach ($dbServices as $idx => $s) {
                $coreServices[] = [
                    'category' => $s->badge ?? 'Travel Service',
                    'number' => (string)($idx + 1),
                    'title' => $s->title,
                    'description' => $s->short_description,
                    'icon' => $s->icon ?? 'globe',
                    'badge' => $s->badge ?? 'Available',
                    'action' => 'Inquire Now',
                    'image' => asset($s->image ?? 'images/services/visa.jpg'),
                    'features' => array_filter(array_map('trim', explode('.', $s->full_description ?? 'Full assistance. Fast processing. Official support.'))),
                ];
            }
        @endphp

        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            @foreach ($coreServices as $index => $service)
                <div class="group card-lift animate-on-scroll rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-between order-{{ $service['number'] }} md:order-none" style="transition-delay: {{ $index * 0.1 }}s">
                    <div>
                        <!-- Image & Header Badge Overlay (Clickable) -->
                        <div onclick="previewImage('{{ $service['image'] }}', '{{ addslashes($service['title']) }} - {{ addslashes($service['category']) }}')" class="relative h-48 sm:h-52 img-zoom overflow-hidden cursor-pointer group/img">
                            <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            <div class="absolute inset-0 bg-gradient-to-t from-navy/85 via-navy/40 to-transparent transition-opacity duration-300 group-hover/img:opacity-90"></div>
                            
                            <!-- Click Zoom Hint Badge -->
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/img:opacity-100 transition-all duration-300 pointer-events-none">
                                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-accent/90 text-dark font-bold text-xs shadow-lg transform -translate-y-2 group-hover/img:translate-y-0 transition-transform">
                                    <i data-lucide="zoom-in" class="w-3.5 h-3.5"></i>
                                    Click to View Details
                                </span>
                            </div>

                            <!-- Category Badge Top Left -->
                            <div class="absolute top-4 left-4 flex items-center gap-2 bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-full shadow-sm">
                                <i data-lucide="{{ $service['icon'] }}" class="w-4 h-4 text-primary"></i>
                                <span class="text-xs font-semibold text-dark">{{ $service['category'] }}</span>
                            </div>

                            <!-- Number Tag Top Right -->
                            <div class="absolute top-4 right-4 w-9 h-9 rounded-full bg-accent/90 text-dark font-heading font-extrabold text-sm flex items-center justify-center shadow-sm">
                                0{{ $service['number'] }}
                            </div>

                            <!-- Title Overlay Bottom Left -->
                            <div class="absolute bottom-4 left-4 right-4">
                                <h3 class="font-heading text-xl sm:text-2xl font-bold text-white tracking-tight drop-shadow-sm">
                                    {{ $service['title'] }}
                                </h3>
                            </div>
                        </div>

                        <!-- Content Body -->
                        <div class="p-6 sm:p-7 space-y-5">
                            <p class="text-dark/70 text-sm leading-relaxed font-normal">
                                {{ $service['description'] }}
                            </p>

                            <!-- Bulleted Feature List -->
                            <div class="pt-2 border-t border-gray-100">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-dark/40 mb-3">Included Services & Processing:</h4>
                                <ul class="space-y-2.5">
                                    @foreach ($service['features'] as $feature)
                                        <li class="flex items-center gap-3 text-sm text-dark/80 font-medium">
                                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs shrink-0 font-bold">
                                                ✓
                                            </span>
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action Bar -->
                    <div class="px-6 sm:px-7 py-4 bg-gray-50/80 border-t border-gray-100 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-primary/10 text-primary font-semibold text-xs border border-primary/15">
                            <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                            {{ $service['badge'] }}
                        </span>

                        <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}" onclick="selectServiceInquiry('{{ addslashes($service['title']) }} - {{ addslashes($service['action']) }}')" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-bold text-xs sm:text-sm rounded-full hover:bg-primary-dark transition-all duration-300 shadow-sm hover:shadow group-hover:bg-accent group-hover:text-dark">
                            <span>{{ $service['action'] }}</span>
                            <i data-lucide="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<script>
    function selectServiceInquiry(serviceName) {
        const messageInput = document.getElementById('message');
        if (messageInput) {
            messageInput.value = "Hello AMEGA Team,\n\nI am interested in requesting details for: " + serviceName + ".\nPlease provide instructions and processing requirements.\n\nThank you!";
        }
    }
</script>