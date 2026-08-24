<div x-data="{ mobileNavOpen: false }"
     x-effect="document.body.style.overflow = mobileNavOpen ? 'hidden' : ''"
     @keydown.escape.window="mobileNavOpen = false">

<nav id="navbar" 
     :class="{ 'drawer-open': mobileNavOpen }"
     class="fixed top-0 left-0 right-0 z-[120] transition-all duration-500" 
     aria-label="Main navigation">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-20">
            <a href="{{ request()->routeIs('home') ? '#hero' : route('home') }}" class="flex items-center gap-3" aria-label="AMEGA Home">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="Amega Travel and Tours Services" class="nav-logo-white h-10 w-auto object-contain transition-opacity duration-300">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services" class="nav-logo-dark h-10 w-auto object-contain hidden transition-opacity duration-300">
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

                <a href="{{ request()->routeIs('home') ? '#contact' : route('contact') }}" class="px-6 py-2.5 bg-[#005ADA] text-white font-bold text-sm rounded-full hover:bg-[#003B95] transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-0.5 border border-white/20">
                    Book Now
                </a>
            </div>

            <!-- Mobile Menu Toggle. The navbar stays visible above the dropdown, so this
                 button doubles as the close control and swaps to an X while open. -->
            <button @click="mobileNavOpen = !mobileNavOpen"
                    id="menu-toggle"
                    type="button"
                    class="lg:hidden text-accent focus:outline-none focus:ring-2 focus:ring-accent/50 rounded-xl p-3 relative shadow-lg transition-transform active:scale-95 flex items-center justify-center"
                    :aria-label="mobileNavOpen ? 'Close navigation menu' : 'Open navigation menu'"
                    aria-controls="mobile-menu"
                    :aria-expanded="mobileNavOpen ? 'true' : 'false'">
                <svg x-show="!mobileNavOpen" class="w-6 h-6 stroke-current text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="mobileNavOpen" class="w-6 h-6 stroke-current text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</nav>

<!-- Dim backdrop. Sits above the page and the chat widget (z-[90]/z-[100]) but below
     the navbar, so the header stays lit and its close button stays tappable. -->
<div x-show="mobileNavOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="mobileNavOpen = false"
     class="lg:hidden fixed inset-0 z-[110] bg-dark/50"
     style="display: none;"
     aria-hidden="true"></div>

<!-- Mobile dropdown panel. A sibling of #navbar rather than a child: the navbar's
     backdrop-filter would otherwise become this fixed element's containing block
     and clip it to the header strip. Anchored just below the bar and only as tall
     as its contents, so the page stays visible underneath. -->
<div x-show="mobileNavOpen"
         x-transition:enter="transition ease-out duration-250 transform"
         x-transition:enter-start="-translate-y-3 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-150 transform"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="-translate-y-3 opacity-0"
         id="mobile-menu"
         class="mobile-menu-panel lg:hidden fixed top-16 left-0 right-0 z-[115] max-h-[calc(100dvh-4rem)] overflow-y-auto overscroll-contain rounded-b-3xl shadow-2xl shadow-dark/40 border-t border-white/10"
         style="display: none;"
         role="dialog"
         aria-modal="true"
         aria-label="Navigation menu">

        @php
            $onHome = request()->routeIs('home');

            // Match the desktop behaviour: in-page anchors while on the home page,
            // real routes elsewhere. Previously every mobile item forced a full
            // page load, and both Tours items pointed at the same URL.
            $mobileLinks = [
                ['label' => 'Home',           'href' => $onHome ? '#hero'         : route('home'),         'route' => 'home'],
                ['label' => 'Services',       'href' => $onHome ? '#services'     : route('services'),     'route' => 'services'],
                ['label' => 'Why Choose Us',  'href' => $onHome ? '#why-us'       : route('why-us'),       'route' => 'why-us'],
                ['label' => 'Gallery',        'href' => $onHome ? '#gallery'      : route('gallery'),      'route' => 'gallery'],
                ['label' => 'Reviews',        'href' => $onHome ? '#testimonials' : route('testimonials'), 'route' => 'testimonials'],
                ['label' => 'About Us',       'href' => $onHome ? '#about'        : route('about'),        'route' => 'about'],
                ['label' => 'Contact Us',     'href' => $onHome ? '#contact'      : route('contact'),      'route' => 'contact'],
            ];

            $rowBase = 'mobile-nav-link flex items-center justify-between w-full px-4 py-3.5 rounded-xl text-lg font-heading font-semibold transition-colors';
        @endphp

        <!-- Panel content. The panel itself handles scrolling when the list is
             taller than the viewport, so this is a plain flow column. -->
        <div class="px-4 py-4 flex flex-col gap-1">
            @foreach ($mobileLinks as $link)
                @php $isActive = ! $onHome && request()->routeIs($link['route']); @endphp
                <a href="{{ $link['href'] }}"
                   @click="mobileNavOpen = false"
                   @class([$rowBase, 'bg-white/10 text-accent' => $isActive, 'text-white hover:bg-white/10 active:bg-white/[0.15]' => ! $isActive])
                   @if ($isActive) aria-current="page" @endif>
                    <span>{{ $link['label'] }}</span>
                    <svg class="w-4 h-4 opacity-40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                @if ($link['label'] === 'Services')
                    <!-- Tours group, kept inline so it reads as part of the same list -->
                    <div class="my-2 py-2 border-y border-white/15">
                        <span class="block px-4 pb-1 text-accent text-[11px] font-bold uppercase tracking-widest">Tours &amp; Packages</span>
                        <a href="{{ $onHome ? '#destinations' : url('/tours#destinations') }}" @click="mobileNavOpen = false" class="mobile-nav-link flex items-center justify-between w-full px-4 py-3 rounded-xl text-white/90 text-base font-medium hover:bg-white/10 active:bg-white/[0.15] transition-colors">
                            <span>Domestic Tours</span>
                            <svg class="w-4 h-4 opacity-40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <a href="{{ $onHome ? '#tours' : url('/tours#tours') }}" @click="mobileNavOpen = false" class="mobile-nav-link flex items-center justify-between w-full px-4 py-3 rounded-xl text-white/90 text-base font-medium hover:bg-white/10 active:bg-white/[0.15] transition-colors">
                            <span>International Tours</span>
                            <svg class="w-4 h-4 opacity-40 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <a href="{{ route('packages.index') }}" @click="mobileNavOpen = false" class="mobile-nav-link flex items-center gap-2 px-4 py-3 rounded-xl text-accent text-sm font-bold hover:bg-white/10 active:bg-white/[0.15] transition-colors">
                            View All Tour Packages &rarr;
                        </a>
                    </div>
                @endif
            @endforeach

            {{-- Staff-only links. Guests get no sign-in entry point here: this is a
                 public marketing menu, and the portal is reached from /login direct. --}}
            @auth
                <div class="mt-4 pt-4 border-t border-white/15 flex flex-col gap-1">
                    @if(Auth::user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" @click="mobileNavOpen = false" class="mobile-nav-link flex items-center w-full px-4 py-3.5 rounded-xl text-accent text-base font-heading font-semibold hover:bg-white/10 active:bg-white/[0.15] transition-colors">
                            Admin Dashboard
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center w-full px-4 py-3.5 rounded-xl text-rose-400 font-bold text-base hover:bg-white/10 active:bg-white/[0.15] transition-colors text-left">
                            Sign Out ({{ Auth::user()->name }})
                        </button>
                    </form>
                </div>
            @endauth
        </div>

        <div class="px-4 pt-3 pb-5 border-t border-white/15 bg-white/[0.03] rounded-b-3xl">
            <a href="{{ $onHome ? '#contact' : route('contact') }}" @click="mobileNavOpen = false" class="mobile-nav-link flex items-center justify-center w-full py-4 bg-accent text-dark font-bold rounded-full text-base hover:bg-accent-dark transition-all shadow-lg shadow-accent/20">
                Book Now
            </a>
        </div>
    </div>
</div>
