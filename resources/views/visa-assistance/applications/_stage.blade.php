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
<div id="current-step" class="scroll-mt-32 rounded-2xl bg-white border shadow-sm p-6 mb-6 {{ $blocker ? 'border-amber-200' : 'border-emerald-200' }}">
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
            @if ($application->service_type === 'visit_visa')
                <p class="text-xs text-dark/70">
                    Destination <strong>{{ $application->destination_country ?: 'not set' }}</strong>: the checklist and the application form follow the country.
                    Estimated price <strong>{{ $application->currency }} {{ number_format((float) $application->total_amount, 2) }}</strong>
                    Add every applicant on the file in <strong>Applicants</strong> below.
                </p>
                @if ($playbook = \App\Support\VisaPlaybook::for($application->destination_country))
                    <p class="mt-2 text-xs text-dark/70"><span class="font-bold text-dark">Portal:</span> {{ $playbook['portal'] }}@foreach ($playbook['fees'] as $fee) &middot; {{ $fee }}@endforeach</p>
                @endif
                @if ($application->checklist())
                    @if ($application->isCleared())
                    <a href="{{ route('visa.applications.checklist.print', $application) }}" target="_blank" rel="noopener" class="{{ $secondary }} mt-3">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print the {{ $application->checklist()['label'] }} checklist for the client
                    </a>
                    @endif
                @endif
            @else
                <p class="text-xs text-dark/70">Add every applicant travelling on this file in <strong>Applicants</strong> below. Their documents are checked at the next step.</p>
            @endif
            @break

        @case ('requirements')
            @if ($application->service_type === 'visit_visa' && $application->checklist())
                @include('visa-assistance.applications._checklist')
                @include('visa-assistance.applications._acknowledgment-step')
                @break
            @endif
            <p class="text-xs text-dark/70 mb-3">Collect and file these documents in the <strong>Document Checklist</strong> below.</p>
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
            @if ($application->service_type === 'visit_visa')
                @include('visa-assistance.applications._acknowledgment-step')
            @endif
            @break

        @case ('agreement')
            <p class="text-xs text-dark/70 mb-3">Have the client read and sign the {{ $application->service_type === 'e_visa' ? 'booking agreement' : 'visa assistance agreement' }}, then record it here.</p>
            @if ($application->isCleared())
            <a href="{{ route('visa.applications.agreement', $application) }}" target="_blank" rel="noopener" class="{{ $secondary }} mb-3">
                <i data-lucide="printer" class="w-4 h-4"></i> Print the service agreement (filled in)
            </a>
            @endif
            @if ($application->agreement_signed_at)
                <p class="text-xs font-semibold text-emerald-700">Signed {{ $application->agreement_signed_at->format('M d, Y g:i A') }}.</p>
            @else
                <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="m-0">
                    @csrf
                    <button type="submit" class="{{ $button }}"><i data-lucide="pen-line" class="w-4 h-4"></i> Client signed the agreement</button>
                </form>
            @endif
            @if ($application->service_type === 'visit_visa')
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
            @if ($application->service_type === 'visit_visa')
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
            <p class="text-xs text-dark/70 mb-3">Book the DFA appointment and enter the date.</p>
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div>
                    <label for="appointment_at_stage" class="{{ $label }}">DFA Appointment</label>
                    <input id="appointment_at_stage" type="date" name="appointment_at" required value="{{ old('appointment_at', $application->appointment_at?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $button }}">Save appointment</button>
            </form>
            @break

        @case ('drafting')
            <p class="text-xs text-dark/70 mb-3">Draft the forms for {{ $application->destination_country ?: 'the destination' }} (or the embassy's prescribed forms) and upload a copy here.</p>

            {{-- A copy of the drafted forms, kept on the file. --}}
            @php $forms = $application->draftedForms(); @endphp
            <div class="rounded-xl border {{ $forms->isEmpty() ? 'border-amber-200 bg-amber-50/40' : 'border-gray-100' }} p-4 mb-3"
                 x-data="{ names: [], dragging: false }">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                    <p class="{{ $label }}">Drafted forms</p>
                    <p class="text-[10px] font-semibold {{ $forms->isEmpty() ? 'text-amber-700' : 'text-emerald-700' }}">
                        {{ $forms->isEmpty() ? 'Upload a copy before recording the forms as drafted' : $forms->count().' '.Str::plural('copy', $forms->count()).' on file' }}
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

            <div class="rounded-xl border border-gray-100 p-4">
                <p class="{{ $label }}">Forms drafted</p>
                @if ($application->drafted_at)
                    <p class="text-xs font-semibold text-emerald-700">Drafted {{ $application->drafted_at->format('M d, Y g:i A') }}@if ($drafter = \App\Models\User::find($application->drafted_by)) by {{ $drafter->name }}@endif.</p>
                @else
                    <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="m-0 mt-2">
                        @csrf
                        <input type="hidden" name="action" value="drafted">
                        <button type="submit" class="{{ $button }}">Forms are drafted</button>
                    </form>
                @endif
            </div>
            @break

        @case ('result')
            <p class="text-xs text-dark/70 mb-3">Record how the embassy or portal answered. Then release the file: the client is emailed that their result is ready.</p>
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                @csrf
                <div>
                    <label for="result" class="{{ $label }}">Result</label>
                    <select id="result" name="result" required class="{{ $field }} font-bold">
                        <option value="">Waiting</option>
                        @foreach (\App\Models\VisaApplication::RESULTS as $result)
                            <option value="{{ $result }}" @selected(old('result', $application->result) === $result)>{{ ucfirst($result) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="result_at" class="{{ $label }}">Result Date</label>
                    <input id="result_at" type="date" name="result_at" required value="{{ old('result_at', $application->result_at?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="result_reference" class="{{ $label }}">Visa Number</label>
                    <input id="result_reference" type="text" name="result_reference" value="{{ old('result_reference', $application->result_reference) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="result_validity" class="{{ $label }}">Validity</label>
                    <input id="result_validity" type="text" name="result_validity" value="{{ old('result_validity', $application->result_validity) }}" placeholder="e.g. 90 days, multiple entry" class="{{ $field }}">
                </div>
                <div class="sm:col-span-4"><button type="submit" class="{{ $button }}">Save result</button></div>
            </form>
            @break

        @case ('lodged')
            @if ($application->service_type === 'visit_visa')
                <p class="text-xs text-dark/70 mb-3">Collect the balance in <strong>Fees</strong> first: the file must be fully paid before submission. Then record the submission, the appointment and biometrics, and how long it should take.</p>
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
                            <label for="appointment_at_stage" class="{{ $label }}">Appointment Date</label>
                            <input id="appointment_at_stage" type="date" name="appointment_at" value="{{ old('appointment_at', $application->appointment_at?->format('Y-m-d')) }}" class="{{ $field }}">
                        </div>
                        <div>
                            <label for="biometrics_at" class="{{ $label }}">Biometrics Date</label>
                            <input id="biometrics_at" type="date" name="biometrics_at" value="{{ old('biometrics_at', $application->biometrics_at?->format('Y-m-d')) }}" class="{{ $field }}">
                        </div>
                    </div>
                    <button type="submit" class="{{ $button }}">Save submission</button>
                </form>
                @break
            @endif
            <p class="text-xs text-dark/70 mb-3">
                Record when the application was lodged{{ $application->service_type === 'visit_visa' ? ' with the embassy' : '' }},
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
                        <label for="embassy_reference" class="{{ $label }}">{{ $application->service_type === 'visit_visa' ? 'Embassy' : 'Application' }} Reference</label>
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
                        <label for="result_reference" class="{{ $label }}">{{ $application->service_type === 'passporting' ? 'Passport' : 'Visa' }} Number</label>
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

    @if ($application->service_type === 'visit_visa')
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
        && ! ($status === 'requirements' && $application->service_type === 'visit_visa' && $application->checklist())
        && ! ($application->service_type === 'visit_visa' && $application->openTasks($status) !== []))
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
            <form method="POST" action="{{ route('visa.applications.advance', $application) }}" class="m-0">
                @csrf
                <button type="submit" @disabled($blocker) title="{{ $blocker ?: 'Move the file to the next stage' }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-primary">
                    <span>Advance to {{ $application->stageLabel($nextStage) }}</span>
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
