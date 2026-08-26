<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Immigration Counter - AMEGA')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-body text-dark antialiased min-h-screen flex flex-col">

    <!-- Top bar: the counter has no sidebar, everything lives up here -->
    <header class="bg-primary-dark text-white shadow-lg sticky top-0 z-40" x-data="{ menuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">

                <!-- Identity -->
                <a href="{{ route('admin.immigration.dashboard') }}" class="flex items-center gap-3 shrink-0 group">
                    <div class="w-9 h-9 rounded-xl bg-accent text-dark flex items-center justify-center shrink-0">
                        <i data-lucide="stamp" class="w-5 h-5"></i>
                    </div>
                    <div class="leading-tight">
                        <div class="font-heading font-black text-sm tracking-tight">AMEGA</div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-accent">Immigration Counter</div>
                    </div>
                </a>

                <!-- Primary navigation -->
                <nav class="hidden md:flex items-center gap-1">
                    @php
                        $navItems = [
                            ['route' => 'admin.immigration.dashboard', 'active' => 'admin.immigration.*', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
                            ['route' => 'admin.client-sheets.index', 'active' => 'admin.client-sheets.*', 'icon' => 'id-card', 'label' => 'Client Sheets'],
                            ['route' => 'admin.immigration-pricing.index', 'active' => 'admin.immigration-pricing.*', 'icon' => 'receipt', 'label' => 'Pricing'],
                            ['route' => 'admin.immigration-categories.index', 'active' => 'admin.immigration-categories.*', 'icon' => 'layers', 'label' => 'Categories'],
                        ];
                    @endphp

                    @foreach ($navItems as $item)
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold transition-all {{ request()->routeIs($item['active']) ? 'bg-white/15 text-white ring-1 ring-white/20' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                            <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <!-- Account -->
                <div class="flex items-center gap-2 shrink-0">
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}"
                           class="hidden sm:flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[11px] font-bold text-white/70 hover:bg-white/10 hover:text-white transition-all">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                            <span>Main Admin</span>
                        </a>
                    @endif

                    <div class="hidden sm:block text-right leading-tight mr-1">
                        <div class="text-xs font-bold">{{ Auth::user()->name }}</div>
                        <div class="text-[10px] text-white/50 capitalize">{{ Auth::user()->role }}</div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" title="Log out"
                                class="p-2.5 rounded-xl text-white/70 hover:bg-white/10 hover:text-white transition-all flex items-center">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                        </button>
                    </form>

                    <button type="button" @click="menuOpen = !menuOpen"
                            class="md:hidden p-2.5 rounded-xl text-white/80 hover:bg-white/10 transition-all">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile navigation -->
            <nav x-show="menuOpen" x-cloak class="md:hidden pb-4 flex flex-col gap-1">
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-xs font-bold transition-all {{ request()->routeIs($item['active']) ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
                @if (Auth::user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-xs font-bold text-white/70 hover:bg-white/10">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back to Main Admin</span>
                    </a>
                @endif
            </nav>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if (session('success'))
            <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 flex items-start gap-3">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5"></i>
                <p class="text-sm font-semibold text-emerald-900">{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 px-5 py-4 flex items-start gap-3">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
                <p class="text-sm font-semibold text-rose-900">{{ session('error') }}</p>
            </div>
        @endif

        @hasSection('page_title')
            <h1 class="font-heading text-2xl font-black text-dark tracking-tight mb-6">@yield('page_title')</h1>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-gray-200 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 text-[11px] text-dark/40 flex flex-col sm:flex-row justify-between gap-2">
            <span>AMEGA Travel and Tours Services &mdash; Bureau of Immigration accredited</span>
            <span>Unit 1&amp;2, Astrofield Building, Balibago, Angeles City</span>
        </div>
    </footer>

    <style>[x-cloak] { display: none !important; }</style>
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide && window.lucide.createIcons());
        document.addEventListener('alpine:initialized', () => window.lucide && window.lucide.createIcons());
    </script>
</body>
</html>
