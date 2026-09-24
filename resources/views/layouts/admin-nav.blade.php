@php
    $isMobile = $isMobile ?? false;
    $clickAttr = $isMobile ? '@click="closeMobile()"' : '';

    // A section header must only render when at least one of its links will, otherwise a
    // restricted agent sees a heading with nothing under it.
    $showOperations = Auth::user()->canAccessPage('dashboard')
        || Auth::user()->canAccessPage('packages')
        || Auth::user()->canAccessPage('bookings')
        || Auth::user()->canAccessPage('destinations')
        || Auth::user()->canAccessPage('inquiries')
        || Auth::user()->canAccessPage('crm');
@endphp

<nav class="p-2 space-y-1" aria-label="Admin primary navigation">
    <!-- Operations Section -->
    @if($showOperations)
        <div class="px-3 pt-2 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400"
             x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">
            <span>Operations</span>
        </div>
        <div class="my-2 border-t border-white/10 mx-2"
             x-show="!isHovered && {{ $isMobile ? 'false' : 'true' }}"></div>
    @endif

    @if(Auth::user()->canAccessPage('dashboard'))
        <a href="{{ route('admin.dashboard') }}" 
           {!! $clickAttr !!}
           aria-current="{{ request()->routeIs('admin.dashboard') ? 'page' : 'false' }}"
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Dashboard' : ''"
           class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.dashboard') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
            @if(request()->routeIs('admin.dashboard'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
            @endif
            <i data-lucide="layout-dashboard" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.dashboard') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Dashboard</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('packages'))
        <a href="{{ route('admin.packages.index') }}" 
           {!! $clickAttr !!}
           aria-current="{{ request()->routeIs('admin.packages.index') || request()->routeIs('admin.packages.create') || request()->routeIs('admin.packages.edit') ? 'page' : 'false' }}"
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Travel Packages' : ''"
           class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.packages.index') || request()->routeIs('admin.packages.create') || request()->routeIs('admin.packages.edit') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
            @if(request()->routeIs('admin.packages.index') || request()->routeIs('admin.packages.create') || request()->routeIs('admin.packages.edit'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
            @endif
            <i data-lucide="package" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.packages.index') || request()->routeIs('admin.packages.create') || request()->routeIs('admin.packages.edit') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Travel Packages</span>
        </a>

        <a href="{{ route('admin.packages.configurator') }}" 
           {!! $clickAttr !!}
           aria-current="{{ request()->routeIs('admin.packages.configurator*') || request()->routeIs('admin.packages.custom-inquiries.*') ? 'page' : 'false' }}"
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Package Configurator' : ''"
           class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.packages.configurator*') || request()->routeIs('admin.packages.custom-inquiries.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
            @if(request()->routeIs('admin.packages.configurator*') || request()->routeIs('admin.packages.custom-inquiries.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
            @endif
            <i data-lucide="sliders" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.packages.configurator*') || request()->routeIs('admin.packages.custom-inquiries.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Package Configurator</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('bookings'))
        <a href="{{ route('admin.bookings.index') }}" 
           {!! $clickAttr !!}
           aria-current="{{ request()->routeIs('admin.bookings.*') ? 'page' : 'false' }}"
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Bookings' : ''"
           class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.bookings.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
            @if(request()->routeIs('admin.bookings.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
            @endif
            <i data-lucide="calendar" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.bookings.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Bookings</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('destinations'))
        <a href="{{ route('admin.destinations.index') }}" 
           {!! $clickAttr !!}
           aria-current="{{ request()->routeIs('admin.destinations.*') ? 'page' : 'false' }}"
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Destinations' : ''"
           class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.destinations.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
            @if(request()->routeIs('admin.destinations.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
            @endif
            <i data-lucide="map-pin" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.destinations.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Destinations</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('inquiries'))
        <a href="{{ route('admin.inquiries.index') }}" 
           {!! $clickAttr !!}
           aria-current="{{ request()->routeIs('admin.inquiries.*') ? 'page' : 'false' }}"
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Inquiries' : ''"
           class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.inquiries.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
            @if(request()->routeIs('admin.inquiries.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
            @endif
            <i data-lucide="inbox" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.inquiries.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Inquiries</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('crm'))
        <a href="{{ route('admin.crm.index') }}" 
           {!! $clickAttr !!}
           aria-current="{{ request()->routeIs('admin.crm.*') ? 'page' : 'false' }}"
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'CRM Pipeline' : ''"
           class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.crm.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
            @if(request()->routeIs('admin.crm.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
            @endif
            <i data-lucide="kanban" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.crm.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>CRM Pipeline</span>
        </a>
    @endif

    <!-- Desks & Specialized Portals -->
    @if(Auth::user()->canAccessPage('chats') || Auth::user()->canAccessPage('ticketing') || Auth::user()->canAccessPage('immigration') || Auth::user()->canAccessPage('visa_assistance') || Auth::user()->canAccessPage('srrv'))
        <div class="px-3 pt-4 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 border-t border-white/10 mt-3"
             x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">
            <span>Portals &amp; Desks</span>
        </div>
        <div class="my-2 border-t border-white/10 mx-2"
             x-show="!isHovered && {{ $isMobile ? 'false' : 'true' }}"></div>

        @if(Auth::user()->canAccessPage('chats'))
            <a href="{{ route('admin.chats.index') }}" 
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('admin.chats.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Live Guest Chats' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.chats.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('admin.chats.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <div class="relative shrink-0">
                    <i data-lucide="messages-square" aria-hidden="true" class="w-5 h-5 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.chats.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                    <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2" x-show="!isHovered && {{ $isMobile ? 'false' : 'true' }}">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                </div>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Live Guest Chats</span>
                <span class="ml-auto flex h-2 w-2 relative" title="Live chats incoming active" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    <span class="sr-only">Live incoming chats active</span>
                </span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('ticketing'))
            <a href="{{ route('ticketing.dashboard') }}" 
               {!! $clickAttr !!}
               aria-current="{{ request()->is('ticketing*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Ticketing System' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->is('ticketing*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->is('ticketing*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="ticket" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->is('ticketing*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Ticketing System</span>
                <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded bg-white/10 text-slate-300 uppercase tracking-wider" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">Desk</span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('immigration'))
            <a href="{{ route('admin.immigration.dashboard') }}"
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('admin.immigration.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Immigration Counter' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.immigration.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('admin.immigration.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="stamp" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.immigration.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Immigration Counter</span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('visa_assistance'))
            <a href="{{ route('visa.dashboard') }}"
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('visa.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Visa Assistance Counter' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('visa.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('visa.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="globe" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('visa.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Visa Assistance</span>
                <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded bg-white/10 text-slate-300 uppercase tracking-wider" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">Desk</span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('srrv'))
            <a href="{{ route('srrv.dashboard') }}"
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('srrv.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'SRRV Desk' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('srrv.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('srrv.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="landmark" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('srrv.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>SRRV Desk</span>
                <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded bg-white/10 text-slate-300 uppercase tracking-wider" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">Desk</span>
            </a>
        @endif
    @endif

    <!-- Administration & Security (Admin Only) -->
    @if(Auth::user()->isAdmin() || Auth::user()->canAccessPage('users'))
        <div class="px-3 pt-4 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 border-t border-white/10 mt-3"
             x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">
            <span>Administration</span>
        </div>
        <div class="my-2 border-t border-white/10 mx-2"
             x-show="!isHovered && {{ $isMobile ? 'false' : 'true' }}"></div>

        @if(Auth::user()->canAccessPage('users'))
            <a href="{{ route('admin.users.index') }}" 
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('admin.users.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Client Accounts' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.users.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('admin.users.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="users" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.users.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Client Accounts</span>
            </a>
        @endif

        @if(Auth::user()->isAdmin())
            <a href="{{ route('admin.agents.index') }}" 
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('admin.agents.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Staff Accounts' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.agents.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('admin.agents.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="user-check" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.agents.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Staff Accounts</span>
                <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 uppercase tracking-wider" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">Staff</span>
            </a>

            <a href="{{ route('admin.activity-logs.index') }}" 
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('admin.activity-logs.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Audit Logs' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.activity-logs.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('admin.activity-logs.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="activity" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.activity-logs.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Audit Logs</span>
                <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 uppercase tracking-wider" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">Audit</span>
            </a>
        @endif
    @endif

    <!-- Content & Site -->
    @if(Auth::user()->canAccessPage('services') || Auth::user()->canAccessPage('testimonials'))
        <div class="px-3 pt-4 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 border-t border-white/10 mt-3"
             x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}">
            <span>Content &amp; Site</span>
        </div>
        <div class="my-2 border-t border-white/10 mx-2"
             x-show="!isHovered && {{ $isMobile ? 'false' : 'true' }}"></div>

        @if(Auth::user()->canAccessPage('services'))
            <a href="{{ route('admin.services.index') }}" 
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('admin.services.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Services' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.services.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('admin.services.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="briefcase" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.services.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Services</span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('testimonials'))
            <a href="{{ route('admin.testimonials.index') }}" 
               {!! $clickAttr !!}
               aria-current="{{ request()->routeIs('admin.testimonials.*') ? 'page' : 'false' }}"
               :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'Testimonials' : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ request()->routeIs('admin.testimonials.*') ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if(request()->routeIs('admin.testimonials.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}"></span>
                @endif
                <i data-lucide="message-square" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:scale-105 {{ request()->routeIs('admin.testimonials.*') ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>Testimonials</span>
            </a>
        @endif
    @endif

    <div class="pt-2">
        <a href="{{ route('home') }}" target="_blank" 
           :class="isHovered || {{ $isMobile ? 'true' : 'false' }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="!isHovered && {{ $isMobile ? 'false' : 'true' }} ? 'View Public Website' : ''"
           class="group/nav flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium text-slate-300 hover:bg-white/10 hover:text-accent transition-all border border-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
            <i data-lucide="external-link" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:translate-x-0.5"></i>
            <span class="truncate" x-show="isHovered || {{ $isMobile ? 'true' : 'false' }}" x-transition.opacity>View Public Website</span>
        </a>
    </div>
</nav>
