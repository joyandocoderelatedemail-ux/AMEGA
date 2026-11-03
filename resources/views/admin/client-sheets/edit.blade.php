@extends('layouts.immigration')

@section('title', 'Client Sheet - AMEGA Admin')
@section('page_title', $service['key'] === 'visa_extension' ? 'Client Information Sheet' : $service['label'])

@php
    use App\Support\ImmigrationCases;
    use App\Support\ImmigrationServices;

    // The case this page is open on (none for Visa Extension, which is the folder's own ledger).
    $current = $overstay ?? $restamping ?? $petition;
    $button = 'h-9 px-3 whitespace-nowrap bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-colors inline-flex items-center justify-center gap-2';
    $openServices = $cases->where('closed', false)->pluck('service')->all();
    $startable = collect(ImmigrationServices::all())->where('available', true);
    $latestExtension = $client->extensions->sortByDesc('sequence')->first();
    // Read-only when an admin opens a file from the approvals page; the banner approves or returns it.
    $preview = $preview ?? false;
    $reviewKind = $current?->exists ? ImmigrationCases::kindOf($current) : 'extensions';
    $reviewId = $current?->exists ? $current->getKey() : $client->id;
    $reviewPending = $current?->exists ? $current->isAwaitingApproval() : $client->extensions->contains(fn ($row) => $row->isAwaitingApproval());
    // The agent's preview: the case's row for the status dropdown, and whether the file can be edited from it.
    // A file with the admin (waiting for approval) or a released one stays read-only. An approved file is locked
    // for anyone who cannot approve: they use Request edit. An approver's change to it goes back through approval.
    $caseRow = $current?->exists ? ImmigrationCases::row($current, $client) : null;
    $approvalState = $current?->exists ? $current->approval_status : $latestExtension?->approval_status;
    $isReleased = $current?->exists && $current->released_on !== null;
    $approvalLocked = $current?->exists && $current->approval_status === 'approved' && ! auth()->user()->can('immigration.approve');
    $canEdit = ! $isReleased && ! $approvalLocked && $approvalState !== 'pending';
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    @if ($preview)
        @if (auth()->user()->isAdmin())
            @include('admin.client-sheets._preview-banner')
        @else
            @include('admin.client-sheets._preview-banner-agent')
        @endif
        {{-- Nothing to type, upload, remove or save while previewing. --}}
        <style>
            form[data-preview] [data-save-controls],
            form[data-preview] label:has(input[type="file"]),
            form[data-preview] [\@click^="removing"] { display: none !important; }
        </style>
    @endif

    <!-- Header with the print action, which is the point of this screen -->
    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-card flex flex-col items-stretch gap-4">
        <div class="min-w-0 w-full">
          <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
            <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-bold text-dark">{{ $client->full_name }}</h2>
                @include('admin.client-sheets.marks', ['client' => $client])
            </div>
            <p class="text-xs text-dark/50 mt-0.5">
                <span class="font-mono font-bold text-primary">{{ $client->passport_number ?: 'No passport on file' }}</span>
                @if ($client->nationality)
                    &nbsp;·&nbsp; {{ $client->nationality }}
                @endif
                &nbsp;·&nbsp; next extension would be the {{ $client->next_extension_number }}{{ \App\Models\ImmigrationClientExtension::ordinalSuffix($client->next_extension_number) }}
            </p>
            </div>
            {{-- The preview banner above already has this link. --}}
            @unless ($preview)
                <a href="{{ route('admin.client-sheets.applications', ['service' => $service['key']]) }}" class="shrink-0 inline-flex items-center gap-1.5 h-9 px-1 text-xs font-bold text-slate-500 hover:text-slate-900 transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Back to applications
                </a>
            @endunless
          </div>
            @if ($current)
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs mt-3 pt-3 border-t border-slate-100">
                    <span class="font-bold text-dark">{{ $service['label'] }}</span>
                    <span class="text-dark/30">&middot;</span> <span class="font-mono font-bold text-primary">{{ $current->reference() }}</span>
                    <span class="text-dark/30">&middot;</span>
                    @if ($caseRow)
                        {{-- Where the case stands, changed right here once the file is printed or filed. --}}
                        @include('admin.immigration._case-status', ['case' => $caseRow, 'readOnly' => $preview])
                        {{-- Released or approved: Reopen for an approver (released), Request edit for an agent --}}
                        @include('admin.immigration._case-lock-actions', ['case' => $caseRow, 'readOnly' => $preview])
                        {{-- The Notice for this status, beside the dropdown; its panel wraps onto its own line --}}
                        @unless ($preview)
                            @include('admin.client-sheets._notice', ['client' => $client, 'caseRow' => $caseRow])
                        @endunless
                    @else
                        <span class="text-amber-700 font-bold">New case: saved when you save the folder</span>
                    @endif
                    @if ($overstay?->overstayLength())
                        <span class="text-dark/50">&middot; {{ $overstay->overstayLength() }} past the stay that lapsed {{ $overstay->expired_on->format('M d, Y') }}</span>
                    @endif
                </div>
            @endif
            @if ($service['key'] === 'visa_extension' && ($band = $client->validity_band))
                <p class="text-xs font-bold mt-1.5 {{ $band['key'] === 'expired' ? 'text-rose-600' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">
                    {{ $band['label'] }} — {{ $band['detail'] }}
                </p>
            @endif
        </div>
        <div class="w-full">
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2" title="Empty forms to hand to the client. The filled ones are on the last step once the admin approves the file.">Blank forms</p>
        {{-- Equal columns that fill the width: a last button on its own row keeps its column width, it is not stretched. --}}
        <div class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-2">
            <a href="{{ route('admin.client-sheets.blank') }}" target="_blank" rel="noopener" class="{{ $button }}" title="An empty AMEGA Client Information Sheet">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Client information sheet
            </a>
            <a href="{{ route('admin.client-sheets.agreement-blank', ['service' => $service['key']]) }}" target="_blank" rel="noopener" class="{{ $button }}"
               title="The Immigration Services Booking Agreement with nothing filled in, for the client to sign">
                <i data-lucide="signature" class="w-4 h-4"></i>
                Services agreement
            </a>
            <a href="{{ route('admin.client-sheets.consent-blank') }}" target="_blank" rel="noopener" class="{{ $button }}">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                Consent Form
            </a>
            @if ($service['key'] === 'visa_overstaying')
                <a href="{{ route('admin.client-sheets.reconsideration-blank') }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's Request for Reconsideration, empty">
                    <i data-lucide="file-pen-line" class="w-4 h-4"></i>
                    Request for Reconsideration
                </a>
            @endif
            @if ($petition)
                {{-- 13(A) files on the immigrant visa CGAF, or the ACR I-Card renewal form --}}
                <a href="{{ route('admin.client-sheets.petition-blank', ['type' => $petition->type, 'form' => 'form']) }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    {{ \App\Models\ImmigrationPetition::FORM_LABELS[$petition->type] ?? 'Application form' }}
                </a>
                <a href="{{ route('admin.client-sheets.petition-blank', ['type' => $petition->type, 'form' => 'checklist']) }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    {{ $petition->type === 'ecc' ? 'Checklist' : 'BI checklist' }}
                </a>
                @if (in_array($petition->type, ['spouse', 'permanent'], true))
                    <a href="{{ route('admin.client-sheets.petition-blank', ['type' => $petition->type, 'form' => 'acr']) }}" target="_blank" rel="noopener" class="{{ $button }}"
                       title="BI Form 2014-08-006, filed with the 13(A) application">
                        <i data-lucide="id-card" class="w-4 h-4"></i>
                        ACR I-Card application
                    </a>
                @endif
            @elseif ($restamping)
                <a href="{{ route('admin.client-sheets.restamping-blank', 'form') }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's IRD Consolidated General Application Form, empty">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    IRD application form
                </a>
                <a href="{{ route('admin.client-sheets.restamping-blank', 'transfer') }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    Transfer checklist
                </a>
                <a href="{{ route('admin.client-sheets.restamping-blank', 'amendment') }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    Amendment checklist
                </a>
            @else
                <a href="{{ route('admin.client-sheets.bi-blank', 'cgaf') }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's Consolidated General Application Form for extension of stay, empty">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    BI form (CGAF)
                </a>
                <a href="{{ route('admin.client-sheets.bi-blank', 'checklist') }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    BI checklist
                </a>
            @endif
        </div>
            @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('admin.client-sheets.destroy', $client) }}" class="flex justify-end m-0 mt-3 pt-3 border-t border-slate-100"
                  onsubmit="return confirm({{ Js::from('Delete '.$client->full_name.'\'s folder, with its extension ledger and every service transaction in it? This cannot be undone.') }});">
                @csrf
                @method('DELETE')
                <input type="hidden" name="service" value="{{ $service['key'] }}">
                <button type="submit" class="h-9 px-3 inline-flex items-center justify-center gap-2 rounded-lg border border-rose-200 bg-white text-rose-600 text-xs font-bold hover:bg-rose-50 transition-colors" title="Delete this client folder and every transaction in it">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Delete folder
                </button>
            </form>
            @endif
        </div>

        {{-- A folder with no case to show a status for (Visa Extension): its Notice is the passport pick-up notice --}}
        @if (! $preview && ! $caseRow)
            @include('admin.client-sheets._notice', ['client' => $client, 'standalone' => true])
        @endif
    </div>

    {{-- A released file (everyone), or an approved one (an agent), is locked: the form below refuses saves until it is unlocked --}}
    @if (($isReleased || $approvalLocked) && ! $preview)
        <div class="rounded-xl bg-slate-50 border border-slate-200 px-5 py-3 text-xs font-semibold text-slate-700 flex items-center gap-2" role="status">
            <i data-lucide="lock" class="w-3.5 h-3.5 shrink-0"></i>
            This file is {{ $isReleased ? 'released' : 'approved' }} and locked, so saving is switched off.
            {{ auth()->user()->can('immigration.approve') ? 'Reopen it above to make changes.' : ($isReleased ? 'Use Request edit above to ask the admin to unlock it.' : 'You can still update its status above until it is released.') }}
        </div>
        <style>form[action="{{ route('admin.client-sheets.update', $client) }}"] [data-save-controls] { display: none !important; }</style>
    @endif

    {{-- What the admin made of this case: waiting, returned with a reason, or approved --}}
    {{-- A file released after the admin returned it is finished: the return no longer applies. --}}
    @if ($current?->exists && $current->approval_status && ! ($current->isReturned() && $current->released_on))
        @if ($current->isAwaitingApproval())
            <div class="rounded-2xl bg-amber-50 border border-amber-200 px-5 py-3 text-xs font-semibold text-amber-900" role="status">
                Waiting for the admin to approve this file. It can be printed, and marked submitted or released, once it is approved.
            </div>
        @elseif ($current->isReturned())
            <div class="rounded-2xl bg-rose-50 border border-rose-200 px-5 py-3 text-xs font-semibold text-rose-900" role="alert">
                Returned by the admin{{ $current->review_note ? ': '.$current->review_note : '.' }} Correct it and save to send it again.
            </div>
        @else
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-3 text-xs font-semibold text-emerald-900">
                Approved{{ $current->reviewedBy ? ' by '.$current->reviewedBy->name : '' }}{{ $current->reviewed_at ? ' on '.$current->reviewed_at->format('M d, Y') : '' }}.
                @if ($current->payment_type) Payment: {{ $current->paymentLabel() }}@if ($current->fee !== null), paid ₱{{ number_format((float) $current->amount_paid, 2) }} of ₱{{ number_format((float) $current->fee, 2) }} @endif. @endif
            </div>
        @endif
    @endif


    @if (! empty($copiedFrom) && ! $preview)
        @include('admin.client-sheets._copied-notice', ['copiedFrom' => $copiedFrom])
    @endif

    <form method="POST" action="{{ route('admin.client-sheets.update', $client) }}" enctype="multipart/form-data" novalidate
          @if ($preview) data-preview x-data x-init="$nextTick(() => $el.querySelectorAll('input, select, textarea').forEach(field => { if (field.type !== 'hidden' && field.name !== '_token') field.disabled = true }))" @endif>
        @csrf
        @method('PUT')

        @include('admin.client-sheets.form', ['client' => $client, 'service' => $service, 'overstay' => $overstay, 'restamping' => $restamping, 'petition' => $petition])
    </form>
</div>
@endsection
