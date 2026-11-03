{{-- Every FILLED form of this service, listed on the last step once the file is approved and unchanged.
     Expects $client, $service. Optional $overstay, $restamping, $petition (the case this page is open on).
     Blank forms are in the page header; the filled ones wait here because an agent may only print an approved file. --}}
@php
    $current = ($overstay ?? null) ?? ($restamping ?? null) ?? ($petition ?? null);
    $caseParam = array_filter(['case' => $current?->exists ? $current->id : null]);
    $folderParam = array_filter(['service' => $service['key'] === \App\Support\ImmigrationServices::DEFAULT ? null : $service['key'], 'case' => $caseParam['case'] ?? null]);
    $button = 'h-9 px-3 whitespace-nowrap bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-colors inline-flex items-center justify-center gap-2';
    $isPetition = ($petition ?? null) !== null;
@endphp
<div data-filled-forms class="space-y-2">
    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Filled forms to print</p>
    <div class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-2">
        <a href="{{ route('admin.client-sheets.print', ['clientSheet' => $client] + $folderParam) }}" target="_blank" rel="noopener" class="{{ $button }}" title="The AMEGA Client Information Sheet with the extension ledger, as last saved">
            <i data-lucide="printer" class="w-4 h-4"></i>
            Client information sheet
        </a>
        <a href="{{ route('admin.client-sheets.agreement', ['clientSheet' => $client, 'service' => $service['key']] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}"
           title="The Immigration Services Booking Agreement, filled from this folder, for the client to sign">
            <i data-lucide="signature" class="w-4 h-4"></i>
            Services agreement
        </a>
        @if ($service['key'] === 'visa_overstaying')
            <a href="{{ route('admin.client-sheets.reconsideration', ['clientSheet' => $client] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}"
               title="The BI's Request for Reconsideration on the Updating and Extension of Authorized Stay, filled from this folder">
                <i data-lucide="file-pen-line" class="w-4 h-4"></i>
                Request for Reconsideration
            </a>
        @endif
        @if ($isPetition)
            @php $type = $petition->type; @endphp
            {{-- 13(A) files on the immigrant visa CGAF, or the ACR I-Card renewal form --}}
            <a href="{{ route('admin.client-sheets.petition-form', ['clientSheet' => $client, 'type' => $type] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                {{ \App\Models\ImmigrationPetition::FORM_LABELS[$type] ?? 'Application form' }}
            </a>
            <a href="{{ route('admin.client-sheets.petition-checklist', ['clientSheet' => $client, 'type' => $type] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}">
                <i data-lucide="list-checks" class="w-4 h-4"></i>
                {{ $type === 'ecc' ? 'Checklist' : 'BI checklist' }}
            </a>
            @if (in_array($type, ['spouse', 'permanent'], true))
                <a href="{{ route('admin.client-sheets.petition-acr', ['clientSheet' => $client, 'type' => $type] + $caseParam) }}" target="_blank" rel="noopener" class="{{ $button }}"
                   title="BI Form 2014-08-006, filed with the 13(A) application">
                    <i data-lucide="id-card" class="w-4 h-4"></i>
                    ACR I-Card application
                </a>
            @endif
        @elseif (($restamping ?? null) !== null)
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
