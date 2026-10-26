{{--
    The passport pick-up notice for this client, in the wording set under Message Templates: copied for a
    text or Messenger blast, or emailed. Expects $client.
--}}
@php
    $notice = \App\Notifications\PassportPickupNotification::message($client, Auth::user()->name);
    $canEmail = \App\Services\ClientNotifier::canReceive($client->email);
@endphp
<div x-data="{ open: false, copied: false }" class="w-full">
    <button type="button" @click="open = !open; $nextTick(() => window.lucide && lucide.createIcons())"
            class="px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-full hover:bg-primary/5 transition-all inline-flex items-center gap-2">
        <i data-lucide="megaphone" class="w-4 h-4"></i>
        <span x-text="open ? 'Hide pick-up notice' : 'Passport pick-up notice'">Passport pick-up notice</span>
    </button>

    <div x-show="open" x-cloak class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-dark/60">
                Ready to send to <strong class="text-dark">{{ $client->full_name }}</strong>.
                @unless ($client->visa_expiry_date)
                    <span class="text-amber-700 font-semibold">No visa valid-until date on the folder yet: it reads “To be confirmed”.</span>
                @endunless
            </p>
            @if (Auth::user()->isAdmin())
                <a href="{{ route('admin.message-templates.edit', 'passport_pickup') }}" class="text-[11px] font-bold text-primary hover:underline">Edit wording</a>
            @endif
        </div>
        <textarea x-ref="notice" readonly rows="12" aria-label="Pick-up notice to copy"
                  class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-200 text-xs text-dark leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary">{{ $notice['body'] }}</textarea>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    @click="navigator.clipboard.writeText($refs.notice.value).then(() => { copied = true; setTimeout(() => copied = false, 2000); }).catch(() => { $refs.notice.select(); document.execCommand('copy'); copied = true; setTimeout(() => copied = false, 2000); })"
                    class="px-4 py-2 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold inline-flex items-center gap-1.5">
                <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                <span x-text="copied ? 'Copied' : 'Copy for text / Messenger'">Copy for text / Messenger</span>
            </button>
            @if ($canEmail)
                <form method="POST" action="{{ route('admin.client-sheets.pickup-notice', $client) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg border border-primary/30 bg-white text-primary text-xs font-bold hover:bg-primary/5 inline-flex items-center gap-1.5">
                        <i data-lucide="mail" class="w-3.5 h-3.5"></i> Email to {{ $client->email }}
                    </button>
                </form>
            @else
                <span class="text-[11px] text-dark/50">No email on the folder: send the copied text instead.</span>
            @endif
        </div>
    </div>
</div>
