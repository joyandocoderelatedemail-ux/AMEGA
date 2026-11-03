{{--
    Shown when an agent opens an application from a service's list: the whole file, read-only, on one page.
    Expects $service, $client, $caseRow (the case's row, null for Visa Extension), $canEdit, $openServices.
--}}
@php
    $hold = $caseRow ? \App\Support\CaseProgress::approvalHold($caseRow['approval']) : null;
    $backUrl = route('admin.client-sheets.applications', ['service' => $service['key']]);
    // A new file of this service for this client: Step 0, with the client taken from this folder.
    $canStartNew = $service['key'] === 'visa_extension' || ! in_array($service['key'], $openServices, true);
    // The new file is filled in from the client's latest one of this service: say so when that is the file shown here.
    $startsFromThis = $canStartNew && $caseRow
        && \App\Support\ImmigrationCases::previousFor($client, $caseRow['kind'])?->getKey() === $caseRow['id'];
@endphp
<div class="rounded-xl bg-white border border-slate-200 border-l-4 {{ $hold === 'returned' ? 'border-l-rose-400' : ($hold ? 'border-l-amber-400' : 'border-l-navy-700') }} shadow-sm px-5 py-4 space-y-3" role="status">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <span class="w-9 h-9 shrink-0 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center"><i data-lucide="eye" class="w-[18px] h-[18px]"></i></span>
            <div>
                <p class="text-sm font-bold text-slate-900">{{ $client->full_name }} &middot; {{ $service['label'] }}</p>
                <p class="text-xs text-slate-500">
                    @if ($canEdit && $approvalState === 'approved')
                        The whole file, as saved. Press Edit to change it; a change goes back to the admin for approval.
                    @elseif ($canEdit)
                        The whole file, as saved. Press Edit to change it.
                    @elseif ($isReleased)
                        The whole file, as saved. It is released and locked: use Request edit to ask the admin to unlock it.
                    @elseif ($approvalLocked)
                        The whole file, as saved. It is approved, so its details are locked; you can still update its status until it is released.
                    @else
                        The whole file, as saved. It can't be changed while it is with the admin.
                    @endif
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $backUrl }}" class="px-3 h-9 inline-flex items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold">Back to applications</a>
            @if ($canEdit)
                <a href="{{ request()->fullUrlWithoutQuery('preview') }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">Edit</a>
            @endif
            @if ($canStartNew)
                <a href="{{ \App\Support\ImmigrationServices::folderUrl($client, $service['key']) }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-accent text-dark hover:bg-accent-dark text-xs font-bold"
                   @if ($startsFromThis) title="Opens a new {{ $service['label'] }} filled in with this file's answers. Change only what is different; this file stays as it is." @endif>{{ $startsFromThis ? 'Start a new '.$service['label'].' from this file' : 'New '.$service['label'] }}</a>
            @endif
        </div>
    </div>

    {{-- The status: changed right here until the file is released (waiting for the admin shows, locked, in the same dropdown). --}}
    @if ($caseRow)
        <div class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</span>
            @include('admin.immigration._case-status', ['case' => $caseRow])
            {{-- A released file (or a locked Approved status) is unlocked by the admin: Request edit --}}
            @include('admin.immigration._case-lock-actions', ['case' => $caseRow])
            {{-- The Notice for this status, beside the dropdown, as on the file itself --}}
            @include('admin.client-sheets._notice', ['client' => $client, 'caseRow' => $caseRow])
            @if ($hold === 'returned' && $caseRow['approval_note'])
                <span class="text-xs font-semibold text-rose-600">{{ $caseRow['approval_note'] }}</span>
            @endif
        </div>
    @elseif ($approvalState)
        <div class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</span>
            <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone(\App\Support\CaseProgress::approvalHold($approvalState) ?? 'approved') }}">
                {{ ($hold = \App\Support\CaseProgress::approvalHold($approvalState)) ? \App\Support\CaseProgress::holdLabel($hold) : 'Approved' }}
            </span>
            {{-- Visa Extension has no case: its Notice is the passport pick-up notice, as on the folder --}}
            @include('admin.client-sheets._notice', ['client' => $client])
        </div>
    @endif
</div>
