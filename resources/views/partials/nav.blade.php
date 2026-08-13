<nav id="navbar" 
     x-data="{ mobileNavOpen: false }"
     @keydown.escape.window="mobileNavOpen = false"
     :class="{ 'overflow-hidden': mobileNavOpen }"
     class="fixed top-0 left-0 right-0 z-[80] transition-all duration-500" 
     aria-label="Main navigation">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <a href="{{ request()->routeIs('home') ? '#hero' : route('home') }}" class="flex items-center gap-3" aria-label="AMEGA Home">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Travel & Tours" class="nav-logo-white h-10 w-auto object-contain transition-opacity duration-300">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA Travel & Tours" class="nav-logo-dark h-10 w-auto object-contain hidden transition-opacity duration-300">
            </a>

            <!-- Desktop Navigation -->
            <div class="hidden lg:flex items-center gap-7">
                <a href="{{ request()->routeIs('home') ? '#hero' : route('home') }}" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide">
                    Home
                </a>

                <a href="{{ request()->routeIs('home') ? '#services' : route('services') }}" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide">
                    Services
                </a>

                <!-- Tours Dropdown -->
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button @click="open = !open" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide flex items-center gap-1.5 py-2">
                        <span>Tours</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                         class="absolute top-full left-0 mt-1 w-60 bg-white/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 p-2.5 z-50 text-dark"
                         style="display: none;">
                        <a href="{{ request()->routeIs('home') ? '#destinations' : url('/tours#destinations') }}" @click="open = false" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors">
                            <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <i data-lucide="map-pin" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-dark">Domestic</div>
                                <div class="text-[10px] text-dark/50 font-normal">Philippine Islands & Packages</div>
                            </div>
                        </a>
                        <a href="{{ request()->routeIs('home') ? '#tours' : url('/tours#tours') }}" @click="open = false" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors mt-1">
                            <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <i data-lucide="globe" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-dark">International</div>
                                <div class="text-[10px] text-dark/50 font-normal">Global World Tours</div>
                            </div>
                        </a>
                        <a href="{{ route('packages.index') }}" @click="open = false" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-primary/5 hover:text-primary transition-colors mt-1 border-t border-gray-100">
                            <div class="w-8 h-8 rounded-lg bg-accent/20 text-dark flex items-center justify-center shrink-0">
                                <i data-lucide="compass" class="w-4 h-4 text-primary"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-dark">All Tour Packages</div>
                                <div class="text-[10px] text-dark/50 font-normal">Browse Full Directory</div>
                            </div>
                        </a>
                    </div>
                </div>

                <a href="{{ request()->routeIs('home') ? '#why-us' : route('why-us') }}" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide">
                    Why Choose Us
                </a>
                <a href="{{ request()->routeIs('home') ? '#gallery' : route('gallery') }}" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide">
                    Gallery
                </a>
                <a href="{{ request()->routeIs('home') ? '#testimonials' : route('testimonials') }}" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide">
                    Reviews
                </a>
                <a href="{{ request()->routeIs('home') ? '#about' : route('about') }}" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide">
                    About
                </a>
                <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}" class="nav-link text-white/80 hover:text-accent transition-all duration-300 text-sm font-medium tracking-wide">
                    Contact
                </a>

                @auth
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-full text-white text-xs font-bold transition-all border border-white/20">
                        <i data-lucide="shield" class="w-4 h-4 text-accent"></i>
                        <span>Staff Portal ({{ Auth::user()->name }})</span>
                    </a>
                @endauth

                <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}" class="px-6 py-2.5 bg-accent text-dark font-bold text-sm rounded-full hover:bg-accent-dark transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                    Book Now
                </a>
            </div>

            <!-- Mobile Menu Toggle Button (Native SVG for 100% Alpine Compatibility) -->
            <button @click="mobileNavOpen = !mobileNavOpen" 
                    id="menu-toggle" 
                    type="button"
                    class="lg:hidden text-accent focus:outline-none focus:ring-2 focus:ring-accent/50 rounded-xl p-2.5 z-[90] relative shadow-lg transition-transform active:scale-95 flex items-center justify-center" 
                    aria-label="Toggle navigation menu">
                <!-- Hamburger Icon when closed -->
                <svg x-show="!mobileNavOpen" class="w-6 h-6 stroke-current text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <!-- Close X Icon when open -->
                <svg x-show="mobileNavOpen" class="w-6 h-6 stroke-accent text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div x-show="mobileNavOpen"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="-translate-y-full opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="-translate-y-full opacity-0"
         id="mobile-menu" 
         class="lg:hidden fixed inset-0 bg-navy/98 backdrop-blur-xl z-[100] flex flex-col" 
         style="display: none;"
         role="dialog" 
         aria-modal="true" 
         aria-label="Mobile navigation">
        
        <!-- Drawer Header -->
        <div class="flex items-center justify-between h-20 px-4 sm:px-6 border-b border-white/10 shrink-0">
            <a href="{{ route('home') }}" @click="mobileNavOpen = false" class="mobile-nav-link flex items-center gap-3">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Travel & Tours" class="h-9 w-auto object-contain">
            </a>
            <button @click="mobileNavOpen = false" id="mobile-menu-close" class="text-white/80 hover:text-white p-2 rounded-xl bg-white/10 border border-white/10 focus:outline-none" aria-label="Close menu">
                <svg class="w-6 h-6 stroke-accent text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Drawer Content -->
        <div class="flex-1 overflow-y-auto px-6 py-8 flex flex-col items-center gap-6 text-center">
            <a href="{{ route('home') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/90 text-xl font-heading font-semibold hover:text-accent">
                Home
            </a>

            <a href="{{ route('services') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/90 text-xl font-heading font-semibold hover:text-accent">
                Services
            </a>

            <!-- Mobile Tours Submenu -->
            <div class="flex flex-col items-center gap-2 py-2 w-full border-y border-white/10">
                <span class="text-accent text-xs font-bold uppercase tracking-widest mb-1">Tours & Packages</span>
                <a href="{{ route('tours') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/80 text-base font-medium hover:text-accent">
                    Domestic Tours
                </a>
                <a href="{{ route('tours') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/80 text-base font-medium hover:text-accent">
                    International Tours
                </a>
                <a href="{{ route('packages.index') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-accent text-sm font-bold hover:underline mt-1">
                    View All Tour Packages &rarr;
                </a>
            </div>

            <a href="{{ route('why-us') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/90 text-xl font-heading font-semibold hover:text-accent">
                Why Choose Us
            </a>
            <a href="{{ route('gallery') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/90 text-xl font-heading font-semibold hover:text-accent">
                Gallery
            </a>
            <a href="{{ route('testimonials') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/90 text-xl font-heading font-semibold hover:text-accent">
                Reviews
            </a>
            <a href="{{ route('about') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/90 text-xl font-heading font-semibold hover:text-accent">
                About Us
            </a>
            <a href="{{ route('contact') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/90 text-xl font-heading font-semibold hover:text-accent">
                Contact Us
            </a>

            @auth
                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-accent text-lg font-heading font-semibold">
                        Admin Dashboard
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="w-full text-center">
                    @csrf
                    <button type="submit" class="text-rose-400 font-bold text-base hover:underline">
                        Sign Out ({{ Auth::user()->name }})
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" @click="mobileNavOpen = false" class="mobile-nav-link text-white/80 text-base font-medium hover:text-accent">
                    Sign In / Register
                </a>
            @endauth

            <a href="{{ route('contact') }}" @click="mobileNavOpen = false" class="mobile-nav-link w-full max-w-xs py-3.5 bg-accent text-dark font-bold rounded-full text-base hover:bg-accent-dark transition-all mt-4 shadow-lg">
                Book Now
            </a>
        </div>
    </div>
</nav>
