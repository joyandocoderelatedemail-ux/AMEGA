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
<div class="rounded-2xl bg-white border shadow-sm p-6 mb-6 {{ $blocker ? 'border-amber-200' : 'border-emerald-200' }}">
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
            <p class="text-xs text-dark/70">Add every applicant travelling on this file in <strong>Applicants</strong> below. Their documents are checked at the next step.</p>
            @break

        @case ('requirements')
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
            @break

        @case ('agreement')
            <p class="text-xs text-dark/70 mb-3">Have the client read and sign the {{ $application->service_type === 'e_visa' ? 'booking agreement' : 'visa assistance agreement' }}, then record it here.</p>
            @if ($application->agreement_signed_at)
                <p class="text-xs font-semibold text-emerald-700">Signed {{ $application->agreement_signed_at->format('M d, Y g:i A') }}.</p>
            @else
                <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="m-0">
                    @csrf
                    <button type="submit" class="{{ $button }}"><i data-lucide="pen-line" class="w-4 h-4"></i> Client signed the agreement</button>
                </form>
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
                <a href="{{ route('visa.applications.acknowledgement', $application) }}" target="_blank" class="{{ $secondary }}">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print acknowledgment
                </a>
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

        @case ('lodged')
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

    @if ($blocker && ! $isFinal)
        <p class="mt-4 pt-3 border-t border-gray-100 text-[11px] font-semibold text-amber-800 flex items-start gap-1.5">
            <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
            <span>{{ $blocker }}</span>
        </p>
    @endif
</div>
@endif
