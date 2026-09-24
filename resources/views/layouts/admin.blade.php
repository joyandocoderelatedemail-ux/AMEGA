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
<body class="font-body bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col relative overflow-x-hidden" 
      x-data="{ 
          sidebarOpen: false, 
          isHovered: false,
          closeMobile() {
              this.sidebarOpen = false;
          }
      }"
      @keydown.escape.window="isHovered = false; closeMobile()"
      :class="{ 'overflow-hidden': sidebarOpen }">

    <!-- Mobile Top Header (Fixed at Top of Screen, Hidden on Desktop) -->
    <header class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-navy text-white border-b border-white/10 flex items-center justify-between px-4 z-[80] shadow-xl">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Admin" class="h-8 w-auto object-contain">
            <span class="font-heading font-bold text-sm tracking-tight text-white/90 truncate max-w-[160px] sm:max-w-none">
                @yield('page_title', 'Admin Dashboard')
            </span>
        </a>

        <button @click="sidebarOpen = !sidebarOpen" 
                type="button"
                class="p-2 rounded-xl bg-accent text-dark font-extrabold shadow-md hover:bg-accent-dark transition-all flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-accent cursor-pointer"
                aria-label="Toggle Mobile Navigation Menu"
                aria-controls="mobile-sidebar"
                :aria-expanded="sidebarOpen.toString()">
            <i data-lucide="menu" class="w-5 h-5" x-show="!sidebarOpen"></i>
            <i data-lucide="x" class="w-5 h-5" x-show="sidebarOpen" style="display: none;"></i>
            <span class="text-xs font-heading font-bold hidden sm:inline">Menu</span>
        </button>
    </header>

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
    <aside id="mobile-sidebar"
           x-show="sidebarOpen"
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
                <button @click="closeMobile()" class="text-white/70 hover:text-white p-2 rounded-xl bg-white/10 hover:bg-white/15 transition-all cursor-pointer" title="Close Sidebar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            @include('layouts.admin-nav', ['isMobile' => true])
        </div>

        <!-- Sidebar Footer / User Profile & Logout -->
        <div class="p-3 border-t border-white/10 shrink-0 bg-black/10">
            <div class="flex items-center gap-3 p-2.5 mb-2 bg-white/[0.06] rounded-xl border border-white/10">
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
                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition-all active:scale-[0.98] cursor-pointer">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Desktop Top Navigation Bar (lg and up) -->
    @include('layouts.admin-topnav')

    <!-- Main Content Area.
         Only the navigation is fixed. The page title scrolls with the content
         rather than occupying a second fixed strip, which gives ~56px of vertical
         space back on every admin screen. -->
    <div class="flex-1 flex flex-col min-w-0 pt-16">

        <!-- Main Body Canvas -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">

            <!-- Page header -->
            <div class="hidden lg:flex items-baseline justify-between gap-4 mb-6">
                <h1 class="font-heading font-bold text-xl text-slate-900 truncate">@yield('page_title', 'Dashboard Overview')</h1>
                <span class="text-xs text-slate-500 shrink-0">{{ now()->format('l, F j, Y') }}</span>
            </div>

            @if (session('success'))
                <div class="mb-6 px-4 py-3 rounded-2xl bg-emerald-50 text-emerald-800 text-sm font-semibold border border-emerald-200 flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 px-4 py-3 rounded-2xl bg-rose-50 text-rose-800 text-sm font-semibold border border-rose-200 flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
        document.addEventListener('alpine:initialized', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
