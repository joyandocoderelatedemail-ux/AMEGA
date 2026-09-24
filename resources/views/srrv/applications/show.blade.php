@extends('layouts.srrv')

@section('title', $application->reference.' - AMEGA Travel and Tours')

@section('content')

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('srrv.applications.index') }}"
                   class="text-[11px] font-bold uppercase tracking-wider text-dark/40 hover:text-primary transition-colors">Retiree Files</a>
                <i data-lucide="chevron-right" class="w-3 h-3 text-dark/25"></i>
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ $application->reference }}</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">{{ $application->retiree_name }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="inline-flex px-2.5 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wider">
                    {{ str_replace('_', ' ', $application->service_type) }}
                </span>
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->isCourtesy() ? 'bg-amber-100 text-amber-800' : 'bg-primary/10 text-primary' }}">
                    {{ $application->visa_class }}
                </span>
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->status === 'released' ? 'bg-emerald-100 text-emerald-800' : ($application->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                    {{ str_replace('_', ' ', $application->status) }}
                </span>
                @if($application->srrv_card_number)
                    <span class="inline-flex px-2.5 py-1 rounded-lg bg-gray-100 text-dark/60 text-[10px] font-bold uppercase tracking-wider">
                        Card {{ $application->srrv_card_number }}
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(! $isFinal && $application->status !== 'cancelled')
                <form method="POST" action="{{ route('srrv.applications.advance', $application) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm">
                        <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                        <span>Advance to {{ str_replace('_', ' ', $nextStage) }}</span>
                    </button>
                </form>
            @endif

            <a href="{{ route('srrv.applications.edit', $application) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                <i data-lucide="pencil-line" class="w-4 h-4"></i>
                <span>Edit</span>
            </a>

            @if($application->status !== 'cancelled')
                <form method="POST" action="{{ route('srrv.applications.cancel', $application) }}" class="m-0"
                      onsubmit="return confirm('Cancel this retiree file?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-rose-600 font-bold text-xs rounded-xl hover:border-rose-300 transition-all">
                        <i data-lucide="ban" class="w-4 h-4"></i>
                        <span>Cancel</span>
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('srrv.applications.destroy', $application) }}" class="m-0"
                  onsubmit="return confirm('Delete this file and every document filed under it? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" title="Delete file"
                        class="p-2.5 bg-white border border-gray-200 text-rose-600 rounded-xl hover:border-rose-300 transition-all">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Pipeline -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6 mb-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Pipeline</h2>
        <div class="flex flex-wrap items-center gap-2">
            @foreach($stages as $index => $stage)
                @php
                    $done = $index < $currentIndex;
                    $current = $index === $currentIndex;
                @endphp
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider border
                        {{ $current ? 'bg-primary text-white border-primary shadow-sm' : ($done ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-50 text-dark/40 border-gray-200') }}">
                        @if($done)
                            <i data-lucide="check" class="w-3 h-3"></i>
                        @elseif($current)
                            <i data-lucide="circle-dot" class="w-3 h-3"></i>
                        @endif
                        <span>{{ str_replace('_', ' ', $stage) }}</span>
                    </span>
                    @if(! $loop->last)
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-dark/25"></i>
                    @endif
                </div>
            @endforeach
        </div>

        @if($application->status === 'cancelled')
            <p class="text-[11px] text-rose-600 font-semibold mt-3">This file was cancelled and is off the pipeline.</p>
        @endif

        <div class="flex flex-wrap gap-x-6 gap-y-2 mt-4 pt-4 border-t border-gray-100">
            @foreach([
                'Email Sent' => $application->email_sent_at,
                'Lodged with PRA' => $application->lodged_at,
                'Paid in Full' => $application->payment_in_full_at,
                'Oath Taken' => $application->oath_at,
                'Released' => $application->released_at,
            ] as $label => $stamp)
                <div class="text-[11px]">
                    <span class="font-bold uppercase tracking-wider text-dark/40">{{ $label }}</span>
                    <span class="ml-2 font-semibold {{ $stamp ? 'text-dark' : 'text-dark/30' }}">
                        {{ $stamp?->format('M d, Y') ?? '—' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Missing proof warning -->
    @if(! $application->hasAllProofs() && $application->status !== 'cancelled')
        <div class="mb-6 rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" role="alert">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
            <div>
                <p class="text-xs font-bold text-amber-900">Class paperwork is incomplete.</p>
                <p class="text-[11px] text-amber-800 mt-0.5">
                    {{ $application->isCourtesy()
                        ? 'Courtesy needs proof of military service on file.'
                        : 'Classic needs police clearance and proof of pension on file.' }}
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

        <!-- Details -->
        <div class="lg:col-span-2 rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">File Details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Retiree</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->retiree_name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Contact</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">
                        {{ $application->retiree_email ?: '—' }}
                        @if($application->retiree_phone)
                            <span class="text-dark/30">&middot;</span> {{ $application->retiree_phone }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Date of Birth</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->date_of_birth?->format('M d, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Nationality</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->nationality ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Investment Amount</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ number_format((float) $application->investment_amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Copies Submitted</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->copies_submitted }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Opened</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">{{ $application->created_at?->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Opened By</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">{{ $application->creator?->name ?? '—' }}</dd>
                </div>
            </dl>

            <!-- Proofs -->
            <div class="mt-5 pt-5 border-t border-gray-100">
                <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40 mb-2">Class Paperwork</dt>
                <div class="flex flex-wrap gap-2">
                    @if($application->isCourtesy())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->military_service_proof_received ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/40' }}">
                            <i data-lucide="{{ $application->military_service_proof_received ? 'check' : 'x' }}" class="w-3 h-3"></i>
                            Military Service Proof
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->police_clearance_received ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/40' }}">
                            <i data-lucide="{{ $application->police_clearance_received ? 'check' : 'x' }}" class="w-3 h-3"></i>
                            Police Clearance
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->pension_proof_received ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/40' }}">
                            <i data-lucide="{{ $application->pension_proof_received ? 'check' : 'x' }}" class="w-3 h-3"></i>
                            Pension Proof
                        </span>
                    @endif
                </div>
            </div>

            @if($application->remarks)
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40 mb-1">Remarks</dt>
                    <dd class="text-xs text-dark/70 leading-relaxed whitespace-pre-line">{{ $application->remarks }}</dd>
                </div>
            @endif
        </div>

        <!-- Fees -->
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Fees</h2>
            <ul class="space-y-2.5">
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Service fee</span>
                    <span class="font-semibold text-dark">{{ number_format((float) $application->service_fee, 2) }}</span>
                </li>
            </ul>
            <div class="mt-4 pt-4 border-t border-gray-100 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Paid</span>
                    <span class="text-xs font-semibold text-emerald-700">{{ number_format((float) $application->amount_paid, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Balance</span>
                    <span class="font-heading text-sm font-extrabold {{ $application->outstandingBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ number_format($application->outstandingBalance(), 2) }}
                    </span>
                </div>
                <div class="pt-1">
                    <span class="inline-flex px-2.5 py-1 rounded-lg bg-gray-100 text-dark/60 text-[10px] font-bold uppercase tracking-wider">
                        {{ $application->currency }} &middot; paid in full
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Renewals -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Annual Renewals</h2>
                <p class="text-[11px] text-dark/45 mt-0.5">Due once a year for as long as the retiree stays</p>
            </div>
            <a href="{{ route('srrv.renewals.create', ['application' => $application->id]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">
                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                <span>Record Renewal</span>
            </a>
        </div>

        @if($application->renewals->isEmpty())
            <div class="px-6 py-10 text-center">
                <i data-lucide="calendar-check" class="w-7 h-7 text-dark/20 mx-auto mb-2"></i>
                <p class="text-xs font-bold text-dark/50">No renewals recorded against this file.</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($application->renewals as $renewal)
                    <li class="px-6 py-3.5 flex items-center justify-between gap-4">
                        <div>
                            <a href="{{ route('srrv.renewals.show', $renewal) }}"
                               class="text-xs font-bold text-primary hover:underline">{{ $renewal->reference }}</a>
                            <p class="text-[10px] text-dark/45 mt-0.5">
                                {{ $renewal->years_paid }} year(s) &middot; USD {{ number_format((float) $renewal->fee_amount, 2) }}
                            </p>
                        </div>
                        <span class="inline-flex px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $renewal->status === 'collected' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ str_replace('_', ' ', $renewal->status) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Documents -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Document Checklist</h2>
                <p class="text-[11px] text-dark/45 mt-0.5">Held privately &mdash; never served from the public web root</p>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">{{ $application->documents->count() }} filed</span>
        </div>

        @if($application->documents->isEmpty())
            <div class="px-6 py-10 text-center">
                <i data-lucide="file-stack" class="w-7 h-7 text-dark/20 mx-auto mb-2"></i>
                <p class="text-xs font-bold text-dark/50">Nothing filed yet.</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($application->documents as $document)
                    <li class="px-6 py-3.5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-dark truncate">{{ $document->original_name ?: basename($document->file_path) }}</p>
                                <p class="text-[10px] text-dark/45">
                                    {{ str_replace('_', ' ', $document->document_type) }}
                                    @if($document->file_size)
                                        <span class="text-dark/30">&middot;</span> {{ number_format($document->file_size / 1024, 0) }} KB
                                    @endif
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('srrv.documents.download', $document) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors shrink-0">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                            <span>Download</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="px-6 py-5 border-t border-gray-100 bg-gray-50/60">
            <form method="POST" action="{{ route('srrv.applications.documents.store', $application) }}" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="document_type" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Document Type</label>
                        <select id="document_type" name="document_type"
                                class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                            @foreach($documentTypes as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="file" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">File</label>
                        <input id="file" type="file" name="file" required
                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="w-full px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-dark text-xs file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                            File Document
                        </button>
                    </div>
                </div>
                <p class="text-[10px] text-dark/45 mt-2">JPG, PNG, WebP or PDF up to 10 MB. Stored privately and released only through this desk.</p>
            </form>
        </div>
    </div>

@endsection
