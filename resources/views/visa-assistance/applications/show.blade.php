@extends('layouts.visa-assistance')

@section('title', $application->reference.' - AMEGA Travel and Tours')

@section('content')

    @php
        // Shared by the web middleware on a normal request, but guarded so the
        // view still renders if it is ever composed outside that stack.
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    @endphp

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('visa.applications.index') }}"
                   class="text-[11px] font-bold uppercase tracking-wider text-dark/40 hover:text-primary transition-colors">File Directory</a>
                <i data-lucide="chevron-right" class="w-3 h-3 text-dark/25"></i>
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ $application->reference }}</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">{{ $application->client_name }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="inline-flex px-2.5 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wider">
                    {{ str_replace('_', ' ', $application->service_type) }}
                </span>
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->status === 'released' ? 'bg-emerald-100 text-emerald-800' : ($application->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                    {{ $application->status_label }}
                </span>
                @if($application->isRush())
                    <span class="inline-flex px-2.5 py-1 rounded-lg bg-rose-100 text-rose-700 text-[10px] font-bold uppercase tracking-wider">Rush</span>
                @endif
                @if($application->service_type === 'visit_visa' && ! $application->needsAppearance())
                    <span class="inline-flex px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase tracking-wider">No appearance</span>
                @endif
                @if($application->service_type === 'e_visa' && $application->isGroupPackage())
                    <span class="inline-flex px-2.5 py-1 rounded-lg bg-accent/20 text-accent-deep text-[10px] font-bold uppercase tracking-wider">Group package</span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(! $isFinal && $application->status !== 'cancelled')
                <form method="POST" action="{{ route('visa.applications.advance', $application) }}" class="m-0">
                    @csrf
                    <button type="submit" @disabled($blocker) title="{{ $blocker ?: 'Move the file to the next stage' }}"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-primary">
                        <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                        <span>Advance to {{ $application->stageLabel($nextStage) }}</span>
                    </button>
                </form>
            @endif

            <a href="{{ route('visa.applications.edit', $application) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                <i data-lucide="pencil-line" class="w-4 h-4"></i>
                <span>Edit</span>
            </a>

            @if($application->status !== 'cancelled')
                <form method="POST" action="{{ route('visa.applications.cancel', $application) }}" class="m-0"
                      onsubmit="return confirm('Cancel this counter file?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-rose-600 font-bold text-xs rounded-xl hover:border-rose-300 transition-all">
                        <i data-lucide="ban" class="w-4 h-4"></i>
                        <span>Cancel</span>
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('visa.applications.destroy', $application) }}" class="m-0"
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

    <x-file-owner :file="$application" type="visa" class="mb-6" />

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
                        <span>{{ $application->stageLabel($stage) }}</span>
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

        <!-- Milestones -->
        @if($application->agreement_signed_at || $application->acknowledgement_signed_at || $application->appointment_at || $application->lodged_at)
            <div class="flex flex-wrap gap-x-6 gap-y-2 mt-4 pt-4 border-t border-gray-100">
                @if($application->appointment_at)
                    <div class="text-[11px]">
                        <span class="font-bold uppercase tracking-wider text-dark/40">DFA Appointment</span>
                        <span class="ml-2 font-semibold text-dark">{{ $application->appointment_at->format('M d, Y') }}</span>
                    </div>
                @endif
                @if($application->agreement_signed_at)
                    <div class="text-[11px]">
                        <span class="font-bold uppercase tracking-wider text-dark/40">Agreement Signed</span>
                        <span class="ml-2 font-semibold text-dark">{{ $application->agreement_signed_at->format('M d, Y') }}</span>
                    </div>
                @endif
                @if($application->acknowledgement_signed_at)
                    <div class="text-[11px]">
                        <span class="font-bold uppercase tracking-wider text-dark/40">Documents Acknowledged</span>
                        <span class="ml-2 font-semibold text-dark">{{ $application->acknowledgement_signed_at->format('M d, Y') }}</span>
                    </div>
                @endif
                @if($application->lodged_at)
                    <div class="text-[11px]">
                        <span class="font-bold uppercase tracking-wider text-dark/40">Lodged</span>
                        <span class="ml-2 font-semibold text-dark">{{ $application->lodged_at->format('M d, Y') }}@if($application->embassy_reference) &middot; {{ $application->embassy_reference }}@endif</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('visa-assistance.applications._stage')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

        <!-- Details -->
        <div class="lg:col-span-2 rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">File Details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Client</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->client_name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Contact</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">
                        {{ $application->client_email ?: '—' }}
                        @if($application->client_phone)
                            <span class="text-dark/30">&middot;</span> {{ $application->client_phone }}
                        @endif
                    </dd>
                </div>

                @if($application->service_type === 'visit_visa')
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Destination</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->destination_country ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Purpose</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5">{{ ucfirst((string) $application->purpose) ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Processing</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5 capitalize">{{ $application->processing_speed }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Insurance</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5">
                            @if($application->insurance_declined)
                                Declined by client
                            @elseif($application->insurance_policy_number)
                                {{ $application->insurance_provider }} &middot; {{ $application->insurance_policy_number }}
                            @else
                                {{ $application->insurance_included ? 'Taken through us' : 'Not recorded yet' }}
                            @endif
                        </dd>
                    </div>
                    @if($application->etravel_reference)
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">e-Travel Reference</dt>
                            <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->etravel_reference }}</dd>
                        </div>
                    @endif
                @endif

                @if($application->service_type === 'e_visa')
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Application Type</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5 capitalize">{{ $application->applicant_type }}</dd>
                    </div>
                @endif

                @if($application->service_type === 'passporting')
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Passport</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5 capitalize">{{ $application->passport_type }} passport</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Embassy</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->embassy_country ?: '—' }}</dd>
                    </div>
                @endif

                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Opened</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">{{ $application->created_at?->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Opened By</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">{{ $application->creator?->name ?? '—' }}</dd>
                </div>
            </dl>

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
                @if((float) $application->visa_fee > 0)
                    <li class="flex items-center justify-between text-xs">
                        <span class="text-dark/55">Visa fee / expenses</span>
                        <span class="font-semibold text-dark">{{ number_format((float) $application->visa_fee, 2) }}</span>
                    </li>
                @endif
                @if((float) $application->rush_fee > 0)
                    <li class="flex items-center justify-between text-xs">
                        <span class="text-dark/55">Rush surcharge</span>
                        <span class="font-semibold text-rose-600">{{ number_format((float) $application->rush_fee, 2) }}</span>
                    </li>
                @endif
                @if((float) $application->insurance_fee > 0)
                    <li class="flex items-center justify-between text-xs">
                        <span class="text-dark/55">Insurance</span>
                        <span class="font-semibold text-dark">{{ number_format((float) $application->insurance_fee, 2) }}</span>
                    </li>
                @endif
                @if((float) $application->etravel_fee > 0)
                    <li class="flex items-center justify-between text-xs">
                        <span class="text-dark/55">e-Travel</span>
                        <span class="font-semibold text-dark">{{ number_format((float) $application->etravel_fee, 2) }}</span>
                    </li>
                @endif
            </ul>

            <div class="mt-4 pt-4 border-t border-gray-100 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Total</span>
                    <span class="font-heading text-sm font-extrabold text-dark">{{ $application->currency }} {{ number_format((float) $application->total_amount, 2) }}</span>
                </div>
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
                    <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $application->isFullyPaid() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $application->isFullyPaid() ? 'Paid in full' : ((float) $application->amount_paid > 0 ? 'Deposit taken' : 'Unpaid') }}
                    </span>
                </div>

                @if($application->outstandingBalance() > 0 && $application->status !== 'cancelled')
                    <form method="POST" action="{{ route('visa.applications.payments', $application) }}" class="pt-3 mt-1 border-t border-gray-100 space-y-2">
                        @csrf
                        <label for="amount" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50">Record a payment</label>
                        <div class="flex gap-2">
                            <input id="amount" type="number" step="0.01" min="0.01" max="{{ $application->outstandingBalance() }}" name="amount" required
                                   value="{{ old('amount', $application->outstandingBalance()) }}"
                                   class="flex-1 min-w-0 px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            <button type="submit" class="px-3.5 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">Record</button>
                        </div>
                        @error('amount')
                            <p class="text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Applicants -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden mb-6"
         x-data="{ adding: {{ $errors->has('first_name') ? 'true' : 'false' }} }">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Applicants</h2>
                <p class="text-[11px] text-dark/45 mt-0.5">{{ $application->applicants->count() }} on this file</p>
            </div>
            <button type="button" @click="adding = !adding"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">
                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                <span x-text="adding ? 'Close' : 'Add Applicant'">Add Applicant</span>
            </button>
        </div>

        @if($application->applicants->isEmpty())
            <div class="px-6 py-10 text-center">
                <i data-lucide="users" class="w-7 h-7 text-dark/20 mx-auto mb-2"></i>
                <p class="text-xs font-bold text-dark/50">No applicants on this file yet.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-dark/50">
                            <th class="px-6 py-3">#</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Passport</th>
                            <th class="px-6 py-3">Expiry</th>
                            <th class="px-6 py-3">Docs</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($application->applicants as $applicant)
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-6 py-3 text-xs font-bold text-dark/40">{{ $applicant->applicant_number }}</td>
                                <td class="px-6 py-3">
                                    <div class="text-xs font-semibold text-dark">{{ $applicant->full_name }}</div>
                                    @if($applicant->is_primary)
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-primary">Primary</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-xs text-dark/60">{{ $applicant->passport_number ?: '—' }}</td>
                                <td class="px-6 py-3 text-xs text-dark/60">
                                    {{ $applicant->passport_expiry_date?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-6 py-3 text-xs text-dark/60">{{ $applicant->documents->count() }}</td>
                                <td class="px-6 py-3 text-right">
                                    <form method="POST"
                                          action="{{ route('visa.applicants.destroy', [$application, $applicant]) }}"
                                          class="m-0 inline-flex"
                                          onsubmit="return confirm('Remove this applicant?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" title="Remove applicant">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div x-show="adding" x-cloak class="px-6 py-5 border-t border-gray-100 bg-gray-50/60">
            <form method="POST" action="{{ route('visa.applicants.store', $application) }}">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div>
                        <label for="first_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">First Name *</label>
                        <input id="first_name" type="text" name="first_name" required value="{{ old('first_name') }}"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label for="middle_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Middle Name</label>
                        <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label for="last_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Last Name *</label>
                        <input id="last_name" type="text" name="last_name" required value="{{ old('last_name') }}"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label for="date_of_birth" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Date of Birth</label>
                        <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label for="nationality" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Nationality</label>
                        <input id="nationality" type="text" name="nationality" value="{{ old('nationality') }}"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label for="passport_number" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Passport No.</label>
                        <input id="passport_number" type="text" name="passport_number" value="{{ old('passport_number') }}"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label for="passport_expiry_date" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Passport Expiry</label>
                        <input id="passport_expiry_date" type="date" name="passport_expiry_date" value="{{ old('passport_expiry_date') }}"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                            Add Applicant
                        </button>
                    </div>
                </div>
            </form>
        </div>
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
                                    @if($document->applicant)
                                        <span class="text-dark/30">&middot;</span> {{ $document->applicant->full_name }}
                                    @endif
                                    @if($document->file_size)
                                        <span class="text-dark/30">&middot;</span> {{ number_format($document->file_size / 1024, 0) }} KB
                                    @endif
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('visa.documents.download', $document) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors shrink-0">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                            <span>Download</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="px-6 py-5 border-t border-gray-100 bg-gray-50/60">
            <form method="POST" action="{{ route('visa.documents.store', $application) }}" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
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
                        <label for="visa_applicant_id" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Applicant</label>
                        <select id="visa_applicant_id" name="visa_applicant_id"
                                class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Whole file</option>
                            @foreach($application->applicants as $applicant)
                                <option value="{{ $applicant->id }}">#{{ $applicant->applicant_number }} {{ $applicant->full_name }}</option>
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
                <p class="text-[10px] text-dark/45 mt-2">JPG, PNG, WebP or PDF up to 10 MB. Stored privately and released only through this counter.</p>
            </form>
        </div>
    </div>

@endsection
