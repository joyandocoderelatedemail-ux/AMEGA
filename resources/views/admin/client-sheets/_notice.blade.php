{{--
    Notify the client, at any status. The template for where their file stands (the wording set under Message
    Templates) is shown to copy; the agent pastes it into the message box (or writes their own), edits it, and
    Notify client emails it at once. The copy also goes by text or Messenger.
    Expects $client. With $caseRow (a row from ImmigrationCases) the template follows that case's status; the
    internal checking steps have none, so the agent writes the message. Without one (Visa Extension has no case)
    the template is the passport pick-up notice.
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
    $canEmail = \App\Services\ClientNotifier::canReceive($client->email);
    $button = $standalone ? 'h-9 px-4 text-xs' : 'h-7 px-2.5 text-[11px]';
    $noticeFor = $caseRow ? $caseRow['kind'].'-'.$caseRow['id'] : 'folder';
    // After a failed send the box opens again with what was typed.
    $reopen = old('notice_for') === $noticeFor;
    $subject = $reopen ? old('subject') : ($notice['subject'] ?? 'Update from AMEGA Travel and Tours');
@endphp
<div x-data="{ open: {{ $reopen ? 'true' : 'false' }}, copied: false, message: @js($reopen ? (string) old('message') : '') }" class="{{ $standalone ? 'w-full' : 'contents' }}">
    <button type="button" @click="open = !open; $nextTick(() => window.lucide && lucide.createIcons())" :aria-expanded="open"
            class="{{ $button }} bg-white border border-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-colors inline-flex items-center justify-center gap-1.5 whitespace-nowrap">
        <i data-lucide="megaphone" class="{{ $standalone ? 'w-4 h-4' : 'w-3 h-3' }}"></i>
        <span>Notice</span>
    </button>

    <div x-show="open" x-cloak @unless ($reopen) style="display: none" @endunless class="{{ $standalone ? 'mt-4' : 'basis-full w-full mt-2' }} rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4 text-left">
        {{-- 1. The template for this status, to copy --}}
        <div class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-600">
                    <span class="font-bold text-slate-900">Template</span>
                    @if ($ready)
                        for {{ $caseRow ? $caseRow['progress_label'] : 'the passport pick-up' }}.
                        @if ($notice['key'] === \App\Support\CaseNotice::RELEASED_KEY && ! $client->visa_expiry_date)
                            <span class="text-amber-700 font-semibold">No visa valid-until date on the folder yet: it reads &ldquo;To be confirmed&rdquo;.</span>
                        @endif
                    @else
                        &middot; {{ $notice['message'] }}
                    @endif
                </p>
                @if ($ready && Auth::user()->isAdmin())
                    <a href="{{ route('admin.message-templates.edit', $notice['key']) }}" class="text-[11px] font-bold text-navy-700 hover:underline">Edit wording</a>
                @endif
            </div>
            @if ($ready)
                <textarea x-ref="notice" readonly rows="{{ $caseRow && $notice['key'] !== \App\Support\CaseNotice::RELEASED_KEY ? 10 : 8 }}" aria-label="Template to copy"
                          class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-200 text-xs text-slate-900 leading-relaxed focus:outline-none focus:ring-2 focus:ring-navy-600">{{ $notice['body'] }}</textarea>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            @click="navigator.clipboard.writeText($refs.notice.value).then(() => { copied = true; setTimeout(() => copied = false, 2000); }).catch(() => { $refs.notice.select(); document.execCommand('copy'); copied = true; setTimeout(() => copied = false, 2000); })"
                            class="h-8 px-3 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 inline-flex items-center gap-1.5">
                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                        <span x-text="copied ? 'Copied' : 'Copy for text / Messenger'">Copy for text / Messenger</span>
                    </button>
                    <button type="button" @click="message = $refs.notice.value; $nextTick(() => $refs.message.focus())"
                            class="h-8 px-3 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 inline-flex items-center gap-1.5">
                        <i data-lucide="clipboard-paste" class="w-3.5 h-3.5"></i> Paste into the message
                    </button>
                </div>
            @endif
        </div>

        {{-- 2. The message the client gets: pasted from the template or written, edited, then emailed now --}}
        <form method="POST" action="{{ route('admin.client-sheets.pickup-notice', $client) }}" class="space-y-2 border-t border-slate-200 pt-4">
            @csrf
            <input type="hidden" name="notice_for" value="{{ $noticeFor }}">
            @if ($caseRow)
                <input type="hidden" name="kind" value="{{ $caseRow['kind'] }}">
                <input type="hidden" name="case" value="{{ $caseRow['id'] }}">
            @endif
            <p class="text-xs font-bold text-slate-900">Message to {{ $client->full_name }}</p>
            <div>
                <label for="notice-subject-{{ $noticeFor }}" class="block text-[11px] font-bold text-slate-600 mb-1">Subject</label>
                <input id="notice-subject-{{ $noticeFor }}" type="text" name="subject" value="{{ $subject }}" maxlength="150" required
                       class="w-full h-9 px-3 rounded-lg bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600">
                @if ($reopen) @error('subject') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror @endif
            </div>
            <div>
                <label for="notice-message-{{ $noticeFor }}" class="block text-[11px] font-bold text-slate-600 mb-1">Message</label>
                <textarea id="notice-message-{{ $noticeFor }}" x-ref="message" x-model="message" name="message" rows="8" maxlength="5000" required
                          placeholder="Paste the template here and edit it, or write your own message."
                          class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 text-xs text-slate-900 leading-relaxed placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600"></textarea>
                @if ($reopen) @error('message') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror @endif
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if ($canEmail)
                    <button type="submit" :disabled="message.trim() === ''" :class="message.trim() === '' && 'opacity-50 cursor-not-allowed'"
                            class="h-9 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold inline-flex items-center gap-1.5">
                        <i data-lucide="send" class="w-3.5 h-3.5"></i> Notify client
                    </button>
                    <span class="text-[11px] text-slate-500">Emailed now to <strong class="text-slate-700">{{ $client->email }}</strong>.</span>
                @else
                    <span class="text-[11px] text-slate-500">No email on the folder: copy the message and send it by text or Messenger instead.</span>
                @endif
            </div>
        </form>
    </div>
</div>
