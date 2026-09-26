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
<body class="bg-slate-50 font-body text-slate-800 antialiased min-h-screen flex flex-col">

    @php
        /**
         * One navy bar carries the brand and account controls; the section tabs
         * sit on a white bar underneath so the header reads as a single unit
         * instead of two stacked blocks of blue.
         */
        $headerButton = 'inline-flex items-center justify-center gap-2 h-9 rounded-lg text-sm font-semibold whitespace-nowrap text-white/85 ring-1 ring-inset ring-white/15 hover:bg-white/10 hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent';

        $navTabs = [
            ['route' => 'ticketing.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard', 'active' => request()->routeIs('ticketing.dashboard', 'ticketing.')],
            ['route' => 'ticketing.tickets.create', 'icon' => 'plus-circle', 'label' => 'New Ticket Booking', 'active' => request()->routeIs('ticketing.tickets.create')],
            ['route' => 'ticketing.tickets.index', 'icon' => 'tickets', 'label' => 'Ticket Directory', 'active' => request()->routeIs('ticketing.tickets.index', 'ticketing.tickets.show')],
        ];
    @endphp

    <header class="sticky top-0 z-50">
        <div class="bg-navy-800 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-4">

                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('ticketing.dashboard') }}" class="shrink-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA" class="h-7 sm:h-8 w-auto object-contain">
                    </a>
                    <span class="h-5 w-px bg-white/20" aria-hidden="true"></span>
                    <span class="text-sm font-semibold text-white/85 truncate">Ticketing Portal</span>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    @if(Auth::user()->isAdmin() || (Auth::user()->isAgent() && Auth::user()->hasAdminAccess()))
                        <a href="{{ route('admin.dashboard') }}" class="{{ $headerButton }} px-3 hidden sm:inline-flex">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span>Main Admin</span>
                        </a>
                        <span class="hidden lg:block h-6 w-px bg-white/15" aria-hidden="true"></span>
                    @endif

                    <div class="hidden lg:block text-right whitespace-nowrap">
                        <div class="max-w-[14rem] truncate text-sm font-semibold text-white leading-5">{{ Auth::user()->name }}</div>
                        <div class="text-xs text-white/60 leading-4 capitalize">{{ Auth::user()->role === 'ticketing' ? 'Ticketing Officer' : ucfirst(Auth::user()->role) }}</div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <input type="hidden" name="redirect_to" value="admin">
                        <button type="submit" title="Log Out" class="{{ $headerButton }} w-9 sm:w-auto sm:px-3">
                            <i data-lucide="log-out" class="w-4 h-4 shrink-0"></i>
                            <span class="hidden sm:inline">Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <nav class="bg-white border-b border-slate-200" aria-label="Ticketing">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="-mb-px flex items-center gap-6 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($navTabs as $tab)
                        <a href="{{ route($tab['route']) }}"
                           @if ($tab['active']) aria-current="page" @endif
                           class="inline-flex items-center gap-2 h-12 border-b-2 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:text-navy-700 {{ $tab['active'] ? 'border-navy-700 text-navy-700' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
                            <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4"></i>
                            <span>{{ $tab['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        @if (session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-start gap-3" role="status">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <p class="text-sm font-semibold text-emerald-900">{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 flex items-start gap-3" role="alert">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <p class="text-sm font-semibold text-rose-900">{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} Amega Travel and Tours Services. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
@if (session('clear_booking_draft'))
    {{-- The ticket was saved: the next Create Ticket starts empty. --}}
    <script>
        try {
            localStorage.removeItem('amega_ticket_booking_draft_v2');
            localStorage.removeItem('amega_ticket_pending_id');
        } catch (e) { /* storage unavailable */ }
    </script>
@endif
</body>
</html>
