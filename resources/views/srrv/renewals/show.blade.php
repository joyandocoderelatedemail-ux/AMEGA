@extends('layouts.srrv')

@section('title', $renewal->reference.' - AMEGA Travel and Tours')

@section('content')

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 mb-2">
                @php $fileService = \App\Http\Controllers\Srrv\SrrvClientLookupController::serviceFor('renewal', $renewal->visa_class); @endphp
                <a href="{{ route('srrv.services', $fileService) }}"
                   class="text-[11px] font-bold uppercase tracking-wider text-dark/40 hover:text-primary transition-colors">{{ \App\Http\Controllers\Srrv\SrrvClientLookupController::SERVICES[$fileService]['label'] }}</a>
                <i data-lucide="chevron-right" class="w-3 h-3 text-dark/25"></i>
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ $renewal->reference }}</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">{{ $renewal->retiree_name }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $renewal->visa_class === 'courtesy' ? 'bg-amber-100 text-amber-800' : 'bg-primary/10 text-primary' }}">
                    {{ $renewal->visa_class }}
                </span>
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ in_array($renewal->status, ['released_to_client', 'closed'], true) ? 'bg-emerald-100 text-emerald-800' : ($renewal->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                    {{ $renewal->status_label }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">


            @if ($renewal->isCleared())
            <a href="{{ route('srrv.renewals.consent', $renewal) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                <span>Consent Form</span>
            </a>

            <a href="{{ route('srrv.renewals.request-form', $renewal) }}" target="_blank" rel="noopener" title="PRA-SD-FORM-0006, with ID Renewal ticked"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Request Form</span>
            </a>
            @endif

            @if(! $renewal->finishedState())
                <form method="POST" action="{{ route('srrv.renewals.cancel', $renewal) }}" class="m-0"
                      onsubmit="return confirm('Cancel this renewal?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-rose-600 font-bold text-xs rounded-xl hover:border-rose-300 transition-all">
                        <i data-lucide="ban" class="w-4 h-4"></i>
                        <span>Cancel</span>
                    </button>
                </form>
            @endif

            @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('srrv.renewals.destroy', $renewal) }}" class="m-0"
                  onsubmit="return confirm('Delete this renewal? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" title="Delete renewal"
                        class="p-2.5 bg-white border border-gray-200 text-rose-600 rounded-xl hover:border-rose-300 transition-all">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Waiting for the admin already shows as the first step's name; only returned or approved need a line here. --}}
    @unless ($renewal->isAwaitingApproval())
        @include('srrv.partials._approval-banner', ['file' => $renewal])
    @endunless

    {{-- Only an admin sees the owner card: it is where they hand the file to another officer. --}}
    @if (auth()->user()->isAdmin())
        <x-file-owner :file="$renewal" type="srrv-renewal" class="mb-6" />
    @endif

    <!-- Steps: the wizard stepper's look (pill, Step N - Title, round number, olive now / navy done / grey ahead), read-only -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider whitespace-nowrap bg-primary/10 text-primary">
                    <i data-lucide="calendar-check" class="w-3.5 h-3.5"></i>
                    <span>{{ ucfirst((string) $renewal->visa_class) }} ID Renewal &middot; {{ $renewal->reference }}</span>
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-dark tracking-tight mt-1.5">Step {{ $currentIndex + 1 }} - {{ $renewal->status_label }}</h1>
            </div>
            <div class="flex items-center gap-2 whitespace-nowrap">
                <span class="text-xs font-bold uppercase tracking-wider text-dark/40">Step</span>
                <span class="w-8 h-8 rounded-full bg-primary text-white font-heading font-extrabold text-sm flex items-center justify-center shadow-md shadow-primary/30">{{ $currentIndex + 1 }}</span>
                <span class="text-xs font-bold text-dark/40">of {{ count($stages) }}</span>
            </div>
        </div>

        <div class="grid gap-1.5 mt-6" style="grid-template-columns: repeat({{ count($stages) }}, minmax(0, 1fr))" aria-label="Renewal steps">
            @foreach ($stages as $index => $stage)
                @php $tone = $index === $currentIndex ? ['bg-accent', 'text-accent font-extrabold'] : ($index < $currentIndex ? ['bg-primary', 'text-primary'] : ['bg-gray-200', 'text-dark/40']); @endphp
                <div class="flex flex-col gap-1.5" @if ($index === $currentIndex) aria-current="step" @endif title="{{ $renewal->stageLabel($stage) }}">
                    <span class="h-2 rounded-full {{ $tone[0] }}"></span>
                    <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block {{ $tone[1] }}">{{ $renewal->stageLabel($stage) }}</span>
                </div>
            @endforeach
        </div>

    </div>

    @include('srrv.renewals._stage')

    <!-- Status: change it as the work moves. Closed and cancelled lock the file. -->
    @php $statusLocked = (bool) $renewal->finishedState() && $renewal->status !== 'released_to_client'; @endphp
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Status</h2>
                <p class="text-[11px] text-dark/50">Advance the file one step at a time as the work is done. Released, closed and cancelled lock it.</p>
            </div>
            <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ in_array($renewal->status, ['released_to_client', 'closed'], true) ? 'bg-emerald-100 text-emerald-800' : ($renewal->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                {{ $renewal->status_label }}
            </span>
        </div>
        @if ($renewal->submitted_at)
            <p class="text-xs text-dark/70 mb-4">Submitted to the PRA on {{ $renewal->submitted_at->format('M d, Y') }}@if ($renewal->submitted_remarks): <span class="font-semibold text-dark">{{ $renewal->submitted_remarks }}</span>@endif.</p>
        @endif
        @php
            // One step at a time: the form moves the file to the stage after this one, never past it.
            $stageIndex = array_search($renewal->status, \App\Models\SrrvRenewal::STAGES, true);
            $nextStage = $stageIndex === false ? null : (\App\Models\SrrvRenewal::STAGES[$stageIndex + 1] ?? null);
        @endphp
        @if (! $statusLocked && $nextStage)
            <form method="POST" action="{{ route('srrv.renewals.status', $renewal) }}" x-data="{ target: '{{ $nextStage }}' }">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <input type="hidden" name="status" value="{{ $nextStage }}">
                    <div x-show="target === 'submitted_to_pra'" x-cloak>
                        <label for="submitted_on" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Submitted on *</label>
                        <input id="submitted_on" type="date" name="submitted_on" value="{{ old('submitted_on', $renewal->submitted_at?->toDateString()) }}" max="{{ now()->toDateString() }}"
                               class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div x-show="target === 'released_to_client'" x-cloak>
                        <label for="released_to_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Released to *</label>
                        <input id="released_to_name" type="text" name="released_to_name" value="{{ old('released_to_name', $renewal->collected_by_name) }}" placeholder="Who received the new ID"
                               class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                <div x-show="target === 'submitted_to_pra'" x-cloak class="mt-3">
                    <label for="submitted_remarks" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Submission remarks *</label>
                    <input id="submitted_remarks" type="text" name="submitted_remarks" value="{{ old('submitted_remarks', $renewal->submitted_remarks) }}" maxlength="500" placeholder="What was submitted, and what the PRA said"
                           class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                @error('status') <p class="mt-2 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                @if ($blocker)
                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800">{{ $blocker }}</p>
                @elseif ($renewal->status === 'pending' && $renewal->approval_status === \App\Models\SrrvRenewal::APPROVAL_APPROVED)
                    {{-- Approval does not move the file: the agent advances it once the documents are in. --}}
                    <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] font-semibold text-emerald-800">Approved by the admin, the documents are in and the cashier has been paid: ready to move to {{ $renewal->stageLabel('ready_for_submission') }}.</p>
                @endif
                <div class="mt-3 flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-sm">
                        Advance to {{ $renewal->stageLabel($nextStage) }} <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                    </button>
                    <span class="text-[11px] text-dark/45">The file is marked as saved on every change.</span>
                </div>
            </form>
        @elseif ($statusLocked)
            <p class="text-xs font-semibold text-dark/50">This file is {{ $renewal->finishedState() }} and locked: nothing on it can be changed.</p>
        @else
            <p class="text-xs font-semibold text-dark/50">This renewal is at its last step.</p>
        @endif
    </div>

    <!-- Notes log: what happened, when, and who wrote it -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 mb-6">
        <div class="mb-4">
            <h2 class="font-heading text-sm font-extrabold text-dark">Notes Log</h2>
            <p class="text-[11px] text-dark/50">A running log on the renewal, newest first.</p>
        </div>
        @if ($renewal->notes->isEmpty())
            <p class="text-xs text-dark/40">Nothing noted yet.</p>
        @else
            <ul class="divide-y divide-gray-100 -mx-6 px-6">
                @foreach ($renewal->notes as $note)
                    <li class="py-3">
                        <p class="text-xs text-dark leading-relaxed whitespace-pre-line">{{ $note->note }}</p>
                        <p class="text-[10px] text-dark/45 mt-1">{{ $note->author?->name ?? 'Staff' }} &middot; {{ $note->created_at?->format('M d, Y g:i A') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
        @if (! $statusLocked || $renewal->status === 'released_to_client')
            <form method="POST" action="{{ route('srrv.renewals.notes.store', $renewal) }}" class="mt-4 pt-4 border-t border-gray-100">
                @csrf
                <label for="note_text" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Add a note</label>
                <textarea id="note_text" name="note" rows="2" maxlength="2000" required placeholder="What happened?"
                          class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('note') }}</textarea>
                @error('note') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                <button type="submit" class="mt-2 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark font-bold text-xs transition-all">
                    <i data-lucide="sticky-note" class="w-4 h-4"></i> Add note
                </button>
            </form>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

        <!-- Details -->
        <div class="lg:col-span-2 rounded-2xl bg-white border border-gray-200 shadow-card p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Renewal Details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Retiree</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $renewal->retiree_name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Email</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">{{ $renewal->retiree_email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">SRRV Card</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $renewal->srrv_card_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Linked File</dt>
                    <dd class="text-xs mt-0.5">
                        @if($renewal->application)
                            <a href="{{ route('srrv.applications.edit', $renewal->application) }}"
                               class="font-bold text-primary hover:underline">{{ $renewal->application->reference }}</a>
                        @else
                            <span class="text-dark/70">Standalone renewal</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">ID &amp; Photocopy</dt>
                    <dd class="text-xs font-semibold mt-0.5 {{ $renewal->id_and_photocopy_received ? 'text-emerald-700' : 'text-dark/40' }}">
                        {{ $renewal->id_and_photocopy_received ? 'Received' : 'Outstanding' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Online Form</dt>
                    <dd class="text-xs font-semibold mt-0.5 {{ $renewal->form_filled_online ? 'text-emerald-700' : 'text-dark/40' }}">
                        {{ $renewal->form_filled_online ? 'Filled in' : 'Outstanding' }}
                    </dd>
                </div>
            </dl>

            @if ($renewal->documents->isNotEmpty())
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40 mb-2">Filed Documents ({{ $renewal->documents->count() }})</dt>
                    <ul class="space-y-2">
                        @foreach ($renewal->documents as $document)
                            <li class="flex items-center justify-between gap-3 text-xs">
                                <span class="min-w-0">
                                    <span class="block font-bold text-dark truncate">{{ $document->original_name ?: basename($document->file_path) }}</span>
                                    <span class="block text-[10px] text-dark/45">{{ \App\Models\SrrvRenewalDocument::TYPE_LABELS[$document->document_type] ?? $document->document_type }}@if ($document->remarks): {{ $document->remarks }}@endif</span>
                                </span>
                                <a href="{{ route('srrv.renewals.documents.download', $document) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors shrink-0">
                                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                    <span>Download</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($renewal->remarks)
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40 mb-1">Remarks</dt>
                    <dd class="text-xs text-dark/70 leading-relaxed whitespace-pre-line">{{ $renewal->remarks }}</dd>
                </div>
            @endif
        </div>

        <!-- Fee -->
        <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Fee</h2>
            <ul class="space-y-2.5">
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Rate ({{ $renewal->visa_class }})</span>
                    <span class="font-semibold text-dark">
                        USD {{ number_format(\App\Models\SrrvRenewal::annualFee($renewal->visa_class, $renewal->dependents), 2) }} a year
                    </span>
                </li>
                @if ($renewal->dependents)
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Dependents</span>
                    <span class="font-semibold text-dark">{{ $renewal->dependents }}</span>
                </li>
                @endif
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Years paid</span>
                    <span class="font-semibold text-dark">{{ $renewal->years_paid }}</span>
                </li>
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">PRA fee (USD)</span>
                    <span class="font-semibold text-dark">USD {{ number_format((float) $renewal->fee_amount, 2) }}</span>
                </li>
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Service fee (PHP)</span>
                    <span class="font-semibold text-dark">&#8369;{{ number_format((float) $renewal->service_fee, 2) }}</span>
                </li>
                @if ((float) $renewal->discount_amount > 0)
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Discount{{ $renewal->discount_type === 'percent' ? ' ('.(float) $renewal->discount_value.'%)' : '' }}</span>
                    <span class="font-semibold text-dark">&minus; &#8369;{{ number_format((float) $renewal->discount_amount, 2) }}</span>
                </li>
                @endif
            </ul>
            <div class="mt-4 pt-4 border-t border-gray-100 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">PRA fee paid (USD)</span>
                    <span class="text-xs font-semibold text-emerald-700">USD {{ number_format((float) $renewal->fee_paid, 2) }} / {{ number_format((float) $renewal->fee_amount, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">PRA fee balance (USD)</span>
                    <span class="font-heading text-sm font-extrabold {{ $renewal->feeBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        USD {{ number_format($renewal->feeBalance(), 2) }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Service fee paid (PHP)</span>
                    <span class="text-xs font-semibold text-emerald-700">&#8369;{{ number_format((float) $renewal->amount_paid, 2) }} / {{ number_format($renewal->serviceDue(), 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Service fee balance (PHP)</span>
                    <span class="font-heading text-sm font-extrabold {{ $renewal->serviceBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        &#8369;{{ number_format($renewal->serviceBalance(), 2) }}
                    </span>
                </div>

                {{-- The cashier takes each payment (Cashier > PRA-SRRV) once the admin has approved the fees and discount. --}}
                @if (! $renewal->isFullyPaid() && ! in_array($renewal->status, ['cancelled', 'released_to_client', 'closed'], true))
                    <p class="pt-3 mt-1 border-t border-gray-100 text-[11px] text-dark/50">
                        {{ $renewal->isCleared() ? 'The cashier takes the payment.' : 'The cashier takes the payment once the admin approves the fees.' }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- New ID ready: notify the client, then release the card in person -->
    @if($renewal->status === 'new_id_ready')
        @php
            $turnoverBody = "Mabuhay {$renewal->retiree_name}!\n\nYour renewed SRRV ID ({$renewal->reference}) is now ready for collection at the PRA office.\n\n\U0001f4cd New Office Address\nUnit 1, Astrofield Building\nMitchell Avenue, Balibago, Angeles City\nNearest landmark: Dennis Locksmith or NBH Hardware at the back of Red Street\n\nPlease bring a valid ID when you claim. Thank you for choosing AMEGA Travel and Tours!\n\nBest Regards,\n".(auth()->user()->name ?? 'AMEGA Travel and Tours');
        @endphp
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-6 mb-6">
            <div class="flex items-start gap-3 mb-4">
                <i data-lucide="package-check" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5"></i>
                <div>
                    <h2 class="font-heading text-sm font-extrabold text-emerald-900">New ID Ready</h2>
                    <p class="text-[11px] text-emerald-800 mt-0.5">
                        Notify the retiree that the new card is ready{{ $renewal->card_expiry_date ? ', valid until '.$renewal->card_expiry_date->format('M d, Y') : '' }}, then release it in person. Released in person, never posted.
                    </p>
                </div>
            </div>

            @include('partials._notify-client', [
                'noticeFor' => 'srrv-renewal-ready',
                'toName' => $renewal->retiree_name,
                'email' => $renewal->retiree_email,
                'canEmail' => \App\Services\ClientNotifier::canReceive($renewal->retiree_email),
                'meta' => $renewal->reference.' \u00b7 New ID Ready',
                'template' => [
                    'label' => 'the new ID (ready for collection)',
                    'body' => $turnoverBody,
                ],
                'emptyMessage' => null,
                'subject' => 'Your renewed SRRV ID is ready for collection ('.$renewal->reference.')',
                'action' => route('srrv.renewals.notify', $renewal),
                'hidden' => [],
                'standalone' => true,
            ])

            <form method="POST" action="{{ route('srrv.renewals.collect', $renewal) }}"
                  class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 pt-4 border-t border-emerald-200/70">
                @csrf
                <div class="sm:col-span-2">
                    <label for="collected_by_name" class="block text-[10px] font-bold uppercase tracking-wider text-emerald-800/70 mb-1">Released To *</label>
                    <input id="collected_by_name" type="text" name="collected_by_name" required
                           placeholder="Name of the person who received the new ID"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-emerald-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" @disabled($blocker) title="{{ $blocker ?: 'Record who received the new ID' }}"
                            class="w-full px-4 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-emerald-600">
                        Record Release
                    </button>
                </div>
            </form>
        </div>
    @elseif($renewal->status === 'released_to_client')
        <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 mb-6 flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-dark">Released to {{ $renewal->collected_by_name ?: 'the retiree' }}</p>
                <p class="text-[10px] text-dark/45 mt-0.5">{{ $renewal->collected_at?->format('M d, Y') }}</p>
            </div>
        </div>
    @elseif($renewal->status === 'closed')
        <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 mb-6 flex items-center gap-3">
            <i data-lucide="archive" class="w-5 h-5 text-dark/40 shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-dark">Closed</p>
                <p class="text-[10px] text-dark/45 mt-0.5">{{ $renewal->closed_at?->format('M d, Y') }}</p>
            </div>
        </div>
    @endif

@endsection
