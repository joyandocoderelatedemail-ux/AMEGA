@extends('layouts.immigration')

@section('title', 'Client Sheet - AMEGA Admin')
@section('page_title', $service['key'] === 'visa_extension' ? 'Client Information Sheet' : $service['label'])

@php
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
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

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
                <p class="text-xs mt-1.5">
                    <span class="font-bold text-dark">{{ $service['label'] }}</span>
                    &middot; <span class="font-mono font-bold text-primary">{{ $current->reference() }}</span>
                    &middot; <span class="{{ $current->exists ? 'text-dark/60' : 'text-amber-700 font-bold' }}">{{ $current->exists ? $current->progressLabel().($current->released_on ? ' · closed' : '') : 'New case: saved when you save the folder' }}</span>
                    @if ($overstay?->overstayLength())
                        <span class="text-dark/50">&middot; {{ $overstay->overstayLength() }} past the stay that lapsed {{ $overstay->expired_on->format('M d, Y') }}</span>
                    @endif
                </p>
            @endif
            @if ($band = $client->validity_band)
                <p class="text-xs font-bold mt-1.5 {{ $band['key'] === 'expired' ? 'text-rose-600' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">
                    {{ $band['label'] }} — {{ $band['detail'] }}
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.immigration.cases') }}" class="px-5 py-2.5 bg-gray-100 text-dark font-bold text-xs rounded-full hover:bg-gray-200 transition-all">
                Back to counter
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
        </div>
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

    <!-- Services: everything this client has done at the counter, in one place -->
    <section class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card space-y-4" aria-labelledby="folder-services">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="folder-services" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Services</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Every case in this client's folder. The details on file are shared by all of them.</p>
            </div>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i> Start a service
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                </button>
                <div x-show="open" x-cloak role="menu"
                     class="absolute right-0 z-20 mt-2 w-72 rounded-2xl bg-white border border-gray-200 shadow-xl p-1.5">
                    @foreach ($startable as $item)
                        <a href="{{ route('admin.client-sheets.edit', array_filter(['client_sheet' => $client->id, 'service' => $item['key'] === ImmigrationServices::DEFAULT ? null : $item['key']])) }}"
                           role="menuitem" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50">
                            <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4 text-primary shrink-0"></i>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-bold text-dark">{{ $item['label'] }}</span>
                                <span class="block text-[11px] text-dark/50 truncate">{{ in_array($item['key'], $openServices, true) ? 'A case is in progress: open it' : $item['note'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <ul class="rounded-2xl border border-gray-200 divide-y divide-gray-100">
            {{-- The folder's own Visa Extension ledger --}}
            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 {{ $service['key'] === 'visa_extension' ? 'bg-primary/5' : '' }}">
                <i data-lucide="calendar-plus" class="w-4 h-4 text-primary shrink-0"></i>
                <div class="min-w-0 flex-1">
                    <span class="block text-xs font-bold text-dark">Visa Extension</span>
                    <span class="block text-[11px] text-dark/50">
                        {{ $client->extensions->count() }} {{ \Illuminate\Support\Str::plural('extension', $client->extensions->count()) }} on the ledger
                        @if ($latestExtension?->extension_date) &middot; last on {{ $latestExtension->extension_date->format('M d, Y') }} @endif
                        @if ($client->visa_expiry_date) &middot; visa valid until {{ $client->visa_expiry_date->format('M d, Y') }} @endif
                    </span>
                </div>
                @if ($service['key'] === 'visa_extension')
                    <span class="text-[11px] font-bold text-primary">Open now</span>
                @else
                    <a href="{{ route('admin.client-sheets.edit', $client) }}" class="text-[11px] font-bold text-primary hover:underline">Open</a>
                @endif
            </li>

            @forelse ($cases as $case)
                @php $isCurrent = $current?->exists && $case['service'] === $service['key'] && $case['reference'] === $current->reference(); @endphp
                <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 {{ $isCurrent ? 'bg-primary/5' : '' }}">
                    <i data-lucide="{{ ImmigrationServices::find($case['service'])['icon'] }}" class="w-4 h-4 text-primary shrink-0"></i>
                    <div class="min-w-0 flex-1">
                        <span class="block text-xs font-bold text-dark">
                            {{ $case['label'] }}
                            <span class="font-mono font-bold text-primary ml-1">{{ $case['reference'] }}</span>
                        </span>
                        <span class="block text-[11px] text-dark/50 truncate">
                            {{ $case['summary'] }} &middot; opened {{ $case['opened']?->format('M d, Y') }}
                            @if ($case['filed_on']) &middot; filed {{ $case['filed_on']->format('M d, Y') }} @endif
                            @if ($case['released_on']) &middot; released {{ $case['released_on']->format('M d, Y') }} @endif
                        </span>
                    </div>
                    <span class="text-[11px] font-semibold {{ ! $case['closed'] && $case['missing'] ? 'text-amber-700' : 'text-dark/50' }}">
                        @if (! $case['closed'] && $case['missing']){{ $case['missing'] }} missing @endif
                    </span>
                    @include('admin.immigration._case-status', ['case' => $case])
                    @include('admin.immigration._case-approval', ['case' => $case])
                    @include('admin.immigration._case-release', ['case' => $case])
                    @if ($isCurrent)
                        <span class="text-[11px] font-bold text-primary">Open now</span>
                    @else
                        <a href="{{ $case['url'] }}" class="text-[11px] font-bold text-primary hover:underline">Open</a>
                    @endif
                </li>
            @empty
                <li class="px-4 py-4 text-xs text-dark/50">No other services yet. Use <strong class="text-dark">Start a service</strong> to open one for this client.</li>
            @endforelse
        </ul>
    </section>

    <form method="POST" action="{{ route('admin.client-sheets.update', $client) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        @include('admin.client-sheets.form', ['client' => $client, 'service' => $service, 'overstay' => $overstay, 'restamping' => $restamping, 'petition' => $petition])
    </form>
</div>
@endsection
