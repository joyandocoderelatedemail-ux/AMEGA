@php
    /**
     * Switch desk: for staff who may open more than one desk (e.g. an agent given Ticketing, Immigration,
     * Visa Assistance and SRRV), a menu in each desk's top bar to move between them. It lists only the
     * desks this person may open (User::canAccessPage) and is left out when there is just one.
     * Administrators open every desk from the admin panel (Main Admin), so they do not get it.
     *
     * @var string $buttonClass  the header's button style
     */
    $deskChoices = collect([
        'ticketing' => ['label' => 'Ticketing', 'icon' => 'ticket', 'url' => route('ticketing.dashboard'), 'current' => request()->is('ticketing*')],
        'immigration' => ['label' => 'Immigration Counter', 'icon' => 'stamp', 'url' => route('admin.immigration.dashboard'), 'current' => request()->routeIs('admin.immigration.*', 'admin.client-sheets.*', 'admin.immigration-pricing.*')],
        'visa_assistance' => ['label' => 'Visa Assistance', 'icon' => 'globe', 'url' => route('visa.dashboard'), 'current' => request()->is('visa-assistance*')],
        'srrv' => ['label' => 'SRRV Desk', 'icon' => 'landmark', 'url' => route('srrv.dashboard'), 'current' => request()->is('srrv*')],
    ])->filter(fn (array $desk, string $page) => Auth::user()->canAccessPage($page));
@endphp

@if ($deskChoices->count() > 1 && ! Auth::user()->isAdmin())
    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu"
                class="{{ $buttonClass }} px-3" title="Switch to another desk">
            <i data-lucide="repeat" class="w-4 h-4 shrink-0"></i>
            <span class="hidden sm:inline">Switch desk</span>
            <i data-lucide="chevron-down" class="w-4 h-4 shrink-0 transition-transform" :class="open && 'rotate-180'"></i>
        </button>
        <div x-show="open" x-cloak role="menu"
             class="absolute right-0 top-full mt-2 w-72 rounded-xl bg-white text-slate-800 border border-slate-200 shadow-2xl p-1.5 z-[90]">
            @foreach ($deskChoices as $desk)
                <a href="{{ $desk['url'] }}" role="menuitem" @if ($desk['current']) aria-current="page" @endif
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors {{ $desk['current'] ? 'bg-slate-100' : 'hover:bg-slate-50' }}">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $desk['current'] ? 'bg-navy-700 text-white' : 'bg-slate-100 text-slate-600' }}">
                        <i data-lucide="{{ $desk['icon'] }}" class="w-4 h-4"></i>
                    </span>
                    <span class="flex-1 text-sm font-semibold text-slate-900">{{ $desk['label'] }}</span>
                    @if ($desk['current'])
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">You're here</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
@endif
