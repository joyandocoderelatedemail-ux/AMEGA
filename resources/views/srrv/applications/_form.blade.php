@php
    // A preview shows every step on one page, read-only, to scroll through.
    $showAll = $preview ?? false;
    $app = $application ?? null;
    $currentService = old('service_type', $app->service_type ?? $serviceType ?? 'renewal_application');
    $currentClass = old('visa_class', $app->visa_class ?? 'classic');
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;

    // Step by step, like the other desks. A new file opens on the PRA's overview (step 0).
    $steps = ($app ? [] : [0 => 'Overview']) + [
        1 => 'Service & Class',
        2 => 'Retiree',
        3 => 'Requirements',
        4 => 'Fees',
        5 => 'PRA Form Details',
        6 => 'Remarks & Save',
    ];
    $firstStep = array_key_first($steps);
    // After a failed save, open the step holding the first problem.
    $firstError = (string) collect($errors->keys())->first();
    $startStep = match (true) {
        $firstError === '' => $firstStep,
        in_array($firstError, ['service_type', 'visa_class'], true) => 1,
        in_array($firstError, ['retiree_name', 'nationality', 'date_of_birth', 'retiree_email', 'retiree_phone', 'srrv_card_number'], true) => 2,
        in_array($firstError, ['copies_submitted', 'investment_amount', 'military_service_proof_received', 'pension_proof_received', 'police_clearance_received'], true),
        str_starts_with($firstError, 'documents') => 3,
        in_array($firstError, ['service_fee', 'amount_paid', 'currency'], true) => 4,
        str_starts_with($firstError, 'form_details') => 5,
        default => 6,
    };
@endphp

@if($errors->any())
    <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 p-4" role="alert">
        <div class="flex items-start gap-3">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
            <div>
                <p class="text-xs font-bold text-rose-900">Please correct the following:</p>
                <ul class="mt-1.5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li class="text-[11px] text-rose-800 leading-relaxed">&bull; {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<div x-data="{ service: '{{ $currentService }}', visaClass: '{{ $currentClass }}', step: {{ $startStep }}, all: {{ $showAll ? 'true' : 'false' }}, first: {{ $firstStep }}, last: {{ array_key_last($steps) }}, visited: [{{ $startStep }}], titles: @js($steps) }"
     x-init="$watch('step', value => { if (! visited.includes(value)) visited.push(value); window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })" class="space-y-6">

    @unless ($showAll)
    @include('srrv.partials._stepper', ['pillIcon' => 'file-badge', 'pillText' => $app ? 'SRRV Application · '.$app->reference : 'SRRV Application · New retiree file'])
    @endunless

    @unless ($app)
    <section x-show="all || step === 0" @if (! $showAll && $startStep !== 0) style="display: none" @endif class="space-y-6">
        @include('srrv.applications._overview')
    </section>

    @endunless

    <section x-show="all || step === 1" @if (! $showAll && $startStep !== 1) style="display: none" @endif class="space-y-6">

    <!-- Job -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Job</h2>
        <p class="text-[11px] text-dark/50 mb-4">Three jobs share this desk. The choice decides the pipeline.</p>

        @if($app)
            <input type="hidden" name="service_type" value="{{ $app->service_type }}">
            <div class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-primary/10 border border-primary/20">
                <i data-lucide="lock" class="w-4 h-4 text-primary"></i>
                <span class="text-xs font-bold text-primary uppercase tracking-wider">{{ $app->service_label }}</span>
            </div>
            <p class="text-[10px] text-dark/45 mt-2">The job is fixed once a file is open.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {{-- Re-stamping returns here once its flow is defined. --}}
                @foreach([
                    'renewal_application' => ['New SRRV Application', 'A retiree applying for the SRRV, with oath taking at the PRA', 'file-badge'],
                ] as $key => [$label, $desc, $icon])
                    <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                           :class="service === '{{ $key }}' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="service_type" value="{{ $key }}" x-model="service"
                               class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                        <div>
                            <div class="text-xs font-bold text-dark flex items-center gap-1.5">
                                <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5 text-primary"></i>
                                <span>{{ $label }}</span>
                            </div>
                            <p class="text-[10px] text-dark/50 mt-0.5">{{ $desc }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Class -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Visa Class</h2>
        <p class="text-[11px] text-dark/50 mb-4">Decided by who the retiree is, not by which one they want.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                   :class="visaClass === 'classic' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="visa_class" value="classic" x-model="visaClass"
                       class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                <div>
                    <div class="text-xs font-bold text-dark">SRRV Classic</div>
                    <p class="text-[10px] text-dark/50 mt-0.5">Not a government employee and not military.</p>
                </div>
            </label>
            <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                   :class="visaClass === 'courtesy' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="visa_class" value="courtesy" x-model="visaClass"
                       class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                <div>
                    <div class="text-xs font-bold text-dark">SRRV Courtesy</div>
                    <p class="text-[10px] text-dark/50 mt-0.5">Government employees and military, aged 50 and above.</p>
                </div>
            </label>
        </div>
    </div>

    </section>

    <section x-show="all || step === 2" @if (! $showAll && $startStep !== 2) style="display: none" @endif class="space-y-6">
    <!-- Retiree -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Retiree</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="retiree_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Full Name *</label>
                <input id="retiree_name" type="text" name="retiree_name" required
                       value="{{ old('retiree_name', $app->retiree_name ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="retiree_email" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Email</label>
                <input id="retiree_email" type="email" name="retiree_email"
                       value="{{ old('retiree_email', $app->retiree_email ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">The file is sent on by email.</p>
            </div>
            <div>
                <label for="retiree_phone" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Phone</label>
                <input id="retiree_phone" type="text" name="retiree_phone"
                       value="{{ old('retiree_phone', $app->retiree_phone ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="date_of_birth" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Date of Birth</label>
                <input id="date_of_birth" type="date" name="date_of_birth"
                       value="{{ old('date_of_birth', optional($app->date_of_birth ?? null)->format('Y-m-d')) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">Courtesy requires age 50 or above.</p>
            </div>
            <div>
                <label for="nationality" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Nationality</label>
                <input id="nationality" type="text" name="nationality"
                       value="{{ old('nationality', $app->nationality ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="srrv_card_number" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">SRRV Card Number</label>
                <input id="srrv_card_number" type="text" name="srrv_card_number"
                       value="{{ old('srrv_card_number', $app->srrv_card_number ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>
    </div>

    </section>

    <section x-show="all || step === 3" @if (! $showAll && $startStep !== 3) style="display: none" @endif class="space-y-6">
    <!-- Requirements -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Requirements</h2>
        <p class="text-[11px] text-dark/50 mb-4">
            The standard PRA checklist applies to both classes. The extra proof depends on the class.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="investment_amount" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Investment Amount</label>
                <input id="investment_amount" type="number" step="0.01" min="0" name="investment_amount"
                       value="{{ old('investment_amount', $app->investment_amount ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">The deposit the visa rests on.</p>
            </div>
            <div>
                <label for="copies_submitted" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Copies Submitted</label>
                <input id="copies_submitted" type="number" min="1" max="10" name="copies_submitted"
                       value="{{ old('copies_submitted', $app->copies_submitted ?? 4) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">Four copies of everything is the standing rule.</p>
            </div>
        </div>

        <!-- Each requirement: tick it as received, and upload its scan beside it -->
        @php
            $requirementRows = [
                ['type' => 'pra_checklist', 'label' => 'Standard PRA checklist', 'flag' => null, 'class' => null],
                ['type' => 'police_clearance', 'label' => 'Police clearance received', 'flag' => 'police_clearance_received', 'class' => 'classic'],
                ['type' => 'pension_proof', 'label' => 'Proof of pension received', 'flag' => 'pension_proof_received', 'class' => 'classic'],
                ['type' => 'military_service_proof', 'label' => 'Proof of military service received', 'flag' => 'military_service_proof_received', 'class' => 'courtesy'],
            ];
            $filedByType = $app ? $app->documents->groupBy('document_type') : collect();
        @endphp
        <div class="space-y-2.5">
            @foreach ($requirementRows as $row)
                <div x-data="{ file: '' }" @if ($row['class']) x-show="visaClass === '{{ $row['class'] }}'" x-cloak @endif
                     class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200">
                    @if ($row['flag'])
                        <label class="flex-1 min-w-0 flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="{{ $row['flag'] }}" value="1" x-ref="tick"
                                   {{ old($row['flag'], $app->{$row['flag']} ?? false) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                            <span class="text-xs font-semibold text-dark">{{ $row['label'] }}</span>
                        </label>
                    @else
                        <span class="flex-1 min-w-0 flex items-center gap-2.5">
                            <i data-lucide="clipboard-list" class="w-4 h-4 text-dark/40"></i>
                            <span class="text-xs font-semibold text-dark">{{ $row['label'] }}</span>
                        </span>
                    @endif

                    @if ($filedByType->has($row['type']))
                        <a href="{{ route('srrv.documents.download', $filedByType[$row['type']]->last()) }}" x-show="!file"
                           class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:underline max-w-[14rem] truncate">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5 shrink-0"></i>
                            <span class="truncate">{{ $filedByType[$row['type']]->last()->original_name }}</span>
                        </a>
                    @endif
                    <span x-show="file" x-cloak x-text="file" class="text-[11px] font-semibold text-dark/70 max-w-[14rem] truncate"></span>

                    <input type="hidden" name="documents[{{ $row['type'] }}][type]" value="{{ $row['type'] }}">
                    <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-primary text-[11px] font-bold cursor-pointer hover:border-primary transition-colors focus-within:ring-2 focus-within:ring-primary">
                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                        <span x-text="file ? 'Change file' : '{{ $filedByType->has($row['type']) ? 'Upload another' : 'Upload' }}'">Upload</span>
                        <input type="file" name="documents[{{ $row['type'] }}][file]" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only"
                               aria-label="Upload {{ strtolower(str_replace(' received', '', $row['label'])) }}"
                               @change="file = $event.target.files[0]?.name || ''; if (file && $refs.tick) $refs.tick.checked = true">
                    </label>
                </div>
            @endforeach
            <p class="text-[10px] text-dark/45" x-text="visaClass === 'courtesy' ? 'Proof of military service is the document that unlocks Courtesy.' : 'Police clearance and proof of pension sit on top of the standard checklist for Classic.'"></p>
        </div>
    </div>

    <!-- Other documents (passport, SRRV card, signed forms...): filed on the retiree file when it is saved -->
    @php
        $docError = collect($errors->keys())->first(fn ($key) => str_starts_with($key, 'documents'));
    @endphp
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6"
         x-data="{ rows: [], add() { this.rows.push({ id: Date.now() + Math.random(), type: 'valid_id', name: '' }); this.$nextTick(() => window.lucide && lucide.createIcons()); } }">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Other Documents</h2>
                <p class="text-[11px] text-dark/50">Passport, SRRV card, signed forms or anything else (PDF, JPG, PNG or WEBP, up to 10 MB each). Uploads here and beside each requirement are filed when the retiree file is saved.</p>
            </div>
            <button type="button" @click="add()"
                    class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-primary text-white text-xs font-bold hover:bg-navy transition-colors">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add document
            </button>
        </div>

        @if ($app && $app->documents->isNotEmpty())
            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Already filed</p>
            <ul class="rounded-xl border border-gray-200 divide-y divide-gray-100 mb-4">
                @foreach ($app->documents as $document)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="min-w-0">
                            <span class="block text-xs font-bold text-dark truncate">{{ \App\Models\SrrvApplicationDocument::TYPE_LABELS[$document->document_type] ?? $document->document_type }}</span>
                            <span class="block text-[11px] text-dark/50 truncate">{{ $document->original_name }}</span>
                        </span>
                        <a href="{{ route('srrv.documents.download', $document) }}" class="shrink-0 text-[11px] font-bold text-primary hover:text-navy-700">Download</a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($docError)
            <p class="mb-3 text-[11px] font-semibold text-rose-600">{{ $errors->first($docError) }} Please choose the files again.</p>
        @endif

        <p x-show="!rows.length" class="rounded-xl bg-gray-50 border border-dashed border-gray-200 px-4 py-3 text-[11px] text-dark/50">No other documents added.</p>

        <div class="space-y-2.5">
            <template x-for="(row, index) in rows" :key="row.id">
                <div class="grid grid-cols-1 sm:grid-cols-[14rem_1fr_auto] gap-2.5 items-center p-3 rounded-xl bg-gray-50 border border-gray-200">
                    <select :name="'documents[' + index + '][type]'" x-model="row.type" aria-label="Document type"
                            class="w-full px-3 py-2 rounded-lg bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        @foreach (\App\Models\SrrvApplicationDocument::TYPE_LABELS as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="file" :name="'documents[' + index + '][file]'" accept=".pdf,.jpg,.jpeg,.png,.webp" aria-label="Document file"
                           class="w-full text-xs text-dark/70 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-white file:text-xs file:font-bold file:text-primary file:ring-1 file:ring-inset file:ring-gray-200">
                    <button type="button" @click="rows.splice(index, 1)" class="justify-self-end p-2 rounded-lg text-dark/40 hover:text-rose-600 hover:bg-rose-50" aria-label="Remove this document">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </template>
        </div>
    </div>

    </section>

    <section x-show="all || step === 4" @if (! $showAll && $startStep !== 4) style="display: none" @endif class="space-y-6">
    <!-- Fees -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Fees</h2>
        <p class="text-[11px] text-dark/50 mb-4">Paid in full at this stage &mdash; no deposit.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="service_fee" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Service Fee</label>
                <input id="service_fee" type="number" step="0.01" min="0" name="service_fee"
                       value="{{ old('service_fee', $app->service_fee ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="amount_paid" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Amount Paid</label>
                <input id="amount_paid" type="number" step="0.01" min="0" name="amount_paid"
                       value="{{ old('amount_paid', $app->amount_paid ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="currency" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Currency</label>
                <select id="currency" name="currency"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    @foreach(['USD', 'PHP'] as $code)
                        <option value="{{ $code }}" {{ old('currency', $app->currency ?? 'USD') === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    </section>

    <section x-show="all || step === 5" @if (! $showAll && $startStep !== 5) style="display: none" @endif class="space-y-6">
    @include('srrv._form-details', ['details' => $app?->form_details, 'forRenewal' => false])

    </section>

    <section x-show="all || step === 6" @if (! $showAll && $startStep !== 6) style="display: none" @endif class="space-y-6">
    <!-- Remarks -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <label for="remarks" class="block font-heading text-sm font-extrabold text-dark mb-1">Remarks</label>
        <p class="text-[11px] text-dark/50 mb-3">Recorded against the file.</p>
        <textarea id="remarks" name="remarks" rows="3"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('remarks', $app->remarks ?? '') }}</textarea>
    </div>

    @unless ($showAll)
    <div class="flex items-center justify-end gap-3">
        <a href="{{ $app ? route('srrv.applications.show', $app) : route('srrv.applications.index') }}"
           class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
        <button type="submit"
                class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm inline-flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>{{ $app ? 'Save Changes' : 'Open Retiree File' }}</span>
        </button>
    </div>
    @endunless
    </section>

    @unless ($showAll)
    @include('srrv.partials._stepper-nav')
    @endunless
</div>
