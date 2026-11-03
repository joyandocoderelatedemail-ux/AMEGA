{{-- Update where a case stands. Choosing a status saves it; choosing Released asks for the release date first.
     Expects $case, a row from ImmigrationCases.
     Optional $readOnly (a preview): the status is shown as text and nothing can be changed.
     A released case is locked the same way, with a lock; _case-lock-actions has the way back (Reopen, or Request edit). --}}
@php
    // Waiting for the admin is part of the status: shown, locked, and set by the save itself. A file the admin
    // returned keeps its real status, which stays free to change; a badge says it was returned.
    $hold = \App\Support\CaseProgress::approvalHold($case['approval'] ?? null);
    $pending = $hold === \App\Support\CaseProgress::AWAITING_ADMIN;
    $returned = $hold === \App\Support\CaseProgress::RETURNED;
    $readOnly = $readOnly ?? false;
    $released = (bool) ($case['released_on'] ?? false);
    $today = now()->toDateString();
    $approver = auth()->user()->can('immigration.approve');
    // The Bureau's Approved status is locked for anyone who cannot approve: only Released is left to pick, until the admin unlocks it.
    $statusLocked = ! $approver && ($case['status_locked'] ?? false);
    // Picking Approved asks first for those it would lock; an approver is never locked.
    $confirmLock = ! $approver && ($case['progress'] ?? null) !== 'approved';
    $confirmText = 'Mark '.$case['reference'].' as Approved? This status will be locked: only an admin can unlock it, by approving a status-change request. You can still mark the file Released.';
@endphp
@if ($readOnly || $released)
    <span class="inline-flex items-center gap-1.5 pl-2.5 pr-3 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($hold ?? $case['progress']) }}"
          @if ($released) title="{{ \App\Support\CaseProgress::closes($case['workflow'] ?? null) ? 'Closed' : 'Released' }} {{ $case['released_on']->format('M d, Y') }}. The file is locked." @endif>
        @if ($released)<i data-lucide="lock" class="w-3 h-3"></i>@endif
        {{ $hold ? \App\Support\CaseProgress::holdLabel($hold) : $case['progress_label'] }}
    </span>
@else
<form method="POST" action="{{ route('admin.immigration.cases.status', [$case['kind'], $case['id']]) }}" class="inline-flex flex-wrap items-center gap-1.5"
      x-data="{ releasing: false, previous: @js($pending ? '' : $case['progress']), confirmLock: @js($confirmLock) }" @keydown.escape="if (releasing) { releasing = false; $refs.status.value = previous }">
    @csrf
    <select name="status" x-ref="status" aria-label="Update status of {{ $case['reference'] }}" @disabled($pending)
            @if ($statusLocked) title="Approved is locked. Use Request edit." @endif
            @change="if ($event.target.value === 'approved' && confirmLock) { if (! confirm(@js($confirmText))) { $event.target.value = previous; return } releasing = false; $el.form.requestSubmit() } else if ($event.target.value === 'released') { releasing = true; $nextTick(() => $refs.date.focus()) } else { releasing = false; $el.form.requestSubmit() }"
            class="pl-2.5 pr-7 py-1.5 rounded-lg border text-[11px] font-bold focus:outline-none focus:ring-2 focus:ring-primary {{ $pending ? 'cursor-not-allowed' : 'cursor-pointer' }} {{ \App\Support\CaseProgress::tone($pending ? $hold : $case['progress']) }}">
        @if ($pending)
            <option value="" selected class="bg-white text-dark font-semibold">{{ \App\Support\CaseProgress::holdLabel($hold) }}</option>
        @endif
        @foreach (\App\Support\CaseProgress::OPTIONS as $statusKey => $statusLabel)
            <option value="{{ $statusKey }}" @selected(! $pending && $case['progress'] === $statusKey) @disabled($statusLocked && $statusKey !== 'approved') class="bg-white text-dark font-semibold">{{ $statusLabel }}</option>
            @if ($statusKey === 'approved' && ! $pending)
                <option value="{{ \App\Support\CaseProgress::RELEASED }}" class="bg-white text-dark font-semibold">Released</option>
            @endif
        @endforeach
    </select>

    @if ($statusLocked)
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-slate-300 bg-slate-50 text-[11px] font-bold text-slate-700" title="Approved is locked. Use Request edit.">
            <i data-lucide="lock" class="w-3 h-3"></i> Approved is locked
        </span>
    @endif
    @if ($returned)
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-rose-300 bg-rose-50 text-[11px] font-bold text-rose-800" title="The admin returned this file. Correct it and save to send it again.">Returned by Admin</span>
    @endif

    {{-- Released closes and locks the file, so it asks when the BI released it (today unless changed) --}}
    @unless ($pending)
        <div x-show="releasing" style="display: none" role="group" aria-label="Release date for {{ $case['reference'] }}"
             class="inline-flex flex-wrap items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 py-1 pl-2.5 pr-1">
            <label for="released-on-{{ $case['kind'] }}-{{ $case['id'] }}" class="text-[11px] font-bold text-emerald-800 whitespace-nowrap">Released on</label>
            <input id="released-on-{{ $case['kind'] }}-{{ $case['id'] }}" type="date" name="released_on" x-ref="date" value="{{ $today }}" max="{{ $today }}"
                   @if ($case['filed_on']) min="{{ $case['filed_on']->toDateString() }}" @endif
                   :required="releasing" :disabled="! releasing"
                   class="h-6 px-1.5 rounded-md border border-emerald-300 bg-white text-[11px] text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            <button type="submit" class="h-6 px-2.5 rounded-md bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold transition-colors">Release &amp; lock</button>
            <button type="button" @click="releasing = false; $refs.status.value = previous" aria-label="Cancel release"
                    class="h-6 w-6 inline-flex items-center justify-center rounded-md text-emerald-800/70 hover:bg-emerald-100 hover:text-emerald-900 text-base leading-none">&times;</button>
        </div>
    @endunless
    <noscript><button type="submit" class="px-2 py-1 rounded-lg bg-primary text-white text-[11px] font-bold">Update</button></noscript>
</form>
@endif
