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
            @unless ($application->isReleasedLocked())
            <a href="{{ route('visa.applications.edit', $application) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                <i data-lucide="pencil-line" class="w-4 h-4"></i>
                <span>Edit</span>
            </a>
            @endunless


            @if(! $application->finishedState())
                <form method="POST" action="{{ route('visa.applications.cancel', $application) }}" class="m-0"
                      onsubmit="return confirm('Cancel this transaction?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-rose-600 font-bold text-xs rounded-xl hover:border-rose-300 transition-all">
                        <i data-lucide="ban" class="w-4 h-4"></i>
                        <span>Cancel</span>
                    </button>
                </form>
            @endif

            @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('visa.applications.destroy', $application) }}" class="m-0"
                  onsubmit="return confirm('Delete this file and every document filed under it? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" title="Delete file"
                        class="p-2.5 bg-white border border-gray-200 text-rose-600 rounded-xl hover:border-rose-300 transition-all">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- The banner says what is next; the step panel's own button below does the advancing. --}}
    @include('visa-assistance._next-step', ['step' => $application->nextStep()])
    <div class="mb-6"></div>

    @include('visa-assistance.partials._approval-banner', ['file' => $application])

    @include('visa-assistance.applications._lock', ['application' => $application])

    @include('visa-assistance.applications._forms', ['application' => $application])

    <x-file-owner :file="$application" type="visa" class="mb-6" />

    <!-- Pipeline -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 mb-6">
        @php
            $totalStages = count($stages);
            $activeIndex = is_int($currentIndex) ? $currentIndex : 0;
        @endphp
        @if ($application->usesSteps())
            {{-- The one Non-Immigrant step list (Step 0 to Step 6), the same as the new-file wizard's: Step 0 was the inquiry. --}}
            @include('visa-assistance.visit-visa._stepper', ['current' => $activeIndex + 1, 'done' => $application->isReleasedVisit(), 'processSteps' => $application->stepTitles(), 'processLabel' => \App\Models\VisaApplication::SERVICE_LABELS[$application->service_type]])
        @else
        <!-- Same stepper as the ticketing wizard: one bar per stage, olive for the current one -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="min-w-0">
                <h2 class="text-[11px] font-bold uppercase tracking-wider text-dark/40">Pipeline</h2>
                <p class="text-xl font-heading font-black text-dark tracking-tight mt-1">
                    Step {{ $activeIndex + 1 }} - {{ $application->stageLabel($stages[$activeIndex] ?? $application->status) }}
                </p>
            </div>
            <div class="flex items-center gap-2 whitespace-nowrap">
                <span class="text-xs font-bold uppercase tracking-wider text-dark/40">Step</span>
                <span class="w-8 h-8 rounded-full bg-primary text-white font-heading font-extrabold text-sm flex items-center justify-center shadow-md shadow-primary/30">{{ $activeIndex + 1 }}</span>
                <span class="text-xs font-bold text-dark/40">of {{ $totalStages }}</span>
            </div>
        </div>
        <div class="grid gap-1.5" style="grid-template-columns: repeat({{ $totalStages }}, minmax(0, 1fr))">
            @foreach($stages as $index => $stage)
                <div class="flex flex-col gap-1.5" @if($index === $activeIndex) aria-current="step" @endif>
                    <div class="h-2 rounded-full transition-all duration-300 {{ $index === $activeIndex ? 'bg-accent' : ($index < $activeIndex ? 'bg-primary' : 'bg-gray-200') }}"></div>
                    <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block {{ $index === $activeIndex ? 'text-accent font-extrabold' : ($index < $activeIndex ? 'text-primary' : 'text-dark/40') }}">{{ $application->stageLabel($stage) }}</span>
                </div>
            @endforeach
        </div>
        @endif

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

    {{-- Non-Immigrant steps 2 to 5 show no applicants or file details; only the step panel (and Fees at Step 5). --}}
    @php $hideDetails = ($application->service_type === 'visit_visa' && in_array($application->status, ['agreement', 'requirements', 'drafting', 'lodged', 'result'], true)) || ($application->isFeeFirst() && in_array($application->status, ['agreement', 'requirements', 'drafting', 'appointment', 'result'], true)); @endphp
    {{-- E-Visa Step 3 shows Fees only while a balance is outstanding. --}}
    @php $eVisaOwes = $application->isFeeFirst() && $application->status === 'requirements' && $application->outstandingBalance() > 0; @endphp
    @unless ($hideDetails)
    <!-- Applicants -->
    <div id="applicants" class="scroll-mt-32 rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden mb-6"
         x-data="{ adding: {{ $errors->has('first_name') ? 'true' : 'false' }} }">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Applicants</h2>
                <p class="text-[11px] text-dark/45 mt-0.5">{{ $application->applicants->count() }} on this file</p>
            </div>
            <button type="button" @click="adding = !adding"
                    class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-primary hover:text-primary transition-all">
                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                <span x-text="adding ? 'Close' : 'Add applicant'">Add applicant</span>
            </button>
        </div>

        @if($application->applicants->isEmpty())
            <div class="px-6 py-10 text-center">
                <i data-lucide="users" class="w-7 h-7 text-dark/20 mx-auto mb-2"></i>
                <p class="text-xs font-bold text-dark/50">No applicants on this file yet.</p>
                <form method="POST" action="{{ route('visa.applicants.store-client', $application) }}" class="mt-4">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                        <span>Add {{ $application->client_name }} as applicant</span>
                    </button>
                </form>
                <p class="text-[11px] text-dark/45 mt-2">Filled from their client profile. Use Add Applicant for anyone else.</p>
            </div>
        @else
            @php
                // What the checklist still waits on, per person (a Non-Immigrant file on a country checklist).
                $missingByApplicant = collect($application->usesSteps() && $application->checklist() ? $application->checklistMissing() : [])
                    ->groupBy(fn (string $line) => \Illuminate\Support\Str::afterLast($line, ' — '));
                // AMEGA's application form for the destination, filled per applicant.
                $worksheet = \App\Support\VisaWorksheets::forFile($application);
            @endphp
            <ul class="p-4 sm:p-5 grid grid-cols-1 lg:grid-cols-2 gap-3">
                @foreach($application->applicants as $applicant)
                    @php
                        $initials = collect([$applicant->first_name, $applicant->last_name])->filter()->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
                        $expiry = $applicant->passport_expiry_date;
                        $expiryTone = match (true) {
                            $expiry === null => null,
                            $expiry->isPast() => ['Expired', 'bg-rose-50 text-rose-700 border-rose-200'],
                            $expiry->lt(now()->addMonths(6)) => ['Expires in '.max(1, (int) round(now()->diffInMonths($expiry))).' mo', 'bg-amber-50 text-amber-800 border-amber-200'],
                            default => null,
                        };
                        $missing = $missingByApplicant->get($applicant->full_name, collect())->count();
                    @endphp
                    <li class="relative rounded-xl border border-gray-100 bg-white p-4 hover:border-gray-300 transition-colors">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-sm flex items-center justify-center shrink-0">
                                {{ mb_strtoupper($initials ?: '?') }}
                            </span>
                            <div class="min-w-0 flex-1 pr-8">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="text-sm font-bold text-dark truncate">{{ $applicant->full_name }}</p>
                                    @if($applicant->is_primary)
                                        <span class="px-1.5 py-0.5 rounded-md bg-primary/10 text-primary text-[9px] font-extrabold uppercase tracking-wider">Primary</span>
                                    @endif
                                    @if($applicant->isMinor())
                                        <span class="px-1.5 py-0.5 rounded-md bg-gray-100 text-dark/60 text-[9px] font-extrabold uppercase tracking-wider">Minor</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-dark/50 mt-0.5">
                                    Applicant #{{ $applicant->applicant_number }}
                                    @if($applicant->nationality) &middot; {{ $applicant->nationality }}@endif
                                    @if($applicant->date_of_birth) &middot; Born {{ $applicant->date_of_birth->format('M d, Y') }}@endif
                                </p>
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-3 gap-2">
                            <div class="rounded-lg bg-gray-50 px-3 py-2 min-w-0">
                                <dt class="text-[9px] font-bold uppercase tracking-wider text-dark/40">Passport</dt>
                                <dd class="text-xs font-semibold text-dark font-mono truncate mt-0.5">{{ $applicant->passport_number ?: '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2 min-w-0">
                                <dt class="text-[9px] font-bold uppercase tracking-wider text-dark/40">Expiry</dt>
                                <dd class="text-xs font-semibold text-dark mt-0.5">{{ $expiry?->format('M d, Y') ?? '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2 min-w-0">
                                <dt class="text-[9px] font-bold uppercase tracking-wider text-dark/40">Documents</dt>
                                <dd class="text-xs font-semibold mt-0.5 {{ $application->checklist() && $application->usesSteps() ? ($missing === 0 ? 'text-emerald-700' : 'text-amber-700') : 'text-dark' }}">
                                    @if($application->usesSteps() && $application->checklist())
                                        {{ $missing === 0 ? 'All in' : $missing.' missing' }}
                                    @else
                                        {{ $applicant->documents->count() }} {{ \Illuminate\Support\Str::plural('file', $applicant->documents->count()) }}
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if($expiryTone)
                            <p class="mt-2 inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border text-[10px] font-bold {{ $expiryTone[1] }}">
                                <i data-lucide="alert-triangle" class="w-3 h-3"></i> Passport {{ mb_strtolower($expiryTone[0]) }}: most embassies need 6 months' validity
                            </p>
                        @endif

                        @if($worksheet)
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <a href="{{ route('visa.worksheets.edit', [$application, $applicant]) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-primary hover:border-primary transition-colors">
                                    <i data-lucide="file-pen-line" class="w-3.5 h-3.5"></i>
                                    {{ filled($applicant->form_details) ? 'Edit draft form' : 'Fill in draft form' }}
                                </a>
                                @if ($application->isCleared())
                                <a href="{{ route('visa.worksheets.print', [$application, $applicant]) }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-dark/70 hover:border-gray-300 transition-colors">
                                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                    Print draft form
                                </a>
                                @endif
                                @unless(filled($applicant->form_details))
                                    <span class="text-[10px] font-semibold text-amber-700">Not filled in yet</span>
                                @endunless
                            </div>
                        @endif

                        <form method="POST" action="{{ route('visa.applicants.destroy', [$application, $applicant]) }}"
                              class="absolute top-3 right-3 m-0"
                              onsubmit="return confirm({{ Js::from('Remove '.$applicant->full_name.' from this file?') }});">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 rounded-lg text-dark/30 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Remove {{ $applicant->full_name }}">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
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

    @endunless

    @if (! $hideDetails || $application->status === 'lodged' || $eVisaOwes)
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

        @php
            // A Non-Immigrant file shows its fees only where money is taken: Agreement & Payment (step 2) and Submission (step 5).
            $showFees = ! $application->usesSteps() || $application->isFeeFirst() || $application->status === 'lodged';
        @endphp
        <!-- Details -->
        @unless ($hideDetails)
        <div class="{{ $showFees ? 'lg:col-span-2' : 'lg:col-span-3' }} rounded-2xl bg-white border border-gray-200 shadow-card p-6">
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

                @if($application->usesSteps())
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

                @if($application->service_type === 'citizenship')
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Application</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5">{{ \App\Models\VisaApplication::CLAIM_TYPES[$application->purpose] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">US citizen parent</dt>
                        <dd class="text-xs font-semibold text-dark mt-0.5">{{ $application->us_citizen_parent ?: '—' }}</dd>
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
        @endunless

        <!-- Fees -->
        @if ($showFees)
        @php $paymentOpen = true; @endphp
        <div id="fees" class="scroll-mt-32 {{ $hideDetails ? 'lg:col-span-3' : '' }} rounded-2xl bg-white border border-gray-200 shadow-card p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">{{ $paymentOpen ? 'Fees' : 'Estimate' }}</h2>
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
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">{{ $paymentOpen ? 'Total' : 'Estimated total' }}</span>
                    <span class="font-heading text-sm font-extrabold text-dark">{{ $application->currency }} {{ number_format((float) $application->total_amount, 2) }}</span>
                </div>
                @if ($paymentOpen)
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

                {{-- The payments the cashier (or an admin) took --}}
                @if ($application->payments->isNotEmpty())
                    <ul class="pt-3 mt-1 border-t border-gray-100 space-y-1.5">
                        @foreach ($application->payments as $entry)
                            <li class="text-[11px] text-dark/60 flex flex-wrap justify-between gap-x-3">
                                <span><span class="font-bold text-dark">{{ $application->currency }} {{ number_format((float) $entry->amount, 2) }}</span> &middot; {{ $entry->methodLabel() }}{{ $entry->reference ? ' · OR '.$entry->reference : '' }}</span>
                                <span>{{ $entry->received_at?->format('M d, Y') }}{{ $entry->receivedBy ? ' · '.$entry->receivedBy->name : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- The cashier takes the payments once the admin approves the file; an admin may record one here. --}}
                @if($application->outstandingBalance() > 0 && $application->status !== 'cancelled' && $application->isCleared() && ! auth()->user()->isAdmin())
                    <p class="pt-3 mt-1 border-t border-gray-100 text-xs text-dark/70 flex items-start gap-2">
                        <i data-lucide="wallet" class="w-4 h-4 text-primary shrink-0"></i>
                        <span><strong class="text-dark">Waiting for the cashier.</strong> Send the client to the cashier with the reference {{ $application->reference }}; the payment shows here once it is recorded.</span>
                    </p>
                @elseif($application->outstandingBalance() > 0 && $application->status !== 'cancelled' && $application->isCleared())
                    @php
                        // Before anything is paid the counter takes a down payment (any amount, typed in)
                        // or the full amount; after it, the rest.
                        $balance = $application->outstandingBalance();
                        $offerDownPayment = (float) $application->amount_paid <= 0;
                    @endphp
                    <form method="POST" action="{{ route('visa.applications.payments', $application) }}" class="pt-3 mt-1 border-t border-gray-100 space-y-2"
                          x-data="{ mode: {{ Js::from($offerDownPayment ? 'down' : 'full') }}, amount: {{ Js::from(old('amount', $offerDownPayment ? '' : $balance)) }} }">
                        @csrf
                        <label for="amount" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50">Record a payment</label>
                        <div class="grid grid-cols-2 gap-2">
                            @if ($offerDownPayment)
                                <button type="button" @click="mode = 'down'; amount = ''; $nextTick(() => $refs.amount.focus())"
                                        :class="mode === 'down' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-dark/70'"
                                        class="px-3 py-2 rounded-xl bg-white border text-left transition-all">
                                    <span class="block text-[11px] font-bold">Down payment</span>
                                    <span class="block text-[10px]">Type the amount</span>
                                </button>
                            @endif
                            <button type="button" @click="mode = 'full'; amount = {{ Js::from($balance) }}"
                                    :class="mode === 'full' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-dark/70'"
                                    class="px-3 py-2 rounded-xl bg-white border text-left transition-all {{ $offerDownPayment ? '' : 'col-span-2' }}">
                                <span class="block text-[11px] font-bold">{{ $offerDownPayment ? 'Full payment' : 'Pay the balance' }}</span>
                                <span class="block text-[10px]">{{ number_format($balance, 2) }}</span>
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <input id="amount" type="number" step="0.01" min="0.01" max="{{ $application->outstandingBalance() }}" name="amount" required
                                   x-model="amount" x-ref="amount" placeholder="Amount"
                                   class="flex-1 min-w-0 px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            <button type="submit" class="px-3.5 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">Record</button>
                        </div>
                        @error('amount')
                            <p class="text-[11px] text-rose-600">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
                @endif
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- E-Visa Step 3: the Document Checklist first, then Collect Documents & Acknowledgment, in one card. --}}
    @php $eVisaDocs = $application->isFeeFirst() && $application->status === 'requirements' && ! $application->checklist(); @endphp
    @if ($eVisaDocs)
        <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden mb-6">
            @include('visa-assistance.applications._documents', ['embedded' => true])
            @include('visa-assistance.applications._stage', ['embedded' => true])
        </div>
    @else
        @include('visa-assistance.applications._stage')
    @endif

    @if ($application->service_type === 'visit_visa')
        @include('visa-assistance.applications._non-immigrant')
    @endif

    <!-- Documents. A Non-Immigrant file uploads them at Receiving Documents only, which has its own step. -->
    {{-- With a country checklist, scans are attached on the checklist rows instead. --}}
    @if (! $eVisaDocs && (! $application->usesSteps() || ($application->status === 'requirements' && ! $application->checklist())))
    @include('visa-assistance.applications._documents')
    @endif

@endsection
