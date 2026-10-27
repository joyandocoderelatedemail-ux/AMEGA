@php
    /**
     * The notification bell in the Ticketing and Cashier headers: a red count of the ticket handoffs
     * waiting for this person (see App\Support\TicketNotifications) and a dropdown of the latest.
     * It polls every 15 seconds; opening a booking clears it from the count.
     *
     * @var array{total: int, latest: list<array>} $initial
     * @var string $url  where the bell polls
     * @var string $buttonClass  the header's button style
     */
@endphp
<div class="relative"
     x-data="deskBell({ url: {{ Js::from($url) }}, initial: {{ Js::from($initial) }} })"
     @click.outside="open = false"
     @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
            :aria-label="data.total ? data.total + ' new notifications' : 'Notifications'"
            class="{{ $buttonClass }} relative w-9">
        <i data-lucide="bell" class="w-4 h-4"></i>
        <span x-show="data.total" x-cloak x-text="data.total > 99 ? '99+' : data.total"
              class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold leading-none ring-2 ring-navy-800">{{ $initial['total'] ?: '' }}</span>
    </button>

    <div x-show="open" x-cloak
         class="absolute right-0 top-full mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl bg-white text-slate-800 shadow-2xl border border-slate-200 p-2 z-[90]">
        <div class="flex items-center justify-between px-3 pt-1.5 pb-2">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Notifications</span>
            <span x-show="data.total" class="text-[10px] font-semibold text-rose-600" x-text="data.total + ' new'"></span>
        </div>

        <template x-if="! data.latest.length">
            <p class="px-3 py-6 text-center text-xs text-slate-500">You're all caught up.</p>
        </template>

        <template x-for="(item, index) in data.latest" :key="index">
            <a :href="item.url" class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 transition-colors">
                <span class="mt-0.5 w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                      :class="item.type === 'returned' ? 'bg-amber-50 text-amber-600' : (item.type === 'paid' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600')">
                    <i x-show="item.type === 'returned'" data-lucide="undo-2" class="w-4 h-4"></i>
                    <i x-show="item.type === 'paid'" data-lucide="badge-check" class="w-4 h-4"></i>
                    <i x-show="item.type === 'payment'" data-lucide="wallet" class="w-4 h-4"></i>
                </span>
                <span class="min-w-0">
                    <span class="block text-xs font-semibold text-slate-900 truncate" x-text="item.title"></span>
                    <span class="block text-[11px] text-slate-500 truncate" x-text="[item.detail, item.when].filter(Boolean).join(' · ')"></span>
                </span>
            </a>
        </template>
    </div>
</div>

@once
    <script>
        function deskBell(config) {
            return {
                open: false,
                data: config.initial,

                init() {
                    this.$watch('open', () => this.$nextTick(() => window.lucide && lucide.createIcons()));
                    setInterval(() => this.refresh(), 15000);
                },

                async refresh() {
                    if (document.hidden) return;
                    try {
                        const response = await fetch(config.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (! response.ok || response.redirected) return;
                        this.data = await response.json();
                    } catch (e) {
                        // Offline for a moment: try again on the next tick.
                    }
                },
            };
        }
    </script>
@endonce
