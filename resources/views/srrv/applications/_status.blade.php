{{-- Where a retiree file stands: the step it is on, updated by the work itself and never set by hand. While the
     admin holds the file it reads as waiting; a returned file keeps its step, with a badge. A finished file is locked
     (Reopen for an approver, Request edit for anyone else: see _lock). Expects $application. --}}
@php
    use App\Support\CaseProgress;

    $hold = CaseProgress::approvalHold($application->approval_status);
    $released = $application->isReleasedLocked();
    $shownHold = ! $released && $hold === CaseProgress::AWAITING_ADMIN ? $hold : null;
@endphp
<span class="inline-flex items-center gap-1.5 pl-2.5 pr-3 py-1.5 rounded-lg border text-[11px] font-bold {{ CaseProgress::tone($shownHold ?? $application->progressKey()) }}"
      @if ($released) title="{{ CaseProgress::closes($application->workflow_status) ? 'Closed' : 'Released' }}{{ $application->released_on ? ' '.$application->released_on->format('M d, Y') : '' }}. The file is locked." @endif>
    @if ($released)<i data-lucide="lock" class="w-3 h-3"></i>@endif
    {{ $shownHold ? CaseProgress::holdLabel($shownHold) : $application->progress_label }}
</span>
@if ($hold === CaseProgress::RETURNED && ! $released)
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-rose-300 bg-rose-50 text-[11px] font-bold text-rose-800" title="The admin returned this file. Correct it and save to send it again.">Returned by Admin</span>
@endif
