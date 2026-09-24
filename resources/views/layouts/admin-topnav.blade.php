@php
    /**
     * Desktop top navigation for the admin panel (lg and up).
     *
     * Every entry in $groups renders as a dropdown in the bar. The `page` key is
     * checked against User::canAccessPage() so agents only ever see the desks
     * they are allowed into, and `admin => true` restricts an entry to admins.
     * Active state is resolved here rather than in the markup so the trigger and
     * its children stay in sync with the current route.
     */
    $isAdmin = Auth::user()->isAdmin();

    $groups = [
        [
            'key' => 'operations',
            'label' => 'Operations',
            'icon' => 'layout-dashboard',
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => route('admin.dashboard'), 'page' => 'dashboard', 'active' => request()->routeIs('admin.dashboard')],
                ['label' => 'Travel Packages', 'icon' => 'package', 'url' => route('admin.packages.index'), 'page' => 'packages', 'active' => request()->routeIs('admin.packages.*')],
                ['label' => 'Bookings', 'icon' => 'calendar', 'url' => route('admin.bookings.index'), 'page' => 'bookings', 'active' => request()->routeIs('admin.bookings.*')],
                ['label' => 'Destinations', 'icon' => 'map-pin', 'url' => route('admin.destinations.index'), 'page' => 'destinations', 'active' => request()->routeIs('admin.destinations.*')],
                ['label' => 'Inquiries', 'icon' => 'inbox', 'url' => route('admin.inquiries.index'), 'page' => 'inquiries', 'active' => request()->routeIs('admin.inquiries.*')],
            ],
        ],
        [
            'key' => 'portals',
            'label' => 'Portals & Desks',
            'icon' => 'briefcase',
            'items' => [
                ['label' => 'Live Guest Chats', 'icon' => 'messages-square', 'url' => route('admin.chats.index'), 'page' => 'chats', 'active' => request()->routeIs('admin.chats.*'), 'live' => true],
                ['label' => 'Ticketing System', 'icon' => 'ticket', 'url' => route('ticketing.dashboard'), 'page' => 'ticketing', 'active' => request()->is('ticketing*'), 'badge' => 'Desk', 'badgeClass' => 'bg-navy/10 text-navy'],
                ['label' => 'Immigration Counter', 'icon' => 'stamp', 'url' => route('admin.immigration.dashboard'), 'page' => 'immigration', 'active' => request()->routeIs('admin.immigration.*'), 'badge' => 'Desk', 'badgeClass' => 'bg-navy/10 text-navy'],
                ['label' => 'Visa Assistance', 'icon' => 'globe', 'url' => route('visa.dashboard'), 'page' => 'visa_assistance', 'active' => request()->routeIs('visa.*'), 'badge' => 'Desk', 'badgeClass' => 'bg-navy/10 text-navy'],
                ['label' => 'SRRV Desk', 'icon' => 'landmark', 'url' => route('srrv.dashboard'), 'page' => 'srrv', 'active' => request()->routeIs('srrv.*'), 'badge' => 'Desk', 'badgeClass' => 'bg-navy/10 text-navy'],
            ],
        ],
        [
            'key' => 'administration',
            'label' => 'Administration',
            'icon' => 'shield-check',
            'items' => [
                ['label' => 'Client Accounts', 'icon' => 'users', 'url' => route('admin.users.index'), 'page' => 'users', 'active' => request()->routeIs('admin.users.*')],
                ['label' => 'Staff Accounts', 'icon' => 'user-check', 'url' => route('admin.agents.index'), 'page' => null, 'admin' => true, 'active' => request()->routeIs('admin.agents.*'), 'badge' => 'Staff', 'badgeClass' => 'bg-emerald-100 text-emerald-700'],
                ['label' => 'Audit Logs', 'icon' => 'activity', 'url' => route('admin.activity-logs.index'), 'page' => null, 'admin' => true, 'active' => request()->routeIs('admin.activity-logs.*'), 'badge' => 'Audit', 'badgeClass' => 'bg-amber-100 text-amber-700'],
            ],
        ],
        [
            'key' => 'content',
            'label' => 'Content & Site',
            'icon' => 'layout-template',
            'items' => [
                ['label' => 'Services', 'icon' => 'briefcase', 'url' => route('admin.services.index'), 'page' => 'services', 'active' => request()->routeIs('admin.services.*')],
                ['label' => 'Testimonials', 'icon' => 'message-square', 'url' => route('admin.testimonials.index'), 'page' => 'testimonials', 'active' => request()->routeIs('admin.testimonials.*')],
            ],
        ],
    ];

    $visibleGroups = [];

    foreach ($groups as $group) {
        $items = array_values(array_filter($group['items'], function (array $item) use ($isAdmin): bool {
            if (($item['admin'] ?? false) && ! $isAdmin) {
                return false;
            }

            $page = $item['page'] ?? null;

            return $page === null || Auth::user()->canAccessPage($page);
        }));

        if ($items === []) {
            continue;
        }

        $group['items'] = $items;
        $group['active'] = (bool) array_filter($items, fn (array $item): bool => (bool) $item['active']);
        $visibleGroups[] = $group;
    }
@endphp

<header class="hidden lg:block fixed top-0 inset-x-0 z-50 bg-navy text-white border-b border-white/10 shadow-lg">
    <div class="h-16 flex items-center gap-2 px-6">

        <!-- Brand. The full lockup needs ~141px, which squeezes the four dropdown triggers at
             exactly 1024px, so narrower desktops get the square brand mark instead. -->
        <a href="{{ route('admin.dashboard') }}"
           class="flex items-center gap-2.5 shrink-0 pr-4 xl:pr-5 mr-2 border-r border-white/15"
           title="Amega Admin Dashboard">
            <img src="{{ asset('newassets/Amega Brand/ICON/AMEGA INFINITY WHITE.png') }}"
                 alt="AMEGA Admin"
                 class="block xl:hidden h-8 w-8 object-contain">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}"
                 alt="AMEGA Admin"
                 class="hidden xl:block h-8 w-auto object-contain">
        </a>

        <!-- Primary navigation -->
        <nav class="flex items-center gap-1 min-w-0" aria-label="Admin primary navigation">
            @foreach ($visibleGroups as $group)
                <div class="relative"
                     x-data="{ open: false }"
                     @click.outside="open = false"
                     @focusout="if (!$el.contains($event.relatedTarget)) open = false"
                     @keydown.escape.window="open = false">

                    <button type="button"
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-controls="admin-nav-panel-{{ $group['key'] }}"
                            class="group/top flex items-center gap-2 h-10 px-3.5 rounded-xl text-xs font-semibold transition-all cursor-pointer border shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ $group['active'] ? 'bg-white text-navy border-white shadow-md' : 'bg-white/10 text-white border-white/15 hover:bg-white/20 hover:border-white/30' }}">
                        <i data-lucide="{{ $group['icon'] }}"
                           aria-hidden="true"
                           class="w-4 h-4 shrink-0 {{ $group['active'] ? 'text-primary' : 'text-accent' }}"></i>
                        <span class="whitespace-nowrap">{{ $group['label'] }}</span>
                        <i data-lucide="chevron-down"
                           aria-hidden="true"
                           class="w-3.5 h-3.5 shrink-0 opacity-70 transition-transform duration-200"
                           :class="open ? 'rotate-180' : ''"></i>
                    </button>

                    <div id="admin-nav-panel-{{ $group['key'] }}"
                         x-show="open"
                         x-cloak
                         aria-label="{{ $group['label'] }} menu"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="absolute left-0 top-full mt-2 w-64 rounded-2xl bg-white text-dark shadow-2xl border border-gray-200 p-2 z-50">
                        <div class="px-3 pt-1.5 pb-2 text-[10px] font-bold uppercase tracking-wider text-dark/40">
                            {{ $group['label'] }}
                        </div>

                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}"
                               @if ($item['active']) aria-current="page" @endif
                               @if (! empty($item['external'])) target="_blank" rel="noopener" @endif
                               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all {{ $item['active'] ? 'bg-navy/5 text-navy' : 'text-dark/70 hover:bg-gray-100 hover:text-dark' }}">
                                <i data-lucide="{{ $item['icon'] }}"
                                   aria-hidden="true"
                                   class="w-4 h-4 shrink-0 {{ $item['active'] ? 'text-navy' : 'text-dark/40' }}"></i>
                                <span class="truncate">{{ $item['label'] }}</span>

                                @if (! empty($item['live']))
                                    <span class="ml-auto flex h-2 w-2 relative shrink-0" title="Live incoming chats active">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                        <span class="sr-only">Live incoming chats active</span>
                                    </span>
                                @endif

                                @if (! empty($item['badge']))
                                    <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider shrink-0 {{ $item['badgeClass'] }}">
                                        {{ $item['badge'] }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <!-- Right cluster: account menu -->
        <div class="ml-auto flex items-center gap-2 shrink-0 pl-3">
            <div class="relative"
                 x-data="{ open: false }"
                 @click.outside="open = false"
                 @keydown.escape.window="open = false">

                <button type="button"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true"
                        class="flex items-center gap-2.5 h-10 pl-1.5 pr-3 rounded-xl border border-white/20 bg-white/10 hover:bg-white/20 hover:border-white/30 transition-all cursor-pointer shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <span class="relative w-7 h-7 rounded-lg bg-gradient-to-br from-accent to-amber-500 text-navy font-black text-[11px] flex items-center justify-center shrink-0">
                        {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                        <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full border border-navy {{ $isAdmin ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                    </span>
                    <span class="hidden xl:flex flex-col items-start leading-tight max-w-[150px]">
                        <span class="text-xs font-bold text-white truncate w-full text-left">{{ Auth::user()->name ?? 'Staff User' }}</span>
                        <span class="text-[10px] font-semibold {{ $isAdmin ? 'text-amber-300' : 'text-emerald-300' }}">
                            {{ $isAdmin ? 'Administrator' : 'Travel Agent' }}
                        </span>
                    </span>
                    <i data-lucide="chevron-down"
                       class="w-3.5 h-3.5 text-white/60 shrink-0 transition-transform duration-200"
                       :class="open ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="open"
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute right-0 top-full mt-2 w-60 rounded-2xl bg-white text-dark shadow-2xl border border-gray-200 p-2 z-50">
                    <div class="px-3 py-2">
                        <div class="text-xs font-bold text-dark truncate">{{ Auth::user()->name ?? 'Staff User' }}</div>
                        <div class="text-[11px] text-dark/50 truncate">{{ Auth::user()->email ?? '' }}</div>
                    </div>

                    <div class="my-1 border-t border-gray-100"></div>

                    <a href="{{ route('home') }}"
                       target="_blank"
                       rel="noopener"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-dark/70 hover:bg-gray-100 hover:text-dark transition-all">
                        <i data-lucide="external-link" class="w-4 h-4 text-dark/40"></i>
                        <span>View Public Website</span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="admin">
                        <button type="submit"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition-all cursor-pointer">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
