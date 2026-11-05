@php
    /**
     * The current stage's task: what to do, what to record, and what still
     * stands between the file and the next stage. Advance is refused until
     * $blocker is null.
     */
    $field = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1';
    $button = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all';
    $secondary = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all';
    $status = $application->status;
@endphp

@if ($status !== 'cancelled')
<div id="current-step" class="scroll-mt-32 {{ ! empty($embedded) ? 'p-6 border-t border-gray-100' : 'rounded-2xl bg-white border shadow-sm p-6 mb-6 '.($blocker ? 'border-amber-200' : 'border-emerald-200') }}">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Current step</p>
            <h2 class="font-heading text-base font-extrabold text-dark mt-0.5">{{ $application->status_label }}</h2>
        </div>
        @if ($isFinal)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> File complete
            </span>
        @elseif ($blocker)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold">
                <i data-lucide="clock" class="w-3.5 h-3.5"></i> To do before advancing
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                <i data-lucide="check" class="w-3.5 h-3.5"></i> Done &mdash; ready to advance
            </span>
        @endif
    </div>

    @switch ($status)
        @case ('pending')
            @if ($application->usesSteps())
                <p class="text-xs text-dark/70">
                    Destination <strong>{{ $application->destination_country ?: 'not set' }}</strong>: the checklist and the application form follow the country.
                    Estimated price <strong>{{ $application->currency }} {{ number_format((float) $application->total_amount, 2) }}</strong>
                    Add every applicant on the file in <strong>Applicants</strong> below.
                </p>
                @if ($playbook = \App\Support\VisaPlaybook::for($application->destination_country))
                    <p class="mt-2 text-xs text-dark/70"><span class="font-bold text-dark">Portal:</span> {{ $playbook['portal'] }}@foreach ($playbook['fees'] as $fee) &middot; {{ $fee }}@endforeach</p>
                @endif

                {{-- Step 1: the draft form (the country application form) of each applicant; it prints once the admin approves. --}}
                @if ($draftForm = \App\Support\VisaWorksheets::forFile($application))
                    @php $draftPrintable = $application->printBlockerFor(auth()->user()) === null; @endphp
                    <div class="mt-4 rounded-xl border border-gray-100 p-4" data-draft-forms>
                        <p class="{{ $label }}">Draft form: {{ \App\Support\VisaWorksheets::TITLES[$draftForm] }}</p>
                        <ul class="mt-2 divide-y divide-gray-100">
                            @foreach ($application->applicants as $applicant)
                                @php $draftDone = filled($applicant->form_details); @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <span class="flex items-center gap-2 text-xs font-semibold text-dark">
                                        <i data-lucide="{{ $draftDone ? 'check-circle-2' : 'circle-dashed' }}" class="w-4 h-4 {{ $draftDone ? 'text-emerald-600' : 'text-amber-600' }}"></i>
                                        {{ $applicant->full_name }}
                                        <span class="font-normal {{ $draftDone ? 'text-emerald-700' : 'text-amber-700' }}">{{ $draftDone ? 'Filled in' : 'Not filled in yet' }}</span>
                                    </span>
                                    <span class="flex flex-wrap items-center gap-2">
                                        @if ($draftPrintable)
                                            <a href="{{ route('visa.worksheets.print', [$application, $applicant]) }}" target="_blank" rel="noopener" class="{{ $button }}">
                                                <i data-lucide="printer" class="w-4 h-4"></i> Print draft form
                                            </a>
                                        @else
                                            <span class="{{ $secondary }} opacity-60 cursor-not-allowed" aria-disabled="true" title="The admin has not approved the file yet.">
                                                <i data-lucide="lock" class="w-4 h-4"></i> Print after the admin approves
                                            </span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @else
                <p class="text-xs text-dark/70">Add every applicant travelling on this file in <strong>Applicants</strong> below. Their documents are checked at the next step.</p>
            @endif
            @break

        @case ('requirements')
            @if ($application->usesSteps() && $application->checklist())
                @include('visa-assistance.applications._checklist')
                @include('visa-assistance.applications._acknowledgment-step')
                @break
            @endif
            <p class="text-xs text-dark/70 mb-3">{!! $application->isFeeFirst() ? 'There is no fixed list: file the documents this client needs in the <strong>Document Checklist</strong> above.' : 'Collect and file these documents in the <strong>Document Checklist</strong> below.' !!}</p>
            <ul class="space-y-1.5">
                @foreach ($application->requiredDocuments() as $required)
                    @foreach ($required['per_applicant'] ? $application->applicants : [null] as $applicant)
                        @php
                            $filed = $application->documents->contains(fn ($document) => $document->document_type === $required['type']
                                && (! $applicant || $document->visa_applicant_id === $applicant->id));
                        @endphp
                        <li class="flex items-center gap-2 text-xs {{ $filed ? 'text-emerald-700' : 'text-dark/70' }}">
                            <i data-lucide="{{ $filed ? 'check-circle-2' : 'circle' }}" class="w-4 h-4 {{ $filed ? 'text-emerald-500' : 'text-amber-500' }}"></i>
                            <span>{{ $required['label'] }}@if ($applicant) &mdash; {{ $applicant->full_name }}@endif</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider {{ $filed ? 'text-emerald-600' : 'text-amber-700' }}">{{ $filed ? 'Received' : 'Missing' }}</span>
                        </li>
                    @endforeach
                @endforeach
            </ul>
            @if ($application->usesSteps())
                @include('visa-assistance.applications._acknowledgment-step')
            @endif
            @break

        @case ('agreement')
            <p class="text-xs text-dark/70 mb-3">Print the {{ $application->isFeeFirst() ? 'service agreement' : 'booking agreement' }}, have the client read and sign it, then record it here.{{ $application->isFeeFirst() ? '' : ' The cashier takes the down payment.' }}</p>
            {{-- The booking agreement prints only once the admin approves the draft form. --}}
            @if ($application->printBlockerFor(auth()->user()) === null)
                <a href="{{ route('visa.applications.agreement', $application) }}" target="_blank" rel="noopener" class="{{ $button }} mb-3">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print Booking Agreement
                </a>
            @else
                <span class="{{ $secondary }} mb-3 opacity-60 cursor-not-allowed" aria-disabled="true" title="The admin has not approved the draft form yet.">
                    <i data-lucide="lock" class="w-4 h-4"></i> Print Booking Agreement (after the admin approves)
                </span>
            @endif
            @if ($application->agreement_signed_at)
                <p class="text-xs font-semibold text-emerald-700">Signed {{ $application->agreement_signed_at->format('M d, Y g:i A') }}.</p>
            @else
                <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="m-0">
                    @csrf
                    <button type="submit" class="{{ $button }}"><i data-lucide="pen-line" class="w-4 h-4"></i> Client signed the agreement</button>
                </form>
            @endif
            @if ($application->isFeeFirst())
                <div class="mt-4 pt-4 border-t border-gray-100">
                {{-- E-Visa Step 2: the fee, then Send to Admin; approved, the cashier takes the payment --}}
                @php
                    $held = $application->isAwaitingApproval();
                    $approved = $application->approval_status === \App\Models\VisaApplication::APPROVAL_APPROVED;
                    $feeLocked = $held || $approved;
                @endphp
                {{-- The total follows what is typed; Save fee stores it. --}}
                <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end"
                      x-data="{ service: {{ Js::from(old('service_fee', (float) $application->service_fee)) }}, visa: {{ Js::from(old('visa_fee', (float) $application->visa_fee)) }}, extra: {{ (float) $application->rush_fee + (float) $application->insurance_fee + (float) $application->etravel_fee }},
                              get total() { return (parseFloat(this.service) || 0) + (parseFloat(this.visa) || 0) + this.extra; } }">
                    @csrf
                    <div>
                        <label for="ev_service_fee" class="{{ $label }}">Service fee (PHP) *</label>
                        <input id="ev_service_fee" type="number" step="0.01" min="0" name="service_fee" required x-model="service" @disabled($feeLocked) value="{{ old('service_fee', (float) $application->service_fee) }}" class="{{ $field }}">
                        @error('service_fee') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="ev_visa_fee" class="{{ $label }}">Visa fee (PHP)</label>
                        <input id="ev_visa_fee" type="number" step="0.01" min="0" name="visa_fee" x-model="visa" @disabled($feeLocked) value="{{ old('visa_fee', (float) $application->visa_fee) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <p class="{{ $label }}">Total</p>
                        <p class="h-[38px] flex items-center text-sm font-bold text-dark">{{ $application->currency }}&nbsp;<span x-text="total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">{{ number_format((float) $application->total_amount, 2) }}</span></p>
                    </div>
                    @unless ($feeLocked)
                        <button type="submit" class="{{ $secondary }}"><i data-lucide="save" class="w-4 h-4"></i> Save fee</button>
                    @endunless
                </form>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    @if ($approved)
                        <p class="text-xs font-semibold text-emerald-700">
                            Approved by the admin. The cashier must record the payment (partial or full) before the file moves on: paid {{ number_format((float) $application->amount_paid, 2) }},
                            balance <strong class="{{ $application->outstandingBalance() > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($application->outstandingBalance(), 2) }}</strong>.
                        </p>
                    @elseif ($held)
                        <p class="text-xs font-semibold text-amber-800">Sent to the admin. Waiting for approval.</p>
                    @else
                        @if ($application->isReturned())
                            <p class="text-xs font-semibold text-rose-700 mb-2">The admin returned this file{{ $application->review_note ? ': '.$application->review_note : '.' }} Correct the fee and send it again.</p>
                        @endif
                        <form method="POST" action="{{ route('visa.applications.send-to-admin', $application) }}" class="m-0">
                            @csrf
                            <button type="submit" @disabled((float) $application->total_amount <= 0) class="{{ $button }} disabled:opacity-40 disabled:cursor-not-allowed">
                                <i data-lucide="send" class="w-4 h-4"></i> {{ $application->isReturned() ? 'Send to admin again' : 'Send to admin' }}
                            </button>
                        </form>
                        @if ((float) $application->total_amount <= 0)
                            <p class="mt-2 text-[11px] text-dark/50">Save the fee first.</p>
                        @endif
                    @endif
                </div>
                </div>
            @endif
            @if ($application->usesSteps() && ! $application->isFeeFirst())
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="{{ $label }}">Payment</p>
                    @if ((float) $application->amount_paid > 0)
                        <p class="text-xs font-semibold text-emerald-700">
                            Paid {{ $application->currency }} {{ number_format((float) $application->amount_paid, 2) }}
                            of {{ number_format((float) $application->total_amount, 2) }}.
                            @if ($application->outstandingBalance() > 0) The balance of {{ number_format($application->outstandingBalance(), 2) }} is collected at Submission. @endif
                        </p>
                    @else
                        <p class="text-xs text-dark/70">
                            Take a <strong>down payment</strong> or the full amount in <strong>Fees</strong>.
                            Billed {{ $application->currency }} {{ number_format((float) $application->total_amount, 2) }}; the rest is collected at Submission.
                        </p>
                    @endif
                </div>
            @endif
            @break

        @case ('insurance')
            <p class="text-xs text-dark/70 mb-3">Record the client's travel insurance, or that they declined it.</p>
            @if ($application->insurance_declined)
                <p class="text-xs font-semibold text-emerald-700 mb-3">Client declined travel insurance.</p>
            @elseif ($application->insurance_policy_number)
                <p class="text-xs font-semibold text-emerald-700 mb-3">{{ $application->insurance_provider }} &middot; Policy {{ $application->insurance_policy_number }}</p>
            @endif
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                @csrf
                <div>
                    <label for="insurance_provider" class="{{ $label }}">Insurance Provider</label>
                    <input id="insurance_provider" type="text" name="insurance_provider" value="{{ old('insurance_provider', $application->insurance_provider) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="insurance_policy_number" class="{{ $label }}">Policy Number</label>
                    <input id="insurance_policy_number" type="text" name="insurance_policy_number" value="{{ old('insurance_policy_number', $application->insurance_policy_number) }}" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $button }}">Save insurance</button>
            </form>
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="mt-3">
                @csrf
                <input type="hidden" name="insurance_declined" value="1">
                <button type="submit" class="{{ $secondary }}">Client declined insurance</button>
            </form>
            @break

        @case ('etravel')
            <p class="text-xs text-dark/70 mb-3">Register the client on e-Travel and enter the reference.</p>
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="etravel_reference_stage" class="{{ $label }}">e-Travel Reference</label>
                    <input id="etravel_reference_stage" type="text" name="etravel_reference" required value="{{ old('etravel_reference', $application->etravel_reference) }}" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $button }}">Save reference</button>
            </form>
            @break

        @case ('payment')
            @if ($application->usesSteps())
                <p class="text-xs text-dark/70">
                    Take a <strong>down payment</strong> or the full amount to start the work; the file must be fully paid before it is submitted.
                    Billed {{ $application->currency }} {{ number_format((float) $application->total_amount, 2) }},
                    paid {{ number_format((float) $application->amount_paid, 2) }},
                    balance <strong class="{{ $application->outstandingBalance() > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($application->outstandingBalance(), 2) }}</strong>.
                    Record payments in <strong>Fees</strong>.
                </p>
                @break
            @endif
            <p class="text-xs text-dark/70">
                The file must be <strong>fully paid</strong> before it moves on.
                Billed {{ $application->currency }} {{ number_format((float) $application->total_amount, 2) }},
                paid {{ number_format((float) $application->amount_paid, 2) }},
                balance <strong class="{{ $application->outstandingBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ number_format($application->outstandingBalance(), 2) }}</strong>.
                Record payments in <strong>Fees</strong>.
            </p>
            @if ((float) $application->total_amount <= 0)
                <p class="text-xs text-amber-700 mt-2">Nothing is billed on this file yet &mdash; set the fees with <strong>Edit</strong>.</p>
            @endif
            @break

        @case ('acknowledged')
            <p class="text-xs text-dark/70 mb-3">Print the Acknowledgment of Documents, have the client sign it, then record it here.</p>
            <div class="flex flex-wrap gap-2">
                @if ($application->isCleared())
                <a href="{{ route('visa.applications.acknowledgement', $application) }}" target="_blank" class="{{ $secondary }}">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print acknowledgment
                </a>
                @endif
                @if ($application->acknowledgement_signed_at)
                    <span class="self-center text-xs font-semibold text-emerald-700">Signed {{ $application->acknowledgement_signed_at->format('M d, Y g:i A') }}.</span>
                @else
                    <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="m-0">
                        @csrf
                        <button type="submit" class="{{ $button }}"><i data-lucide="pen-line" class="w-4 h-4"></i> Client signed the acknowledgment</button>
                    </form>
                @endif
            </div>
            @break

        @case ('appointment')
            <p class="text-xs text-dark/70 mb-3">{{ match (true) {
                $application->service_type === 'citizenship' => 'File the application (eCRBA for a minor; the DS-11 with the fee paid at Pay.gov for an adult), request the appointment at the US Embassy, and enter its date.',
                $application->passport_type === 'foreign' => 'Record the embassy appointment or submission date, if there is one, then move on to the result.',
                default => 'Book the DFA appointment and enter the date.',
            } }}</p>
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div>
                    <label for="appointment_at_stage" class="{{ $label }}">{{ $application->service_type === 'citizenship' ? 'US Embassy Appointment' : 'DFA Appointment' }}</label>
                    <input id="appointment_at_stage" type="date" name="appointment_at" @required($application->passport_type !== 'foreign' || $application->service_type === 'citizenship') value="{{ old('appointment_at', $application->appointment_at?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $button }}">Save appointment</button>
            </form>
            @break

        @case ('drafting')
            @php $portal = config('visa_playbooks.'.$application->destination_country.'.portal'); @endphp
            @unless ($application->isEVisa())
            <p class="text-xs text-dark/70 mb-3">Enter the client's details on the official website{{ $portal ? ' ('.$portal.')' : '' }}, then tick it below with the reference it gives.{{ $application->isEVisa() ? ' Either the reference or an uploaded copy of what was submitted is needed to move on.' : ' A copy of what was submitted can be kept on the file (optional).' }}</p>
            @endunless

            {{-- Step 3: the details are on the official website --}}
            <div class="rounded-xl border {{ ($application->encoded_at || $application->drafted_at) ? 'border-gray-100' : 'border-amber-200 bg-amber-50/40' }} p-4 mb-3">
                <p class="{{ $label }}">Official website</p>
                @if ($application->encoded_at)
                    <p class="text-xs font-semibold text-emerald-700">
                        Details encoded on official website {{ $application->encoded_at->format('M d, Y g:i A') }}@if ($encoder = \App\Models\User::find($application->encoded_by)) by {{ $encoder->name }}@endif.
                        @if ($application->portal_reference) Reference: <span class="font-mono">{{ $application->portal_reference }}</span>. @endif
                    </p>
                @elseif ($application->drafted_at)
                    <p class="text-xs font-semibold text-emerald-700">Forms drafted {{ $application->drafted_at->format('M d, Y g:i A') }}.</p>
                @else
                    <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end" x-data="{ done: false }">
                        @csrf
                        <input type="hidden" name="action" value="encoded">
                        <label class="sm:col-span-3 flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" x-model="done" class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                            <span class="text-xs font-semibold text-dark">Details encoded on official website</span>
                        </label>
                        <div class="sm:col-span-2">
                            <label for="portal_reference" class="{{ $label }}">Reference number{{ $application->isEVisa() ? '' : ' (optional)' }}</label>
                            <input id="portal_reference" type="text" name="portal_reference" maxlength="100" value="{{ old('portal_reference') }}" class="{{ $field }}">
                            @error('portal_reference') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <button type="submit" class="{{ $button }}" :disabled="! done" :class="! done && 'opacity-50 cursor-not-allowed'">Save</button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- A copy of the drafted forms, kept on the file. --}}
            @php $forms = $application->draftedForms(); @endphp
            <div class="rounded-xl border border-gray-100 p-4 mb-3"
                 x-data="{ names: [], dragging: false }">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                    <p class="{{ $label }}">Copy of the submitted application{{ $application->isEVisa() ? '' : ' (optional)' }}</p>
                    <p class="text-[10px] font-semibold {{ $forms->isEmpty() ? 'text-dark/40' : 'text-emerald-700' }}">
                        {{ $forms->isEmpty() ? 'None kept yet' : $forms->count().' '.Str::plural('copy', $forms->count()).' on file' }}
                    </p>
                </div>

                @if ($forms->isNotEmpty())
                    <ul class="mb-3 divide-y divide-gray-100 rounded-lg border border-gray-100 bg-white">
                        @foreach ($forms as $form)
                            <li class="flex items-center justify-between gap-3 px-3 py-2">
                                <a href="{{ route('visa.documents.download', ['document' => $form, 'inline' => 1]) }}" target="_blank" rel="noopener"
                                   class="flex items-center gap-2 min-w-0 text-xs font-semibold text-dark hover:text-primary" title="Open {{ $form->original_name }}">
                                    <i data-lucide="file-text" class="w-4 h-4 text-primary shrink-0"></i>
                                    <span class="truncate">{{ $form->original_name ?: basename($form->file_path) }}</span>
                                    <span class="text-[10px] font-normal text-dark/40 shrink-0">{{ $form->file_size ? number_format($form->file_size / 1024, 0).' KB' : '' }} &middot; {{ $form->created_at?->format('M d, g:i A') }}</span>
                                </a>
                                <form method="POST" action="{{ route('visa.documents.destroy', $form) }}" class="m-0 shrink-0"
                                      onsubmit="return confirm({{ Js::from('Remove '.$form->original_name.'?') }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-dark/40 hover:text-rose-600 hover:bg-rose-50" title="Remove this copy">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('visa.applications.forms.store', $application) }}" enctype="multipart/form-data" class="m-0">
                    @csrf
                    <label class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-lg border-2 border-dashed px-4 py-3 cursor-pointer transition-colors"
                           :class="dragging ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-primary'"
                           @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                           @drop.prevent="dragging = false; $refs.forms.files = $event.dataTransfer.files; names = [...$event.dataTransfer.files].map(f => f.name)">
                        <i data-lucide="upload" class="w-5 h-5 text-primary shrink-0"></i>
                        <span class="flex-1 min-w-0 text-xs text-dark/70">
                            <span x-show="!names.length"><strong class="text-dark">Choose the forms</strong> or drop them here &middot; PDF, Word or images, up to 10 MB each</span>
                            <span x-show="names.length" x-cloak class="font-semibold text-dark" x-text="names.join(', ')"></span>
                        </span>
                        <input type="file" name="forms[]" x-ref="forms" multiple required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="sr-only"
                               @change="names = [...$event.target.files].map(f => f.name)">
                        <button type="submit" class="{{ $button }} shrink-0" x-bind:disabled="!names.length" :class="!names.length && 'opacity-50 cursor-not-allowed'">
                            {{ $forms->isEmpty() ? 'Upload' : 'Upload another copy' }}
                        </button>
                    </label>
                    @error('forms') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    @error('forms.*') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </form>
            </div>

            @break

        @case ('result')
            <p class="text-xs text-dark/70 mb-3">Record how the embassy or portal answered. Save while it is still processing; once the result is in, release the file and the client is emailed that it is ready.</p>
            <form id="result-form" method="POST" action="{{ route('visa.applications.stage', $application) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                @csrf
                <div>
                    <label for="result" class="{{ $label }}">Result</label>
                    <select id="result" name="result" class="{{ $field }} font-bold">
                        <option value="">Waiting</option>
                        @foreach (\App\Models\VisaApplication::RESULTS as $result)
                            <option value="{{ $result }}" @selected(old('result', $application->result) === $result)>{{ ucfirst($result) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="result_at" class="{{ $label }}">Result Date</label>
                    <input id="result_at" type="date" name="result_at" value="{{ old('result_at', $application->result_at?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="result_reference" class="{{ $label }}">Visa Number</label>
                    <input id="result_reference" type="text" name="result_reference" value="{{ old('result_reference', $application->result_reference) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="result_validity" class="{{ $label }}">Validity</label>
                    <input id="result_validity" type="text" name="result_validity" value="{{ old('result_validity', $application->result_validity) }}" placeholder="e.g. 90 days, multiple entry" class="{{ $field }}">
                </div>
            </form>
            @if ($application->usesSteps() && in_array($application->result, \App\Models\VisaApplication::RESULTS, true))
                <div class="mt-4 pt-4 border-t border-gray-100">
                    @php $canEmail = \App\Services\ClientNotifier::canReceive($application->client_email); $resultWord = $application->result; @endphp
                    <form method="POST" action="{{ route('visa.applications.notify-approved', $application) }}" class="m-0 mt-3">
                        @csrf
                        <button type="submit" @disabled(! $canEmail) class="{{ $secondary }} disabled:opacity-50 disabled:cursor-not-allowed"
                                title="{{ $canEmail ? 'Email '.$application->client_email.' that the file was '.$resultWord : 'No client email address on file' }}">
                            <i data-lucide="mail" class="w-4 h-4"></i> Email the client: {{ $application->serviceLabel ?? \App\Models\VisaApplication::SERVICE_LABELS[$application->service_type] }} {{ $resultWord }}
                        </button>
                    </form>
                </div>
            @endif
            @break

        @case ('lodged')
            @if ($application->usesSteps())
                <p class="text-xs text-dark/70 mb-3">The cashier collects the balance first: the file must be fully paid before submission. Then record the submission, the appointment schedule and the biometrics date{{ $application->needsAppearance() ? '' : ' (none needed for '.$application->destination_country.')' }}, and how long it should take.</p>
                <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label for="lodged_at" class="{{ $label }}">Submitted On *</label>
                            <input id="lodged_at" type="date" name="lodged_at" required value="{{ old('lodged_at', $application->lodged_at?->format('Y-m-d')) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="embassy_reference" class="{{ $label }}">Reference</label>
                            <input id="embassy_reference" type="text" name="embassy_reference" value="{{ old('embassy_reference', $application->embassy_reference) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="processing_days" class="{{ $label }}">Expected Duration (days)</label>
                            <input id="processing_days" type="number" min="1" max="365" name="processing_days" value="{{ old('processing_days', $application->processing_days) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="appointment_at_stage" class="{{ $label }}">Appointment Schedule{{ $application->needsAppearance() ? ' *' : '' }}</label>
                            <input id="appointment_at_stage" type="date" name="appointment_at" @required($application->needsAppearance()) value="{{ old('appointment_at', $application->appointment_at?->format('Y-m-d')) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="biometrics_at" class="{{ $label }}">Biometrics Date{{ $application->needsAppearance() ? ' *' : '' }}</label>
                            <div class="flex items-center gap-3">
                                <input id="biometrics_at" type="date" name="biometrics_at" @required($application->needsAppearance()) value="{{ old('biometrics_at', $application->biometrics_at?->format('Y-m-d')) }}" class="{{ $field }}">
                                @if ($application->needsAppearance())
                                    <label for="notify_client" class="inline-flex items-center gap-2 shrink-0 text-xs font-semibold text-dark/70 cursor-pointer"
                                           title="{{ \App\Services\ClientNotifier::canReceive($application->client_email) ? 'Email '.$application->client_email.' the appointment and biometrics dates' : 'No client email address on file' }}">
                                        <input id="notify_client" type="checkbox" name="notify_client" value="1" @checked(old('notify_client')) @disabled(! \App\Services\ClientNotifier::canReceive($application->client_email))
                                               class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                                        Notify client
                                    </label>
                                @endif
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="{{ $button }}">Save submission</button>
                </form>
                @break
            @endif
            <p class="text-xs text-dark/70 mb-3">
                Record when the application was lodged{{ $application->usesSteps() ? ' with the embassy' : '' }},
                then the result when it comes back.
            </p>
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="lodged_at" class="{{ $label }}">Lodged On</label>
                        <input id="lodged_at" type="date" name="lodged_at" value="{{ old('lodged_at', $application->lodged_at?->format('Y-m-d')) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="embassy_reference" class="{{ $label }}">{{ $application->usesSteps() ? 'Embassy' : 'Application' }} Reference</label>
                        <input id="embassy_reference" type="text" name="embassy_reference" value="{{ old('embassy_reference', $application->embassy_reference) }}" class="{{ $field }}">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-3 border-t border-gray-100">
                    <div>
                        <label for="result" class="{{ $label }}">Result</label>
                        <select id="result" name="result" class="{{ $field }} font-bold">
                            <option value="">Waiting</option>
                            @foreach (\App\Models\VisaApplication::RESULTS as $result)
                                <option value="{{ $result }}" @selected(old('result', $application->result) === $result)>{{ ucfirst($result) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="result_at" class="{{ $label }}">Result Date</label>
                        <input id="result_at" type="date" name="result_at" value="{{ old('result_at', $application->result_at?->format('Y-m-d')) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="result_reference" class="{{ $label }}">{{ match ($application->service_type) { 'passporting' => 'Passport', 'citizenship' => 'CRBA / Passport', default => 'Visa' } }} Number</label>
                        <input id="result_reference" type="text" name="result_reference" value="{{ old('result_reference', $application->result_reference) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="result_validity" class="{{ $label }}">Validity</label>
                        <input id="result_validity" type="text" name="result_validity" value="{{ old('result_validity', $application->result_validity) }}" placeholder="e.g. 90 days, multiple entry" class="{{ $field }}">
                    </div>
                </div>
                <button type="submit" class="{{ $button }}">Save</button>
            </form>
            @break

        @case ('released')
            <p class="text-xs text-dark/70">
                @if ($application->result)
                    Result: <strong class="{{ $application->result === 'approved' ? 'text-emerald-700' : 'text-rose-600' }}">{{ ucfirst($application->result) }}</strong>
                    @if ($application->result_at) on {{ $application->result_at->format('M d, Y') }}@endif
                    @if ($application->result_reference) &middot; No. {{ $application->result_reference }}@endif
                    @if ($application->result_validity) &middot; {{ $application->result_validity }}@endif
                @else
                    This file is complete.
                @endif
            </p>
            @break
    @endswitch

    @if ($application->usesSteps() && ! $application->isFeeFirst())
        @php
            $playbookTasks = \App\Support\VisaPlaybook::tasks($application->destination_country, $status);
            $playbookNotes = \App\Support\VisaPlaybook::notes($application->destination_country, $status);
        @endphp
        @if ($playbookTasks !== [])
            @php
                $taskState = collect($playbookTasks)->map(fn ($taskLabel, $taskId) => [
                    'id' => $taskId,
                    'label' => $taskLabel,
                    'done' => $application->taskDone($taskId),
                    'url' => route('visa.applications.tasks', [$application, $taskId]),
                ])->values();
            @endphp
            {{-- The country's own steps for this stage: ticked in place, saved without a reload. --}}
            <div class="mt-4 pt-4 border-t border-gray-100" x-data="visaTasks({{ Js::from($taskState) }})">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <p class="{{ $label }}">{{ $application->destination_country }} steps</p>
                    <span class="text-[11px] font-bold" :class="done === tasks.length ? 'text-emerald-700' : 'text-dark/50'"
                          x-text="done === tasks.length ? 'All done' : done + ' of ' + tasks.length + ' done'"></span>
                </div>
                <ul class="rounded-xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    <template x-for="task in tasks" :key="task.id">
                        <li>
                            <label class="flex items-start gap-3 px-3.5 py-2.5 cursor-pointer select-none transition-colors"
                                   :class="task.done ? 'bg-emerald-50/60' : 'bg-white hover:bg-gray-50'">
                                <input type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer shrink-0"
                                       :checked="task.done" :disabled="task.saving" @change="toggle(task)">
                                <span class="text-xs leading-relaxed" :class="task.done ? 'text-emerald-800' : 'text-dark/80'" x-text="task.label"></span>
                            </label>
                        </li>
                    </template>
                </ul>
                <p x-show="error" x-cloak x-text="error" class="mt-2 text-[11px] font-semibold text-rose-600" role="alert"></p>
                <p x-show="done === tasks.length && reloading" x-cloak class="mt-2 text-[11px] font-semibold text-emerald-700">All steps ticked. Updating the step&hellip;</p>
            </div>
        @endif
        @foreach ($playbookNotes as $note)
            <p class="mt-3 text-[11px] text-dark/60 flex items-start gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i><span>{{ $note }}</span></p>
        @endforeach
    @endif

    {{-- The checklist table, or the country steps above, already show what is left. --}}
    @if ($blocker && ! $isFinal
        && ! ($application->isEVisa() && $status === 'drafting')
        && ! ($status === 'requirements' && $application->usesSteps() && $application->checklist())
        && ! ($application->usesSteps() && $application->openTasks($status) !== []))
        <p class="mt-4 pt-3 border-t border-gray-100 text-[11px] font-semibold text-amber-800 flex items-start gap-1.5">
            <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
            <span>{{ $blocker }}</span>
        </p>
    @endif

    {{-- Advance sits under the step's own work: finish it, then move the file on. --}}
    @if (! $isFinal && $nextStage)
        <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-end gap-3">
            @if ($blocker)
                <span class="text-[11px] text-dark/50">Finish this step to move on.</span>
            @endif
            @if ($status === 'result' && $application->usesSteps())
                {{-- Still processing: Save keeps the result as it stands; Release only once the result is in. --}}
                <button type="submit" form="result-form" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm">
                    <i data-lucide="save" class="w-4 h-4"></i> <span>Save</span>
                </button>
            @endif
            <form method="POST" action="{{ route('visa.applications.advance', $application) }}" class="m-0">
                @csrf
                <button type="submit" @disabled($blocker) title="{{ $blocker ?: 'Move the file to the next stage' }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-primary">
                    <span>{{ $status === 'result' && $application->usesSteps() ? ($application->result === 'denied' ? 'Finish' : 'Release file') : 'Advance to '.$application->stageLabel($nextStage) }}</span>
                    <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    @endif
</div>
@endif

<script>
    // A stage's country steps: each tick saves in the background. When the last one is
    // ticked the page refreshes once, so the banner and the Advance button catch up.
    function visaTasks(tasks) {
        return {
            tasks: tasks.map((task) => ({ ...task, saving: false })),
            error: '',
            reloading: false,
            get done() {
                return this.tasks.filter((task) => task.done).length;
            },
            async toggle(task) {
                const before = task.done;
                task.done = !before;
                task.saving = true;
                this.error = '';
                try {
                    const response = await fetch(task.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) throw new Error('save failed');
                    const result = await response.json();
                    task.done = result.done;
                    if (result.open === 0) {
                        this.reloading = true;
                        setTimeout(() => window.location.reload(), 600);
                    }
                } catch (e) {
                    task.done = before;
                    this.error = 'That tick could not be saved. Please try again.';
                } finally {
                    task.saving = false;
                }
            },
        };
    }
</script>
