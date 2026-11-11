<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Local Tours - AMEGA Travel and Tours')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="staff-type bg-slate-50 font-body text-slate-800 antialiased min-h-screen flex flex-col">

    @php
        /**
         * Mirrors the ticketing portal shell: one navy bar for brand and account,
         * a white tab bar underneath for the sections.
         */
        $headerButton = 'inline-flex items-center justify-center gap-2 h-9 rounded-lg text-sm font-semibold whitespace-nowrap text-white/85 ring-1 ring-inset ring-white/15 hover:bg-white/10 hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent';

        $navTabs = [
            ['route' => 'local-tours.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Bookings', 'active' => request()->routeIs('local-tours.dashboard', 'local-tours.bookings.show', 'local-tours.bookings.edit')],
            ['route' => 'local-tours.bookings.create', 'icon' => 'plus', 'label' => 'New Booking', 'active' => request()->routeIs('local-tours.bookings.create'), 'permission' => 'local_tours.create'],
            ['route' => 'local-tours.procedures', 'icon' => 'book-open', 'label' => 'Suppliers & Procedures', 'active' => request()->routeIs('local-tours.procedures')],
        ];
    @endphp

    <header class="sticky top-0 z-50 print:hidden">
        <div class="bg-navy-800 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-4">

                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('local-tours.dashboard') }}" class="shrink-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA" class="h-7 sm:h-8 w-auto object-contain">
                    </a>
                    <span class="h-5 w-px bg-white/20" aria-hidden="true"></span>
                    <span class="text-sm font-semibold text-white/85 truncate">Local Tours</span>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    {{-- Approvals and other admin notifications, for whoever the admin bell reports to. --}}
                    @if (\App\Support\AdminNotifications::shown(Auth::user()))
                        @include('layouts.desk-bell', ['url' => route('admin.notifications'), 'initial' => \App\Support\AdminNotifications::for(Auth::user()), 'buttonClass' => $headerButton])
                    @endif
                    @include('layouts.desk-switcher', ['buttonClass' => $headerButton])

                    @if ($adminPanel = Auth::user()->adminPanelRoute())
                        <a href="{{ route($adminPanel) }}" class="{{ $headerButton }} px-3 hidden sm:inline-flex">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span>Main Admin</span>
                        </a>
                        <span class="hidden lg:block h-6 w-px bg-white/15" aria-hidden="true"></span>
                    @endif

                    <div class="hidden lg:block text-right whitespace-nowrap">
                        <div class="max-w-[14rem] truncate text-sm font-semibold text-white leading-5">{{ Auth::user()->name }}</div>
                        <div class="text-xs text-white/60 leading-4">{{ Auth::user()->roleLabel() }}</div>
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

        <nav class="bg-white border-b border-slate-200" aria-label="Local Tours sections">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="-mb-px flex items-center gap-6 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($navTabs as $tab)
                        @continue(isset($tab['permission']) && ! Auth::user()->hasPermission($tab['permission']))
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
            <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-start gap-3 print:hidden" role="status">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <p class="text-sm font-semibold text-emerald-900">{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 flex items-start gap-3 print:hidden" role="alert">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <p class="text-sm font-semibold text-rose-900">{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 print:hidden">
        &copy; {{ date('Y') }} Amega Travel and Tours Services. All rights reserved.
    </footer>

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide && window.lucide.createIcons());
        document.addEventListener('alpine:initialized', () => window.lucide && window.lucide.createIcons());
    </script>
    @include('partials._inline-validation')
    @include('layouts.session-ended')
</body>
</html>
