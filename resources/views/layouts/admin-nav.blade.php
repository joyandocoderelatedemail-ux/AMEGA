@php
    $isMobile = $isMobile ?? false;
    $clickAttr = $isMobile ? '@click="closeMobile()"' : '';
@endphp

<nav class="p-3 space-y-1">
    <div class="px-3 pt-2 pb-1 text-[10px] font-extrabold uppercase tracking-widest text-accent/90 flex items-center justify-between">
        <span>Operations</span>
    </div>

    @if(Auth::user()->canAccessPage('dashboard'))
        <a href="{{ route('admin.dashboard') }}" 
           {!! $clickAttr !!}
           class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
            @if(request()->routeIs('admin.dashboard'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
            @endif
            <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.dashboard') ? 'text-accent' : 'text-slate-300' }}"></i>
            <span>Dashboard</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('packages'))
        <a href="{{ route('admin.packages.index') }}" 
           {!! $clickAttr !!}
           class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.packages.*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
            @if(request()->routeIs('admin.packages.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
            @endif
            <i data-lucide="package" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.packages.*') ? 'text-accent' : 'text-slate-300' }}"></i>
            <span>Travel Packages</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('bookings'))
        <a href="{{ route('admin.bookings.index') }}" 
           {!! $clickAttr !!}
           class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.bookings.*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
            @if(request()->routeIs('admin.bookings.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
            @endif
            <i data-lucide="calendar" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.bookings.*') ? 'text-accent' : 'text-slate-300' }}"></i>
            <span>Bookings</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('destinations'))
        <a href="{{ route('admin.destinations.index') }}" 
           {!! $clickAttr !!}
           class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.destinations.*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
            @if(request()->routeIs('admin.destinations.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
            @endif
            <i data-lucide="map-pin" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.destinations.*') ? 'text-accent' : 'text-slate-300' }}"></i>
            <span>Destinations</span>
        </a>
    @endif

    @if(Auth::user()->canAccessPage('inquiries'))
        <a href="{{ route('admin.inquiries.index') }}" 
           {!! $clickAttr !!}
           class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.inquiries.*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
            @if(request()->routeIs('admin.inquiries.*'))
                <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
            @endif
            <i data-lucide="inbox" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.inquiries.*') ? 'text-accent' : 'text-slate-300' }}"></i>
            <span>Inquiries</span>
        </a>
    @endif

    <!-- Desks & Specialized Portals -->
    @if(Auth::user()->canAccessPage('chats') || Auth::user()->canAccessPage('ticketing') || Auth::user()->canAccessPage('immigration'))
        <div class="px-3 pt-4 pb-1 text-[10px] font-extrabold uppercase tracking-widest text-accent/90 flex items-center justify-between border-t border-white/10 mt-3">
            <span>Portals &amp; Desks</span>
        </div>

        @if(Auth::user()->canAccessPage('chats'))
            <a href="{{ route('admin.chats.index') }}" 
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.chats.*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                @if(request()->routeIs('admin.chats.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
                @endif
                <i data-lucide="messages-square" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 text-accent"></i>
                <span>Live Guest Chats</span>
                <span class="ml-auto flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('ticketing'))
            <a href="{{ route('ticketing.dashboard') }}" 
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->is('ticketing*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                @if(request()->is('ticketing*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
                @endif
                <i data-lucide="ticket" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 text-accent"></i>
                <span>Ticketing System</span>
                <span class="ml-auto text-[9px] font-extrabold px-1.5 py-0.5 rounded-md bg-accent/20 text-accent uppercase tracking-wider">Desk</span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('immigration'))
            <a href="{{ route('admin.immigration.dashboard') }}"
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all text-slate-300 hover:bg-white/10 hover:text-white">
                <i data-lucide="stamp" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 text-accent"></i>
                <span>Immigration Counter</span>
                <i data-lucide="external-link" class="w-3.5 h-3.5 ml-auto opacity-50"></i>
            </a>
        @endif
    @endif

    <!-- Administration & Security (Admin Only) -->
    @if(Auth::user()->isAdmin() || Auth::user()->canAccessPage('users'))
        <div class="px-3 pt-4 pb-1 text-[10px] font-extrabold uppercase tracking-widest text-accent/90 flex items-center justify-between border-t border-white/10 mt-3">
            <span>Administration</span>
        </div>

        @if(Auth::user()->canAccessPage('users'))
            <a href="{{ route('admin.users.index') }}" 
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.users.*') ? 'bg-primary text-white shadow-md ring-1 ring-white/20 font-semibold' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                @if(request()->routeIs('admin.users.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
                @endif
                <i data-lucide="users" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.users.*') ? 'text-accent' : 'text-slate-300' }}"></i>
                <span>Client Accounts</span>
            </a>
        @endif

        @if(Auth::user()->isAdmin())
            <a href="{{ route('admin.agents.index') }}" 
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.agents.*') ? 'bg-emerald-600 text-white shadow-md font-semibold' : 'text-emerald-300/80 hover:bg-emerald-500/10 hover:text-emerald-200' }}">
                @if(request()->routeIs('admin.agents.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-emerald-200"></span>
                @endif
                <i data-lucide="user-check" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 text-emerald-400"></i>
                <span>Travel Agent Staff</span>
                <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 uppercase tracking-wider">Staff</span>
            </a>

            <a href="{{ route('admin.activity-logs.index') }}" 
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.activity-logs.*') ? 'bg-amber-500 text-white shadow-md font-semibold' : 'text-amber-300/80 hover:bg-amber-500/10 hover:text-amber-200' }}">
                @if(request()->routeIs('admin.activity-logs.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-amber-200"></span>
                @endif
                <i data-lucide="activity" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 text-amber-400"></i>
                <span>Audit Logs</span>
                <span class="ml-auto text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-amber-500/20 text-amber-200 uppercase tracking-wider">Audit</span>
            </a>
        @endif
    @endif

    <!-- Content & Site -->
    @if(Auth::user()->canAccessPage('services') || Auth::user()->canAccessPage('testimonials'))
        <div class="px-3 pt-4 pb-1 text-[10px] font-extrabold uppercase tracking-widest text-accent/90 flex items-center justify-between border-t border-white/10 mt-3">
            <span>Content &amp; Site</span>
        </div>

        @if(Auth::user()->canAccessPage('services'))
            <a href="{{ route('admin.services.index') }}" 
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.services.*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                @if(request()->routeIs('admin.services.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
                @endif
                <i data-lucide="briefcase" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.services.*') ? 'text-accent' : 'text-slate-300' }}"></i>
                <span>Services</span>
            </a>
        @endif

        @if(Auth::user()->canAccessPage('testimonials'))
            <a href="{{ route('admin.testimonials.index') }}" 
               {!! $clickAttr !!}
               class="group/nav relative flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition-all {{ request()->routeIs('admin.testimonials.*') ? 'bg-primary text-white shadow-sm font-semibold ring-1 ring-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                @if(request()->routeIs('admin.testimonials.*'))
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent"></span>
                @endif
                <i data-lucide="message-square" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:scale-110 {{ request()->routeIs('admin.testimonials.*') ? 'text-accent' : 'text-slate-300' }}"></i>
                <span>Testimonials</span>
            </a>
        @endif
    @endif

    <div class="pt-3">
        <a href="{{ route('home') }}" target="_blank" 
           class="group/nav flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-300 hover:bg-white/10 hover:text-accent transition-all border border-white/10">
            <i data-lucide="external-link" class="w-4 h-4 shrink-0 transition-transform duration-200 group-hover/nav:translate-x-0.5"></i>
            <span>View Public Website</span>
        </a>
    </div>
</nav>
