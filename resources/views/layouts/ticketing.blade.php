<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Ticketing Portal - AMEGA Travel and Tours')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800;900&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-body text-dark antialiased min-h-screen flex flex-col">

    <!-- Header Navigation -->
    <header class="bg-navy text-white border-b border-white/10 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-4">
            
            <!-- Brand / Logo -->
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="{{ route('ticketing.dashboard') }}" class="flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded-xl">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA" class="h-7 sm:h-9 w-auto object-contain">
                </a>
                <div class="h-6 w-px bg-white/20 hidden sm:block"></div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] sm:text-xs font-heading font-extrabold uppercase tracking-wider bg-accent/20 text-accent border border-accent/30">
                        <i data-lucide="ticket" class="w-3.5 h-3.5 mr-1 sm:inline hidden"></i>
                        Ticketing Portal
                    </span>
                </div>
            </div>

            <!-- User Status & Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                @if(Auth::user()->isAdmin() || (Auth::user()->isAgent() && Auth::user()->canAccessPage('dashboard')))
                    <a href="{{ route('admin.dashboard') }}" 
                       class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white/80 hover:text-white bg-white/10 hover:bg-white/15 transition-all">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Main Admin</span>
                    </a>
                @endif

                <div class="hidden sm:flex flex-col text-right leading-tight pr-2">
                    <span class="text-xs font-heading font-bold text-white">{{ Auth::user()->name }}</span>
                    <span class="text-[10px] font-medium text-white/60 capitalize">{{ Auth::user()->role === 'ticketing' ? 'Ticketing Officer' : ucfirst(Auth::user()->role) }}</span>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <input type="hidden" name="redirect_to" value="admin">
                    <button type="submit" 
                            title="Log Out"
                            class="p-2 sm:px-3 sm:py-2 rounded-xl bg-white/10 hover:bg-rose-500/20 hover:text-rose-300 text-white/80 border border-white/10 hover:border-rose-400/30 text-xs font-bold transition-all flex items-center gap-1.5">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Sign Out</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Sub-navigation Bar -->
        <div class="bg-navy/90 border-t border-white/10 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto flex items-center gap-1 sm:gap-2 overflow-x-auto py-2">
                <a href="{{ route('ticketing.dashboard') }}" 
                   class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-heading font-bold whitespace-nowrap transition-colors {{ request()->routeIs('ticketing.dashboard') ? 'bg-primary text-white shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                    <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('ticketing.tickets.create') }}" 
                   class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-heading font-bold whitespace-nowrap transition-colors {{ request()->routeIs('ticketing.tickets.create') ? 'bg-accent text-dark shadow-sm' : 'text-accent hover:bg-accent/10' }}">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                    <span>New Ticket Booking</span>
                </a>
                <a href="{{ route('ticketing.tickets.index') }}" 
                   class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-heading font-bold whitespace-nowrap transition-colors {{ request()->routeIs('ticketing.tickets.index') || request()->routeIs('ticketing.tickets.show') ? 'bg-primary text-white shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                    <i data-lucide="tickets" class="w-3.5 h-3.5"></i>
                    <span>Ticket Directory</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Workspace Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
        
        <!-- Flash Feedback Messages -->
        @if (session('success'))
            <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-start gap-3 shadow-sm" role="status">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5"></i>
                <div>
                    <p class="text-xs font-bold text-emerald-900">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 p-4 flex items-start gap-3 shadow-sm" role="alert">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
                <div>
                    <p class="text-xs font-bold text-rose-900">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 py-4 text-center text-xs text-dark/40 font-medium">
        &copy; {{ date('Y') }} Amega Travel and Tours Services. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
