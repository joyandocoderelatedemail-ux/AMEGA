@extends('layouts.immigration')

@section('title', 'Client Sheet - AMEGA Admin')
@section('page_title', $service['key'] === 'visa_extension' ? 'Client Information Sheet' : $service['label'])

@php
    use App\Support\ImmigrationCases;
    use App\Support\ImmigrationServices;

    // The case this page is open on (none for Visa Extension, which is the folder's own ledger).
    $current = $overstay ?? $restamping ?? $petition;
    $caseParam = array_filter(['case' => $current?->id]);
    // The client sheet and consent prints come back to this same folder and case.
    $folderParam = array_filter(['service' => $service['key'] === ImmigrationServices::DEFAULT ? null : $service['key'], 'case' => $current?->id]);
    $button = 'px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-full hover:bg-primary/5 transition-all flex items-center gap-2';
    $openServices = $cases->where('closed', false)->pluck('service')->all();
    $startable = collect(ImmigrationServices::all())->where('available', true);
    $latestExtension = $client->extensions->sortByDesc('sequence')->first();
    // Read-only when an admin opens a file from the approvals page; the banner approves or returns it.
    $preview = $preview ?? false;
    $reviewKind = $current?->exists ? ImmigrationCases::kindOf($current) : 'extensions';
    $reviewId = $current?->exists ? $current->getKey() : $client->id;
    $reviewPending = $current?->exists ? $current->isAwaitingApproval() : $client->extensions->contains(fn ($row) => $row->isAwaitingApproval());
    // The agent's preview: the case's row for the status dropdown, and whether the file can still be edited
    // (returned, or never sent for approval). Waiting-for-admin and approved files stay read-only.
    $caseRow = $current?->exists ? ImmigrationCases::row($current, $client) : null;
    $approvalState = $current?->exists ? $current->approval_status : $latestExtension?->approval_status;
    $canEdit = $approvalState === null || $approvalState === 'returned';
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
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card flex flex-col items-start gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-heading text-xl font-bold text-dark">{{ $client->full_name }}</h2>
                @include('admin.client-sheets.marks', ['client' => $client])
            </div>
            <p class="text-xs text-dark/50 mt-0.5">
                <span class="font-mono font-bold text-primary">{{ $client->passport_number ?: 'No passport on file' }}</span>
                @if ($client->nationality)
                    &nbsp;·&nbsp; {{ $client->nationality }}
                @endif
                &nbsp;·&nbsp; next extension would be the {{ $client->next_extension_number }}{{ \App\Models\ImmigrationClientExtension::ordinalSuffix($client->next_extension_number) }}
            </p>
            @if ($current)
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs mt-1.5">
                    <span class="font-bold text-dark">{{ $service['label'] }}</span>
                    <span class="text-dark/30">&middot;</span> <span class="font-mono font-bold text-primary">{{ $current->reference() }}</span>
                    <span class="text-dark/30">&middot;</span>
                    @if ($caseRow)
                        {{-- Where the case stands, changed right here once the file is printed or filed. --}}
                        @include('admin.immigration._case-status', ['case' => $caseRow])
                        @include('admin.immigration._case-release', ['case' => $caseRow])
                        @if ($current->released_on)<span class="text-dark/60">· closed</span>@endif
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
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.client-sheets.applications', ['service' => $service['key']]) }}" class="px-5 py-2.5 bg-gray-100 text-dark font-bold text-xs rounded-full hover:bg-gray-200 transition-all">
                Back to applications
            </a>
            <a href="{{ route('admin.client-sheets.print', ['clientSheet' => $client] + $folderParam) }}" target="_blank" class="{{ $button }}" title="The AMEGA Client Information Sheet with the extension ledger, as last saved">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Client information sheet
            </a>
            @if ($service['key'] === 'visa_overstaying')
                <a href="{{ route('admin.client-sheets.reconsideration', ['clientSheet' => $client] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's Request for Reconsideration on the Updating and Extension of Authorized Stay, filled from this folder">
                    <i data-lucide="file-pen-line" class="w-4 h-4"></i>
                    Request for Reconsideration
                </a>
            @endif
            @if ($petition)
                {{-- 13(A) files on the immigrant visa CGAF, or the ACR I-Card renewal form --}}
                <a href="{{ route('admin.client-sheets.petition-form', ['clientSheet' => $client, 'type' => $petition->type] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    {{ \App\Models\ImmigrationPetition::FORM_LABELS[$petition->type] ?? 'Application form' }}
                </a>
                <a href="{{ route('admin.client-sheets.petition-checklist', ['clientSheet' => $client, 'type' => $petition->type] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    {{ $petition->type === 'ecc' ? 'Checklist' : 'BI checklist' }}
                </a>
                @if (in_array($petition->type, ['spouse', 'permanent'], true))
                    <a href="{{ route('admin.client-sheets.petition-acr', ['clientSheet' => $client, 'type' => $petition->type] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}"
                       title="BI Form 2014-08-006, filed with the 13(A) application">
                        <i data-lucide="id-card" class="w-4 h-4"></i>
                        ACR I-Card application
                    </a>
                @endif
            @elseif ($restamping)
                {{-- Re-stamping files on the IRD form, with the checklist of the application ticked --}}
                <a href="{{ route('admin.client-sheets.restamping-form', ['clientSheet' => $client] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's IRD Consolidated General Application Form, filled from this folder">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    IRD application form
                </a>
                @if ($restamping->isTransfer() || ! $restamping->isAmendment())
                    <a href="{{ route('admin.client-sheets.restamping-checklist', ['clientSheet' => $client, 'kind' => 'transfer'] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                        <i data-lucide="list-checks" class="w-4 h-4"></i>
                        Transfer checklist
                    </a>
                @endif
                @if ($restamping->isAmendment() || ! $restamping->isTransfer())
                    <a href="{{ route('admin.client-sheets.restamping-checklist', ['clientSheet' => $client, 'kind' => 'amendment'] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                        <i data-lucide="list-checks" class="w-4 h-4"></i>
                        Amendment checklist
                    </a>
                @endif
            @else
                <a href="{{ route('admin.client-sheets.bi-form', ['clientSheet' => $client, 'form' => 'cgaf'] + $folderParam) }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's Consolidated General Application Form for extension of stay, filled from this sheet">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    BI form (CGAF)
                </a>
                <a href="{{ route('admin.client-sheets.bi-form', ['clientSheet' => $client, 'form' => 'checklist'] + $folderParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    BI checklist
                </a>
            @endif
            <a href="{{ route('admin.client-sheets.consent', ['clientSheet' => $client] + $folderParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                Consent Form
            </a>
            <form method="POST" action="{{ route('admin.client-sheets.destroy', $client) }}" class="inline-flex m-0"
                  onsubmit="return confirm({{ Js::from('Delete '.$client->full_name.'\'s folder, with its extension ledger and every service case in it? This cannot be undone.') }});">
                @csrf
                @method('DELETE')
                <input type="hidden" name="service" value="{{ $service['key'] }}">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-rose-200 bg-white text-rose-600 text-xs font-bold hover:bg-rose-50 transition-all" title="Delete this client folder and every case in it">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Delete folder
                </button>
            </form>
        </div>

        {{-- The passport pick-up notice: copy it for a text blast, or email it --}}
        @unless ($preview)
            @include('admin.client-sheets._pickup-notice', ['client' => $client])
        @endunless
    </div>

    {{-- What the admin made of this case: waiting, returned with a reason, or approved --}}
    @if ($current?->exists && $current->approval_status)
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
