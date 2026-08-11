<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A1F3F">

    <title>@yield('title', 'Admin Dashboard - AMEGA Travel & Tours')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-body bg-gray-50 text-dark antialiased min-h-screen flex relative" x-data="{ sidebarOpen: false, isHovered: false }">

    <!-- Desktop Hover Trigger Handle Area -->
    <div @mouseenter="isHovered = true" class="hidden lg:block fixed left-0 top-0 bottom-0 w-4 z-40"></div>

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="sidebarOpen" 
         @click="sidebarOpen = false" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/60 lg:hidden"></div>

    <!-- Sidebar Navigation (Smooth Auto Hide & Hover Expand) -->
    <aside @mouseenter="isHovered = true"
           @mouseleave="isHovered = false"
           :class="(sidebarOpen || isHovered) ? 'translate-x-0 shadow-2xl border-white/20' : '-translate-x-full lg:-translate-x-[calc(100%-14px)] border-primary/30'" 
           class="fixed inset-y-0 left-0 z-50 w-64 bg-navy text-white flex flex-col justify-between transition-all duration-300 ease-in-out border-r group">
        
        <!-- Collapsed Peek Handle Indicator -->
        <div x-show="!isHovered && !sidebarOpen" class="hidden lg:flex absolute right-1 top-1/2 -translate-y-1/2 flex-col items-center gap-1.5 opacity-60 group-hover:opacity-100 transition-opacity">
            <span class="w-1.5 h-10 rounded-full bg-accent animate-pulse"></span>
        </div>

        <div>
            <!-- Sidebar Header / Logo -->
            <div class="h-20 flex items-center justify-between px-6 border-b border-white/10">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Admin" class="h-9 w-auto object-contain">
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-white/70 hover:text-white p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5">
                <div class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-accent mb-2">Management</div>

                @if(Auth::user()->canAccessPage('dashboard'))
                    <a href="{{ route('admin.dashboard') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        <span>Dashboard</span>
                    </a>
                @endif

                @if(Auth::user()->canAccessPage('packages'))
                    <a href="{{ route('admin.packages.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.packages.*') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="package" class="w-4 h-4"></i>
                        <span>Travel Packages</span>
                    </a>
                @endif

                @if(Auth::user()->canAccessPage('bookings'))
                    <a href="{{ route('admin.bookings.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.bookings.*') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span>Bookings</span>
                    </a>
                @endif

                @if(Auth::user()->canAccessPage('destinations'))
                    <a href="{{ route('admin.destinations.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.destinations.*') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                        <span>Destinations</span>
                    </a>
                @endif

                @if(Auth::user()->canAccessPage('inquiries'))
                    <a href="{{ route('admin.inquiries.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.inquiries.*') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="inbox" class="w-4 h-4"></i>
                        <span>Inquiries</span>
                    </a>
                @endif

                @if(Auth::user()->canAccessPage('users'))
                    <a href="{{ route('admin.users.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.users.*') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        <span>Client Accounts</span>
                    </a>
                @endif

                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.agents.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.agents.*') ? 'bg-emerald-600 text-white shadow-md' : 'text-emerald-300/80 hover:bg-emerald-500/10 hover:text-emerald-300' }}">
                        <i data-lucide="user-check" class="w-4 h-4 text-emerald-400"></i>
                        <span>Travel Agent Staff</span>
                    </a>

                    <a href="{{ route('admin.activity-logs.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.activity-logs.*') ? 'bg-amber-500 text-white shadow-md' : 'text-amber-300/80 hover:bg-amber-500/10 hover:text-amber-300' }}">
                        <i data-lucide="activity" class="w-4 h-4 text-amber-400"></i>
                        <span>Real-Time Audit Logs</span>
                    </a>
                @endif

                @if(Auth::user()->canAccessPage('services') || Auth::user()->canAccessPage('testimonials'))
                    <div class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-accent mt-6 mb-2">Content & Site</div>
                @endif

                @if(Auth::user()->canAccessPage('services'))
                    <a href="{{ route('admin.services.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.services.*') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="briefcase" class="w-4 h-4"></i>
                        <span>Services</span>
                    </a>
                @endif

                @if(Auth::user()->canAccessPage('testimonials'))
                    <a href="{{ route('admin.testimonials.index') }}" 
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('admin.testimonials.*') ? 'bg-primary text-white shadow-md' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="message-square" class="w-4 h-4"></i>
                        <span>Testimonials</span>
                    </a>
                @endif

                <a href="{{ route('home') }}" target="_blank" 
                   class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-white/70 hover:bg-white/10 hover:text-accent transition-all mt-4 border border-white/10">
                    <i data-lucide="external-link" class="w-4 h-4"></i>
                    <span>View Public Website</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer / Logout -->
        <div class="p-4 border-t border-white/10">
            <div class="flex items-center gap-3 px-3 py-2.5 mb-3 bg-white/5 rounded-xl border border-white/10">
                <div class="w-8 h-8 rounded-full bg-accent text-dark font-extrabold text-xs flex items-center justify-center shrink-0">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                </div>
                <div class="overflow-hidden">
                    <div class="text-xs font-bold text-white truncate">{{ Auth::user()->name ?? 'Staff User' }}</div>
                    <div class="text-[10px] truncate flex items-center gap-1 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full {{ Auth::user()->isAdmin() ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                        <span class="{{ Auth::user()->isAdmin() ? 'text-amber-300 font-bold' : 'text-emerald-300 font-semibold' }}">
                            {{ Auth::user()->isAdmin() ? 'Administrator' : 'Travel Agent' }}
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-bold text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition-all">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        
        <!-- Top Navbar -->
        <header class="h-20 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 lg:px-8 shrink-0">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="text-dark/70 hover:text-dark p-2 rounded-lg hover:bg-gray-100" title="Toggle Navigation Sidebar">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <div>
                    <h1 class="font-heading font-bold text-lg text-dark">@yield('page_title', 'Dashboard Overview')</h1>
                    <p class="text-xs text-dark/50 hidden sm:block">{{ now()->format('l, F j, Y') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    System Active
                </span>

                <div class="w-9 h-9 rounded-full bg-navy text-white flex items-center justify-center text-xs font-bold shadow-sm">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                </div>
            </div>
        </header>

        <!-- Main Body Canvas -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            @if (session('success'))
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 text-rose-800 text-xs font-semibold border border-rose-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

</body>
</html>
