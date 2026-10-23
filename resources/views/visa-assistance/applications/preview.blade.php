@extends('layouts.visa-assistance')

@section('title', $application->reference.' - AMEGA Travel and Tours')

@php
    /**
     * The whole counter file on one page, read-only: what the service list opens and what the admin
     * reviews. The admin approves or returns it here; the agent can go back, edit (while it can still
     * change), open the working file, or start the next one.
     */
    $isAdmin = auth()->user()->isAdmin();
    $hold = \App\Support\CaseProgress::approvalHold($application->approval_status);
    $label = \App\Models\VisaApplication::SERVICE_LABELS[$application->service_type] ?? $application->service_type;
    $canEdit = $isAdmin || $application->approval_status === null || $application->isReturned();
    $newUrl = $application->service_type === 'visit_visa'
        ? route('visa.visit.new')
        : route('visa.applications.create', ['service_type' => $application->service_type]);
    $card = 'rounded-2xl bg-white border border-gray-200 shadow-card p-6';
    $dt = 'text-[10px] font-bold uppercase tracking-wider text-dark/40';
    $dd = 'text-xs font-semibold text-dark mt-0.5';
    $money = fn ($amount) => $application->currency.' '.number_format((float) $amount, 2);
    $stages = $application->stages();
    $done = $application->completedStages();
@endphp

@section('content')
<div class="space-y-6" x-data="{ mode: null }">

    <!-- Top bar: who, status, and what can be done -->
    <div class="rounded-2xl bg-slate-800 text-white px-5 py-4 space-y-3" role="status">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <i data-lucide="eye" class="w-5 h-5 shrink-0"></i>
                <div>
                    <p class="text-sm font-bold">{{ $application->client_name }} &middot; {{ $label }} &middot; <span class="font-mono">{{ $application->reference }}</span></p>
                    <p class="text-[11px] text-white/70">
                        @if ($isAdmin)
                            The file as saved. Nothing here can be changed from this page.
                        @elseif ($canEdit)
                            The whole file, as saved. Press Edit to change it.
                        @else
                            The whole file, as saved. It can't be changed while it is with the admin or once approved.
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $isAdmin ? route('admin.visa-approvals.index') : route('visa.services', $application->service_type) }}" class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">{{ $isAdmin ? 'Back to approvals' : 'Back to files' }}</a>
                <a href="{{ route('visa.applications.show', $application) }}" class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">Open file</a>
                @if ($isAdmin && $application->isAwaitingApproval())
                    <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">Approve</button>
                    <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Return</button>
                @endif
                @if (! $isAdmin && $canEdit)
                    <a href="{{ route('visa.applications.edit', $application) }}" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Edit</a>
                @endif
                @unless ($isAdmin)
                    <a href="{{ $newUrl }}" class="px-4 py-2 rounded-lg bg-accent text-dark hover:bg-accent-dark text-xs font-bold">New {{ $label }}</a>
                @endunless
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-white/70">Status</span>
            <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($hold ?? 'preparing') }}">
                {{ $hold ? \App\Support\CaseProgress::holdLabel($hold) : $application->status_label }}
            </span>
            @if ($hold === 'returned' && $application->review_note)
                <span class="text-xs font-semibold text-rose-200">{{ $application->review_note }}</span>
            @endif
        </div>

        @if ($isAdmin && $application->isAwaitingApproval())
            <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route('admin.visa-approvals.approve', ['file', $application->id]) }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
                @csrf
                <label class="flex items-center gap-2 text-xs font-semibold">
                    <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded">
                    I reviewed the details and they are correct.
                </label>
                <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">Approve</button>
            </form>
            <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route('admin.visa-approvals.reject', ['file', $application->id]) }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
                @csrf
                <label for="preview-note" class="sr-only">What to correct</label>
                <input id="preview-note" type="text" name="review_note" required maxlength="2000" placeholder="What does the agent need to correct?"
                       class="flex-1 min-w-[16rem] px-3 py-2 rounded-lg bg-white text-slate-900 text-xs placeholder:text-slate-400">
                <button type="submit" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Return to the agent</button>
            </form>
        @endif

        @if ($errors->any())
            <p class="text-xs font-semibold text-rose-200">{{ $errors->first() }}</p>
        @endif
    </div>

    <!-- Progress -->
    <div class="{{ $card }}">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Progress</h2>
        <ol class="flex flex-wrap gap-2">
            @foreach ($stages as $stage)
                @php $isDone = in_array($stage, $done, true); $isCurrent = $stage === $application->status; @endphp
                <li class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-bold {{ $isCurrent ? 'bg-amber-50 text-amber-800 border-amber-300' : ($isDone ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-gray-50 text-dark/40 border-gray-200') }}">
                    @if ($isDone) <i data-lucide="check" class="w-3 h-3"></i> @endif
                    {{ $application->stageLabel($stage) }}
                </li>
            @endforeach
        </ol>
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5">
            @foreach ([
                'Agreement signed' => $application->agreement_signed_at,
                'Acknowledgment signed' => $application->acknowledgement_signed_at,
                'Drafted' => $application->drafted_at,
                'Lodged' => $application->lodged_at,
                'Result on' => $application->result_at,
            ] as $name => $when)
                @if ($when)
                    <div><dt class="{{ $dt }}">{{ $name }}</dt><dd class="{{ $dd }}">{{ $when->format('M d, Y') }}</dd></div>
                @endif
            @endforeach
            @if ($application->result)
                <div><dt class="{{ $dt }}">Result</dt><dd class="{{ $dd }} capitalize">{{ $application->result }}</dd></div>
            @endif
            @if ($application->embassy_reference)
                <div><dt class="{{ $dt }}">Embassy reference</dt><dd class="{{ $dd }}">{{ $application->embassy_reference }}</dd></div>
            @endif
        </dl>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- File details -->
        <div class="lg:col-span-2 {{ $card }}">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">File Details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div><dt class="{{ $dt }}">Client</dt><dd class="{{ $dd }}">{{ $application->client_name }}</dd></div>
                <div><dt class="{{ $dt }}">Contact</dt><dd class="text-xs text-dark/70 mt-0.5">{{ $application->client_email ?: '—' }}@if ($application->client_phone) <span class="text-dark/30">&middot;</span> {{ $application->client_phone }} @endif</dd></div>
                <div><dt class="{{ $dt }}">Service</dt><dd class="{{ $dd }}">{{ $label }}</dd></div>
                @if ($application->service_type === 'visit_visa')
                    <div><dt class="{{ $dt }}">Destination</dt><dd class="{{ $dd }}">{{ $application->destination_country ?: '—' }}</dd></div>
                    <div><dt class="{{ $dt }}">Purpose</dt><dd class="{{ $dd }}">{{ ucfirst((string) $application->purpose) ?: '—' }}</dd></div>
                    <div><dt class="{{ $dt }}">Processing</dt><dd class="{{ $dd }} capitalize">{{ $application->processing_speed }}</dd></div>
                    <div>
                        <dt class="{{ $dt }}">Insurance</dt>
                        <dd class="{{ $dd }}">
                            @if ($application->insurance_declined) Declined by client
                            @elseif ($application->insurance_policy_number) {{ $application->insurance_provider }} &middot; {{ $application->insurance_policy_number }}
                            @else {{ $application->insurance_included ? 'Taken through us' : 'Not recorded yet' }} @endif
                        </dd>
                    </div>
                    @if ($application->etravel_reference)
                        <div><dt class="{{ $dt }}">e-Travel reference</dt><dd class="{{ $dd }}">{{ $application->etravel_reference }}</dd></div>
                    @endif
                @endif
                @if ($application->service_type === 'e_visa')
                    <div><dt class="{{ $dt }}">Application type</dt><dd class="{{ $dd }} capitalize">{{ $application->applicant_type }}</dd></div>
                @endif
                @if ($application->service_type === 'passporting')
                    <div><dt class="{{ $dt }}">Passport</dt><dd class="{{ $dd }} capitalize">{{ $application->passport_type }} passport</dd></div>
                    <div><dt class="{{ $dt }}">Embassy</dt><dd class="{{ $dd }}">{{ $application->embassy_country ?: '—' }}</dd></div>
                @endif
                @if ($application->appointment_at)
                    <div><dt class="{{ $dt }}">Appointment</dt><dd class="{{ $dd }}">{{ $application->appointment_at->format('M d, Y g:i A') }}</dd></div>
                @endif
                <div><dt class="{{ $dt }}">Opened</dt><dd class="text-xs text-dark/70 mt-0.5">{{ $application->created_at?->format('M d, Y') }}</dd></div>
                <div><dt class="{{ $dt }}">Opened by</dt><dd class="text-xs text-dark/70 mt-0.5">{{ $application->creator?->name ?? '—' }}</dd></div>
            </dl>
            @if ($application->remarks)
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <p class="{{ $dt }} mb-1">Remarks</p>
                    <p class="text-xs text-dark/70 leading-relaxed whitespace-pre-line">{{ $application->remarks }}</p>
                </div>
            @endif
        </div>

        <!-- Fees -->
        <div class="{{ $card }}">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Fees</h2>
            <dl class="space-y-2 text-xs">
                @foreach (['Service fee' => $application->service_fee, 'Visa fee' => $application->visa_fee, 'Rush fee' => $application->rush_fee, 'Insurance' => $application->insurance_fee, 'e-Travel' => $application->etravel_fee] as $name => $amount)
                    @if ((float) $amount > 0)
                        <div class="flex justify-between"><dt class="text-dark/60">{{ $name }}</dt><dd class="font-semibold text-dark">{{ $money($amount) }}</dd></div>
                    @endif
                @endforeach
                <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-bold text-dark">Total</dt><dd class="font-bold text-dark">{{ $money($application->total_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark/60">Paid</dt><dd class="font-semibold text-emerald-700">{{ $money($application->amount_paid) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark/60">Balance</dt><dd class="font-semibold {{ $application->outstandingBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $money($application->outstandingBalance()) }}</dd></div>
            </dl>
        </div>
    </div>

    <!-- Applicants -->
    <div class="{{ $card }}">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Applicants <span class="text-dark/40 font-semibold">&middot; {{ $application->applicants->count() }}</span></h2>
        @if ($application->applicants->isEmpty())
            <p class="text-xs text-dark/50">No applicants added yet.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($application->applicants as $applicant)
                    <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs font-bold text-dark">{{ $applicant->full_name }}@if ($applicant->is_primary) <span class="ml-1 text-[10px] font-bold uppercase tracking-wider text-primary">Primary</span>@endif</span>
                        <span class="text-[11px] text-dark/50">
                            {{ collect([$applicant->nationality, $applicant->passport_number ? 'Passport '.$applicant->passport_number : null])->filter()->implode(' · ') ?: 'No passport details yet' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Documents -->
    <div class="{{ $card }}">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Documents <span class="text-dark/40 font-semibold">&middot; {{ $application->documents->count() }}</span></h2>
        @if ($application->documents->isEmpty())
            <p class="text-xs text-dark/50">No documents filed yet.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($application->documents as $document)
                    <li class="py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span class="font-semibold text-dark">{{ str_replace('_', ' ', ucfirst((string) ($document->checklist_item ?: $document->document_type))) }}</span>
                        <span class="text-dark/50">{{ $document->original_name }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
