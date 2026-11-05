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
    $newUrl = $application->usesSteps()
        ? route('visa.visit.new', $application->service_type === 'visit_visa' ? [] : ['service' => $application->service_type])
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
    <div class="rounded-xl bg-white border border-slate-200 border-l-4 border-l-amber-400 shadow-sm px-5 py-4 space-y-3" role="status">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 shrink-0 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center"><i data-lucide="eye" class="w-[18px] h-[18px]"></i></span>
                <div>
                    <p class="text-sm font-bold text-slate-900">{{ $application->client_name }} &middot; {{ $label }} &middot; <span class="font-mono">{{ $application->reference }}</span></p>
                    <p class="text-xs text-slate-500">
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
                <a href="{{ $isAdmin ? route('admin.visa-approvals.index') : route('visa.services', $application->service_type) }}" class="px-3 h-9 inline-flex items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold">{{ $isAdmin ? 'Back to approvals' : 'Back to files' }}</a>
                <a href="{{ route('visa.applications.show', $application) }}" class="px-3 h-9 inline-flex items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold">Open file</a>
                @if ($isAdmin && $application->isAwaitingApproval())
                    <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="px-4 h-9 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Approve</button>
                    <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="px-4 h-9 inline-flex items-center rounded-lg border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 text-xs font-bold">Return</button>
                @endif
                @if (! $isAdmin && $canEdit)
                    <a href="{{ route('visa.applications.edit', $application) }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">Edit</a>
                @endif
                @unless ($isAdmin)
                    <a href="{{ $newUrl }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">New {{ $label }}</a>
                @endunless
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</span>
            <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($hold ?? 'preparing') }}">
                {{ $hold ? \App\Support\CaseProgress::holdLabel($hold) : $application->status_label }}
            </span>
            @if ($hold === 'returned' && $application->review_note)
                <span class="text-xs font-semibold text-rose-600">{{ $application->review_note }}</span>
            @endif
        </div>

        @if ($isAdmin && $application->isAwaitingApproval())
            <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route('admin.visa-approvals.approve', ['file', $application->id]) }}" class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
                @csrf
                <label class="flex items-center gap-2 text-xs font-semibold">
                    <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded">
                    I reviewed the details and they are correct.
                </label>
                <button type="submit" class="px-4 h-9 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Approve</button>
            </form>
            <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route('admin.visa-approvals.reject', ['file', $application->id]) }}" class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
                @csrf
                <label for="preview-note" class="sr-only">What to correct</label>
                <input id="preview-note" type="text" name="review_note" required maxlength="2000" placeholder="What does the agent need to correct?"
                       class="flex-1 min-w-[16rem] px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-900 text-xs placeholder:text-slate-400">
                <button type="submit" class="px-4 h-9 inline-flex items-center rounded-lg border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 text-xs font-bold">Return to the agent</button>
            </form>
        @endif

        @if ($errors->any())
            <p class="text-xs font-semibold text-rose-600">{{ $errors->first() }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- File details -->
        <div class="lg:col-span-2 {{ $card }}">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Client Information</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div><dt class="{{ $dt }}">Client</dt><dd class="{{ $dd }}">{{ $application->client_name }}</dd></div>
                <div><dt class="{{ $dt }}">Contact</dt><dd class="text-xs text-dark/70 mt-0.5">{{ $application->client_email ?: '—' }}@if ($application->client_phone) <span class="text-dark/30">&middot;</span> {{ $application->client_phone }} @endif</dd></div>
                <div><dt class="{{ $dt }}">Service</dt><dd class="{{ $dd }}">{{ $label }}</dd></div>
                @if ($application->usesSteps())
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
                @if ($application->service_type === 'citizenship')
                    <div><dt class="{{ $dt }}">Application</dt><dd class="{{ $dd }}">{{ \App\Models\VisaApplication::CLAIM_TYPES[$application->purpose] ?? '—' }}</dd></div>
                    <div><dt class="{{ $dt }}">US citizen parent</dt><dd class="{{ $dd }}">{{ $application->us_citizen_parent ?: '—' }}</dd></div>
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
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Payment</h2>
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
            <p class="mt-4 text-[11px] text-dark/50">Once approved, the cashier takes the payment.</p>
        </div>
    </div>

    <!-- The draft form, read-only, one per applicant -->
    @php
        $worksheet = \App\Support\VisaWorksheets::forFile($application);
        $sections = $worksheet ? \App\Support\VisaWorksheets::sections($worksheet) : [];
        $formTitle = $worksheet ? \App\Support\VisaWorksheets::TITLES[$worksheet] : 'Draft form';
    @endphp
    <style>form[data-preview] [data-save-controls] { display: none !important; }</style>
    <form data-preview onsubmit="return false" class="space-y-6"
          x-init="$nextTick(() => $el.querySelectorAll('input:not([type=hidden]), select, textarea').forEach(f => f.disabled = true))">
        @forelse ($worksheet ? $application->applicants : [] as $applicant)
            @include('shared._form-details', [
                'sections' => $sections,
                'details' => $applicant->form_details ?? [],
                'title' => $formTitle.' · '.$applicant->full_name,
                'intro' => 'The draft form as the agent saved it.',
                'prefix' => 'form_'.$applicant->id,
                'open' => true,
            ])
        @empty
            <div class="{{ $card }}"><p class="text-xs text-dark/50">No draft form filled in yet.</p></div>
        @endforelse
    </form>
@endsection
