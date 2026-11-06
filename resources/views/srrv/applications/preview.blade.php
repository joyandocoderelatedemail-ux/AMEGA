@extends('layouts.srrv')

@section('title', $application->reference.' - AMEGA Travel and Tours')

@php
    /**
     * The whole retiree file on one page, read-only: what the service list opens and what the admin reviews.
     * Laid out like the Visa Assistance preview. The admin approves or returns it here; the agent can go back,
     * edit (while it can still change), or start the next one.
     */
    $isAdmin = auth()->user()->isAdmin();
    $hold = \App\Support\CaseProgress::approvalHold($application->approval_status);
    $service = \App\Http\Controllers\Srrv\SrrvClientLookupController::serviceFor('application', $application->visa_class);
    $label = \App\Http\Controllers\Srrv\SrrvClientLookupController::SERVICES[$service]['label'];
    $canEdit = $isAdmin || $application->approval_status === null || $application->isReturned();
    $continueUrl = \App\Http\Controllers\Srrv\SrrvClientLookupController::continueUrl($application);
    // The retiree's client account (matched the way the file was saved), so New starts with them filled in.
    $client = \App\Models\User::where('role', 'client')
        ->where(fn ($q) => $q->when($application->retiree_email, fn ($w) => $w->where('email', strtolower((string) $application->retiree_email)))->orWhere('name', $application->retiree_name))
        ->first();
    $newUrl = $client
        ? \App\Http\Controllers\Srrv\SrrvClientLookupController::formUrl($service, $client->id)
        : route('srrv.start', $service);
    $card = 'rounded-2xl bg-white border border-gray-200 shadow-card p-6';
    $dt = 'text-[10px] font-bold uppercase tracking-wider text-dark/40';
    $dd = 'text-xs font-semibold text-dark mt-0.5';
    $money = fn ($amount) => $application->currency.' '.number_format((float) $amount, 2);
    $detail = fn (string $key) => $application->detail($key);
    $dateDetail = fn (string $key) => $application->dateDetail($key)?->format('M d, Y');
    $family = collect(\App\Support\SrrvForms::dependents($application))->map(function (string $name, string $who) use ($dateDetail) {
        $born = $dateDetail("{$who}_dob");

        return trim($name.($born ? ' · born '.$born : '').' ('.($who === 'spouse' ? 'spouse' : 'child').')');
    });
@endphp

@section('content')
<div class="space-y-6" x-data="{ mode: null }">

    <!-- Top bar: who, status, and what can be done -->
    <div class="rounded-xl bg-white border border-slate-200 border-l-4 border-l-amber-400 shadow-sm px-5 py-4 space-y-3" role="status">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 shrink-0 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center"><i data-lucide="eye" class="w-[18px] h-[18px]"></i></span>
                <div>
                    <p class="text-sm font-bold text-slate-900">{{ $application->retiree_name }} &middot; {{ $label }} &middot; <span class="font-mono">{{ $application->reference }}</span></p>
                    <p class="text-xs text-slate-500">
                        @if ($isAdmin)
                            The file as saved. Nothing here can be changed from this page{{ $continueUrl ? '; press Continue to carry on with it' : '' }}.
                        @elseif ($canEdit)
                            The whole file, as saved. Press Edit to change it.
                        @else
                            The whole file, as saved. It can't be changed while it is with the admin or once approved.
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $isAdmin ? route('admin.srrv-approvals.index') : route('srrv.services', $service) }}" class="px-3 h-9 inline-flex items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold">{{ $isAdmin ? 'Back to approvals' : 'Back to files' }}</a>
                @if ($isAdmin && $application->isAwaitingApproval())
                    <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="px-4 h-9 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Approve</button>
                    <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="px-4 h-9 inline-flex items-center rounded-lg border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 text-xs font-bold">Return</button>
                @endif
                @if ($continueUrl)
                    <a href="{{ $continueUrl }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">Continue</a>
                @endif
                @if (! $isAdmin && $canEdit)
                    <a href="{{ route('srrv.applications.edit', $application) }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">Edit</a>
                @endif
                @unless ($isAdmin)
                    <a href="{{ $newUrl }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">New {{ $label }}</a>
                @endunless
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</span>
            @include('srrv.applications._status', ['application' => $application])
            @if ($application->isAwaitingApproval())
                <span class="text-[11px] text-slate-400">Step: {{ $application->status_label }}</span>
            @endif
            @if ($hold === 'returned' && $application->review_note)
                <span class="text-xs font-semibold text-rose-600">{{ $application->review_note }}</span>
            @endif
        </div>

        @include('srrv.applications._lock', ['application' => $application])

        @if ($isAdmin && $application->isAwaitingApproval())
            <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route('admin.srrv-approvals.approve', ['application', $application->id]) }}" class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                @csrf
                <label class="flex items-center gap-2 text-xs font-semibold">
                    <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded">
                    I reviewed the details and they are correct.
                </label>
                <button type="submit" class="px-4 h-9 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Approve</button>
            </form>
            <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route('admin.srrv-approvals.reject', ['application', $application->id]) }}" class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
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
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Retiree Information</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div><dt class="{{ $dt }}">Retiree</dt><dd class="{{ $dd }}">{{ $application->retiree_name }}</dd></div>
                <div><dt class="{{ $dt }}">Contact</dt><dd class="text-xs text-dark/70 mt-0.5">{{ $application->retiree_email ?: '—' }}@if ($application->retiree_phone) <span class="text-dark/30">&middot;</span> {{ $application->retiree_phone }}@endif</dd></div>
                <div><dt class="{{ $dt }}">Service</dt><dd class="{{ $dd }}">{{ $application->service_label }}</dd></div>
                <div><dt class="{{ $dt }}">Option</dt><dd class="{{ $dd }}">{{ $application->optionLabel() }}</dd></div>
                <div><dt class="{{ $dt }}">Date of birth</dt><dd class="{{ $dd }}">{{ $application->date_of_birth?->format('M d, Y') ?? '—' }}@if ($application->age() !== null) <span class="font-normal text-dark/50">&middot; age {{ $application->age() }}</span>@endif</dd></div>
                <div><dt class="{{ $dt }}">Nationality</dt><dd class="{{ $dd }}">{{ $application->nationality ?: '—' }}</dd></div>
                <div><dt class="{{ $dt }}">Gender</dt><dd class="{{ $dd }}">{{ ['M' => 'Male', 'F' => 'Female'][$detail('gender')] ?? '—' }}</dd></div>
                <div><dt class="{{ $dt }}">SRRV card number</dt><dd class="{{ $dd }}">{{ $application->srrv_card_number ?: '—' }}</dd></div>
                <div><dt class="{{ $dt }}">Passport</dt><dd class="{{ $dd }}">{{ $detail('passport_no') ?: '—' }}@if ($dateDetail('passport_expires_on')) <span class="font-normal text-dark/50">&middot; valid until {{ $dateDetail('passport_expires_on') }}</span>@endif</dd></div>
                <div><dt class="{{ $dt }}">Dependents</dt><dd class="{{ $dd }}">{{ $family->isEmpty() ? 'None named' : $family->implode('; ') }}</dd></div>
                <div class="sm:col-span-2"><dt class="{{ $dt }}">Philippine address</dt><dd class="{{ $dd }}">{{ $detail('primary_address') ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="{{ $dt }}">Overseas address</dt><dd class="{{ $dd }}">{{ $detail('home_address') ?: '—' }}</dd></div>
                <div><dt class="{{ $dt }}">Opened</dt><dd class="text-xs text-dark/70 mt-0.5">{{ $application->created_at?->format('M d, Y') }}</dd></div>
                <div><dt class="{{ $dt }}">Opened by</dt><dd class="text-xs text-dark/70 mt-0.5">{{ $application->creator?->name ?? '—' }}</dd></div>
                <div>
                    <dt class="{{ $dt }}">Service agreement</dt>
                    <dd class="text-xs mt-0.5 {{ $application->agreement_signed_at ? 'font-semibold text-emerald-700' : 'text-dark/50' }}">{{ $application->agreement_signed_at ? 'Signed '.$application->agreement_signed_at->format('M d, Y') : 'Not signed yet' }}</dd>
                </div>
                <div>
                    <dt class="{{ $dt }}">Acknowledgment of Documents</dt>
                    <dd class="text-xs mt-0.5 {{ $application->acknowledgement_signed_at ? 'font-semibold text-emerald-700' : 'text-dark/50' }}">{{ $application->acknowledgement_signed_at ? 'Signed '.$application->acknowledgement_signed_at->format('M d, Y') : 'Not signed yet' }}</dd>
                </div>
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
                <div class="flex justify-between"><dt class="text-dark/60">Service fee</dt><dd class="font-semibold text-dark">{{ $money($application->service_fee) }}</dd></div>
                @if ((float) $application->investment_amount > 0)
                    <div class="flex justify-between"><dt class="text-dark/60">Investment amount</dt><dd class="font-semibold text-dark">{{ $money($application->investment_amount) }}</dd></div>
                @endif
                <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-bold text-dark">Total</dt><dd class="font-bold text-dark">{{ $money($application->service_fee) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark/60">Paid</dt><dd class="font-semibold text-emerald-700">{{ $money($application->amount_paid) }}</dd></div>
                <div class="flex justify-between"><dt class="text-dark/60">Balance</dt><dd class="font-semibold {{ $application->outstandingBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $money($application->outstandingBalance()) }}</dd></div>
            </dl>
            <p class="mt-4 text-[11px] text-dark/50">Once approved, the cashier takes the payment. The total is the service fee; the investment amount is the visa deposit.</p>
        </div>
    </div>

    <!-- Documents filed -->
    <div class="{{ $card }}">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Documents</h2>
        <p class="text-[11px] text-dark/50 mb-4">Held privately; downloaded only through the SRRV desk.</p>
        @forelse ($application->documents as $document)
            @if ($loop->first) <ul class="rounded-xl border border-gray-200 divide-y divide-gray-100"> @endif
                <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <span class="min-w-0">
                        <span class="block text-xs font-bold text-dark truncate">{{ \App\Models\SrrvApplicationDocument::TYPE_LABELS[$document->document_type] ?? $document->document_type }}</span>
                        <span class="block text-[11px] text-dark/50 truncate">{{ $document->original_name }} &middot; {{ $document->created_at?->format('M d, Y') }}</span>
                    </span>
                    <a href="{{ route('srrv.documents.download', $document) }}" class="shrink-0 text-[11px] font-bold text-primary hover:text-navy-700">Download</a>
                </li>
            @if ($loop->last) </ul> @endif
        @empty
            <p class="text-xs text-dark/50">No documents filed yet.</p>
        @endforelse
    </div>

    <!-- The PRA form details, read-only -->
    <style>form[data-preview] [data-save-controls] { display: none !important; }</style>
    <form data-preview onsubmit="return false" class="space-y-6"
          x-init="$nextTick(() => $el.querySelectorAll('input:not([type=hidden]), select, textarea').forEach(f => f.disabled = true))">
        @include('shared._form-details', [
            'sections' => \App\Support\SrrvForms::sections(false),
            'details' => $application->form_details ?? [],
            'title' => 'PRA Form Details',
            'intro' => 'The PRA form details as the agent saved them.',
            'open' => true,
        ])
    </form>
</div>
@endsection
