@php
    // A preview shows every step on one page, read-only, to scroll through.
    $showAll = $preview ?? false;
    $app = $application ?? null;
    $currentService = old('service_type', $app->service_type ?? $serviceType ?? 'renewal_application');
    // The visa type comes from the service (SRRV Classic or SRRV Courtesy) or the saved file, so the form
    // does not ask for it; only an older link without one still offers the choice.
    $requestedClass = in_array(request('visa_class'), \App\Models\SrrvApplication::VISA_CLASSES, true) ? request('visa_class') : null;
    $classLocked = $app !== null || $requestedClass !== null;
    $currentClass = $app->visa_class ?? $requestedClass ?? old('visa_class', 'classic');
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
        in_array($firstError, ['service_fee', 'investment_amount', 'currency'], true) => 5,
        in_array($firstError, ['military_service_proof_received', 'pension_proof_received', 'police_clearance_received'], true),
        str_starts_with($firstError, 'documents'), str_starts_with($firstError, 'requirements') => 4,
        in_array(str_replace('form.', '', $firstError), \App\Support\SrrvForms::RETIREE_STEP_FIELDS, true) => 2,
        str_starts_with($firstError, 'form') => 3,
        default => 6,
    };

    // Step 5 holds the agreement, the fee, the admin's approval and the cashier's payment: a new file stays on it until
    // the admin has approved it and the cashier has taken a payment. Older files (not fee-first) are not held there.
    $canContinue = $app ? (! $app->isFeeFirst() || $app->status !== 'agreement' || $app->stageBlocker() === null) : false;
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
     gateStep: 5, locked: {{ $canContinue ? 'null' : '5' }}, step: {{ $startStep }}, all: {{ $showAll ? 'true' : 'false' }}, first: {{ $firstStep }}, last: {{ array_key_last($steps) }}, visited: [{{ $startStep }}], titles: @js($steps) }"
     @input="if (/^form\[(spouse|child_\d)_name\]$/.test($event.target.name || '')) deps = countDeps()"
     x-init="$watch('step', value => { if (! visited.includes(value)) visited.push(value); window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })" class="space-y-6">

    @unless ($showAll)
    @include('srrv.partials._stepper', ['pillIcon' => 'file-badge', 'pillText' => ($classLocked ? ($currentClass === 'courtesy' ? 'SRRV Courtesy' : 'SRRV Classic') : 'SRRV Application').' · '.($app ? $app->reference : 'New retiree file')])
    @endunless

    @unless ($app)
    <section x-show="all || step === 0" @if (! $showAll && $startStep !== 0) style="display: none" @endif class="space-y-6">
        @include('srrv.applications._overview')
    </section>

    @endunless

    <section x-show="all || step === 1" @if (! $showAll && $startStep !== 1) style="display: none" @endif class="space-y-6">

    {{-- One job opens here (a new SRRV application); it is fixed once a file is open. --}}
    <input type="hidden" name="service_type" value="{{ $app ? $app->service_type : $currentService }}">

    <!-- Class: the PRA's options (Expanded SRRV Program, PRA-PD-LORQ-0010, August 2025) -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 space-y-4">
        <div>
            @if ($classLocked)
                <h2 class="font-heading text-sm font-extrabold text-dark mb-1">{{ $currentClass === 'courtesy' ? 'SRRV Courtesy' : 'SRRV Classic' }}</h2>
                <p class="text-[11px] text-dark/50">The age bracket, eligibility and deposit follow the retiree's birth date.</p>
            @else
                <h2 class="font-heading text-sm font-extrabold text-dark mb-1">SRRV Option</h2>
                <p class="text-[11px] text-dark/50">Decided by who the retiree is. The age bracket, eligibility and deposit follow the retiree's birth date.</p>
            @endif
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

        @if ($classLocked)
        <input type="hidden" name="visa_class" value="{{ $currentClass }}">
        @else
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
        @endif

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
    @php
        // Step 2 look: one card per topic, each with an icon header, and every input with a leading icon.
        $in = 'w-full pl-9 pr-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs placeholder:text-dark/30 hover:border-gray-300 focus:bg-white focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition';
        $lb = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5';
        $ic = 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-dark/30';
        $detail = fn (string $key) => old("form.{$key}", $app?->detail($key) ?? '');
    @endphp

    <!-- Retiree -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-primary/5 to-transparent">
            <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0"><i data-lucide="user-round" class="w-4.5 h-4.5"></i></span>
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Retiree</h2>
                <p class="text-[11px] text-dark/50">Who the file is for, and how to reach them.</p>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-5">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="retiree_name" class="{{ $lb }}">Full name <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <i data-lucide="user" class="{{ $ic }}"></i>
                    <input id="retiree_name" type="text" name="retiree_name" required placeholder="Last name, first name"
                           value="{{ old('retiree_name', $app->retiree_name ?? '') }}" class="{{ $in }}">
                </div>
            </div>
            <div>
                <label for="date_of_birth" class="{{ $lb }}">Date of birth</label>
                <div class="relative">
                    <i data-lucide="cake" class="{{ $ic }}"></i>
                    <input id="date_of_birth" type="date" name="date_of_birth" x-model="dob" value="{{ $currentDob }}" class="{{ $in }}">
                </div>
                <p class="text-[10px] mt-1.5" :class="age() !== null && age() < 40 ? 'text-rose-600 font-semibold' : 'text-dark/45'"
                   x-text="age() === null ? 'The PRA requires 40 or older; the deposit depends on age.' : (age() < 40 ? 'Age ' + age() + ': the PRA requires a principal to be 40 or older.' : 'Age ' + age() + '.')">The PRA requires 40 or older; the deposit depends on age.</p>
            </div>
            <div>
                <label for="nationality" class="{{ $lb }}">Nationality</label>
                <div class="relative">
                    <i data-lucide="flag" class="{{ $ic }}"></i>
                    <input id="nationality" type="text" name="nationality" value="{{ old('nationality', $app->nationality ?? '') }}" class="{{ $in }}">
                </div>
            </div>
            <div>
                <label for="form_gender" class="{{ $lb }}">Gender</label>
                <div class="relative">
                    <i data-lucide="users-round" class="{{ $ic }}"></i>
                    <select id="form_gender" name="form[gender]" class="{{ $in }} appearance-none">
                        <option value="">&mdash;</option>
                        @foreach (['M' => 'Male', 'F' => 'Female'] as $code => $genderLabel)
                            <option value="{{ $code }}" @selected((string) $detail('gender') === $code)>{{ $genderLabel }}</option>
                        @endforeach
                    </select>
                </div>
                @error('form.gender') <p class="mt-1 text-[10px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="retiree_email" class="{{ $lb }}">Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="{{ $ic }}"></i>
                    <input id="retiree_email" type="email" name="retiree_email" placeholder="name@example.com"
                           value="{{ old('retiree_email', $app->retiree_email ?? '') }}" class="{{ $in }}">
                </div>
                <p class="text-[10px] text-dark/45 mt-1.5">The file is sent on by email.</p>
            </div>
            <div>
                <label for="retiree_phone" class="{{ $lb }}">Mobile / WhatsApp</label>
                <div class="relative">
                    <i data-lucide="phone" class="{{ $ic }}"></i>
                    <input id="retiree_phone" type="text" name="retiree_phone" placeholder="+63 900 000 0000"
                           value="{{ old('retiree_phone', $app->retiree_phone ?? '') }}" class="{{ $in }}">
                </div>
            </div>
            <div>
                <label for="srrv_card_number" class="{{ $lb }}">SRRV card number</label>
                <div class="relative">
                    <i data-lucide="id-card" class="{{ $ic }}"></i>
                    <input id="srrv_card_number" type="text" name="srrv_card_number" value="{{ old('srrv_card_number', $app->srrv_card_number ?? '') }}" class="{{ $in }}">
                </div>
            </div>
        </div>
    </div>

    </section>

    <section x-show="all || step === 3" @if (! $showAll && $startStep !== 3) style="display: none" @endif class="space-y-6">
    @include('srrv._form-details', ['details' => $app?->form_details, 'forRenewal' => false, 'except' => \App\Support\SrrvForms::RETIREE_STEP_FIELDS])

    </section>

    <section x-show="all || step === 4" @if (! $showAll && $startStep !== 4) style="display: none" @endif class="space-y-6">
    <!-- Requirements -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Requirements</h2>
        <p class="text-[11px] text-dark/50 mb-4">
            The PRA's list for this option (Expanded SRRV Program, August 2025). Tick each one you have seen, and upload its scan beside it.
        </p>

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

    <!-- Acknowledgment of Documents: printed for the retiree to sign once their documents are in (as on Passporting) -->
    @php
        $signedScans = $app ? $app->documents->where('document_type', 'signed_acknowledgement') : collect();
    @endphp
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden"
         x-data="{ signed: {{ Js::from((bool) ($app?->acknowledgement_signed_at || old('acknowledgement_signed'))) }}, scan: '',
                   {{-- A file not yet saved prints what has been ticked and chosen so far on this step. --}}
                   ackUrl() {
                       const form = this.$el.closest('form'), q = new URLSearchParams({ visa_class: this.visaClass });
                       if (this.visaClass === 'courtesy' && this.courtesyType) q.set('courtesy_type', this.courtesyType);
                       if (this.pensioner) q.set('is_pensioner', 1);
                       const name = (form?.elements['retiree_name']?.value || '').trim();
                       if (name) q.set('retiree_name', name);
                       form?.querySelectorAll('input[name=&quot;requirements[]&quot;]:checked').forEach(box => q.append('received[]', box.value));
                       {{-- Other Documents rows: the type chosen on each row that has a file (a requirement's own file is already ticked above). --}}
                       form?.querySelectorAll('input[type=file]').forEach(input => {
                           const row = input.name.match(/^documents\[(\d+)\]\[file\]$/);
                           if (row &amp;&amp; input.files.length) q.append('received[]', form.elements['documents[' + row[1] + '][type]']?.value);
                       });
                       return '{{ route('srrv.acknowledgement-blank') }}?' + q.toString();
                   } }">
        <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-primary/5 to-transparent">
            <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0"><i data-lucide="file-signature" class="w-4.5 h-4.5"></i></span>
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Acknowledgment of Documents</h2>
                <p class="text-[11px] text-dark/50">Once the documents are in, print the Acknowledgment, have the retiree sign it, then record it here and upload a scan of the signed copy, so the system keeps a record.</p>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <input type="hidden" name="acknowledgement_signed" :value="signed ? 1 : 0">
            <div class="flex flex-wrap items-center gap-3">
                <a @if ($app) href="{{ route('srrv.applications.acknowledgement', $app) }}" @else @mouseenter="$el.href = ackUrl()" @focus="$el.href = ackUrl()" @click="$el.href = ackUrl()" href="{{ route('srrv.acknowledgement-blank') }}" @endif target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print acknowledgment
                </a>
                @if ($app?->acknowledgement_signed_at)
                    <span class="text-xs font-semibold text-emerald-700">Signed {{ $app->acknowledgement_signed_at->format('M d, Y g:i A') }}.</span>
                @else
                    <button type="button" x-show="!signed" @click="signed = true"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                        <i data-lucide="pen-line" class="w-4 h-4"></i> Retiree signed the acknowledgment
                    </button>
                    <p x-show="signed" x-cloak class="text-xs font-semibold text-emerald-700">
                        Signed. Recorded when the file is saved.
                        <button type="button" @click="signed = false" class="ml-1 text-[11px] font-bold text-dark/50 hover:text-dark underline">Undo</button>
                    </p>
                @endif
            </div>

            <!-- The scanned signed copy, kept on the file when it is saved -->
            <div>
                <p class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Signed copy (scan)</p>
                @foreach ($signedScans as $scan)
                    <p class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                        <i data-lucide="file-check" class="w-4 h-4 text-emerald-600"></i>
                        <a href="{{ route('srrv.documents.download', $scan) }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ $scan->original_name }}</a>
                        <span class="text-dark/50">uploaded {{ $scan->created_at?->format('M d, Y g:i A') }}</span>
                    </p>
                @endforeach
                <input type="hidden" name="documents[signed_acknowledgement][type]" value="signed_acknowledgement">
                <input type="file" name="documents[signed_acknowledgement][file]" accept=".pdf,.jpg,.jpeg,.png,.webp" aria-label="Scan of the signed acknowledgment"
                       class="text-xs text-dark/70 file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:bg-gray-100 file:text-dark/70 file:font-bold file:text-xs">
                <p class="mt-1.5 text-[10px] text-dark/45">PDF, JPG, PNG or WEBP, up to 10 MB. Filed when the file is saved.</p>
            </div>
        </div>
    </div>

    </section>

    <section x-show="all || step === 5" @if (! $showAll && $startStep !== 5) style="display: none" @endif class="space-y-6">
    <!-- Service agreement: the SRRV assistance booking agreement itself -->
    {{-- Laid out like Passporting's Step 2 (visa-assistance/applications/_stage). Save fee and Send to admin save the
         whole wizard first (opening the file if it is new), then come back here or send the file to the admin. --}}
    @php
        $signedAt = $app?->agreement_signed_at;
        $held = $app?->isAwaitingApproval() ?? false;
        $approved = $app?->approval_status === \App\Models\SrrvApplication::APPROVAL_APPROVED;
        $feeLocked = $held || $approved;
        $stepButton = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all';
    @endphp
    <div class="rounded-2xl bg-white border shadow-sm p-6 {{ $canContinue ? 'border-emerald-200' : 'border-amber-200' }}"
         x-data="{ savedFee: {{ Js::from((float) ($app->service_fee ?? 0)) }}, savedInv: {{ Js::from((float) ($app->investment_amount ?? 0)) }}, needName: false,
                   {{-- Saved: the fee on screen is the fee the file holds. Send to admin waits for that. --}}
                   get saved() { return this.savedFee > 0 && (parseFloat(this.fee) || 0) === this.savedFee && (parseFloat(this.inv) || 0) === this.savedInv; },
                   nameOk() { return (this.$el.closest('form')?.elements['retiree_name']?.value || '').trim() !== ''; },
                   {{-- A real POST (a form built on the fly, so it is not nested in this one) to the file's Advance, landing on the next step. --}}
                   advanceFile() {
                       const f = document.createElement('form');
                       f.method = 'POST'; f.action = @js($app ? route('srrv.applications.advance', $app) : '');
                       [['_token', this.$el.closest('form').elements['_token'].value], ['step', this.step + 1]].forEach(([n, v]) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); });
                       document.body.appendChild(f); f.submit();
                   },
                   signed: {{ Js::from((bool) ($signedAt || old('agreement_signed'))) }}, fee: {{ Js::from(old('service_fee', (float) ($app->service_fee ?? 0))) }}, inv: {{ Js::from(old('investment_amount', (float) ($app->investment_amount ?? 0))) }}, cur: {{ Js::from(old('currency', $app->currency ?? 'USD')) }},
                   get total() { return (parseFloat(this.fee) || 0) + (parseFloat(this.inv) || 0); },
                   {{-- The blank agreement, filled from what has been typed so far on the earlier steps and this one. --}}
                   agreementUrl() {
                       const form = this.$el.closest('form'), q = new URLSearchParams({ visa_class: this.visaClass, service_fee: this.fee || 0, currency: this.cur });
                       if (this.visaClass === 'courtesy' && this.courtesyType) q.set('courtesy_type', this.courtesyType);
                       if (this.pensioner) q.set('is_pensioner', 1);
                       ['retiree_name', 'retiree_email', 'retiree_phone', 'date_of_birth', 'nationality', ...@js(array_map(fn ($key) => "form[{$key}]", \App\Support\SrrvForms::AGREEMENT_FIELDS))].forEach(name => { const value = (form?.elements[name]?.value || '').trim(); if (value) q.set(name, value); });
                       return '{{ route('srrv.agreement-blank') }}?' + q.toString();
                   } }">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Current step</p>
                <h2 class="font-heading text-base font-extrabold text-dark mt-0.5">Service Agreement &amp; Fees</h2>
            </div>
            @if ($canContinue)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Done &mdash; ready to advance
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold">
                    <i data-lucide="clock" class="w-3.5 h-3.5"></i> To do before advancing
                </span>
            @endif
        </div>

        <p class="text-xs text-dark/70 mb-3">Print the service agreement, have the client read and sign it, then record it here.</p>
        {{-- A saved file prints filled in; a new one prints the blank agreement for its class. --}}
        <a @if ($app) href="{{ route('srrv.applications.agreement', $app) }}" @else @mouseenter="$el.href = agreementUrl()" @focus="$el.href = agreementUrl()" @click="$el.href = agreementUrl()" href="{{ route('srrv.agreement-blank') }}" @endif target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all mb-3">
            <i data-lucide="printer" class="w-4 h-4"></i> Print Booking Agreement
        </a>
        <input type="hidden" name="agreement_signed" :value="signed ? 1 : 0">
        @if ($signedAt)
            <p class="text-xs font-semibold text-emerald-700">Signed {{ $signedAt->format('M d, Y g:i A') }}.</p>
        @else
            <div>
                <button type="button" x-show="!signed" @click="signed = true" class="{{ $stepButton }}">
                    <i data-lucide="pen-line" class="w-4 h-4"></i> Client signed the agreement
                </button>
                <p x-show="signed" x-cloak class="text-xs font-semibold text-emerald-700">
                    Signed. Recorded when the file is saved.
                    <button type="button" @click="signed = false" class="ml-1 text-[11px] font-bold text-dark/50 hover:text-dark underline">Undo</button>
                </p>
            </div>
        @endif

        <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div>
                <label for="service_fee" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Service fee (<span x-text="cur"></span>) *</label>
                <input id="service_fee" type="number" step="0.01" min="0" name="service_fee" x-model="fee" @readonly($feeLocked)
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                @error('service_fee') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="investment_amount" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Investment amount</label>
                <input id="investment_amount" type="number" step="0.01" min="0" name="investment_amount" x-model="inv"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                @error('investment_amount') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="currency" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Currency</label>
                <select id="currency" name="currency" x-model="cur"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    @foreach(['USD', 'PHP'] as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <p class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Total</p>
                <p class="h-[38px] flex items-center text-sm font-bold text-dark"><span x-text="cur"></span>&nbsp;<span x-text="total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">0.00</span></p>
            </div>
            @unless ($feeLocked || $showAll)
                <button type="submit" name="then" value="fee" @click="needName = ! nameOk(); if (needName) $event.preventDefault()"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                    <i data-lucide="save" class="w-4 h-4"></i> Save fee
                </button>
            @endunless
        </div>
        @unless ($feeLocked || $showAll)
            <p x-show="saved" x-cloak class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700"><i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Fee saved.</p>
            <p x-show="!saved" class="mt-3 flex items-center gap-1.5 text-[11px] font-semibold text-amber-800"><i data-lucide="info" class="w-3.5 h-3.5"></i> <span x-text="fee > 0 ? 'Press Save fee to keep this amount.' : 'Enter the service fee, then press Save fee.'"></span></p>
            <p x-show="needName" x-cloak class="mt-1 flex items-center gap-1.5 text-[11px] font-semibold text-rose-600"><i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Enter the retiree's full name on Step 2 first; the fee is saved with the file.</p>
        @endunless

        <div class="mt-4 pt-4 border-t border-gray-100">
            @if ($approved)
                <p class="text-xs font-semibold text-emerald-700">
                    Approved by the admin. The cashier must record the payment (partial or full) before the file moves on: paid {{ number_format((float) $app->amount_paid, 2) }},
                    balance <strong class="{{ $app->outstandingBalance() > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($app->outstandingBalance(), 2) }}</strong>.
                </p>
            @elseif ($held)
                <p class="text-xs font-semibold text-amber-800">Sent to the admin. Waiting for approval.</p>
            @elseif (! $showAll)
                @if ($app?->isReturned())
                    <p class="text-xs font-semibold text-rose-700 mb-2">The admin returned this file{{ $app->review_note ? ': '.$app->review_note : '.' }} Correct it and send it again.</p>
                @endif
                {{-- Saves the wizard (opening the file if it is new), then sends it to the admin. --}}
                <button type="submit" name="then" value="send" :disabled="!saved" class="{{ $stepButton }} disabled:opacity-40 disabled:cursor-not-allowed">
                    <i data-lucide="send" class="w-4 h-4"></i> {{ $app?->isReturned() ? 'Send to admin again' : 'Send to admin' }}
                </button>
                <p x-show="!saved" class="mt-2 text-[11px] text-dark/50">Save the fee first.</p>
            @endif
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100 space-y-1.5" x-show="!(signed && fee > 0)">
            <p x-show="!signed" class="flex items-center gap-1.5 text-[11px] font-semibold text-amber-800">
                <i data-lucide="info" class="w-3.5 h-3.5"></i> Record that the client signed the service agreement.
            </p>
            <p x-show="signed && !(fee > 0)" x-cloak class="flex items-center gap-1.5 text-[11px] font-semibold text-amber-800">
                <i data-lucide="info" class="w-3.5 h-3.5"></i> Enter the service fee.
            </p>
        </div>

        @unless ($showAll)
            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-end gap-3">
                @unless ($canContinue)
                    <p class="text-[11px] text-dark/45">{{ $app ? $app->stageBlocker() : 'Save the file and send it to the admin; once approved and paid at the cashier, you can move on.' }}</p>
                @endunless
                {{-- Moves the file itself on (not just the wizard), then opens the next step. --}}
                <button type="button" @disabled(! $canContinue) class="{{ $stepButton }} disabled:opacity-40 disabled:cursor-not-allowed"
                        @if ($app) @click="advanceFile()" @else @click="step++" @endif>
                    <span x-text="'Advance to ' + titles[step + 1]">Advance</span> <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                </button>
            </div>
        @endunless
    </div>

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
        <a href="{{ $app ? route('srrv.applications.edit', [$app, 'preview' => 1]) : route('srrv.applications.index') }}"
           class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
        @unless ($app)
            @include('partials._save-pending', ['desk' => 'srrv', 'title' => 'SRRV application'])
        @endunless
        {{-- Saves and sends the file to the admin, then ends the wizard on the file list (no printing). --}}
        <button type="submit" name="then" value="finish"
                class="px-6 py-2.5 bg-white border border-primary text-primary font-heading font-bold text-xs rounded-xl hover:bg-primary/5 transition-all inline-flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
            <span>Finish</span>
        </button>
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
