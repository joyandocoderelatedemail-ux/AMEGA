<footer class="bg-primary-dark text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-12">
            <div class="animate-on-scroll">
                <p class="font-heading text-lg font-bold text-white tracking-wide mb-4">AMEGA TRAVEL AND TOURS SERVICES</p>
                <p class="text-blue-200/60 text-sm leading-relaxed">
                    Your trusted partner for extraordinary travel experiences since 2001. Let us take you on a journey you'll never forget.
                </p>
            </div>

            <div class="animate-on-scroll">
                <h4 class="font-heading text-base font-semibold text-white mb-4">Quick Links</h4>
                <ul class="space-y-3">
                    @php $quickLinks = [['Home', route('home')], ['Our Core Services', route('services')], ['Tour Packages', route('tours')], ['Cruise Packages', route('cruises')], ['Why Travelers Choose Us', route('why-us')], ['Travel Moments & Gallery', route('gallery')], ['What Our Clients Say', route('testimonials')], ['Our Legacy & History', route('about')], ['FAQ', route('faq')], ['Get In Touch', route('contact')]]; @endphp
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
                            <a href="{{ route('packages.index', ['search' => $dest]) }}" class="text-blue-200/50 hover:text-accent transition-colors text-sm">{{ $dest }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10 py-8 bg-black/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- A null link shows the icon as "Coming soon" until the account exists. --}}
            {{-- Each icon on its official brand colour. --}}
            @php $socials = [
                ['Facebook', 'fa-facebook-f', 'https://www.facebook.com/amegatravel', '#1877F2'],
                ['TikTok', 'fa-tiktok', 'https://www.tiktok.com/@amegatravelandtours', '#000000'],
                ['WhatsApp', 'fa-whatsapp', null, '#25D366'],
                ['Viber', 'fa-viber', null, '#7360F2'],
                ['Threads', 'fa-threads', null, '#000000'],
                ['Instagram', 'fa-instagram', null, 'radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%)'],
            ]; @endphp
            <ul class="flex flex-wrap items-start justify-end gap-3 sm:gap-4">
                @foreach ($socials as [$name, $icon, $url, $bg])
                    <li class="flex flex-col items-center gap-1 w-16">
                        @if ($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" style="background: {{ $bg }}" class="w-8 h-8 rounded-lg flex items-center justify-center text-base text-white shadow-md hover:scale-110 transition-transform duration-200" aria-label="{{ $name }}">
                                <i class="fa-brands {{ $icon }}" aria-hidden="true"></i>
                            </a>
                            <span class="text-xs text-blue-200/60">{{ $name }}</span>
                        @else
                            <span style="background: {{ $bg }}" class="w-8 h-8 rounded-lg flex items-center justify-center text-base text-white shadow-md" title="{{ $name }}: coming soon">
                                <i class="fa-brands {{ $icon }}" aria-hidden="true"></i>
                            </span>
                            <span class="text-xs text-blue-200/60 text-center leading-tight">{{ $name }}<br><span class="text-blue-200/40">Coming soon</span></span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-blue-200/40 text-xs sm:text-sm">
            <p>&copy; Copyright 2017-2026. Amega Travel and Tours Services. All rights reserved. Developed and managed by RNZ Software Development Services</p>
            <p class="mt-2">
                <a href="{{ route('privacy') }}" class="hover:text-blue-200/70 underline underline-offset-2 transition-colors">Privacy Policy</a>
                <span aria-hidden="true" class="mx-2">·</span>
                <a href="{{ route('photo-credits') }}" class="hover:text-blue-200/70 underline underline-offset-2 transition-colors">Photo credits</a>
            </p>
        </div>
    </div>
</footer>
