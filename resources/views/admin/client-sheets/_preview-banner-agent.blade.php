{{--
    Shown when an agent opens an application from a service's list: the whole file, read-only, on one page.
    Expects $service, $client, $caseRow (the case's row, null for Visa Extension), $canEdit, $openServices.
--}}
@php
    $hold = $caseRow ? \App\Support\CaseProgress::approvalHold($caseRow['approval']) : null;
    $backUrl = route('admin.client-sheets.applications', ['service' => $service['key']]);
    // A new file of this service for this client: Step 0, with the client taken from this folder.
    $canStartNew = $service['key'] === 'visa_extension' || ! in_array($service['key'], $openServices, true);
@endphp
<div class="rounded-2xl bg-slate-800 text-white px-5 py-4 space-y-3" role="status">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <i data-lucide="eye" class="w-5 h-5 shrink-0"></i>
            <div>
                <p class="text-sm font-bold">{{ $client->full_name }} &middot; {{ $service['label'] }}</p>
                <p class="text-[11px] text-white/70">
                    @if ($canEdit)
                        The whole file, as saved. Press Edit to change it.
                    @else
                        The whole file, as saved. It can't be changed while it is with the admin or once approved.
                    @endif
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $backUrl }}" class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">Back to applications</a>
            @if ($canEdit)
                <a href="{{ request()->fullUrlWithoutQuery('preview') }}" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Edit</a>
            @endif
            @if ($canStartNew)
                <a href="{{ \App\Support\ImmigrationServices::folderUrl($client, $service['key']) }}" class="px-4 py-2 rounded-lg bg-accent text-dark hover:bg-accent-dark text-xs font-bold">New {{ $service['label'] }}</a>
            @endif
        </div>
    </div>

    {{-- The status: waiting for the admin shows here, in the same dropdown as every other status. --}}
    @if ($caseRow)
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-white/70">Status</span>
            @include('admin.immigration._case-status', ['case' => $caseRow])
            @if ($hold === 'returned' && $caseRow['approval_note'])
                <span class="text-xs font-semibold text-rose-200">{{ $caseRow['approval_note'] }}</span>
            @endif
        </div>
    @elseif ($approvalState)
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-white/70">Status</span>
            <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone(\App\Support\CaseProgress::approvalHold($approvalState) ?? 'approved') }}">
                {{ ($hold = \App\Support\CaseProgress::approvalHold($approvalState)) ? \App\Support\CaseProgress::holdLabel($hold) : 'Approved' }}
            </span>
        </div>
    @endif
</div>
