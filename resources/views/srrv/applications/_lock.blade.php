{{--
    A released retiree file is locked, as a released Immigration case is. An approver (an admin, or an SRRV approver)
    reopens it; anyone else asks the admin with Request edit (a reason), then sees the request waiting, or the admin's
    reply when it was declined. Expects $application.
--}}
@if ($application->isReleasedLocked())
    @php
        $approver = auth()->user()->can('srrv.approve');
    @endphp
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 space-y-3" role="status" x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs font-semibold text-slate-700 inline-flex items-center gap-2">
                <i data-lucide="lock" class="w-4 h-4 shrink-0"></i>
                This file is {{ $application->finishedState() }} and locked: nothing on it can be changed.
                {{ $approver ? 'Reopen it to make changes.' : 'Use Request edit to ask the admin to unlock it.' }}
                Payments and client notices still go through.
            </p>
            @if ($approver)
                <form method="POST" action="{{ route('srrv.applications.reopen', $application) }}" class="m-0 flex items-center gap-2"
                      onsubmit="return confirm(@js('Reopen '.$application->reference.'?'))">
                    @csrf
                    @if ($application->hasEditRequest())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-amber-300 bg-amber-50 text-[11px] font-bold text-amber-800" title="{{ $application->edit_request_note }}">Edit request</span>
                    @endif
                    <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:border-navy-700/40 hover:text-navy-700">
                        <i data-lucide="lock-open" class="w-4 h-4"></i> Reopen
                    </button>
                </form>
            @elseif ($application->hasEditRequest())
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-xs font-bold text-amber-900">
                    <i data-lucide="clock" class="w-3.5 h-3.5"></i> Edit requested: waiting for the admin
                </span>
            @elseif (! $approver)
                <button type="button" x-show="! open" @click="open = true; $nextTick(() => $refs.reason.focus())"
                        class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border border-amber-300 bg-amber-50 text-xs font-bold text-amber-900 hover:bg-amber-100">
                    <i data-lucide="pencil-line" class="w-4 h-4"></i> Request edit
                </button>
            @endif
        </div>

        @if (! $approver && $application->hasEditRequest() && $application->edit_request_note)
            <p class="text-xs text-amber-900/80">&ldquo;{{ $application->edit_request_note }}&rdquo;</p>
        @endif
        @if (! $approver && $application->editRequestDeclined())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2">
                <p class="text-[11px] font-bold text-rose-800">Edit declined: the file stays locked</p>
                @if ($application->edit_request_reply)<p class="mt-0.5 text-xs text-rose-900/80">&ldquo;{{ $application->edit_request_reply }}&rdquo;</p>@endif
            </div>
        @endif

        @if (! $approver && ! $application->hasEditRequest())
            <form x-show="open" style="display: none" method="POST" action="{{ route('srrv.applications.request-edit', $application) }}"
                  class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 space-y-2">
                @csrf
                <label for="srrv-edit-reason" class="block text-[11px] font-bold text-amber-900">Why does this released file need to be edited?</label>
                <textarea id="srrv-edit-reason" name="reason" x-ref="reason" rows="3" maxlength="1000" required placeholder="For example: the passport number was typed wrong."
                          class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600">{{ old('reason') }}</textarea>
                @error('reason')<p class="text-[11px] font-semibold text-rose-700">{{ $message }}</p>@enderror
                <p class="text-[11px] text-amber-900/80">The admin sees this under SRRV Approvals. The file stays locked until they approve it.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="h-8 px-3 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">Send request</button>
                    <button type="button" @click="open = false" class="h-8 px-3 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                </div>
            </form>
        @endif
    </div>
@endif
