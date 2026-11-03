{{--
    Notify the client, at any status. The template for where their file stands (the wording set under Message
    Templates) is on the left, to copy for a text or Messenger or to put into the message; the message on the
    right is edited (or written) and Notify client emails it at once.
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
            :class="open && 'bg-navy-50 border-navy-200 text-navy-700'"
            class="{{ $button }} bg-white border border-slate-200 text-slate-700 font-bold rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-colors inline-flex items-center justify-center gap-1.5 whitespace-nowrap">
        <i data-lucide="megaphone" class="{{ $standalone ? 'w-4 h-4' : 'w-3 h-3' }}"></i>
        <span>Notice</span>
    </button>

    <div x-show="open" x-cloak @unless ($reopen) style="display: none" @endunless
         class="{{ $standalone ? 'mt-4' : 'basis-full w-full mt-2' }} rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left">
        {{-- Who it goes to, and about which file --}}
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-slate-200 bg-slate-50">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 shrink-0 rounded-lg bg-navy-700 text-white flex items-center justify-center"><i data-lucide="send" class="w-4 h-4"></i></span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-900 truncate">Notify {{ $client->full_name }}</p>
                    <p class="text-[11px] text-slate-500 truncate">
                        {{ $canEmail ? $client->email : 'No email on the folder' }}
                        @if ($caseRow) &middot; {{ $caseRow['reference'] }} &middot; {{ $caseRow['progress_label'] }} @endif
                    </p>
                </div>
            </div>
            <button type="button" @click="open = false" aria-label="Close the notice"
                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 inline-flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="grid lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-slate-200">
            {{-- 1. The template for this status --}}
            <div class="p-4 space-y-3 flex flex-col">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Template</p>
                    @if ($ready && Auth::user()->isAdmin())
                        <a href="{{ route('admin.message-templates.edit', $notice['key']) }}" class="text-[11px] font-bold text-navy-700 hover:underline">Edit wording</a>
                    @endif
                </div>
                @if ($ready)
                    <p class="text-xs text-slate-600">
                        For <span class="font-semibold text-slate-900">{{ $caseRow ? $caseRow['progress_label'] : 'the passport pick-up' }}</span>.
                        @if ($notice['key'] === \App\Support\CaseNotice::RELEASED_KEY && ! $client->visa_expiry_date)
                            <span class="text-amber-700 font-semibold">No visa valid-until date on the folder yet: it reads &ldquo;To be confirmed&rdquo;.</span>
                        @endif
                    </p>
                    <textarea x-ref="notice" readonly rows="11" aria-label="Template to copy"
                              class="flex-1 w-full px-3.5 py-3 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 leading-relaxed resize-none focus:outline-none focus:ring-2 focus:ring-navy-600">{{ $notice['body'] }}</textarea>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="message = $refs.notice.value; $nextTick(() => $refs.message.focus())"
                                class="h-8 px-3 rounded-lg bg-navy-50 border border-navy-200 text-navy-700 text-xs font-bold hover:bg-navy-100 inline-flex items-center gap-1.5">
                            <i data-lucide="clipboard-paste" class="w-3.5 h-3.5"></i> Paste into the message
                        </button>
                        <button type="button"
                                @click="navigator.clipboard.writeText($refs.notice.value).then(() => { copied = true; setTimeout(() => copied = false, 2000); }).catch(() => { $refs.notice.select(); document.execCommand('copy'); copied = true; setTimeout(() => copied = false, 2000); })"
                                class="h-8 px-3 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 inline-flex items-center gap-1.5">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            <span x-text="copied ? 'Copied' : 'Copy for text / Messenger'">Copy for text / Messenger</span>
                        </button>
                    </div>
                @else
                    <div class="flex-1 flex items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                        <p class="text-xs text-slate-500">{{ $notice['message'] }}</p>
                    </div>
                @endif
            </div>

            {{-- 2. The message the client gets, emailed now --}}
            <form method="POST" action="{{ route('admin.client-sheets.pickup-notice', $client) }}" class="p-4 space-y-3 flex flex-col">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Message</p>
                <div>
                    <label for="notice-subject-{{ $noticeFor }}" class="block text-[11px] font-semibold text-slate-600 mb-1">Subject</label>
                    <input id="notice-subject-{{ $noticeFor }}" type="text" name="subject" value="{{ $subject }}" maxlength="150" required
                           class="w-full h-9 px-3 rounded-lg bg-white border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">
                    @if ($reopen) @error('subject') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror @endif
                </div>
                <div class="flex-1 flex flex-col">
                    <label for="notice-message-{{ $noticeFor }}" class="block text-[11px] font-semibold text-slate-600 mb-1">Message</label>
                    <textarea id="notice-message-{{ $noticeFor }}" x-ref="message" x-model="message" name="message" rows="9" maxlength="5000" required
                              placeholder="Paste the template here and edit it, or write your own message."
                              class="flex-1 w-full px-3.5 py-3 rounded-lg bg-white border border-slate-300 text-xs text-slate-900 leading-relaxed placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600"></textarea>
                    <div class="mt-1 flex items-center justify-between gap-2">
                        @if ($reopen) @error('message') <p class="text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror @endif
                        <span class="ml-auto text-[11px] text-slate-400" x-text="message.length + ' / 5000'"></span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-200">
                    @if ($canEmail)
                        <span class="text-[11px] text-slate-500 inline-flex items-center gap-1.5"><i data-lucide="mail" class="w-3.5 h-3.5"></i> Sent now to {{ $client->email }}</span>
                        <button type="submit" :disabled="message.trim() === ''" :class="message.trim() === '' && 'opacity-50 cursor-not-allowed'"
                                class="h-9 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="send" class="w-3.5 h-3.5"></i> Notify client
                        </button>
                    @else
                        <p class="text-[11px] text-amber-800 font-semibold">No email on the folder: copy the message and send it by text or Messenger instead.</p>
                    @endif
                </div>
                {{-- Kept last: hidden fields at the top would push the heading down (space-y) --}}
                @csrf
                <input type="hidden" name="notice_for" value="{{ $noticeFor }}">
                @if ($caseRow)
                    <input type="hidden" name="kind" value="{{ $caseRow['kind'] }}">
                    <input type="hidden" name="case" value="{{ $caseRow['id'] }}">
                @endif
            </form>
        </div>
    </div>
</div>
