<footer class="bg-primary-dark text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-12">
            <div class="animate-on-scroll">
                <div class="flex items-center gap-3 mb-4">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Travel & Tours" class="h-10 w-auto object-contain">
                </div>
                <p class="text-blue-200/60 text-sm leading-relaxed mb-6">
                    Your trusted partner for extraordinary travel experiences since 2001. Let us take you on a journey you'll never forget.
                </p>
                <div class="flex items-center gap-3">
                    <a href="#" class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center hover:bg-accent hover:text-dark transition-all duration-200" aria-label="Facebook">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="#" class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center hover:bg-accent hover:text-dark transition-all duration-200" aria-label="Instagram">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="#" class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center hover:bg-accent hover:text-dark transition-all duration-200" aria-label="X (Twitter)">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="#" class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center hover:bg-accent hover:text-dark transition-all duration-200" aria-label="LinkedIn">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.262-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
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

            <div class="animate-on-scroll">
                <h4 class="font-heading text-base font-semibold text-white mb-4">Newsletter</h4>
                <p class="text-blue-200/50 text-sm mb-4">Stay updated with exclusive deals, travel tips, and destination inspiration.</p>
                <form action="#" method="POST" class="flex flex-col gap-3">
                    @csrf
                    <input type="email" placeholder="Your email address" class="w-full px-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white text-sm placeholder-blue-200/40 focus:outline-none focus:border-accent transition-colors">
                    <button type="submit" class="w-full px-4 py-3 bg-accent text-dark text-sm font-bold rounded-xl hover:bg-accent-dark transition-all duration-300">
                        Subscribe
                    </button>
                </form>
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <p class="text-center text-blue-200/30 text-sm">
                &copy; {{ date('Y') }} AMEGA Travel & Tours. All rights reserved. | Your Trusted Travel Partner Since 2001.
            </p>
        </div>
    </div>
</footer>
