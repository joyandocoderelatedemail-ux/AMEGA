{{-- The cashier portal: the ticketing portal's look, with the cashier's own tabs. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Cashier - AMEGA Travel and Tours')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800;900&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        /* Apply the saved theme before first paint so dark mode does not flash. */
        try { if (localStorage.getItem('amega_ticketing_theme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) { /* storage unavailable */ }
    </script>
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-minimal bg-slate-50 font-body text-slate-800 antialiased min-h-screen flex flex-col">

    @php
        /**
         * One navy bar carries the brand and account controls; the section tabs
         * sit on a white bar underneath so the header reads as a single unit
         * instead of two stacked blocks of blue.
         */
        $headerButton = 'inline-flex items-center justify-center gap-2 h-9 rounded-lg text-sm font-semibold whitespace-nowrap text-white/85 ring-1 ring-inset ring-white/15 hover:bg-white/10 hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent';

        $waiting = \App\Support\TicketNotifications::waitingForCashier();

        $navTabs = [
            ['route' => 'cashier.dashboard', 'icon' => 'ticket', 'label' => 'Ticketing', 'count' => $waiting['ticketing'], 'active' => request()->routeIs('cashier.dashboard', 'cashier.paid', 'cashier.payments.*')],
            ['route' => 'cashier.immigration', 'icon' => 'landmark', 'label' => 'Immigration', 'active' => request()->routeIs('cashier.immigration')],
            ['route' => 'cashier.visa.index', 'icon' => 'stamp', 'label' => 'Visa Assistance', 'count' => $waiting['visa'], 'active' => request()->routeIs('cashier.visa.*')],
            ['route' => 'cashier.srrv.index', 'icon' => 'badge-check', 'label' => 'PRA-SRRV', 'count' => $waiting['srrv'], 'active' => request()->routeIs('cashier.srrv.*')],
            ['route' => 'cashier.local-tours.index', 'icon' => 'map', 'label' => 'Local Tours', 'count' => $waiting['local_tours'], 'active' => request()->routeIs('cashier.local-tours.*')],
            ['route' => 'cashier.receivables.index', 'icon' => 'building-2', 'label' => 'Corporate A/R', 'active' => request()->routeIs('cashier.receivables.*')],
        ];
    @endphp

    <header class="sticky top-0 z-50 print:hidden">
        <div class="bg-navy-800 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-4">

                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('cashier.dashboard') }}" class="shrink-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA" class="h-7 sm:h-8 w-auto object-contain">
                    </a>
                    <span class="h-5 w-px bg-white/20" aria-hidden="true"></span>
                    <span class="text-sm font-semibold text-white/85 truncate">Cashier</span>
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
                        <div class="text-xs text-white/60 leading-4 capitalize">{{ Auth::user()->isCashier() ? 'Cashier' : ucfirst(Auth::user()->role) }}</div>
                    </div>

                    @include('layouts.desk-bell', ['url' => route('cashier.notifications'), 'initial' => \App\Support\TicketNotifications::forCashier(), 'buttonClass' => $headerButton])

                    <button type="button" id="theme-toggle" class="{{ $headerButton }} w-9" title="Switch theme" aria-label="Switch between light and dark theme">
                        <i data-lucide="moon" class="theme-icon-moon w-4 h-4"></i>
                        <i data-lucide="sun" class="theme-icon-sun w-4 h-4"></i>
                    </button>

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

        <nav class="bg-white border-b border-slate-200" aria-label="Cashier">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="-mb-px flex items-center gap-6 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($navTabs as $tab)
                        <a href="{{ route($tab['route']) }}"
                           @if ($tab['active']) aria-current="page" @endif
                           class="inline-flex items-center gap-2 h-12 border-b-2 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:text-navy-700 {{ $tab['active'] ? 'border-navy-700 text-navy-700' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
                            <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4"></i>
                            <span>{{ $tab['label'] }}</span>
                            @if (($tab['count'] ?? 0) > 0)
                                <span class="min-w-5 h-5 px-1.5 rounded-full bg-rose-600 text-white text-[11px] font-bold inline-flex items-center justify-center" title="{{ $tab['count'] }} waiting for payment">{{ $tab['count'] }}</span>
                            @endif
                        </a>
                    @endforeach

                    {{-- Look up a client's account: every booking and file with its payment history. --}}
                    <form method="GET" action="{{ route('cashier.accounts') }}" role="search" class="ml-auto my-1.5 shrink-0 flex items-center gap-2">
                        <label for="cashier-account-search" class="sr-only">Search an account</label>
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            <input id="cashier-account-search" type="search" name="search" value="{{ request()->routeIs('cashier.accounts') ? request('search') : '' }}"
                                   placeholder="Search an account" autocomplete="off"
                                   class="h-9 w-48 lg:w-64 pl-9 pr-3 rounded-lg bg-slate-50 border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">
                        </div>
                    </form>
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

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 print:hidden">
        &copy; {{ date('Y') }} Amega Travel and Tours Services. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            document.getElementById('theme-toggle')?.addEventListener('click', () => {
                const dark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('amega_ticketing_theme', dark ? 'dark' : 'light'); } catch (e) { /* storage unavailable */ }
            });
        });
    </script>
    @include('partials._inline-validation')
    @include('layouts.session-ended')
</body>
</html>
