<div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-xl space-y-6 shrink-0 relative overflow-hidden" x-data="{ mobileNavOpen: false }">
    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-accent via-primary to-navy"></div>

    <!-- Client Profile Header Card -->
    <div class="text-center pb-5 border-b border-gray-100 space-y-3">
        <div class="relative inline-block">
            @if($user->profile_photo_url)
                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="w-20 h-20 rounded-full object-cover border-4 border-primary/20 shadow-md mx-auto">
            @else
                <div class="w-20 h-20 rounded-full bg-navy text-accent font-heading font-extrabold text-3xl flex items-center justify-center border-4 border-primary/20 shadow-md mx-auto">
                    {{ substr($user->full_name, 0, 1) }}
                </div>
            @endif
            <span class="absolute bottom-0 right-0 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full" title="Active Client Account"></span>
        </div>

        <div>
            <span class="inline-block px-2.5 py-0.5 rounded-full bg-primary/10 text-primary font-extrabold text-[10px] uppercase tracking-wider mb-1">
                {{ $user->account_category ?? 'Individual' }} Category
            </span>
            <h3 class="font-heading text-lg font-bold text-dark">{{ $user->full_name }}</h3>
            <p class="text-xs text-dark/50 truncate max-w-[200px] mx-auto">{{ $user->email }}</p>
        </div>

        <!-- Mobile Navigation Toggle Button (Mobile Only) -->
        <button @click="mobileNavOpen = !mobileNavOpen" 
                type="button"
                class="w-full lg:hidden flex items-center justify-between px-4 py-2.5 bg-gray-50 hover:bg-gray-100 rounded-2xl text-xs font-bold text-dark border border-gray-200 transition-all mt-2"
                aria-label="Toggle client portal menu">
            <div class="flex items-center gap-2">
                <i data-lucide="compass" class="w-4 h-4 text-primary"></i>
                <span>Client Menu Options</span>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': mobileNavOpen }"></i>
        </button>
    </div>

    <!-- Client Sidebar Navigation Menu (Always visible on lg, toggled on mobile) -->
    <div class="space-y-6" :class="mobileNavOpen ? 'block' : 'hidden lg:block'">
        <nav class="space-y-2">
            <div class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-dark/40 mb-2">Client Portal Sidebar</div>

            <!-- 1. View Profile Button -->
            <a href="{{ route('client.profile') }}" 
               class="flex items-center justify-between px-4 py-3.5 rounded-2xl text-xs font-bold transition-all shadow-sm {{ request()->routeIs('client.profile') ? 'bg-navy text-white ring-2 ring-accent' : 'bg-gradient-to-r from-accent/20 to-primary/10 text-dark hover:bg-accent/30 border border-accent/20' }}">
                <div class="flex items-center gap-3">
                    <i data-lucide="eye" class="w-4 h-4 text-primary"></i>
                    <span class="font-heading font-bold">View Profile</span>
                </div>
            </a>

            <!-- 2. My Dashboard / Reservations -->
            <a href="{{ route('client.dashboard') }}" 
               class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs font-bold transition-all {{ request()->routeIs('client.dashboard') ? 'bg-primary text-white shadow-md' : 'text-dark/70 hover:bg-gray-50 hover:text-dark' }}">
                <div class="flex items-center gap-3">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>My Reservations</span>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ request()->routeIs('client.dashboard') ? 'bg-white/20 text-white' : 'bg-gray-100 text-dark/60' }}">
                    {{ $user->bookings()->count() }}
                </span>
            </a>

            <!-- 3. Inquire Now Link -->
            <a href="{{ route('client.dashboard') }}#inquire-section" 
               class="flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-bold text-dark/70 hover:bg-gray-50 hover:text-dark transition-all">
                <i data-lucide="message-square" class="w-4 h-4 text-primary"></i>
                <span>Inquire Now</span>
            </a>

            <!-- 4. Explore Tour Packages -->
            <a href="{{ route('packages.index') }}" 
               class="flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-bold text-dark/70 hover:bg-gray-50 hover:text-dark transition-all">
                <i data-lucide="compass" class="w-4 h-4 text-emerald-600"></i>
                <span>Explore Tour Packages</span>
            </a>
        </nav>

        <!-- Account Action / Sign Out -->
        <div class="pt-4 border-t border-gray-100">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-all">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </div>
</div>

