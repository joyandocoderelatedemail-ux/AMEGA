@extends('layouts.immigration')

@section('title', 'Client Sheet - AMEGA Admin')
@section('page_title', $service['key'] === 'visa_extension' ? 'Client Information Sheet' : $service['label'])

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Header with the print action, which is the point of this screen -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col items-start gap-4">
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
            @php
                $openOverstay = $client->overstays->firstWhere('released_on', null);
                $openRestamping = $client->restampings->firstWhere('released_on', null);
                $button = 'px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-full hover:bg-primary/5 transition-all flex items-center gap-2';
            @endphp
            @if ($service['key'] === 're_stamping_amendment')
                <p class="text-xs mt-1.5">
                    <span class="font-bold text-dark">{{ $restamping->exists ? 'Re-stamping case in progress' : 'New re-stamping case' }}</span>
                    @if ($restamping->applications)
                        <span class="text-dark/50">&middot; {{ collect($restamping->applications)->map(fn ($key) => \App\Models\ImmigrationRestamping::APPLICATIONS[$key] ?? $key)->implode(', ') }}</span>
                    @endif
                    &middot; <a href="{{ route('admin.client-sheets.edit', $client) }}" class="font-bold text-primary hover:underline">Open as Visa Extension</a>
                </p>
            @elseif ($service['key'] === 'visa_overstaying')
                <p class="text-xs mt-1.5">
                    <span class="font-bold text-dark">{{ $overstay->exists ? 'Overstay case in progress' : 'New overstay case' }}</span>
                    @if ($overstay->overstayLength())
                        <span class="text-dark/50">&middot; {{ $overstay->overstayLength() }} past the stay that lapsed {{ $overstay->expired_on->format('M d, Y') }}</span>
                    @endif
                    &middot; <a href="{{ route('admin.client-sheets.edit', $client) }}" class="font-bold text-primary hover:underline">Open as Visa Extension</a>
                </p>
            @elseif ($openOverstay)
                <p class="text-xs mt-1.5">
                    <a href="{{ route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'visa_overstaying']) }}"
                       class="inline-flex items-center gap-1 font-bold text-amber-700 hover:underline">
                        <i data-lucide="clock-alert" class="w-3.5 h-3.5"></i> Overstay case in progress: open it
                    </a>
                </p>
            @endif
            @if ($openRestamping && $service['key'] === 'visa_extension')
                <p class="text-xs mt-1.5">
                    <a href="{{ route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 're_stamping_amendment']) }}"
                       class="inline-flex items-center gap-1 font-bold text-amber-700 hover:underline">
                        <i data-lucide="stamp" class="w-3.5 h-3.5"></i> Re-stamping case in progress: open it
                    </a>
                </p>
            @endif
            @if ($band = $client->validity_band)
                <p class="text-xs font-bold mt-1.5 {{ $band['key'] === 'expired' ? 'text-rose-600' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">
                    {{ $band['label'] }} — {{ $band['detail'] }}
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.client-sheets.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark font-bold text-xs rounded-full hover:bg-gray-200 transition-all">
                Back to counter
            </a>
            <a href="{{ route('admin.client-sheets.print', $client) }}" target="_blank"
               class="px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-full hover:bg-primary/5 transition-all flex items-center gap-2"
               title="Print the last saved version without reviewing">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Print last saved
            </a>
            @if ($service['key'] === 'visa_overstaying')
                <a href="{{ route('admin.client-sheets.reconsideration', $client) }}" target="_blank" rel="noopener"
                   class="px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-full hover:bg-primary/5 transition-all flex items-center gap-2"
                   title="The BI's Request for Reconsideration on the Updating and Extension of Authorized Stay, filled from this folder">
                    <i data-lucide="file-pen-line" class="w-4 h-4"></i>
                    Request for Reconsideration
                </a>
            @endif
            @if ($service['key'] === 're_stamping_amendment')
                {{-- Re-stamping files on the IRD form, with the checklist of the application ticked --}}
                <a href="{{ route('admin.client-sheets.restamping-form', $client) }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's IRD Consolidated General Application Form, filled from this folder">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    IRD application form
                </a>
                @if ($restamping->isTransfer() || ! $restamping->isAmendment())
                    <a href="{{ route('admin.client-sheets.restamping-checklist', [$client, 'transfer']) }}" target="_blank" rel="noopener" class="{{ $button }}">
                        <i data-lucide="list-checks" class="w-4 h-4"></i>
                        Transfer checklist
                    </a>
                @endif
                @if ($restamping->isAmendment() || ! $restamping->isTransfer())
                    <a href="{{ route('admin.client-sheets.restamping-checklist', [$client, 'amendment']) }}" target="_blank" rel="noopener" class="{{ $button }}">
                        <i data-lucide="list-checks" class="w-4 h-4"></i>
                        Amendment checklist
                    </a>
                @endif
            @else
                <a href="{{ route('admin.client-sheets.bi-form', [$client, 'cgaf']) }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="The BI's Consolidated General Application Form for extension of stay, filled from this sheet">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    BI form (CGAF)
                </a>
                <a href="{{ route('admin.client-sheets.bi-form', [$client, 'checklist']) }}" target="_blank" rel="noopener" class="{{ $button }}">
                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                    BI checklist
                </a>
            @endif
            <a href="{{ route('admin.client-sheets.consent', $client) }}" target="_blank" rel="noopener"
               class="px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-full hover:bg-primary/5 transition-all flex items-center gap-2">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                Consent Form
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.client-sheets.update', $client) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        @include('admin.client-sheets.form', ['client' => $client, 'service' => $service, 'overstay' => $overstay, 'restamping' => $restamping])
    </form>
</div>
@endsection
