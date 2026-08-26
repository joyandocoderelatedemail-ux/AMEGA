@php
    // The dashboard is the hub — it navigates through cards, so it skips the section switcher.
    $isHub = View::hasSection('is_hub');

    $sections = [
        ['route' => 'admin.immigration.dashboard', 'active' => 'admin.immigration.*', 'icon' => 'layout-dashboard', 'label' => 'Counter'],
        ['route' => 'admin.client-sheets.index', 'active' => 'admin.client-sheets.*', 'icon' => 'id-card', 'label' => 'Client Sheets'],
        ['route' => 'admin.immigration-pricing.index', 'active' => 'admin.immigration-pricing.*', 'icon' => 'receipt', 'label' => 'Pricing'],
        ['route' => 'admin.immigration-categories.index', 'active' => 'admin.immigration-categories.*', 'icon' => 'layers', 'label' => 'Categories'],
    ];
@endphp
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

    <!-- Inline header: identity and account only, no bar -->
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 flex items-center justify-between gap-4">
        <a href="{{ route('admin.immigration.dashboard') }}"
           class="flex items-center gap-3 rounded-2xl cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
            <span class="w-10 h-10 rounded-2xl bg-primary text-white flex items-center justify-center shrink-0 shadow-sm">
                <i data-lucide="stamp" class="w-5 h-5" aria-hidden="true"></i>
            </span>
            <span class="leading-tight">
                <span class="block font-heading font-black text-sm text-dark tracking-tight">AMEGA</span>
                <span class="block text-[10px] font-bold uppercase tracking-widest text-primary">Immigration Counter</span>
            </span>
        </a>

        <div class="flex items-center gap-2">
            @if (Auth::user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}"
                   class="hidden sm:flex items-center gap-1.5 px-4 py-2.5 rounded-full text-[11px] font-bold text-dark/60 hover:text-dark hover:bg-gray-100 transition-colors duration-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5" aria-hidden="true"></i>
                    <span>Main Admin</span>
                </a>
            @endif

            <div class="hidden sm:block text-right leading-tight px-2">
                <div class="text-xs font-bold text-dark">{{ Auth::user()->name }}</div>
                <div class="text-[10px] text-dark/40 capitalize">{{ Auth::user()->role }}</div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" aria-label="Log out"
                        class="w-11 h-11 rounded-2xl bg-white border border-gray-200 text-dark/50 hover:text-rose-600 hover:border-rose-200 hover:bg-rose-50 transition-colors duration-200 flex items-center justify-center cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <i data-lucide="log-out" class="w-4 h-4" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>

    @unless ($isHub)
        <!-- Section switcher: a segmented pill control, inline rather than a bar -->
        <nav aria-label="Counter sections" class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            <div class="inline-flex items-center gap-1 p-1.5 rounded-2xl bg-white border border-gray-200/80 shadow-sm max-w-full overflow-x-auto">
                @foreach ($sections as $section)
                    @php $isActive = request()->routeIs($section['active']); @endphp
                    <a href="{{ route($section['route']) }}"
                       @if ($isActive) aria-current="page" @endif
                       class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold whitespace-nowrap transition-colors duration-200 cursor-pointer
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1
                              {{ $isActive ? 'bg-primary text-white shadow-sm' : 'text-dark/55 hover:text-dark hover:bg-gray-100' }}">
                        <i data-lucide="{{ $section['icon'] }}" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
                        <span>{{ $section['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endunless

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if (session('success'))
            <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 flex items-start gap-3" role="status">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" aria-hidden="true"></i>
                <p class="text-sm font-semibold text-emerald-900">{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 px-5 py-4 flex items-start gap-3" role="alert">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" aria-hidden="true"></i>
                <p class="text-sm font-semibold text-rose-900">{{ session('error') }}</p>
            </div>
        @endif

        @hasSection('page_title')
            <h1 class="font-heading text-2xl font-black text-dark tracking-tight mb-6">@yield('page_title')</h1>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-gray-200 bg-white mt-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 text-[11px] text-dark/40 flex flex-col sm:flex-row justify-between gap-2">
            <span>AMEGA Travel and Tours Services &mdash; Bureau of Immigration accredited</span>
            <span>Unit 1&amp;2, Astrofield Building, Balibago, Angeles City</span>
        </div>
    </footer>

    <style>
        [x-cloak] { display: none !important; }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide && window.lucide.createIcons());
        document.addEventListener('alpine:initialized', () => window.lucide && window.lucide.createIcons());
    </script>
</body>
</html>
