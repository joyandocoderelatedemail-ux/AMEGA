@extends('layouts.srrv')

@section('title', 'PRA Submission - '.$application->reference.' - AMEGA Travel and Tours')

@php
    /**
     * PRA Submission, the steps after the wizard (SrrvPraController), with the wizard's stepper. Each step is its own
     * form: its fields, the documents filed on it (what each is, as typed, and the file), its remarks, then Save or
     * Continue. A released or closed file shows everything read-only.
     */
    $a = $application;
    $steps = \App\Models\SrrvApplication::PRA_STEPS;
    $filed = $a->documents->groupBy('document_type');
    $descriptions = [
        1 => 'Submit the application and supporting documents to the Philippine Retirement Authority. Record the PRA reference number and upload a photo or scan as proof of submission.',
        2 => 'Upload the proofs of the visa deposit and the payment verification.',
        3 => 'Where the PRA stands with the application. Withdrawn or Declined closes the file.',
        4 => 'Receive the approved SRRV documentation and coordinate its release to the client.',
        5 => 'Turn over the SRRV documents to the client. This releases the file.',
    ];
    $firstDocument = [1 => 'Proof of submission', 2 => 'Proof of visa deposit'];
    $continueLabels = [1 => 'Save & Continue to Visa Deposit and Payment Verification', 2 => 'Save & Continue to PRA Evaluation and Processing',
        3 => 'Save & Continue to Document Receive', 4 => 'Save & Continue to Client Turnover of Documents', 5 => 'Turn Over to Client & Release'];
    // After a refused save, its step keeps what was typed.
    $old = fn (int $n, string $key, $default = null) => (int) old('step') === $n ? old($key, $default) : $default;

    $card = 'rounded-2xl bg-white border border-gray-200 shadow-card p-6';
    $lb = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5';
    $in = 'w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs placeholder:text-dark/30 focus:bg-white focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $primary = 'inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md';
    $secondary = 'inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-white border border-gray-200 text-dark/70 font-heading font-bold text-xs hover:border-gray-300 transition-colors';

    // PRA steps stay open once approved: the approved-details lock notice is hidden here.
    // Request edit / Reopen lives in the Step 4 header instead (standalone forms at the bottom).
    $praApprover = auth()->user()?->can('srrv.approve');
    $praReleased = $a->isReleasedLocked();
    $praApprovedLock = ! $praReleased && ! $praApprover && $a->isFeeFirst() && $a->approval_status === \App\Models\SrrvApplication::APPROVAL_APPROVED;
    $praUnlocked = $praApprovedLock && ! $a->detailsLocked();
    $praHasRequest = $a->hasEditRequest();
    $praShowRequestEdit = ! $praApprover && ($praReleased || $praApprovedLock) && ! $praUnlocked && ! $praHasRequest;
@endphp

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
                <i data-lucide="landmark" class="w-4 h-4"></i>
                <span>{{ $a->reference }}</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">PRA Submission</h1>
            <p class="text-sm text-dark/50 mt-1">{{ $a->retiree_name }} &middot; {{ $a->optionLabel() }}</p>
        </div>
        <div class="flex items-center gap-3">
            @include('srrv.applications._status', ['application' => $a])
            <a href="{{ route('srrv.applications.edit', [$a, 'step' => array_key_last(\App\Support\SrrvForms::WIZARD_STEPS)]) }}" class="{{ $secondary }}">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to the application
            </a>
        </div>
    </div>

    @if ($a->isReleasedLocked())
        <div class="mb-6 space-y-3">@include('srrv.applications._lock', ['application' => $a])</div>
    @endif

    <div x-data="{ step: {{ $startStep }}, first: 1, last: {{ array_key_last($steps) }}, locked: {{ $reached }}, visited: @js(range(1, $reached)), titles: @js($steps) }"
         x-init="$watch('step', value => { const url = new URL(location.href); url.searchParams.set('step', value); history.replaceState(history.state, '', url); window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })"
         class="space-y-6">

        @include('srrv.partials._stepper', ['steps' => $steps, 'startStep' => $startStep, 'pillIcon' => 'landmark', 'pillText' => 'PRA Submission · '.$a->reference])

        @foreach ($steps as $n => $title)
            <section x-show="step === {{ $n }}" @if ($startStep !== $n) style="display: none" @endif>
                <form method="POST" action="{{ route('srrv.applications.pra.save', $a) }}" enctype="multipart/form-data" class="space-y-6"
                      x-data="{ decision: @js($old($n, 'decision', $a->praDecision())) }">
                    @csrf
                    <input type="hidden" name="step" value="{{ $n }}">

                    <fieldset @disabled($locked) class="space-y-6">
                        <div class="{{ $card }} space-y-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="font-heading text-sm font-extrabold text-dark mb-1">{{ $title }}</h2>
                                    <p class="text-[11px] text-dark/50">{{ $descriptions[$n] }}</p>
                                </div>
                                @if ($n === 4)
                                    @if ($praApprover && $praReleased)
                                        <button type="submit" form="pra-reopen-form"
                                                onclick="return confirm(@js('Reopen '.$a->reference.'?'));"
                                                class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:border-navy-700/40 hover:text-navy-700 shrink-0">
                                            <i data-lucide="lock-open" class="w-4 h-4"></i> Reopen
                                        </button>
                                    @elseif ($praHasRequest)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-xs font-bold text-amber-900 shrink-0">
                                            <i data-lucide="clock" class="w-3.5 h-3.5"></i> Edit requested: waiting for the admin
                                        </span>
                                    @elseif ($praShowRequestEdit)
                                        <button type="button" onclick="document.getElementById('pra-edit-box').classList.toggle('hidden');"
                                                class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border border-amber-300 bg-amber-50 text-xs font-bold text-amber-900 hover:bg-amber-100 shrink-0">
                                            <i data-lucide="pencil-line" class="w-4 h-4"></i> Request edit
                                        </button>
                                    @endif
                                @endif
                                @if ($n === 5)
                                    {{-- A link, not a button: the fieldset disables buttons once the file is released, and the notice is sent then. --}}
                                    <a href="#pra-turnover-notice" role="button"
                                       @click.prevent="$dispatch('open-notice', 'srrv-pra-turnover'); $nextTick(() => document.getElementById('pra-turnover-notice').scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                                       class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-white border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-50 hover:border-slate-300 transition-colors shrink-0">
                                        <i data-lucide="megaphone" class="w-4 h-4"></i> Notice
                                    </a>
                                @endif
                            </div>
                            @if ($n === 4 && $praShowRequestEdit)
                                <div id="pra-edit-box" class="{{ $errors->has('reason') ? '' : 'hidden ' }}rounded-xl border border-amber-200 bg-amber-50/60 p-3 space-y-2">
                                    <label for="pra-edit-reason" class="block text-[11px] font-bold text-amber-900">Which approved detail needs to change, and why?</label>
                                    <textarea id="pra-edit-reason" name="reason" form="pra-request-edit-form" rows="3" maxlength="1000" required placeholder="For example: the passport number was typed wrong."
                                              class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600">{{ old('reason') }}</textarea>
                                    @error('reason')<p class="text-[11px] font-semibold text-rose-700">{{ $message }}</p>@enderror
                                    <p class="text-[11px] text-amber-900/80">The admin sees this under SRRV Approvals. The details stay locked until they approve it.</p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="submit" form="pra-request-edit-form" class="h-8 px-3 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">Send request</button>
                                        <button type="button" onclick="document.getElementById('pra-edit-box').classList.add('hidden');" class="h-8 px-3 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                                    </div>
                                </div>
                            @endif
                            @if ($n === 4 && $praHasRequest && $a->edit_request_note)
                                <p class="text-xs text-amber-900/80">&ldquo;{{ $a->edit_request_note }}&rdquo;</p>
                            @endif

                            @if ($n === 1)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="pra_reference" class="{{ $lb }}">PRA reference number</label>
                                        <input id="pra_reference" type="text" name="pra_reference" value="{{ $old(1, 'pra_reference', $a->pra_reference) }}" class="{{ $in }}">
                                    </div>
                                </div>
                                <label class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                                    <input type="hidden" name="submitted" value="0">
                                    <input type="checkbox" name="submitted" value="1" @checked($a->lodged_at || $old(1, 'submitted')) class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                    <span>
                                        <span class="block text-xs font-bold text-dark">Submitted to the PRA, proof uploaded</span>
                                        <span class="block text-[10px] text-dark/50 mt-0.5">
                                            {{ $a->lodged_at ? 'Submitted '.$a->lodged_at->format('M d, Y g:i A').'.' : 'Tick once the application and documents are with the PRA, then continue.' }}
                                        </span>
                                    </span>
                                </label>
                            @endif

                            @if ($n === 4)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="documents_received_at" class="{{ $lb }}">Date and time received</label>
                                        <input id="documents_received_at" type="datetime-local" name="documents_received_at"
                                               value="{{ $old(4, 'documents_received_at', $a->documents_received_at?->format('Y-m-d\TH:i')) }}" class="{{ $in }}">
                                        <p class="text-[10px] text-dark/45 mt-1">Set by the agent: when the approved documents were received.</p>
                                        @if ($a->documents_received_at)
                                            <p class="text-[10px] font-semibold text-emerald-700 mt-1">Received {{ $a->documents_received_at->format('M d, Y g:i A') }}.</p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if ($n === 3)
                                <div class="max-w-xs">
                                    <label for="decision" class="{{ $lb }}">PRA status</label>
                                    <select id="decision" name="decision" x-model="decision" class="{{ $in }} font-bold">
                                        @foreach (\App\Models\SrrvApplication::PRA_DECISIONS as $value => $decisionLabel)
                                            <option value="{{ $value }}">{{ $decisionLabel }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="decision === 'withdrawn'" x-cloak class="mt-1.5 text-[11px] font-semibold text-rose-700">Saving marks the file Withdrawn by Client and closes it.</p>
                                    <p x-show="decision === 'declined'" x-cloak class="mt-1.5 text-[11px] font-semibold text-rose-700">Saving marks the file Declined by PRA and closes it.</p>
                                    <p x-show="decision === 'approved'" x-cloak class="mt-1.5 text-[11px] font-semibold text-emerald-700">Continue to receive the approved documents.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Document Checklist for this step: what is filed on it, then a type and a file, filed at once (as on Step 5) -->
                        @php $stepDocuments = $filed->get("pra_step_{$n}", collect()); $uploadError = (int) old('step') === $n && ($errors->has('document_type') || $errors->has('file')); @endphp
                        <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="font-heading text-sm font-extrabold text-dark">{{ $n === 1 ? 'Upload Reference' : 'Document Checklist' }}</h2>
                                    <p class="text-[11px] text-dark/45 mt-0.5">Held privately &mdash; never served from the public web root</p>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">{{ $stepDocuments->count() }} filed</span>
                            </div>

                            @if ($stepDocuments->isEmpty())
                                <div class="px-6 py-10 text-center">
                                    <i data-lucide="file-stack" class="w-7 h-7 text-dark/20 mx-auto mb-2"></i>
                                    <p class="text-xs font-bold text-dark/50">Nothing filed yet.</p>
                                </div>
                            @else
                                <ul class="divide-y divide-gray-100">
                                    @foreach ($stepDocuments as $document)
                                        <li class="px-6 py-3.5 flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-dark truncate">{{ $document->original_name ?: basename($document->file_path) }}</p>
                                                    <p class="text-[10px] text-dark/45">
                                                        {{ $document->remarks ?: \App\Models\SrrvApplicationDocument::TYPE_LABELS[$document->document_type] }}
                                                        @if ($document->file_size)
                                                            <span class="text-dark/30">&middot;</span> {{ number_format($document->file_size / 1024, 0) }} KB
                                                        @endif
                                                        <span class="text-dark/30">&middot;</span> {{ $document->created_at?->format('M d, Y g:i A') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <a href="{{ route('srrv.documents.download', $document) }}"
                                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors shrink-0">
                                                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                                    <span>Download</span>
                                                </a>
                                                @unless ($locked)
                                                    <button type="submit" form="pra-remove-{{ $document->id }}" title="Remove (incorrect file)"
                                                            onclick="return confirm('Remove {{ addslashes($document->original_name ?: basename($document->file_path)) }}? Use this for an incorrect file.');"
                                                            class="inline-flex items-center justify-center w-8 h-8 bg-gray-100 text-dark/50 rounded-lg hover:bg-rose-600 hover:text-white transition-colors">
                                                        <i data-lucide="x" class="w-4 h-4"></i>
                                                    </button>
                                                @endunless
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @unless ($locked)
                                {{-- Part of the step's form: File Document saves what is typed on the step and files the document. --}}
                                <div class="px-6 py-5 border-t border-gray-100 bg-gray-50/60">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div>
                                            <label for="pra_document_type_{{ $n }}" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Document Type</label>
                                            <input id="pra_document_type_{{ $n }}" type="text" name="document_type" maxlength="100"
                                                   value="{{ (int) old('step') === $n ? old('document_type') : '' }}" placeholder="e.g. {{ $firstDocument[$n] ?? 'Official receipt' }}"
                                                   class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label for="pra_document_file_{{ $n }}" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">File</label>
                                            <input id="pra_document_file_{{ $n }}" type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                                   class="w-full px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-dark text-xs file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                        </div>
                                        <div class="flex items-end">
                                            <button type="submit" name="then" value="document"
                                                    class="w-full px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                                                File Document
                                            </button>
                                        </div>
                                    </div>
                                    @if ($uploadError)
                                        <p class="mt-2 text-[11px] font-semibold text-rose-600">{{ $errors->first('document_type') ?: $errors->first('file') }}</p>
                                    @endif
                                    <p class="text-[10px] text-dark/45 mt-2">JPG, PNG, WebP or PDF up to 10 MB. Stored privately and released only through this counter.</p>
                                </div>
                            @endunless
                        </div>

                        <!-- Remarks for this step -->
                        <div class="{{ $card }}">
                            <label for="remarks-{{ $n }}" class="block font-heading text-sm font-extrabold text-dark mb-1">Remarks</label>
                            <p class="text-[11px] text-dark/50 mb-3">Kept on this step.</p>
                            <textarea id="remarks-{{ $n }}" name="remarks" rows="3" maxlength="2000"
                                      class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ $old($n, 'remarks', $a->praRemarks($n)) }}</textarea>
                        </div>
                    </fieldset>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        @if ($n > 1)
                            <button type="button" @click="step--" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                            </button>
                        @else
                            <span></span>
                        @endif
                        @unless ($locked)
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($n === 3)
                                    <button type="submit" name="then" value="save" class="{{ $secondary }}"
                                            x-text="['withdrawn', 'declined'].includes(decision) ? 'Save & Close the File' : 'Save'">Save</button>
                                    <button type="submit" name="then" value="continue" x-show="decision === 'approved'" x-cloak class="{{ $primary }}">
                                        {{ $continueLabels[3] }} <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </button>
                                @else
                                    <button type="submit" name="then" value="save" class="{{ $secondary }}">Save</button>
                                    <button type="submit" name="then" value="continue" class="{{ $primary }}">
                                        {{ $continueLabels[$n] }} <i data-lucide="{{ $n === 5 ? 'package-check' : 'arrow-right' }}" class="w-4 h-4"></i>
                                    </button>
                                @endif
                            </div>
                        @endunless
                    </div>
                </form>
                @if ($n === 5)
                    @php
                        $received = $a->documents_received_at?->format('F j, Y \a\t g:i A');
                        $turnoverBody = "Mabuhay {$a->retiree_name}!\n\nYour SRRV documents ({$a->reference}) are now ready for collection".($received ? " since {$received}" : '').".\n\n📍 New Office Address\nUnit 1, Astrofield Building\nMitchell Avenue, Balibago, Angeles City\nNearest landmark: Dennis Locksmith or NBH Hardware at the back of Red Street\n\nPlease bring a valid ID when you claim. Thank you for choosing AMEGA Travel and Tours!\n\nBest Regards,\n".(auth()->user()->name ?? 'AMEGA Travel and Tours');
                    @endphp
                    <div class="mt-4" id="pra-turnover-notice">
                        @include('partials._notify-client', [
                            'noticeFor' => 'srrv-pra-turnover',
                            'toName' => $a->retiree_name,
                            'email' => $a->retiree_email,
                            'canEmail' => \App\Services\ClientNotifier::canReceive($a->retiree_email),
                            'meta' => $a->reference.' · Step 5 - Client Turnover of Documents',
                            'template' => [
                                'label' => 'the turnover (documents ready for collection)',
                                'body' => $turnoverBody,
                            ],
                            'emptyMessage' => null,
                            'subject' => 'Your SRRV documents are ready for collection ('.$a->reference.')',
                            'action' => route('srrv.applications.pra.notify', $a),
                            'hidden' => [],
                            'standalone' => true,
                            'showButton' => false,
                            'noEmailText' => 'No email on the application',
                            'askEmail' => true,
                        ])
                    </div>
                    @if ($a->finishedState() === 'released')
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center">
                            <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-600 text-white">
                                <i data-lucide="check" class="w-7 h-7"></i>
                            </span>
                            <h2 class="font-heading text-lg font-extrabold text-emerald-900">File complete</h2>
                            <p class="text-xs text-emerald-800 mt-1">
                                {{ $a->reference }} was turned over to {{ $a->retiree_name }}{{ $a->released_at ? ' on '.$a->released_at->format('M d, Y g:i A') : '' }} and released.
                            </p>
                            <a href="{{ route('srrv.dashboard') }}" class="{{ $primary }} mt-5">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Back to dashboard
                            </a>
                        </div>
                    @endif
                @endif
            </section>
        @endforeach
    </div>

    {{-- One standalone form per filed document (nested forms are dropped by browsers). --}}
    @foreach ($steps as $n => $title)
        @foreach ($filed->get("pra_step_{$n}", collect()) as $document)
            <form id="pra-remove-{{ $document->id }}" method="POST" action="{{ route('srrv.pra.documents.destroy', $document) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endforeach

    {{-- Request edit + Reopen live here (they cannot nest inside the step form). --}}
    @if ($praShowRequestEdit)
        <form id="pra-request-edit-form" method="POST" action="{{ route('srrv.applications.request-edit', $a) }}" class="hidden">
            @csrf
        </form>
    @endif
    @if ($praApprover && $praReleased)
        <form id="pra-reopen-form" method="POST" action="{{ route('srrv.applications.reopen', $a) }}" class="hidden">
            @csrf
        </form>
    @endif

@endsection
