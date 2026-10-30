@php
    /**
     * Every notification this person gets, on the admin dashboard: each kind with how many are
     * waiting (approvals at every desk, inquiries, package bookings, guest chats), and the latest
     * items themselves. The same figures as the bell (App\Support\AdminNotifications).
     */
    $panelUser = auth()->user();
    $panelNotes = \App\Support\AdminNotifications::for($panelUser, 15);
    $panelKinds = \App\Support\AdminNotifications::categories($panelUser, $panelNotes);
    // Written out in full so Tailwind builds each one.
    $panelColumns = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5', 6 => 'lg:grid-cols-6', 7 => 'lg:grid-cols-7'];
    $panelIcons = ['approval' => 'stamp', 'inquiry' => 'inbox', 'booking' => 'calendar', 'chat' => 'messages-square'];
@endphp
@if ($panelKinds !== [])
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden" aria-labelledby="notifications-heading">
        <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100">
            <div>
                <h2 id="notifications-heading" class="font-heading text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="bell" class="w-4 h-4 text-navy-700"></i>
                    Notifications
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Everything waiting for you, the same as the bell.</p>
            </div>
            @if ($panelNotes['total'] > 0)
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-rose-50 text-rose-700">{{ $panelNotes['total'] }} waiting</span>
            @endif
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 {{ $panelColumns[min(max(count($panelKinds), 1), 7)] }} border-b border-slate-100">
            @foreach ($panelKinds as $kind)
                <a href="{{ $kind['url'] }}" class="flex items-center gap-3 px-5 py-4 border-slate-100 border-r border-b lg:border-b-0 hover:bg-slate-50 transition-colors" data-notification-kind="{{ $kind['key'] }}">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $kind['count'] > 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-400' }}">
                        <i data-lucide="{{ $kind['icon'] }}" class="w-4 h-4"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-lg font-bold tabular-nums {{ $kind['count'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $kind['count'] }}</span>
                        <span class="block text-xs text-slate-500 leading-tight">{{ $kind['label'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        @if ($panelNotes['latest'] !== [])
            <ul class="divide-y divide-slate-100">
                @foreach ($panelNotes['latest'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="flex items-center gap-3 px-5 sm:px-6 py-3 hover:bg-slate-50/70 transition-colors">
                            <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                                <i data-lucide="{{ $panelIcons[$item['type']] ?? 'bell' }}" class="w-4 h-4"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900 truncate">{{ $item['title'] }}</span>
                                <span class="block text-xs text-slate-500 truncate">{{ $item['detail'] }}</span>
                            </span>
                            <span class="text-xs text-slate-400 shrink-0">{{ $item['when'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex items-center justify-center gap-2 py-8 text-sm font-semibold text-slate-500">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500"></i>
                You're all caught up.
            </div>
        @endif
    </section>
@endif
