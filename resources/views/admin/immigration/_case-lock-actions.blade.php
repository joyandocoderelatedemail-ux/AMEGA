{{-- What can be done with a released or approved (locked) case. Expects $case, a row from ImmigrationCases.
     An admin or Immigration approver can Reopen it. Anyone else asks the admin with Request edit (a reason),
     then sees "Edit requested" until the admin answers, or the admin's reply when it was declined.
     An open case has nothing here: its status is released from the status dropdown. The exception is a case whose
     Approved status is locked (status_locked): an agent asks for a status change the same way.
     Optional $readOnly: no control at all. Optional $compact (the Cases table): a narrower request box. --}}
@php
    $compact = $compact ?? false;
    $mine = 'request-edit-'.$case['kind'].'-'.$case['id'];
    $statusLocked = (bool) ($case['status_locked'] ?? false);
    // The file's details are locked once released or approved; a case that is only status-locked can only ask for a status change.
    $contentLocked = (bool) $case['released_on'] || $case['approval'] === 'approved';
@endphp
@if (($readOnly ?? false) || (! $case['released_on'] && $case['approval'] !== 'approved' && ! $statusLocked && $case['edit_request'] === null))
    {{-- Nothing to do from a preview, or while the case is open and not approved. --}}
@elseif (auth()->user()->can('immigration.approve') && ! $case['released_on'])
    {{-- An approver edits an approved case directly: only show a request that is waiting --}}
    @if ($case['edit_request'] === 'pending')
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-amber-300 bg-amber-50 text-[11px] font-bold text-amber-800" title="{{ $case['edit_note'] }}">Edit requested</span>
    @endif
@elseif (auth()->user()->can('immigration.approve'))
    <form method="POST" action="{{ route('admin.immigration.cases.reopen', [$case['kind'], $case['id']]) }}" class="inline-flex items-center gap-2"
          onsubmit="return confirm('Reopen {{ $case['reference'] }}?');">
        @csrf
        @if ($case['edit_request'] === 'pending')
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-amber-300 bg-amber-50 text-[11px] font-bold text-amber-800" title="{{ $case['edit_note'] }}">Edit requested</span>
        @endif
        <button type="submit" class="inline-flex items-center gap-1 h-7 px-2.5 rounded-lg border border-slate-200 bg-white text-[11px] font-bold text-slate-700 hover:border-navy-700/40 hover:text-navy-700 transition-colors whitespace-nowrap">
            <i data-lucide="lock-open" class="w-3 h-3"></i> Reopen
        </button>
    </form>
@elseif ($case['edit_request'] === 'pending')
    <div class="{{ $compact ? 'inline-block max-w-xs whitespace-normal text-left' : 'basis-full w-full' }}">
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
            <p class="inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-900"><i data-lucide="clock" class="w-3 h-3"></i> {{ $case['edit_type'] === 'status' ? 'Status change requested' : 'Edit requested' }}: waiting for the admin</p>
            @if ($case['edit_note'])<p class="mt-0.5 text-xs text-amber-900/80">&ldquo;{{ $case['edit_note'] }}&rdquo;</p>@endif
        </div>
    </div>
@else
    <div x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }" class="contents" @keydown.escape="open = false">
        <button type="button" x-show="! open" @click="open = true; $nextTick(() => $refs.reason.focus())"
                class="inline-flex items-center gap-1 h-7 px-2.5 rounded-lg border border-amber-300 bg-amber-50 text-[11px] font-bold text-amber-900 hover:bg-amber-100 transition-colors whitespace-nowrap">
            <i data-lucide="pencil-line" class="w-3 h-3"></i> Request edit
        </button>
        @if ($case['edit_request'] === 'declined')
            <div class="{{ $compact ? 'mt-2 max-w-xs whitespace-normal text-left' : 'basis-full w-full' }}">
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2">
                    <p class="text-[11px] font-bold text-rose-800">Edit declined: the file stays locked</p>
                    @if ($case['edit_reply'])<p class="mt-0.5 text-xs text-rose-900/80">&ldquo;{{ $case['edit_reply'] }}&rdquo;</p>@endif
                </div>
            </div>
        @endif
        <form x-show="open" style="display: none" method="POST" action="{{ route('admin.immigration.cases.request-edit', [$case['kind'], $case['id']]) }}"
              class="{{ $compact ? 'mt-2 w-72 max-w-full whitespace-normal text-left' : 'basis-full w-full mt-1' }} rounded-xl border border-amber-200 bg-amber-50/60 p-3 space-y-2">
            @csrf
            @if ($case['released_on'] || $statusLocked)
                <fieldset class="space-y-1.5">
                    <legend class="text-[11px] font-bold text-amber-900">What do you need?</legend>
                    <label class="flex items-start gap-2 text-xs text-slate-800 cursor-pointer">
                        <input type="radio" name="type" value="status" class="mt-0.5 text-navy-700 focus:ring-navy-600" @checked(old('type', 'status') === 'status' || ! $contentLocked)>
                        <span><span class="font-semibold">Change the status only</span> <span class="text-slate-500">({{ $case['released_on'] ? 'reopens the file; its details stay locked' : 'unlocks the Approved status; the details stay locked' }})</span></span>
                    </label>
                    @if ($contentLocked)
                        <label class="flex items-start gap-2 text-xs text-slate-800 cursor-pointer">
                            <input type="radio" name="type" value="details" class="mt-0.5 text-navy-700 focus:ring-navy-600" @checked(old('type') === 'details')>
                            <span><span class="font-semibold">Correct the file's details</span> <span class="text-slate-500">(the admin approves the changes again)</span></span>
                        </label>
                    @endif
                </fieldset>
            @else
                <input type="hidden" name="type" value="details">
            @endif
            <label for="{{ $mine }}" class="block text-[11px] font-bold text-amber-900">{{ $contentLocked || ! $statusLocked ? 'Why does this '.($case['released_on'] ? 'released' : 'approved').' file need to be edited?' : 'Why does the Approved status need to change?' }}</label>
            <textarea id="{{ $mine }}" name="reason" x-ref="reason" rows="3" maxlength="1000" required placeholder="For example: the passport number was typed wrong."
                      class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">{{ old('reason') }}</textarea>
            @error('reason')<p class="text-[11px] font-semibold text-rose-700">{{ $message }}</p>@enderror
            <p class="text-[11px] text-amber-900/80">The admin sees this under Approvals. The file stays locked until they approve it.</p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="h-8 px-3 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold transition-colors">Send request</button>
                <button type="button" @click="open = false" class="h-8 px-3 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
            </div>
        </form>
    </div>
@endif
