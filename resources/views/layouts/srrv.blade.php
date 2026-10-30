<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SRRV Desk - AMEGA Travel and Tours')</title>

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
            ['route' => 'srrv.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Desk Dashboard', 'active' => request()->routeIs('srrv.dashboard')],
            // Like the other desks: a Services menu; each service opens its active files, and New starts one.
            ['icon' => 'briefcase', 'label' => 'Services', 'active' => request()->routeIs('srrv.services', 'srrv.start', 'srrv.applications.*', 'srrv.renewals.*', 'srrv.requests.*'), 'menu' => [
                ['url' => route('srrv.services', 'classic'), 'icon' => 'file-badge', 'label' => 'SRRV Classic', 'note' => 'New retiree, through to oath taking at the PRA'],
                ['url' => route('srrv.services', 'courtesy'), 'icon' => 'award', 'label' => 'SRRV Courtesy', 'note' => 'Former Filipinos, retired diplomats and international staff'],
                ['url' => route('srrv.services', 'renewal_classic'), 'icon' => 'calendar-check', 'label' => 'SRRV Classic Renewal', 'note' => 'Renewal of a Classic retiree\'s SRRV ID card'],
                ['url' => route('srrv.services', 'renewal_courtesy'), 'icon' => 'calendar-heart', 'label' => 'SRRV Courtesy Renewal', 'note' => 'Renewal of a Courtesy retiree\'s SRRV ID card'],
                ['url' => route('srrv.services', 'conversion'), 'icon' => 'trending-up', 'label' => 'SRRV Conversion into Active Investment', 'note' => 'Visa deposit into the sublease of a residential property'],
                ['url' => route('srrv.services', 'restamping'), 'icon' => 'stamp', 'label' => 'SRRV Re-stamping', 'note' => 'Move the SRRV sticker to a new passport'],
                ['url' => route('srrv.services', 'termination'), 'icon' => 'log-out', 'label' => 'SRRV Termination of Membership', 'note' => 'Cancel the SRRV and withdraw the visa deposit'],
            ]],
            // The PRA's blank forms for the counter, each opening in a new tab to print.
            ['icon' => 'printer', 'label' => 'Blank PRA Forms', 'active' => false, 'menu' => [
                ['url' => route('srrv.forms.blank', 'application'), 'icon' => 'file-text', 'label' => \App\Support\SrrvForms::LABELS['application'], 'note' => 'PRA-CR-FORM-0004, three pages', 'newTab' => true],
                ['url' => route('srrv.forms.blank', 'dependent'), 'icon' => 'users', 'label' => \App\Support\SrrvForms::LABELS['dependent'], 'note' => 'PRA-CR-FORM-0008, for a spouse or child', 'newTab' => true],
                ['url' => route('srrv.forms.blank', 'checklist'), 'icon' => 'list-checks', 'label' => \App\Support\SrrvForms::LABELS['checklist'], 'note' => 'PRA-CR-FORM-0001', 'newTab' => true],
                ['url' => route('srrv.forms.blank', 'medical'), 'icon' => 'stethoscope', 'label' => \App\Support\SrrvForms::LABELS['medical'], 'note' => 'PRA-CR-FORM-0012, for the physician', 'newTab' => true],
                ['url' => route('srrv.forms.blank', 'request'), 'icon' => 'file-pen-line', 'label' => \App\Support\SrrvForms::LABELS['request'], 'note' => 'PRA-SD-FORM-0006, renewal and re-stamping', 'newTab' => true],
            ]],
        ];

        // Government sites the desk works in all day, one click from every page.
        $externalLinks = [
            ['url' => 'https://pra.gov.ph/SRRVisa', 'label' => 'PRA SRRV', 'short' => 'PRA', 'title' => 'Philippine Retirement Authority SRRV page'],
        ];
    @endphp

    <header class="sticky top-0 z-50">
        <div class="bg-navy-800 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-4">

                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('srrv.dashboard') }}" class="shrink-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA" class="h-7 sm:h-8 w-auto object-contain">
                    </a>
                    <span class="h-5 w-px bg-white/20" aria-hidden="true"></span>
                    <span class="text-sm font-semibold text-white/85 truncate">SRRV Desk</span>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    @foreach ($externalLinks as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                           title="{{ $link['title'] }} (opens in a new tab)" aria-label="{{ $link['title'] }} (opens in a new tab)"
                           class="{{ $headerButton }} px-2.5 lg:px-3">
                            <i data-lucide="external-link" class="w-4 h-4 shrink-0 hidden lg:block"></i>
                            <span class="lg:hidden">{{ $link['short'] }}</span>
                            <span class="hidden lg:inline">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                    <span class="h-6 w-px bg-white/15" aria-hidden="true"></span>

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
                        <div class="text-xs text-white/60 leading-4 capitalize">{{ Auth::user()->role === 'srrv' ? 'SRRV Officer' : ucfirst(Auth::user()->role) }}</div>
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

        <nav class="bg-white border-b border-slate-200" aria-label="SRRV Desk sections">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="-mb-px flex items-center gap-6 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($navTabs as $tab)
                        @if (isset($tab['menu']))
                            @php $tabClass = 'inline-flex items-center gap-2 h-12 border-b-2 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:text-navy-700 '.($tab['active'] ? 'border-navy-700 text-navy-700' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300'); @endphp
                            {{-- Fixed under the tab: the tab bar scrolls sideways on phones and would clip a normal dropdown. --}}
                            <div x-data="{ open: false, top: 0, left: 0, place() { const r = $refs.button.getBoundingClientRect(); this.top = r.bottom; this.left = Math.max(8, Math.min(r.left, window.innerWidth - 296)); } }"
                                 @keydown.escape.window="open = false" @scroll.window="open && place()" @resize.window="open && place()">
                                <button type="button" x-ref="button" @click="place(); open = !open" aria-haspopup="menu" :aria-expanded="open" class="{{ $tabClass }}">
                                    <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4"></i>
                                    <span>{{ $tab['label'] }}</span>
                                    <span class="inline-flex transition-transform" :class="open && 'rotate-180'"><i data-lucide="chevron-down" class="w-4 h-4"></i></span>
                                </button>
                                <div x-show="open" x-cloak @click.outside="if (!$refs.button.contains($event.target)) open = false" role="menu"
                                     :style="`top: ${top}px; left: ${left}px; max-height: 70vh`"
                                     class="fixed z-50 mt-1 w-72 overflow-y-auto bg-white rounded-xl border border-slate-200 shadow-lg p-1.5">
                                    @foreach ($tab['menu'] as $service)
                                        @if (! empty($service['soon']))
                                            <div role="menuitem" aria-disabled="true" class="flex items-center gap-3 px-3 py-2.5 rounded-lg opacity-60 cursor-not-allowed">
                                                <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                                    <i data-lucide="{{ $service['icon'] }}" class="w-4 h-4"></i>
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-semibold text-slate-900">{{ $service['label'] }}</span>
                                                    <span class="block text-xs text-slate-500">{{ $service['note'] }}</span>
                                                </span>
                                            </div>
                                            @continue
                                        @endif
                                        <a href="{{ $service['url'] }}" role="menuitem" @if (! empty($service['newTab'])) target="_blank" rel="noopener" @endif
                                           class="group flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 focus-visible:outline-none focus-visible:bg-slate-50">
                                            <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 group-hover:bg-navy-700 group-hover:text-white transition-colors">
                                                <i data-lucide="{{ $service['icon'] }}" class="w-4 h-4"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-slate-900">{{ $service['label'] }}</span>
                                                <span class="block text-xs text-slate-500">{{ $service['note'] }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                            @continue
                        @endif
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

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide && window.lucide.createIcons());
        document.addEventListener('alpine:initialized', () => window.lucide && window.lucide.createIcons());
    </script>
    @include('partials._inline-validation')
</body>
</html>
