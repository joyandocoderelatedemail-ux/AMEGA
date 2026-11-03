{{--
    The Notice for this client: ready-to-send wording for where their file stands, in the wording set under
    Message Templates. Copied for a text or Messenger, or emailed.
    Expects $client. With $caseRow (a row from ImmigrationCases) it follows that case's status: a template per
    client-facing status, nothing at the internal checking steps, and nothing while the admin is holding the
    file. Without one (Visa Extension has no case) it is the passport pick-up notice, as ever.
    Optional $standalone: the block sits on its own line (Visa Extension); otherwise it joins the status row,
    the button inline and the panel wrapping onto its own full-width line.
--}}
@php
    $caseRow = $caseRow ?? null;
    $standalone = $standalone ?? false;
    $sender = Auth::user()->name;
    $notice = $caseRow
        ? \App\Support\CaseNotice::for($caseRow, $client, $sender)
        : ['state' => 'ready', 'key' => \App\Support\CaseNotice::RELEASED_KEY] + \App\Support\CaseNotice::pickup($client, $sender) + ['message' => null];
    $ready = $notice['state'] === 'ready';
    $canEmail = $ready && \App\Services\ClientNotifier::canReceive($client->email);
    $button = $standalone ? 'h-9 px-4 text-xs' : 'h-7 px-2.5 text-[11px]';
@endphp
<div x-data="{ open: false, copied: false }" class="{{ $standalone ? 'w-full' : 'contents' }}">
    <button type="button" @click="open = !open; $nextTick(() => window.lucide && lucide.createIcons())" :aria-expanded="open"
            class="{{ $button }} bg-white border border-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-colors inline-flex items-center justify-center gap-1.5 whitespace-nowrap">
        <i data-lucide="megaphone" class="{{ $standalone ? 'w-4 h-4' : 'w-3 h-3' }}"></i>
        <span>Notice</span>
    </button>

    <div x-show="open" x-cloak style="display: none" class="{{ $standalone ? 'mt-4' : 'basis-full w-full mt-2' }} rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3 text-left">
        @if ($ready)
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-600">
                    Ready to send to <strong class="text-slate-900">{{ $client->full_name }}</strong>
                    @if ($caseRow)
                        for <strong class="text-slate-900">{{ $caseRow['progress_label'] }}</strong>.
                    @else
                        : their passport is ready for pick-up.
                    @endif
                    @if ($notice['key'] === \App\Support\CaseNotice::RELEASED_KEY && ! $client->visa_expiry_date)
                        <span class="text-amber-700 font-semibold">No visa valid-until date on the folder yet: it reads &ldquo;To be confirmed&rdquo;.</span>
                    @endif
                </p>
                @if (Auth::user()->isAdmin())
                    <a href="{{ route('admin.message-templates.edit', $notice['key']) }}" class="text-[11px] font-bold text-navy-700 hover:underline">Edit wording</a>
                @endif
            </div>
            <textarea x-ref="notice" readonly rows="{{ $caseRow && $notice['key'] !== \App\Support\CaseNotice::RELEASED_KEY ? 14 : 12 }}" aria-label="Notice to copy"
                      class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-200 text-xs text-slate-900 leading-relaxed focus:outline-none focus:ring-2 focus:ring-navy-600">{{ $notice['body'] }}</textarea>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.notice.value).then(() => { copied = true; setTimeout(() => copied = false, 2000); }).catch(() => { $refs.notice.select(); document.execCommand('copy'); copied = true; setTimeout(() => copied = false, 2000); })"
                        class="h-9 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold inline-flex items-center gap-1.5">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                    <span x-text="copied ? 'Copied' : 'Copy for text / Messenger'">Copy for text / Messenger</span>
                </button>
                @if ($canEmail)
                    <form method="POST" action="{{ route('admin.client-sheets.pickup-notice', $client) }}">
                        @csrf
                        @if ($caseRow)
                            <input type="hidden" name="kind" value="{{ $caseRow['kind'] }}">
                            <input type="hidden" name="case" value="{{ $caseRow['id'] }}">
                            <input type="hidden" name="key" value="{{ $notice['key'] }}">
                        @endif
                        <button type="submit" class="h-9 px-4 rounded-lg border border-navy-700/30 bg-white text-navy-700 text-xs font-bold hover:bg-navy-50 inline-flex items-center gap-1.5">
                            <i data-lucide="mail" class="w-3.5 h-3.5"></i> Email to {{ $client->email }}
                        </button>
                    </form>
                @else
                    <span class="text-[11px] text-slate-500">No email on the folder: send the copied text instead.</span>
                @endif
            </div>
        @else
            <p class="text-xs font-semibold {{ $notice['state'] === 'held' ? 'text-amber-800' : 'text-slate-600' }}">{{ $notice['message'] }}</p>
        @endif
    </div>
</div>
