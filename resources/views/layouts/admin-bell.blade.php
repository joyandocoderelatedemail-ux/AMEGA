@php
    /**
     * The notification bell: a red count of what is new (website inquiries and online bookings) and a dropdown
     * of the latest. It polls the server every 30 seconds and keeps every red badge on the page
     * (data-notify-count) in step, so a new inquiry shows without reloading.
     */
    $notifications = \App\Support\AdminNotifications::for(Auth::user());
@endphp

@if (\App\Support\AdminNotifications::shown(Auth::user()))
<div class="relative"
     x-data="adminBell({ url: {{ Js::from(route('admin.notifications')) }}, initial: {{ Js::from($notifications) }} })"
     @click.outside="open = false"
     @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
            :aria-label="data.total ? data.total + ' new notifications' : 'Notifications'"
            class="relative flex items-center justify-center w-10 h-10 rounded-xl border border-white/20 bg-white/10 hover:bg-white/20 hover:border-white/30 transition-all cursor-pointer shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
        <i data-lucide="bell" class="w-5 h-5 text-white"></i>
        <span x-show="data.total" x-cloak x-text="data.total > 99 ? '99+' : data.total"
              class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-extrabold leading-none ring-2 ring-navy">{{ $notifications['total'] ?: '' }}</span>
    </button>

    <div x-show="open" x-cloak
         class="absolute right-0 top-full mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl bg-white text-dark shadow-2xl border border-gray-200 p-2 z-[90]">
        <div class="flex items-center justify-between px-3 pt-1.5 pb-2">
            <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Notifications</span>
            <span x-show="data.total" class="text-[10px] font-bold text-rose-600" x-text="data.total + ' new'"></span>
        </div>

        <template x-if="! data.latest.length">
            <p class="px-3 py-6 text-center text-xs text-dark/50">You're all caught up.</p>
        </template>

        <template x-for="(item, index) in data.latest" :key="index">
            <a :href="item.url" class="flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-100 transition-colors">
                <span class="mt-0.5 w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <i x-show="item.type === 'inquiry'" data-lucide="inbox" class="w-4 h-4"></i>
                    <i x-show="item.type === 'booking'" data-lucide="calendar" class="w-4 h-4"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-xs font-bold text-dark truncate" x-text="item.title"></span>
                    <span class="block text-[11px] text-dark/50 truncate" x-text="[item.detail, item.when].filter(Boolean).join(' · ')"></span>
                </span>
            </a>
        </template>

        <div class="mt-1 pt-1 border-t border-gray-100 flex">
            @if (Auth::user()->canAccessPage('inquiries'))
                <a href="{{ route('admin.inquiries.index') }}" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-primary hover:bg-gray-100">
                    Inquiries <span x-show="data.inquiries" class="px-1.5 rounded-full bg-rose-600 text-white text-[10px]" x-text="data.inquiries"></span>
                </a>
            @endif
            @if (Auth::user()->canAccessPage('bookings'))
                <a href="{{ route('admin.bookings.index') }}" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-primary hover:bg-gray-100">
                    Bookings <span x-show="data.bookings" class="px-1.5 rounded-full bg-rose-600 text-white text-[10px]" x-text="data.bookings"></span>
                </a>
            @endif
        </div>
    </div>
</div>

@once
    <script>
        function adminBell(config) {
            return {
                open: false,
                data: config.initial,

                init() {
                    this.$watch('open', () => this.$nextTick(() => window.lucide && lucide.createIcons()));
                    setInterval(() => this.refresh(), 30000);
                },

                async refresh() {
                    if (document.hidden) return;
                    try {
                        const response = await fetch(config.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (! response.ok || response.redirected) return;
                        this.data = await response.json();
                        // Every red badge on the page adds up the counts it shows.
                        document.querySelectorAll('[data-notify-count]').forEach((badge) => {
                            const count = badge.dataset.notifyCount.split(',').reduce((sum, key) => sum + (this.data[key] || 0), 0);
                            badge.textContent = count > 99 ? '99+' : count;
                            badge.classList.toggle('hidden', count === 0);
                            badge.classList.toggle('inline-flex', count > 0);
                        });
                    } catch (e) {
                        // Offline for a moment: try again on the next tick.
                    }
                },
            };
        }
    </script>
@endonce
@endif
