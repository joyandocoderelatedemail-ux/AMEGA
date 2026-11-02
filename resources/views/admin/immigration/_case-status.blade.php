{{-- Update where a case stands. Choosing a status saves it. Expects $case, a row from ImmigrationCases.
     Optional $readOnly (a preview): the status is shown as text and nothing can be changed.
     A released case is locked the same way, with a lock; Reopen (in _case-release) is the way back. --}}
@php
    // Waiting for the admin (or returned by them) is part of the status: shown, locked, and set by the save itself.
    $hold = \App\Support\CaseProgress::approvalHold($case['approval'] ?? null);
    $readOnly = $readOnly ?? false;
    $released = (bool) ($case['released_on'] ?? false);
@endphp
@if ($readOnly || $released)
    <span class="inline-flex items-center gap-1.5 pl-2.5 pr-3 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($hold ?? $case['progress']) }}"
          @if ($released) title="Released: the status is locked. Reopen the case to change it." @endif>
        @if ($released)<i data-lucide="lock" class="w-3 h-3"></i>@endif
        {{ $hold ? \App\Support\CaseProgress::holdLabel($hold) : $case['progress_label'] }}
    </span>
@else
<form method="POST" action="{{ route('admin.immigration.cases.status', [$case['kind'], $case['id']]) }}" class="inline-flex items-center gap-1.5">
    @csrf
    <select name="status" onchange="this.form.requestSubmit()" aria-label="Update status of {{ $case['reference'] }}" @disabled($hold)
            class="pl-2.5 pr-7 py-1.5 rounded-lg border text-[11px] font-bold focus:outline-none focus:ring-2 focus:ring-primary {{ $hold ? 'cursor-not-allowed' : 'cursor-pointer' }} {{ \App\Support\CaseProgress::tone($hold ?? $case['progress']) }}">
        @if ($hold)
            <option value="" selected class="bg-white text-dark font-semibold">{{ \App\Support\CaseProgress::holdLabel($hold) }}</option>
        @endif
        @foreach (\App\Support\CaseProgress::OPTIONS as $statusKey => $statusLabel)
            <option value="{{ $statusKey }}" @selected(! $hold && $case['progress'] === $statusKey) class="bg-white text-dark font-semibold">{{ $statusLabel }}</option>
        @endforeach
    </select>
    <noscript><button type="submit" class="px-2 py-1 rounded-lg bg-primary text-white text-[11px] font-bold">Update</button></noscript>
</form>
@endif
