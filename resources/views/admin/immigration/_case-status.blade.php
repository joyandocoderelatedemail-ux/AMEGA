{{-- Update where a case stands. Choosing a status asks for a remark (and, for Released, the release date) and saves
     with it; every change and its remark is kept in the status history shown underneath.
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
    {{-- Released shows Released; otherwise only Waiting replaces the status (a returned file keeps its status, with a badge). --}}
    @php
        $shownHold = ! $released && $pending ? $hold : null;
    @endphp
    <span class="inline-flex items-center gap-1.5 pl-2.5 pr-3 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($shownHold ?? $case['progress']) }}"
          @if ($released) title="{{ \App\Support\CaseProgress::closes($case['workflow'] ?? null) ? 'Closed' : 'Released' }} {{ $case['released_on']->format('M d, Y') }}. The file is locked." @endif>
        @if ($released)<i data-lucide="lock" class="w-3 h-3"></i>@endif
        {{ $shownHold ? \App\Support\CaseProgress::holdLabel($shownHold) : $case['progress_label'] }}
    </span>
    @if ($returned && ! $released)
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-rose-300 bg-rose-50 text-[11px] font-bold text-rose-800">Returned by Admin</span>
    @endif
@else
<form method="POST" action="{{ route('admin.immigration.cases.status', [$case['kind'], $case['id']]) }}" class="inline-flex flex-wrap items-center gap-1.5"
      x-data="{ picked: false, releasing: false, previous: @js($pending ? '' : $case['progress']), confirmLock: @js($confirmLock) }"
      @keydown.escape="if (picked) { picked = false; releasing = false; $refs.status.value = previous }">
    @csrf
    {{-- Picking a status opens the remark; nothing is saved until the remark is written and saved. --}}
    <select name="status" x-ref="status" aria-label="Update status of {{ $case['reference'] }}" @disabled($pending)
            @if ($statusLocked) title="Approved is locked. Use Request edit." @endif
            @change="const v = $event.target.value; if (v === 'approved' && confirmLock && ! confirm(@js($confirmText))) { $event.target.value = previous; return } picked = v !== previous; releasing = v === 'released'; if (picked) $nextTick(() => $refs.remark.focus())"
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

    @unless ($pending)
        {{-- The remark for the change (always), and for Released the date the BI released it --}}
        <div x-show="picked" style="display: none" role="group" aria-label="Remark for the status change of {{ $case['reference'] }}"
             class="inline-flex flex-wrap items-center gap-1.5 rounded-lg border py-1 pl-2.5 pr-1"
             :class="releasing ? 'border-emerald-300 bg-emerald-50' : 'border-amber-300 bg-amber-50'">
            <span x-show="releasing" class="inline-flex items-center gap-1.5">
                <label for="released-on-{{ $case['kind'] }}-{{ $case['id'] }}" class="text-[11px] font-bold text-emerald-800 whitespace-nowrap">Released on</label>
                <input id="released-on-{{ $case['kind'] }}-{{ $case['id'] }}" type="date" name="released_on" value="{{ $today }}" max="{{ $today }}"
                       @if ($case['filed_on']) min="{{ $case['filed_on']->toDateString() }}" @endif
                       :required="releasing" :disabled="! releasing"
                       class="h-6 px-1.5 rounded-md border border-emerald-300 bg-white text-[11px] text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            </span>
            <label for="remark-{{ $case['kind'] }}-{{ $case['id'] }}" class="text-[11px] font-bold whitespace-nowrap" :class="releasing ? 'text-emerald-800' : 'text-amber-900'">Remark</label>
            <input id="remark-{{ $case['kind'] }}-{{ $case['id'] }}" type="text" name="remark" x-ref="remark" maxlength="1000" :required="picked" :disabled="! picked"
                   placeholder="Why the status changes" autocomplete="off"
                   class="h-6 w-56 px-1.5 rounded-md border border-slate-300 bg-white text-[11px] text-slate-900 focus:outline-none focus:ring-2 focus:ring-primary">
            <button type="submit" class="h-6 px-2.5 rounded-md text-white text-[11px] font-bold transition-colors"
                    :class="releasing ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-primary hover:bg-primary-dark'"
                    x-text="releasing ? 'Release & lock' : 'Save status'">Save status</button>
            <button type="button" @click="picked = false; releasing = false; $refs.status.value = previous" aria-label="Cancel the status change"
                    class="h-6 w-6 inline-flex items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-slate-900 text-base leading-none">&times;</button>
        </div>
    @endunless
    <noscript><input type="text" name="remark" placeholder="Remark" required><button type="submit" class="px-2 py-1 rounded-lg bg-primary text-white text-[11px] font-bold">Update</button></noscript>
</form>
@endif

{{-- The status history: every change with its remark, newest first --}}
@php
    $statusHistory = \App\Models\ImmigrationStatusChange::forCase($case['kind'], (int) $case['id'])->with('user:id,name')->limit(50)->get();
    $latestChange = $statusHistory->first();
@endphp
@if ($latestChange)
    <details data-status-history class="group basis-full order-last mt-1.5 text-[11px] text-slate-600">
        <summary class="cursor-pointer list-none inline-flex flex-wrap items-center gap-1.5">
            <i data-lucide="message-square-text" class="w-3.5 h-3.5 text-slate-400"></i>
            <span class="font-semibold text-slate-700">{{ $latestChange->toLabel() }}:</span>
            <span class="text-slate-700">{{ $latestChange->remark }}</span>
            <span class="text-slate-400">&middot; {{ $latestChange->user?->name ?? 'Someone' }}, {{ $latestChange->created_at->format('M d, Y g:i A') }}</span>
            @if ($statusHistory->count() > 1)
                <span class="font-bold text-primary group-open:hidden">History ({{ $statusHistory->count() }})</span>
            @endif
        </summary>
        @if ($statusHistory->count() > 1)
            <ol class="mt-2 space-y-1.5 border-l-2 border-slate-200 pl-3">
                @foreach ($statusHistory as $change)
                    <li>
                        <span class="font-semibold text-slate-700">{{ $change->fromLabel() ? $change->fromLabel().' → ' : '' }}{{ $change->toLabel() }}</span>
                        @if ($change->released_on) <span class="text-slate-500">({{ $change->released_on->format('M d, Y') }})</span> @endif
                        <span class="text-slate-400">&middot; {{ $change->user?->name ?? 'Someone' }}, {{ $change->created_at->format('M d, Y g:i A') }}</span>
                        <span class="block text-slate-700">{{ $change->remark }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </details>
@endif
