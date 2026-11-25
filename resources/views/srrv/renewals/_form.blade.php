@php
    // A preview shows every step on one page, read-only, to scroll through.
    $showAll = $preview ?? false;
    $renewal = $renewal ?? null;
    $application = $application ?? $renewal?->application;
    $linkedId = old('srrv_application_id', $application?->id ?? $renewal?->srrv_application_id);
    // The visa type comes from the service (Classic or Courtesy ID Renewal), the linked SRRV file or the saved
    // renewal, so the form does not ask for it; only an older link without one still offers the choice.
    $requestedClass = in_array(request('visa_class'), \App\Models\SrrvApplication::VISA_CLASSES, true) ? request('visa_class') : null;
    $knownClass = $renewal?->visa_class ?? $application?->visa_class ?? $requestedClass;
    $classLocked = in_array($knownClass, \App\Models\SrrvApplication::VISA_CLASSES, true);
    $currentClass = $classLocked ? $knownClass : old('visa_class', 'classic');
    $currentYears = (int) old('years_paid', $renewal->years_paid ?? 1);
    // A new renewal linked to a retiree file starts from the dependents named on it.
    $currentDependents = (int) old('dependents', $renewal->dependents ?? $application?->dependentCount() ?? 0);
    $currentDob = old('date_of_birth', optional($renewal->date_of_birth ?? $application?->date_of_birth)->format('Y-m-d'));
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;

    // Step by step, like the other desks. A new renewal opens on the PRA's overview (step 0), which also names the visa.
    $steps = ($renewal ? [] : [0 => 'Overview']) + [
        1 => 'Retiree',
        2 => 'Years & Fee',
        3 => 'Documentation',
        4 => 'PRA Form Details',
        5 => 'Remarks & Save',
    ];
    $firstStep = array_key_first($steps);
    $firstError = (string) collect($errors->keys())->first();
    // After filing a document the edit comes back to Step 3 with ?step=3.
    $askedStep = (int) request('step', 0);
    $startStep = match (true) {
        $firstError === '' && $askedStep >= $firstStep && $askedStep <= array_key_last($steps) => $askedStep,
        $firstError === '' => $firstStep,
        in_array($firstError, ['visa_class', 'srrv_application_id'], true) => $firstStep,
        in_array($firstError, ['retiree_name', 'retiree_email', 'retiree_phone', 'nationality', 'date_of_birth', 'srrv_card_number'], true) => 1,
        in_array($firstError, ['years_paid', 'dependents', 'service_fee', 'discount_type', 'discount_value', 'fee_amount', 'amount_paid'], true) => 2,
        in_array($firstError, ['form_filled_online', 'id_and_photocopy_received'], true) || str_starts_with($firstError, 'documents.') => 3,
        str_starts_with($firstError, 'form_details') => 4,
        default => 5,
    };

    // The application form's look: one card per topic with an icon header, and every input with a leading icon.
    $in = 'w-full pl-9 pr-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs placeholder:text-dark/30 hover:border-gray-300 focus:bg-white focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 transition';
    $lb = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5';
    $ic = 'pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-dark/30';
    $cardHead = 'flex items-center gap-3 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-primary/5 to-transparent';
    $cardIcon = 'w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0';
    $covered = \App\Models\SrrvRenewal::DEPENDENTS_COVERED;
    $inPlain = str_replace('pl-9', 'pl-3.5', $in);
    // The PRA fee is always in USD; AMEGA's service fee (and its discount) always in pesos. No conversion.
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

<div x-data="{
        visaClass: '{{ $currentClass }}',
        years: {{ $currentYears }},
        deps: {{ $currentDependents }},
        dob: '{{ $currentDob }}',
        fees: {{ \Illuminate\Support\Js::from($classFees) }},
        extras: {{ \Illuminate\Support\Js::from(\App\Models\SrrvRenewal::EXTRA_DEPENDENT_FEES) }},
        covered: {{ $covered }},
        {{-- Same sum as SrrvRenewal::annualFee: the class rate, plus each dependent beyond the two it covers. --}}
        extraDeps() { return (this.extras[this.visaClass] || 0) > 0 ? Math.max(0, (parseInt(this.deps) || 0) - this.covered) : 0; },
        annual() { return this.fees[this.visaClass] + this.extraDeps() * (this.extras[this.visaClass] || 0); },
        service: {{ \Illuminate\Support\Js::from((float) old('service_fee', $renewal->service_fee ?? 0)) }},
        discType: {{ \Illuminate\Support\Js::from((string) old('discount_type', $renewal->discount_type ?? '')) }},
        discValue: {{ \Illuminate\Support\Js::from((float) old('discount_value', $renewal->discount_value ?? 0)) }},
        {{-- The PRA fee is always in USD; the service fee and its discount always in pesos. No conversion. --}}
        praFee() { return this.annual() * this.years; },
        discount() { const fee = parseFloat(this.service) || 0, value = parseFloat(this.discValue) || 0; return this.discType === 'percent' ? Math.round(fee * value) / 100 : (this.discType === 'amount' ? value : 0); },
        serviceTotal() { return (parseFloat(this.service) || 0) - this.discount(); },
        money(value, currency) { return (currency === 'PHP' ? '₱' : 'USD ') + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        age() { if (! this.dob) return null; const b = new Date(this.dob), t = new Date(); let a = t.getFullYear() - b.getFullYear(); if (t.getMonth() < b.getMonth() || (t.getMonth() === b.getMonth() && t.getDate() < b.getDate())) a--; return a; },
        {{-- Step 5 opens only once the fingerprint card has been saved and printed. --}}
        locked: {{ $renewal?->card_printed_at ? 'null' : 4 }},
        step: {{ $startStep }}, all: {{ $showAll ? 'true' : 'false' }}, first: {{ $firstStep }}, last: {{ array_key_last($steps) }}, visited: [{{ $startStep }}], titles: @js($steps)
     }"
     x-init="$watch('step', value => { if (! visited.includes(value)) visited.push(value); const url = new URL(location.href); url.searchParams.set('step', value); history.replaceState(history.state, '', url); window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })" class="space-y-6">

    @unless ($showAll)
    @include('srrv.partials._stepper', ['pillIcon' => 'calendar-check', 'pillText' => ($currentClass === 'courtesy' ? 'Courtesy ID Renewal' : 'Classic ID Renewal').' · '.($renewal ? ($renewal->reference ?? $renewal->retiree_name) : 'New renewal')])
    @endunless

    @if($linkedId)
        <input type="hidden" name="srrv_application_id" value="{{ $linkedId }}">
    @endif
    <input type="hidden" name="form_step" :value="step">

    @if($application)
        <div class="rounded-2xl bg-primary/5 border border-primary/20 p-4 flex items-center gap-3">
            <i data-lucide="link" class="w-4 h-4 text-primary shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-dark">Linked to retiree file {{ $application->reference }}</p>
                <p class="text-[10px] text-dark/50 mt-0.5">{{ $application->retiree_name }} &middot; {{ ucfirst($application->visa_class) }}</p>
            </div>
        </div>
    @endif

    @if ($classLocked)
        <input type="hidden" name="visa_class" value="{{ $currentClass }}">
    @endif

    @unless ($renewal)
    <section x-show="all || step === 0" @if (! $showAll && $startStep !== 0) style="display: none" @endif class="space-y-6">
        @include('srrv.renewals._overview')

    <!-- Which visa, a reminder of the fee (was its own step): as the application's Step 1 -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 space-y-4">
        <div>
            @if ($classLocked)
                <h2 class="font-heading text-sm font-extrabold text-dark mb-1">{{ $currentClass === 'courtesy' ? 'Courtesy ID Renewal' : 'Classic ID Renewal' }}</h2>
            @else
                <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Which visa is it?</h2>
            @endif
            <p class="text-[11px] text-dark/50">The renewal of the retiree's PRA ID card. The annual fee follows the visa and, on a Classic SRRV, the dependents.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 flex items-start gap-3">
            <i data-lucide="receipt" class="w-4 h-4 text-primary shrink-0 mt-0.5"></i>
            <p class="text-[11px] text-dark/70 leading-relaxed" x-text="(extras[visaClass] || 0) > 0
                ? money(fees[visaClass], 'USD') + ' a year for the retiree and up to ' + covered + ' dependents, plus ' + money(extras[visaClass], 'USD') + ' a year for each dependent beyond ' + covered + '.'
                : money(fees[visaClass], 'USD') + ' a year.'"></p>
        </div>

        @unless ($classLocked)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach([
                'classic' => ['SRRV Classic', 'USD '.number_format($classFees['classic'] ?? 0, 2).' a year'],
                'courtesy' => ['SRRV Courtesy', 'USD '.number_format($classFees['courtesy'] ?? 0, 2).' a year'],
            ] as $key => [$label, $desc])
                <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                       :class="visaClass === '{{ $key }}' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                    <input type="radio" name="visa_class" value="{{ $key }}" x-model="visaClass"
                           class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                    <div>
                        <div class="text-xs font-bold text-dark">{{ $label }}</div>
                        <p class="text-[10px] text-dark/50 mt-0.5">{{ $desc }}</p>
                    </div>
                </label>
            @endforeach
        </div>
        @endunless
    </div>
    </section>

    @endunless

    <section x-show="all || step === 1" @if (! $showAll && $startStep !== 1) style="display: none" @endif class="space-y-6">
    <!-- Retiree: as the application's Step 2 -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden">
        <div class="{{ $cardHead }}">
            <span class="{{ $cardIcon }}"><i data-lucide="user-round" class="w-4.5 h-4.5"></i></span>
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Retiree</h2>
                <p class="text-[11px] text-dark/50">Who the renewal is for, and how to reach them.</p>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-5">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="retiree_name" class="{{ $lb }}">Full name <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <i data-lucide="user" class="{{ $ic }}"></i>
                    <input id="retiree_name" type="text" name="retiree_name" required placeholder="Last name, first name"
                           value="{{ old('retiree_name', $renewal->retiree_name ?? $application?->retiree_name ?? '') }}" class="{{ $in }}">
                </div>
            </div>
            <div>
                <label for="date_of_birth" class="{{ $lb }}">Date of birth</label>
                <div class="relative">
                    <i data-lucide="cake" class="{{ $ic }}"></i>
                    <input id="date_of_birth" type="date" name="date_of_birth" x-model="dob" value="{{ $currentDob }}" class="{{ $in }}">
                </div>
                <p x-show="age() !== null" x-cloak class="text-[10px] text-dark/45 mt-1.5" x-text="'Age ' + age() + '.'"></p>
            </div>
            <div>
                <label for="nationality" class="{{ $lb }}">Nationality</label>
                <div class="relative">
                    <i data-lucide="flag" class="{{ $ic }}"></i>
                    <input id="nationality" type="text" name="nationality"
                           value="{{ old('nationality', $renewal->nationality ?? $application?->nationality ?? '') }}" class="{{ $in }}">
                </div>
            </div>
            <div>
                <label for="retiree_email" class="{{ $lb }}">Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="{{ $ic }}"></i>
                    <input id="retiree_email" type="email" name="retiree_email" placeholder="name@example.com"
                           value="{{ old('retiree_email', $renewal->retiree_email ?? $application?->retiree_email ?? '') }}" class="{{ $in }}">
                </div>
                <p class="text-[10px] text-dark/45 mt-1.5">The file is sent on by email.</p>
            </div>
            <div>
                <label for="retiree_phone" class="{{ $lb }}">Mobile / WhatsApp</label>
                <div class="relative">
                    <i data-lucide="phone" class="{{ $ic }}"></i>
                    <input id="retiree_phone" type="text" name="retiree_phone" placeholder="+63 900 000 0000"
                           value="{{ old('retiree_phone', $renewal->retiree_phone ?? $application?->retiree_phone ?? '') }}" class="{{ $in }}">
                </div>
            </div>
            <div>
                <label for="srrv_card_number" class="{{ $lb }}">SRRV card number <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <i data-lucide="id-card" class="{{ $ic }}"></i>
                    <input id="srrv_card_number" type="text" name="srrv_card_number" required placeholder="e.g. M-0071234"
                           value="{{ old('srrv_card_number', $renewal->srrv_card_number ?? $application?->srrv_card_number ?? '') }}" class="{{ $in }}">
                </div>
                <p class="text-[10px] text-dark/45 mt-1.5">From the retiree's current PRA ID card. Only an SRRV holder can renew.</p>
                @error('srrv_card_number') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    </section>

    <section x-show="all || step === 2" @if (! $showAll && $startStep !== 2) style="display: none" @endif class="space-y-6">
    <!-- Years and fee -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden">
        <div class="{{ $cardHead }}">
            <span class="{{ $cardIcon }}"><i data-lucide="calculator" class="w-4.5 h-4.5"></i></span>
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Years and Fee</h2>
                <p class="text-[11px] text-dark/50">Two years at most &mdash; the ceiling on how far ahead a retiree can pay.</p>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-5">
            <div>
                <label for="years_paid" class="{{ $lb }}">Years paid <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <i data-lucide="calendar-range" class="{{ $ic }}"></i>
                    <select id="years_paid" name="years_paid" x-model.number="years" required class="{{ $in }} appearance-none font-bold">
                        @for($y = 1; $y <= $maxYears; $y++)
                            <option value="{{ $y }}" {{ $currentYears === $y ? 'selected' : '' }}>{{ $y }} year{{ $y > 1 ? 's' : '' }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            {{-- Only a Classic renewal charges for dependents; a Courtesy one keeps the number at nought. --}}
            <div x-show="(extras[visaClass] || 0) > 0" @unless (isset(\App\Models\SrrvRenewal::EXTRA_DEPENDENT_FEES[$currentClass])) style="display: none" @endunless>
                <label for="dependents" class="{{ $lb }}">Dependents on the SRRV</label>
                <div class="relative">
                    <i data-lucide="users-round" class="{{ $ic }}"></i>
                    <input id="dependents" type="number" name="dependents" min="0" max="20" x-model.number="deps" :disabled="(extras[visaClass] || 0) === 0" class="{{ $in }}">
                </div>
                <p class="text-[10px] text-dark/45 mt-1.5">The fee covers up to {{ $covered }}; each one beyond adds <span x-text="money(extras[visaClass] || 0, 'USD')"></span> a year.</p>
                @error('dependents') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <span class="{{ $lb }}">Fee due (USD)</span>
                <div class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="font-heading text-sm font-extrabold text-dark" x-text="money(praFee(), 'USD')"></span>
                </div>
                <p class="text-[10px] text-dark/45 mt-1.5"
                   x-text="money(fees[visaClass], 'USD') + (extraDeps() ? ' + ' + extraDeps() + ' × ' + money(extras[visaClass], 'USD') : '') + ' a year × ' + years + (years > 1 ? ' years' : ' year')
                       + '. Not editable here.'"></p>
            </div>
        </div>
    </div>

    <!-- Service fee: AMEGA's fee with the agent's discount; the admin approves it, then the cashier takes the payment -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden">
        <div class="{{ $cardHead }}">
            <span class="{{ $cardIcon }}"><i data-lucide="badge-percent" class="w-4.5 h-4.5"></i></span>
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Service Fee</h2>
                <p class="text-[11px] text-dark/50">AMEGA's fee in pesos, with any discount. The PRA fee above stays in USD. The admin approves both, then the cashier takes each payment.</p>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-5">
            <div class="sm:col-start-1">
                <label for="service_fee" class="{{ $lb }}">Service fee (PHP)</label>
                <div class="relative">
                    <i data-lucide="wallet" class="{{ $ic }}"></i>
                    <input id="service_fee" type="number" step="0.01" min="0" name="service_fee" x-model.number="service" class="{{ $in }}">
                </div>
                @error('service_fee') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="discount_type" class="{{ $lb }}">Discount</label>
                <div class="flex gap-2">
                    <select id="discount_type" name="discount_type" x-model="discType" class="{{ str_replace('w-full ', '', $inPlain) }} appearance-none w-36 shrink-0">
                        <option value="">None</option>
                        @foreach (\App\Models\SrrvRenewal::DISCOUNT_TYPES as $discountKey => $discountLabel)
                            <option value="{{ $discountKey }}">{{ $discountLabel }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" name="discount_value" x-model.number="discValue" x-show="discType" x-cloak
                           :max="discType === 'percent' ? 100 : (parseFloat(service) || 0)" aria-label="Discount value" class="{{ $inPlain }} flex-1 min-w-0">
                </div>
                @error('discount_value') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <span class="{{ $lb }}">PRA fee total (USD)</span>
                <div class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="font-heading text-sm font-extrabold text-dark" x-text="money(praFee(), 'USD')"></span>
                </div>
                <p class="text-[10px] text-dark/45 mt-1.5">Paid separately to the PRA, in US dollars.</p>
            </div>
            <div>
                <span class="{{ $lb }}">Service fee total (PHP)</span>
                <div class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="font-heading text-sm font-extrabold text-dark" x-text="money(serviceTotal(), 'PHP')"></span>
                </div>
                <p class="text-[10px] text-dark/45 mt-1.5"
                   x-text="'PRA fee ' + money(praFee()) + ' + service fee ' + money(parseFloat(service) || 0) + (discount() ? ' − discount ' + money(discount()) : '') + '.'"></p>
            </div>
        </div>
    </div>

    </section>

    <section x-show="all || step === 3" @if (! $showAll && $startStep !== 3) style="display: none" @endif class="space-y-6">
    <!-- Document Checklist: what is filed on the renewal, then a type, what it is, and the file (as on a new application) -->
    @php $checklistDocuments = $renewal ? $renewal->documents->reject(fn ($document) => $document->document_type === 'signed_acknowledgement') : collect(); @endphp
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Document Checklist</h2>
                <p class="text-[11px] text-dark/45 mt-0.5">Held privately &mdash; never served from the public web root</p>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">{{ $checklistDocuments->count() }} filed</span>
        </div>

        @if ($checklistDocuments->isEmpty())
            <div class="px-6 py-10 text-center">
                <i data-lucide="file-stack" class="w-7 h-7 text-dark/20 mx-auto mb-2"></i>
                <p class="text-xs font-bold text-dark/50">Nothing filed yet.</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($checklistDocuments as $document)
                    <li class="px-6 py-3.5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-dark truncate">{{ $document->original_name ?: basename($document->file_path) }}</p>
                                <p class="text-[10px] text-dark/45">
                                    {{ \App\Models\SrrvRenewalDocument::TYPE_LABELS[$document->document_type] ?? str_replace('_', ' ', $document->document_type) }}@if ($document->remarks): {{ $document->remarks }}@endif
                                    @if ($document->file_size)
                                        <span class="text-dark/30">&middot;</span> {{ number_format($document->file_size / 1024, 0) }} KB
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('srrv.renewals.documents.download', $document) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors shrink-0">
                                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                <span>Download</span>
                            </a>
                            @unless ($showAll)
                                <button type="submit" form="renewal-remove-{{ $document->id }}" title="Remove (incorrect file)"
                                        onclick="return confirm('Remove {{ addslashes($document->original_name ?: basename($document->file_path)) }}? Use this for an incorrect file.');"
                                        class="inline-flex items-center justify-center w-8 h-8 bg-gray-100 text-dark/50 rounded-lg hover:bg-rose-600 hover:text-white transition-colors">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! $showAll)
            {{-- The upload row opens with Add file (as on the other desks); File Document saves the renewal and files it. --}}
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" onclick="document.getElementById('renewal-doc-upload').classList.toggle('hidden');"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-primary hover:text-primary transition-all">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add file
                </button>
            </div>
            <div id="renewal-doc-upload" class="{{ collect($errors->keys())->contains(fn ($key) => str_starts_with((string) $key, 'documents.')) ? '' : 'hidden ' }}px-6 py-5 border-t border-gray-100 bg-gray-50/60">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4" x-data="{ docType: '{{ old('documents.new.type', 'id_card') }}' }">
                    <div>
                        <label for="document_type" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Document Type</label>
                        <select id="document_type" name="documents[new][type]" x-model="docType" x-on:change="if (docType !== 'other' && $refs.remarks) $refs.remarks.value = ''"
                                class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                            @foreach (\App\Models\SrrvRenewalDocument::DOCUMENT_TYPES as $type)
                                <option value="{{ $type }}" @selected(old('documents.new.type') === $type)>{{ \App\Models\SrrvRenewalDocument::TYPE_LABELS[$type] }}</option>
                            @endforeach
                        </select>
                        @error('documents.new.type') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div x-show="docType === 'other'" x-cloak @unless (old('documents.new.type') === 'other' || $errors->has('documents.new.remarks')) style="display: none" @endunless>
                        <label for="document_remarks" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Remarks <span class="text-rose-500">*</span></label>
                        <input id="document_remarks" type="text" name="documents[new][remarks]" maxlength="255" value="{{ old('documents.new.remarks') }}" x-ref="remarks"
                               placeholder="What is this document?"
                               class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('documents.new.remarks') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="document_file" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">File</label>
                        <input id="document_file" type="file" name="documents[new][file]" accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="w-full px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-dark text-xs file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-[11px] file:font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('documents.new.file') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="then" value="document"
                                class="w-full px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                            File Document
                        </button>
                    </div>
                </div>
                <p class="text-[10px] text-dark/45 mt-2">JPG, PNG, WebP or PDF up to 10 MB. Stored privately and released only through this counter.</p>
            </div>
        @endif
    </div>


    <!-- Acknowledgment of Documents: printed for the retiree to sign once their documents are in (as on a new application) -->
    @php $signedScans = $renewal ? $renewal->documents->where('document_type', 'signed_acknowledgement') : collect(); @endphp
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card overflow-hidden"
         x-data="{ signed: {{ \Illuminate\Support\Js::from((bool) ($renewal?->acknowledgement_signed_at || old('acknowledgement_signed'))) }} }">
        <div class="{{ $cardHead }}">
            <span class="{{ $cardIcon }}"><i data-lucide="file-signature" class="w-4.5 h-4.5"></i></span>
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Acknowledgment of Documents</h2>
                <p class="text-[11px] text-dark/50">Once the documents are in, print the Acknowledgment, have the retiree sign it, then record it here and upload a scan or photo of the signed copy.</p>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <input type="hidden" name="acknowledgement_signed" :value="signed ? 1 : 0">
            <div class="flex flex-wrap items-center gap-3">
                @if ($renewal)
                    <a href="{{ route('srrv.renewals.acknowledgement', $renewal) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print acknowledgment
                    </a>
                @else
                    <span class="text-[11px] font-semibold text-amber-800">Save the renewal first: the acknowledgment lists the documents filed on it.</span>
                @endif
                @if ($renewal?->acknowledgement_signed_at)
                    <span class="text-xs font-semibold text-emerald-700">Signed {{ $renewal->acknowledgement_signed_at->format('M d, Y g:i A') }}.</span>
                @else
                    <button type="button" x-show="!signed" @click="signed = true"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                        <i data-lucide="pen-line" class="w-4 h-4"></i> Retiree signed the acknowledgment
                    </button>
                    <p x-show="signed" x-cloak class="text-xs font-semibold text-emerald-700">
                        Signed. Recorded when the renewal is saved.
                        <button type="button" @click="signed = false" class="ml-1 text-[11px] font-bold text-dark/50 hover:text-dark underline">Undo</button>
                    </p>
                @endif
            </div>

            <div>
                <p class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">Signed copy (scan or photo)</p>
                @foreach ($signedScans as $scan)
                    <p class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                        <i data-lucide="file-check" class="w-4 h-4 text-emerald-600"></i>
                        <span class="font-semibold text-dark">{{ $scan->original_name }}</span>
                        <span class="text-dark/50">uploaded {{ $scan->created_at?->format('M d, Y g:i A') }}</span>
                    </p>
                @endforeach
                <input type="file" name="signed_acknowledgement" accept=".pdf,.jpg,.jpeg,.png,.webp" aria-label="Scan or photo of the signed acknowledgment"
                       class="text-xs text-dark/70 file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:bg-gray-100 file:text-dark/70 file:font-bold file:text-xs">
                <p class="mt-1.5 text-[10px] text-dark/45">PDF, JPG, PNG or WEBP, up to 10 MB. Filed when the renewal is saved.</p>
                @error('signed_acknowledgement') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    </section>

    <section x-show="all || step === 4" @if (! $showAll && $startStep !== 4) style="display: none" @endif class="space-y-6">
    @include('srrv._form-details', ['details' => $renewal?->detailsWithClient(), 'forRenewal' => true])

    <!-- PRA-SD-FORM-0016: the fingerprint card prints filled from the details above; the impressions and signature are taken by hand -->
    @unless ($showAll)
    <div class="flex justify-end">
        {{-- Saves the renewal with what is typed here, then opens the filled card (its Back returns to Step 4). --}}
        <button type="submit" name="then" value="card"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
            <i data-lucide="printer" class="w-4 h-4"></i> Save &amp; print fingerprint card
        </button>
    </div>
    @unless ($renewal?->card_printed_at)
        <p class="text-right text-[11px] font-semibold text-amber-800">Save &amp; print the fingerprint card to continue to Step 5.</p>
    @endunless
    @endunless

    </section>

    <section x-show="all || step === 5" @if (! $showAll && $startStep !== 5) style="display: none" @endif class="space-y-6">
    <!-- Service agreement: printed filled from the renewal, signed by the client; the renewal is recorded only once signed -->
    <div class="rounded-2xl bg-white border shadow-card overflow-hidden {{ $errors->has('agreement_signed') ? 'border-rose-300' : 'border-gray-200' }}"
         x-data="{ signed: {{ \Illuminate\Support\Js::from((bool) ($renewal?->agreement_signed_at || old('agreement_signed'))) }} }">
        <div class="{{ $cardHead }}">
            <span class="{{ $cardIcon }}"><i data-lucide="signature" class="w-4.5 h-4.5"></i></span>
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Service Agreement</h2>
                <p class="text-[11px] text-dark/50">Print the SRRV Assistance Booking Agreement, filled from this renewal, and have the client sign it. The renewal is saved only once it is signed.</p>
            </div>
        </div>
        <div class="p-6 space-y-3">
            <input type="hidden" name="agreement_signed" :value="signed ? 1 : 0">
            <div class="flex flex-wrap items-center gap-3">
                @unless ($showAll)
                    {{-- Saves what is typed so far, then opens the filled agreement (its Back returns to Step 5). --}}
                    <button type="submit" name="then" value="agreement"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                        <i data-lucide="printer" class="w-4 h-4"></i> Save &amp; print agreement
                    </button>
                @endunless
                @if ($renewal?->agreement_signed_at)
                    <span class="text-xs font-semibold text-emerald-700">Signed {{ $renewal->agreement_signed_at->format('M d, Y g:i A') }}.</span>
                @else
                    <button type="button" x-show="!signed" @click="signed = true"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                        <i data-lucide="pen-line" class="w-4 h-4"></i> Client signed the service agreement
                    </button>
                    <p x-show="signed" x-cloak class="text-xs font-semibold text-emerald-700">
                        Signed. Recorded when the renewal is saved.
                        <button type="button" @click="signed = false" class="ml-1 text-[11px] font-bold text-dark/50 hover:text-dark underline">Undo</button>
                    </p>
                @endif
            </div>
            @error('agreement_signed') <p class="text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <!-- Remarks -->
    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
        <label for="remarks" class="block font-heading text-sm font-extrabold text-dark mb-1">Remarks</label>
        <p class="text-[11px] text-dark/50 mb-3">Recorded against the renewal.</p>
        <textarea id="remarks" name="remarks" rows="3"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('remarks', $renewal->remarks ?? '') }}</textarea>
    </div>

    @unless ($showAll)
    <div class="flex items-center justify-end gap-3">
        <a href="{{ $renewal ? route('srrv.renewals.show', $renewal) : route('srrv.renewals.index') }}"
           class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
        @unless ($renewal)
            @include('partials._save-pending', ['desk' => 'srrv', 'title' => 'SRRV ID renewal'])
        @endunless
        <button type="submit"
                class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm inline-flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>{{ $renewal ? 'Save Changes' : 'Save Renewal' }}</span>
        </button>
    </div>
    @endunless
    </section>

    @unless ($showAll)
    @include('srrv.partials._stepper-nav')
    @endunless
</div>
