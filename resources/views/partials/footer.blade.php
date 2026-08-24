<footer class="bg-primary-dark text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-12">
            <div class="animate-on-scroll">
                <div class="flex items-center gap-3 mb-4">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="Amega Travel and Tours Services" class="h-10 w-auto object-contain">
                </div>
                <p class="text-blue-200/60 text-sm leading-relaxed mb-6">
                    Your trusted partner for extraordinary travel experiences since 2001. Let us take you on a journey you'll never forget.
                </p>
                <div class="flex items-center gap-3">
                    <a href="https://www.facebook.com/amegatravel" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center hover:bg-accent hover:text-dark transition-all duration-200" aria-label="Facebook">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://www.tiktok.com/@amegatravelandtours" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center hover:bg-accent hover:text-dark transition-all duration-200" aria-label="TikTok">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 1 1-5.2-1.74 2.89 2.89 0 0 1 2.31-2.83V7.63a6.33 6.33 0 0 0-5.11 6.16 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V9.69a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-2.22-1.12z"/></svg>
                    </a>
                </div>
            </div>

            <div class="animate-on-scroll">
                <h4 class="font-heading text-base font-semibold text-white mb-4">Quick Links</h4>
                <ul class="space-y-3">
                    @php $quickLinks = [['Home', route('home')], ['Our Core Services', route('services')], ['Tour Packages', route('tours')], ['Why Travelers Choose Us', route('why-us')], ['Travel Moments & Gallery', route('gallery')], ['What Our Clients Say', route('testimonials')], ['Our Legacy & History', route('about')], ['Get In Touch', route('contact')]]; @endphp
                    @foreach ($quickLinks as [$label, $href])
                        <li>
                            <a href="{{ $href }}" class="text-blue-200/50 hover:text-accent transition-colors text-sm">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="animate-on-scroll">
                <h4 class="font-heading text-base font-semibold text-white mb-4">Popular Destinations</h4>
                <ul class="space-y-3">
                    @php $popularDests = ['Boracay', 'Palawan', 'Cebu', 'Japan', 'South Korea', 'Switzerland', 'Bali', 'Dubai']; @endphp
                    @foreach ($popularDests as $dest)
                        <li>
                            <a href="#destinations" class="text-blue-200/50 hover:text-accent transition-colors text-sm">{{ $dest }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10 py-8 bg-black/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="text-center md:text-left">
                    <h5 class="text-xs font-semibold uppercase tracking-wider text-blue-200/80">Official Accreditations & Licenses</h5>
                    <p class="text-xs text-blue-200/50 mt-1">Fully licensed by government tourism, immigration, & development authorities</p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6">
                    <div class="h-14 w-32 bg-white rounded-xl p-2 flex items-center justify-center shadow-md hover:scale-105 transition-transform duration-300" title="Department of Tourism (DOT)">
                        <img src="{{ asset('images/logoapproved/Department_of_Tourism_(DOT).svg.webp') }}" alt="DOT Certified" class="max-h-full max-w-full object-contain">
                    </div>
                    <div class="h-14 w-32 bg-white rounded-xl p-2 flex items-center justify-center shadow-md hover:scale-105 transition-transform duration-300" title="Bureau of Immigration (BI)">
                        <img src="{{ asset('images/logoapproved/Bureau_of_Immigration_PH_2022.png') }}" alt="BI Accredited" class="max-h-full max-w-full object-contain">
                    </div>
                    <div class="h-14 w-32 bg-white rounded-xl p-2 flex items-center justify-center shadow-md hover:scale-105 transition-transform duration-300" title="Philippine Retirement Authority (PRA)">
                        <img src="{{ asset('images/logoapproved/PhilipineretirementAutorityLogo.jpg') }}" alt="PRA Authorized" class="max-h-full max-w-full object-contain">
                    </div>
                    <div class="h-14 w-32 bg-white rounded-xl p-2 flex items-center justify-center shadow-md hover:scale-105 transition-transform duration-300" title="Clark Development Corporation (CDC)">
                        <img src="{{ asset('images/logoapproved/clarkdevelopmentlogo.webp') }}" alt="CDC Registered" class="max-h-full max-w-full object-contain">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-blue-200/40 text-xs sm:text-sm">
            <p>&copy; Copyright 2017-2026. Amega Travel and Tours Services. All rights reserved. Developed and managed by RNZ Software Development Services</p>
        </div>
    </div>
</footer>
