@php
    // A preview shows every step on one page, read-only, to scroll through.
    $showAll = $preview ?? false;
    $app = $application ?? null;
    $currentService = old('service_type', $app->service_type ?? $serviceType ?? 'renewal_application');
    $currentClass = old('visa_class', $app->visa_class ?? 'classic');
    $currentCourtesyType = old('courtesy_type', $app->courtesy_type ?? 'foreign_national') ?: 'foreign_national';
    $currentPensioner = (bool) old('is_pensioner', $app->is_pensioner ?? false);
    $currentDob = old('date_of_birth', optional($app->date_of_birth ?? null)->format('Y-m-d'));
    $currentDependents = $app ? $app->dependentCount() : 0;
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;

    // Step by step, like the other desks. A new file opens on the PRA's overview (step 0).
    $steps = ($app ? [] : [0 => 'Overview']) + \App\Support\SrrvForms::WIZARD_STEPS;
    $firstStep = array_key_first($steps);
    // After a failed save, open the step holding the first problem.
    $firstError = (string) collect($errors->keys())->first();
    $startStep = match (true) {
        // ?step= opens a given step, e.g. the last one when coming back from the printed form.
        $firstError === '' => array_key_exists((int) request('step', -1), $steps) ? (int) request('step') : $firstStep,
        in_array($firstError, ['service_type', 'visa_class', 'courtesy_type', 'is_pensioner'], true) => 1,
        in_array($firstError, ['retiree_name', 'nationality', 'date_of_birth', 'retiree_email', 'retiree_phone', 'srrv_card_number'], true) => 2,
        in_array($firstError, ['copies_submitted', 'investment_amount', 'military_service_proof_received', 'pension_proof_received', 'police_clearance_received'], true),
        str_starts_with($firstError, 'documents'), str_starts_with($firstError, 'requirements') => 3,
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

<div x-data="{ service: '{{ $currentService }}', visaClass: '{{ $currentClass }}', courtesyType: '{{ $currentCourtesyType }}', pensioner: {{ $currentPensioner ? 'true' : 'false' }}, dob: '{{ $currentDob }}', deps: {{ $currentDependents }},
     pra: @js(['deposits' => \App\Models\SrrvApplication::DEPOSITS, 'annual' => \App\Models\SrrvApplication::ANNUAL_FEES, 'processing' => \App\Models\SrrvApplication::PROCESSING_FEE, 'perDependent' => \App\Models\SrrvApplication::DEPENDENT_PROCESSING_FEE, 'extraDeposit' => \App\Models\SrrvApplication::EXTRA_DEPENDENT_DEPOSIT]),
     age() { if (! this.dob) return null; const b = new Date(this.dob), t = new Date(); let a = t.getFullYear() - b.getFullYear(); if (t.getMonth() < b.getMonth() || (t.getMonth() === b.getMonth() && t.getDate() < b.getDate())) a--; return a; },
     option() { return this.visaClass === 'courtesy' ? this.courtesyType : 'classic'; },
     deposit() { const age = this.age(); if (age === null) return null; return this.pra.deposits[this.option()][this.pensioner && this.option() !== 'former_filipino' ? 'pensioner' : 'non_pensioner'][age >= 50 ? 1 : 0] + Math.max(0, this.deps - 2) * this.pra.extraDeposit; },
     annualFee() { const [fee, extra] = this.pra.annual[this.option()]; return fee + Math.max(0, this.deps - 2) * extra; },
     usd(n) { return 'USD ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2 }); },
     countDeps() { return ['spouse', 'child_1', 'child_2', 'child_3', 'child_4'].filter(k => (this.$root.querySelector('[name=&quot;form[' + k + '_name]&quot;]')?.value || '').trim()).length; },
     step: {{ $startStep }}, all: {{ $showAll ? 'true' : 'false' }}, first: {{ $firstStep }}, last: {{ array_key_last($steps) }}, visited: [{{ $startStep }}], titles: @js($steps) }"
     @input="if (/^form\[(spouse|child_\d)_name\]$/.test($event.target.name || '')) deps = countDeps()"
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

    <!-- Class: the PRA's options (Expanded SRRV Program, PRA-PD-LORQ-0010, August 2025) -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 space-y-4">
        <div>
            <h2 class="font-heading text-sm font-extrabold text-dark mb-1">SRRV Option</h2>
            <p class="text-[11px] text-dark/50">Decided by who the retiree is. The age bracket, eligibility and deposit follow the retiree's birth date.</p>
        </div>

        <!-- The age, read from the selected client's record (or this file), and what it means under the PRA rules -->
        @php
            $ageSource = $app ? 'this file' : (request()->filled('client') && old('retiree_name') ? old('retiree_name')."'s client record" : null);
        @endphp
        <div class="rounded-xl border p-4"
             :class="age() === null ? 'border-amber-200 bg-amber-50/60' : (age() < 40 ? 'border-rose-200 bg-rose-50/60' : 'border-emerald-200 bg-emerald-50/60')">
            <template x-if="age() !== null">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold" :class="age() < 40 ? 'text-rose-800' : 'text-emerald-800'">
                            <span x-text="'Retiree is ' + age() + ' years old'"></span>
                            @if ($ageSource)
                                <span class="font-semibold opacity-70">&middot; from {{ $ageSource }}</span>
                            @endif
                        </p>
                        <p class="text-[11px] mt-0.5" :class="age() < 40 ? 'text-rose-700' : 'text-emerald-700'"
                           x-text="age() < 40
                               ? 'Not eligible yet: the PRA requires a principal retiree to be 40 or older.'
                               : (age() >= 50 ? 'PRA bracket: 50 years old & above (the lower deposits).' : 'PRA bracket: 40 to 49 years old (the higher deposits).')"></p>
                    </div>
                    <button type="button" @click="dob = ''" class="text-[11px] font-bold text-dark/50 hover:text-dark">Change birth date</button>
                </div>
            </template>
            <template x-if="age() === null">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <p class="text-xs font-bold text-amber-800">No birth date on record</p>
                        <p class="text-[11px] text-amber-700 mt-0.5">Enter it to see the age bracket and deposit. It also fills the Retiree step.</p>
                    </div>
                    <input type="date" x-model="dob" aria-label="Retiree's date of birth"
                           class="px-3 py-2 rounded-lg bg-white border border-amber-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                   :class="visaClass === 'classic' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="visa_class" value="classic" x-model="visaClass"
                       class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                <div>
                    <div class="text-xs font-bold text-dark">SRRV Classic</div>
                    <p class="text-[10px] text-dark/50 mt-0.5">Any foreign national aged 40 or older.</p>
                </div>
            </label>
            <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                   :class="visaClass === 'courtesy' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="visa_class" value="courtesy" x-model="visaClass"
                       class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                <div>
                    <div class="text-xs font-bold text-dark">SRRV Courtesy</div>
                    <p class="text-[10px] text-dark/50 mt-0.5">Retired diplomats, international-organisation officers, treaty military and high achievers, or former Filipinos.</p>
                </div>
            </label>
        </div>

        <!-- Courtesy: for whom -->
        <div x-show="visaClass === 'courtesy'" x-cloak>
            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Courtesy for</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border cursor-pointer"
                       :class="courtesyType === 'foreign_national' ? 'border-primary' : 'border-gray-200'">
                    <input type="radio" name="courtesy_type" value="foreign_national" x-model="courtesyType" class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                    <span>
                        <span class="block text-xs font-bold text-dark">Foreign national</span>
                        <span class="block text-[10px] text-dark/50 mt-0.5">Retired diplomat, officer of a DFA-recognised international organisation (UN, ADB, WB, IMF, WTO), treaty military, or high achiever.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border cursor-pointer"
                       :class="courtesyType === 'former_filipino' ? 'border-primary' : 'border-gray-200'">
                    <input type="radio" name="courtesy_type" value="former_filipino" x-model="courtesyType" class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                    <span>
                        <span class="block text-xs font-bold text-dark">Former Filipino</span>
                        <span class="block text-[10px] text-dark/50 mt-0.5">Was a Filipino citizen: shows a Philippine passport, naturalization certificate or PSA birth certificate.</span>
                    </span>
                </label>
            </div>
            @error('courtesy_type') <p class="mt-1.5 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>

        <!-- Pension: asked only where it changes the deposit or the requirements: always for Classic,
             and for a foreign-national Courtesy retiree aged 40 to 49 (never for former Filipinos) -->
        <label x-show="option() === 'classic' || (option() === 'foreign_national' && (age() === null || age() < 50))" class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
            <input type="hidden" name="is_pensioner" value="0">
            <input type="checkbox" name="is_pensioner" value="1" x-model="pensioner" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
            <span>
                <span class="block text-xs font-bold text-dark">Pensioner</span>
                <span class="block text-[10px] text-dark/50 mt-0.5"
                      x-text="visaClass === 'classic' ? 'Receives a pension of at least USD 800 a month (single) or USD 1,000 (family). Lowers the deposit; proof of pension is then required.' : 'Receives a pension of at least USD 1,000 a month. Lowers the deposit for ages 40 to 49.'"></span>
            </span>
        </label>
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
                <input id="date_of_birth" type="date" name="date_of_birth" x-model="dob"
                       value="{{ $currentDob }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] mt-1" :class="age() !== null && age() < 40 ? 'text-rose-600 font-semibold' : 'text-dark/45'"
                   x-text="age() === null ? 'The PRA requires 40 or older; the deposit depends on age.' : (age() < 40 ? 'Age ' + age() + ': the PRA requires a principal to be 40 or older.' : 'Age ' + age() + '.')">The PRA requires 40 or older; the deposit depends on age.</p>
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
            The PRA's list for this option (Expanded SRRV Program, August 2025). Tick each one you have seen, and upload its scan beside it.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="investment_amount" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Investment Amount</label>
                <input id="investment_amount" type="number" step="0.01" min="0" name="investment_amount"
                       value="{{ old('investment_amount', $app->investment_amount ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">The visa deposit remitted to the PRA-accredited bank.</p>
            </div>
            <div>
                <label for="copies_submitted" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Copies Submitted</label>
                <input id="copies_submitted" type="number" min="1" max="10" name="copies_submitted"
                       value="{{ old('copies_submitted', $app->copies_submitted ?? 4) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">Four copies of everything is the standing rule.</p>
            </div>
        </div>

        <!-- What the PRA charges for this option, from the option, pension, age and dependents -->
        <div class="mb-4 rounded-xl border border-primary/15 bg-primary/5 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-primary mb-2">PRA fees for this option</p>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <dt class="text-[10px] text-dark/50">Visa deposit</dt>
                    <dd class="text-sm font-extrabold text-dark" x-text="deposit() === null ? 'Enter the birth date' : usd(deposit())"></dd>
                    <button type="button" x-show="deposit() !== null" @click="document.getElementById('investment_amount').value = deposit()"
                            class="mt-1 text-[10px] font-bold text-primary hover:text-navy-700">Use as the investment amount</button>
                </div>
                <div>
                    <dt class="text-[10px] text-dark/50">Annual fee</dt>
                    <dd class="text-sm font-extrabold text-dark" x-text="usd(annualFee())"></dd>
                </div>
                <div>
                    <dt class="text-[10px] text-dark/50">Processing fee</dt>
                    <dd class="text-sm font-extrabold text-dark" x-text="usd(pra.processing + deps * pra.perDependent)"></dd>
                </div>
            </dl>
            <p class="mt-2 text-[10px] text-dark/50">
                Deposit covers the principal and two dependents; each dependent beyond two adds USD 15,000. Processing fee is USD 1,500 for the principal and USD 300 per dependent.
                <span x-show="deps" x-text="'Dependents named on this file: ' + deps + '.'"></span>
            </p>
        </div>

        <!-- Each PRA requirement: tick it as seen, and upload its scan beside it -->
        @php
            // When each one applies (option, pension, age, dependents), and which are only sometimes needed.
            $requirementShows = [
                'relationship_proof' => 'deps > 0',
                'pension_proof' => "(option() === 'classic' && pensioner) || (option() === 'foreign_national' && age() !== null && age() < 50)",
                'employment_certificate' => "option() === 'foreign_national'",
                'military_service_proof' => "option() === 'foreign_national'",
                'former_filipino_proof' => "option() === 'former_filipino'",
            ];
            $optionalRequirements = ['nbi_clearance', 'military_service_proof'];
            $ticked = old('requirements', $app ? $app->ticked() : []);
            $filedByType = $app ? $app->documents->groupBy('document_type') : collect();
        @endphp
        <div class="space-y-2.5">
            @foreach (\App\Models\SrrvApplication::REQUIREMENTS as $type => [$label, $hint])
                <div x-data="{ file: '' }" @isset($requirementShows[$type]) x-show="{{ $requirementShows[$type] }}" x-cloak @endisset
                     class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200">
                    <label class="flex-1 min-w-0 flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" name="requirements[]" value="{{ $type }}" x-ref="tick" @checked(in_array($type, (array) $ticked, true))
                               class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                        <span class="min-w-0">
                            <span class="text-xs font-semibold text-dark">{{ $label }}</span>
                            @if (in_array($type, $optionalRequirements, true))
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-gray-200 text-[9px] font-bold uppercase tracking-wider text-dark/50">If applicable</span>
                            @endif
                            <span class="block text-[10px] text-dark/45 mt-0.5">{{ $hint }}</span>
                        </span>
                    </label>

                    @if ($filedByType->has($type))
                        <a href="{{ route('srrv.documents.download', $filedByType[$type]->last()) }}" x-show="!file"
                           class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:underline max-w-[14rem] truncate">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5 shrink-0"></i>
                            <span class="truncate">{{ $filedByType[$type]->last()->original_name }}</span>
                        </a>
                    @endif
                    <span x-show="file" x-cloak x-text="file" class="text-[11px] font-semibold text-dark/70 max-w-[14rem] truncate"></span>

                    <input type="hidden" name="documents[{{ $type }}][type]" value="{{ $type }}">
                    <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-primary text-[11px] font-bold cursor-pointer hover:border-primary transition-colors focus-within:ring-2 focus-within:ring-primary">
                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                        <span x-text="file ? 'Change file' : '{{ $filedByType->has($type) ? 'Upload another' : 'Upload' }}'">Upload</span>
                        <input type="file" name="documents[{{ $type }}][file]" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only"
                               aria-label="Upload {{ strtolower($label) }}"
                               @change="file = $event.target.files[0]?.name || ''; if (file) $refs.tick.checked = true">
                    </label>
                </div>
            @endforeach
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
        {{-- Saves, then the filled PRA Application Form opens to print (once the admin has approved it). --}}
        <button type="submit" name="then" value="print"
                class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm inline-flex items-center gap-2">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>{{ $app ? 'Save & Print Application Form' : 'Open File & Print Application Form' }}</span>
        </button>
    </div>
    @endunless
    </section>

    @unless ($showAll)
    @include('srrv.partials._stepper-nav')
    @endunless
</div>
