@php
    /**
     * The admin menu as a vertical list (the mobile drawer). Entries come from
     * App\Support\AdminNavigation, shared with the desktop top bar: Services,
     * Clients, Users, Reports & Analytics and Contents. A 'menu' group gets a
     * heading over its links; a 'link' group is a single entry.
     */
    $isMobile = $isMobile ?? false;
    $clickAttr = $isMobile ? '@click="closeMobile()"' : '';
    $expanded = 'isHovered || '.($isMobile ? 'true' : 'false');
    $collapsed = '!isHovered && '.($isMobile ? 'false' : 'true');
    $groups = \App\Support\AdminNavigation::for(Auth::user());
@endphp

<nav class="p-2 space-y-1" aria-label="Admin primary navigation">
    @foreach ($groups as $group)
        @if ($group['type'] === 'menu')
            <div class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 {{ $loop->first ? 'pt-2' : 'pt-4 border-t border-white/10 mt-3' }}"
                 x-show="{{ $expanded }}">
                <span>{{ $group['label'] }}</span>
            </div>
        @elseif (! $loop->first)
            <div class="pt-2 mt-2 border-t border-white/10" x-show="{{ $expanded }}"></div>
        @endif
        @unless ($loop->first)
            <div class="my-2 border-t border-white/10 mx-2" x-show="{{ $collapsed }}"></div>
        @endunless

        @foreach ($group['items'] as $item)
            @php
                // A single-button group is shown under its own name ("Clients", "Users").
                $label = $group['type'] === 'link' ? $group['label'] : $item['label'];
            @endphp
            <a href="{{ $item['url'] }}"
               {!! $clickAttr !!}
               aria-current="{{ $item['active'] ? 'page' : 'false' }}"
               :class="{{ $expanded }} ? 'justify-start px-3' : 'justify-center px-0'"
               :title="{{ $collapsed }} ? @js($label) : ''"
               class="group/nav relative flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent {{ $item['active'] ? 'bg-white/15 text-white shadow-sm font-semibold border border-white/20' : 'text-slate-300 hover:bg-white/10 hover:text-white border border-transparent' }}">
                @if ($item['active'])
                    <span class="absolute left-1 top-2 bottom-2 w-1 rounded-full bg-accent" x-show="{{ $expanded }}"></span>
                @endif
                <span class="relative shrink-0">
                    <i data-lucide="{{ $item['icon'] }}" aria-hidden="true" class="w-5 h-5 transition-transform duration-150 group-hover/nav:scale-105 {{ $item['active'] ? 'text-accent' : 'text-slate-400 group-hover/nav:text-white' }}"></i>
                    @if ($item['live'])
                        <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2" x-show="{{ $collapsed }}">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                    @endif
                </span>
                <span class="truncate" x-show="{{ $expanded }}" x-transition.opacity>{{ $label }}</span>
                @if ($item['live'])
                    <span class="ml-auto flex h-2 w-2 relative" title="Live chats incoming active" x-show="{{ $expanded }}">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        <span class="sr-only">Live incoming chats active</span>
                    </span>
                @endif
            </a>
        @endforeach
    @endforeach

    <div class="pt-2">
        <a href="{{ route('home') }}" target="_blank"
           :class="{{ $expanded }} ? 'justify-start px-3' : 'justify-center px-0'"
           :title="{{ $collapsed }} ? 'View Public Website' : ''"
           class="group/nav flex items-center gap-3 py-2.5 rounded-xl text-xs font-medium text-slate-300 hover:bg-white/10 hover:text-accent transition-all border border-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
            <i data-lucide="external-link" aria-hidden="true" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover/nav:translate-x-0.5"></i>
            <span class="truncate" x-show="{{ $expanded }}" x-transition.opacity>View Public Website</span>
        </a>
    </div>
</nav>
