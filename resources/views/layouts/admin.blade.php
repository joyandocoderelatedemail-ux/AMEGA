<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#003B95">

    <title>@yield('title', 'Admin Dashboard - Amega Travel and Tours Services')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-body bg-gray-50 text-dark antialiased min-h-screen flex flex-col lg:flex-row relative overflow-x-hidden" 
      x-data="{ 
          sidebarOpen: false, 
          isHovered: false,
          closeMobile() {
              this.sidebarOpen = false;
              this.isHovered = false;
          }
      }"
      @keydown.escape.window="closeMobile()"
      :class="{ 'overflow-hidden': sidebarOpen }">

    <!-- Desktop Hover Trigger Area -->
    <div @mouseenter="isHovered = true" class="hidden lg:block fixed left-0 top-0 bottom-0 w-4 z-40"></div>

    <!-- Mobile Top Header (Fixed at Top of Screen) -->
    <header class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-navy text-white border-b border-white/10 flex items-center justify-between px-4 z-[80] shadow-xl">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Admin" class="h-8 w-auto object-contain">
            <span class="font-heading font-bold text-sm tracking-tight text-white/90 truncate max-w-[160px] sm:max-w-none">
                @yield('page_title', 'Admin Dashboard')
            </span>
        </a>

        <button @click="sidebarOpen = !sidebarOpen" 
                type="button"
                class="p-2 rounded-xl bg-accent text-dark font-extrabold shadow-md hover:bg-accent-dark transition-all flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-accent"
                aria-label="Toggle Mobile Navigation Menu">
            <i data-lucide="menu" class="w-5 h-5" x-show="!sidebarOpen"></i>
            <i data-lucide="x" class="w-5 h-5" x-show="sidebarOpen" style="display: none;"></i>
            <span class="text-xs font-heading font-bold hidden sm:inline">Menu</span>
        </button>
    </header>

    <!-- Floating Hallmark Mobile Navigation Trigger Pill -->
    <button @click="sidebarOpen = !sidebarOpen" 
            type="button"
            class="lg:hidden fixed bottom-6 left-6 z-[80] flex items-center gap-2 px-4 py-3 bg-gradient-to-r from-navy via-primary to-navy text-accent border border-accent/40 rounded-full shadow-2xl hover:scale-105 active:scale-95 transition-all focus:outline-none focus:ring-4 focus:ring-accent/40 group"
            aria-label="Floating Mobile Menu Toggle">
        <span class="relative flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-accent"></span>
        </span>
        <i data-lucide="layout-grid" class="w-4 h-4 text-accent group-hover:rotate-90 transition-transform duration-300"></i>
        <span class="font-heading font-extrabold text-xs uppercase tracking-wider text-white">Menu</span>
    </button>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div x-show="sidebarOpen" 
         @click="closeMobile()" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[95] bg-black/80 backdrop-blur-md lg:hidden"
         style="display: none;"></div>

    <!-- Mobile Dedicated Drawer Navigation (lg:hidden) -->
    <aside x-show="sidebarOpen"
           x-transition:enter="transition ease-out duration-300 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed inset-y-0 left-0 z-[100] w-72 bg-navy text-white flex flex-col justify-between shadow-2xl border-r border-white/10 lg:hidden"
           style="display: none;">
        
        <div class="sidebar-scroll flex-1 overflow-y-auto">
            <!-- Sidebar Header / Logo -->
            <div class="h-20 flex items-center justify-between px-6 border-b border-white/10 bg-black/10">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Admin" class="h-9 w-auto object-contain">
                </a>
                <button @click="closeMobile()" class="text-white/70 hover:text-white p-2 rounded-xl bg-white/10 hover:bg-white/15 transition-all" title="Close Sidebar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            @include('layouts.admin-nav', ['isMobile' => true])
        </div>

        <!-- Sidebar Footer / User Profile & Logout -->
        <div class="p-3 border-t border-white/10 shrink-0 bg-black/10">
            <div class="flex items-center gap-3 p-2.5 mb-2 bg-white/[0.06] hover:bg-white/[0.09] rounded-2xl border border-white/10 transition-colors">
                <div class="relative w-8 h-8 rounded-xl bg-gradient-to-br from-accent to-amber-500 text-navy font-black text-xs flex items-center justify-center shrink-0 shadow-sm">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                    <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full border border-navy {{ Auth::user()->isAdmin() ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                </div>
                <div class="overflow-hidden min-w-0 flex-1">
                    <div class="text-xs font-bold text-white truncate">{{ Auth::user()->name ?? 'Staff User' }}</div>
                    <div class="text-[10px] truncate flex items-center gap-1 mt-0.5">
                        <span class="{{ Auth::user()->isAdmin() ? 'text-amber-300 font-bold' : 'text-emerald-300 font-semibold' }}">
                            {{ Auth::user()->isAdmin() ? 'Administrator' : 'Travel Agent' }}
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <input type="hidden" name="redirect_to" value="admin">
                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition-all active:scale-[0.98]">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Desktop Peek & Hover Sidebar (Desktop Only: hidden lg:flex) -->
    <aside @mouseenter="isHovered = true"
           @mouseleave="isHovered = false"
           :class="isHovered ? 'translate-x-0 shadow-2xl border-white/20' : '-translate-x-[calc(100%-14px)] border-accent/60'" 
           class="hidden lg:flex fixed inset-y-0 left-0 z-50 w-64 bg-navy text-white flex-col justify-between transition-all duration-300 ease-in-out border-r group">
        
        <!-- Collapsed Peek Handle Indicator (Desktop Only) -->
        <div :class="isHovered ? 'opacity-0' : 'opacity-100'"
             class="absolute right-0.5 top-1/2 -translate-y-1/2 flex flex-col items-center gap-1.5 transition-opacity duration-300 pointer-events-none">
            <span class="w-2 h-12 rounded-full bg-accent animate-pulse shadow-[0_0_12px_rgba(244,180,0,0.7)]"></span>
        </div>

        <!-- Collapsed Hamburger Hint (Top Left) -->
        <div :class="isHovered ? 'opacity-0' : 'opacity-100'"
             class="absolute top-5 left-0.5 transition-opacity duration-300 pointer-events-none">
            <svg class="w-5 h-5 stroke-accent drop-shadow-[0_0_6px_rgba(244,180,0,0.6)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </div>

        <div class="sidebar-scroll flex-1 overflow-y-auto">
            <!-- Sidebar Header / Logo -->
            <div class="h-20 flex items-center justify-between px-6 border-b border-white/10 bg-black/10">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Admin" class="h-9 w-auto object-contain">
                </a>
            </div>

            @include('layouts.admin-nav', ['isMobile' => false])
        </div>

        <!-- Sidebar Footer / User Profile & Logout -->
        <div class="p-3 border-t border-white/10 shrink-0 bg-black/10">
            <div class="flex items-center gap-3 p-2.5 mb-2 bg-white/[0.06] hover:bg-white/[0.09] rounded-2xl border border-white/10 transition-colors">
                <div class="relative w-8 h-8 rounded-xl bg-gradient-to-br from-accent to-amber-500 text-navy font-black text-xs flex items-center justify-center shrink-0 shadow-sm">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                    <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full border border-navy {{ Auth::user()->isAdmin() ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                </div>
                <div class="overflow-hidden min-w-0 flex-1">
                    <div class="text-xs font-bold text-white truncate">{{ Auth::user()->name ?? 'Staff User' }}</div>
                    <div class="text-[10px] truncate flex items-center gap-1 mt-0.5">
                        <span class="{{ Auth::user()->isAdmin() ? 'text-amber-300 font-bold' : 'text-emerald-300 font-semibold' }}">
                            {{ Auth::user()->isAdmin() ? 'Administrator' : 'Travel Agent' }}
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <input type="hidden" name="redirect_to" value="admin">
                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition-all active:scale-[0.98]">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 pt-16 lg:pt-0 lg:pl-3.5">
        
        <!-- Desktop Topbar Header -->
        <header class="hidden lg:flex h-20 bg-white border-b border-gray-200 items-center justify-between px-8 shrink-0 shadow-sm">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="font-heading font-bold text-lg text-dark">@yield('page_title', 'Dashboard Overview')</h1>
                    <p class="text-xs text-dark/50">{{ now()->format('l, F j, Y') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    System Active
                </span>

                <div class="w-9 h-9 rounded-full bg-navy text-white flex items-center justify-center text-xs font-bold shadow-sm">
                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                </div>
            </div>
        </header>

        <!-- Main Body Canvas -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
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
