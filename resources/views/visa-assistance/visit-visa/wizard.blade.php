@extends('layouts.visa-assistance')

@section('title', 'New '.$serviceLabel.' File - AMEGA')

{{--
    A new Non-Immigrant (visit visa) file, step by step like the Immigration counter's forms: Step 0 Overview,
    1 Client, 2 Country & Visa Type, 3 Fees & Payment, 4 Draft Form (the destination's application form, filled in
    before the file can be sent), 5 Review & Send to Admin. Everything posts to visa.applications.store, which
    validates it again; a failed save opens on the step holding the first problem. The draft form is saved onto
    the client as the first applicant, so it already exists once the admin sees the file.
    Expects $countryRates, $picked (?array, a client from the lookup), $prefillCountry, $rushFee.
--}}
@php
    $isAdmin = auth()->user()->isAdmin();
    // The pages of the wizard: page 0 is Step 0 (the inquiry); pages 1-5 are the parts of Step 1, the draft form,
    // which ends with Send to Admin. The process itself, Step 0 to Step 6, is drawn once by visit-visa._stepper.
    $lastTitle = 'Review & Send to Admin';
    $steps = [0 => 'Inquiry & Initial Assessment', 1 => 'Client', 2 => 'Country & Visa Type', 3 => 'Fees', 4 => 'Draft Form', 5 => $lastTitle];
    // Every form a destination can resolve to (the country's own, plus Australia's business variant), for the
    // Draft Form step: only the one matching what was chosen in Country & Visa Type is ever shown.
    $worksheetKeys = array_values(array_unique([...array_values(\App\Support\VisaWorksheets::COUNTRIES), 'australia_business']));

    $intake = \App\Support\VisaChecklist::forIntake();
    $playbooks = collect(config('visa_playbooks'));
    $state = [
        'countryRates' => $countryRates->map(fn ($rate) => [
            'country' => $rate->country, 'service_fee' => (float) $rate->service_fee, 'visa_fee' => (float) $rate->visa_fee,
            'inclusions' => $rate->inclusions, 'notes' => $rate->condition_notes, 'processing_time' => $rate->processing_time,
        ])->values(),
        'variants' => $intake['variants'],
        'checklistPreview' => $intake['preview'],
        'assessments' => $playbooks->map(fn ($playbook) => array_values($playbook['tasks']['pending'] ?? []))->all(),
        'embassyFees' => $playbooks->map(fn ($playbook) => $playbook['fees'] ?? [])->all(),
        'lookupUrl' => route('visa.lookup', $serviceType === 'visit_visa' ? [] : ['service' => $serviceType]),
        'countryWorksheet' => \App\Support\VisaWorksheets::COUNTRIES,
        'rushFee' => (float) $rushFee,
        'name' => old('client_name', $picked['name'] ?? ''),
        'email' => old('client_email', $picked['email'] ?? ''),
        'phone' => old('client_phone', $picked['phone'] ?? ''),
        'pickedId' => $picked['id'] ?? null,
        'destination' => old('destination_country', $prefillCountry),
        'checklistKey' => old('checklist_key', ''),
        'purpose' => old('purpose', ''),
        'pax' => (int) old('pax', 1),
        'rush' => old('processing_speed') === 'rush',
        'serviceFee' => old('service_fee') !== null ? (float) old('service_fee') : null,
        'visaFee' => old('visa_fee') !== null ? (float) old('visa_fee') : null,
    ];

    // After a failed save, open the step holding the first problem.
    $firstError = collect($errors->keys())->first();
    $startStep = match (true) {
        $firstError === null => 0,
        str_starts_with($firstError, 'client_') => 1,
        in_array($firstError, ['destination_country', 'checklist_key', 'purpose'], true) => 1,
        $firstError === 'processing_speed' => 3,
        str_starts_with($firstError, 'form_') => 4,
        default => 3,
    };

    $label = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1';
    $field = 'w-full h-11 px-3.5 rounded-xl bg-white border border-gray-200 text-dark text-sm placeholder:text-dark/40 focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

@section('content')
<div class="max-w-5xl mx-auto">
    <form method="POST" action="{{ route('visa.applications.store') }}" novalidate
          x-data="visaWizard({{ Js::from($state) }}, {{ $startStep }}, {{ Js::from(array_values($steps)) }})"
          x-init="$watch('step', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })"
          class="space-y-6">
        @csrf
        @include('visa-assistance.visit-visa._copied-from')
        <input type="hidden" name="service_type" value="{{ $serviceType }}">

        <!-- The process, Step 0 to Step 6, drawn once (the same list as on the file): the wizard is Steps 0 and 1 -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-5">
            @include('visa-assistance.visit-visa._stepper', [
                'current' => $startStep === 0 ? 0 : 1,
                'alpine' => '(step === 0 ? 0 : 1)',
                'pill' => $serviceLabel.' · '.($picked['name'] ?? 'New client'),
                'pillAlpine' => "'".$serviceLabel." · ' + (name.trim() || 'New client')",
            ])

            {{-- Step 1, the draft form, in parts: where the agent is in it --}}
            <div x-show="step > 0" @if ($startStep === 0) style="display: none" @endif class="flex flex-wrap items-center gap-2 pt-4 border-t border-gray-100">
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/50 mr-1">Draft form</span>
                {{-- Step 2 (Country & Visa Type) is answered on the Client step, so it has no pill --}}
                @foreach (collect($steps)->except([0, 2])->all() as $number => $title)
                    <button type="button" @click="go({{ $number }})"
                            class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-xs font-bold transition-colors"
                            :class="step === {{ $number }} ? 'bg-primary text-white' : (step > {{ $number }} ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-dark/50')">
                        <span>{{ $loop->iteration }}</span><span class="hidden sm:inline">{{ $title }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4" role="alert">
                <p class="text-xs font-bold text-rose-800 mb-1">Please correct the following:</p>
                <ul class="list-disc list-inside text-[11px] text-rose-700 space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <!-- The current step's fields -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">

        {{-- Step 0: Overview --}}
        <section x-show="step === 0" @if ($startStep !== 0) style="display: none" @endif class="space-y-5" aria-labelledby="step-overview">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Inquiry &amp; Initial Assessment</h3>
            </div>
            <div class="space-y-6">
                <div>
                    <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
                    <p class="mt-2 text-sm text-dark/70 leading-relaxed">Anyone travelling to another country for a short stay: tourism, a business trip, or visiting family or friends. The destination sets the requirements, the fees and the processing time.</p>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-dark">How to apply?</h4>
                    <ol class="mt-2 space-y-3">
                        @foreach (\App\Models\VisaApplication::VISIT_VISA_STEPS as $number => $stepTitle)
                            <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $number }}</span>
                                <span class="pt-0.5"><strong class="text-dark">{{ $stepTitle }}</strong>: {{ [
                                    0 => 'we note the inquiry, check what the embassy needs, and hand over the blank forms.',
                                    1 => 'we fill in the draft form with the amount to pay and send it to the admin, who approves it or returns it; once approved it goes to the cashier.',
                                    2 => 'the booking agreement prints once the admin approves; the client signs it and pays the down payment at the cashier.',
                                    3 => 'the client brings the documents on the checklist, and signs the Acknowledgement of the forms received.',
                                    4 => 'we enter the details of the client on the official website.',
                                    5 => 'the full payment is submitted, with the appointment schedule and the biometrics date.',
                                    6 => 'the result comes in; the client is emailed when it is ready for release.',
                                ][$number] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-dark">What to bring?</h4>
                    <p class="mt-2 text-sm text-dark/70 leading-relaxed">Choose the country and the visa type to tell the client the requirements right away, or print them to hand over. What you pick here carries on to Step 2.</p>
                </div>

                {{-- The requirements, for the client at the counter: only the countries with a checklist are listed. --}}
                <div class="rounded-2xl border border-gray-200 p-4 sm:p-5 space-y-4" data-step0-requirements>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="req_country" class="{{ $label }}">Country</label>
                            <select id="req_country" x-model="choice" @change="applyRates()" class="{{ $field }}">
                                <option value="">Select a country</option>
                                @foreach (array_keys($intake['variants']) as $checklistCountry)
                                    <option value="{{ $checklistCountry }}">{{ $checklistCountry }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="req_type" class="{{ $label }}">Visa type</label>
                            <select id="req_type" x-model="checklistKey" :disabled="! typeOptions.length" class="{{ $field }}">
                                <option value="">Select the visa type</option>
                                <template x-for="option in typeOptions" :key="option.key">
                                    <option :value="option.key" x-text="option.label" :selected="option.key === checklistKey"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <p x-show="! typeOptions.length" class="text-xs text-dark/50">Choose a country to see its requirements. Countries without a checklist yet are not listed here.</p>

                    {{-- The key facts for the country --}}
                    <div x-show="typeOptions.length" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Our price</p>
                            <p class="text-sm font-bold text-dark" x-text="rate ? 'PHP ' + money(rate.service_fee + rate.visa_fee) + ' per person' : 'Not on the price list yet'"></p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Processing</p>
                            <p class="text-sm font-bold text-dark" x-text="rate && rate.processing_time ? rate.processing_time : 'Ask the embassy'"></p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Embassy appearance</p>
                            <p class="text-sm font-bold text-dark" x-text="{{ Js::from(\App\Models\VisaApplication::NO_APPEARANCE_COUNTRIES) }}.includes(destinationValue) ? 'Not needed' : 'Needed'"></p>
                        </div>
                    </div>
                    <template x-for="fee in (embassyFees[destinationValue] || [])" :key="fee"><p class="text-xs text-dark/60" x-text="fee"></p></template>

                    {{-- The checklist for the visa type --}}
                    <div x-show="checklistKey" x-cloak class="space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Documents the client needs</p>
                            <div class="flex flex-wrap items-center gap-3">
                                <a :href="{{ Js::from(route('visa.checklists.blank', '__KEY__')) }}.replace('__KEY__', checklistKey)" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 text-[11px] font-bold text-primary hover:underline"><i data-lucide="printer" class="w-3.5 h-3.5"></i> Print checklist</a>
                                @foreach (array_keys(\App\Support\VisaWorksheets::COUNTRIES) as $worksheetCountry)
                                    @foreach (\App\Support\VisaWorksheets::formsForCountry($worksheetCountry) as $worksheet)
                                        <a x-show="destinationValue === {{ Js::from($worksheetCountry) }}" href="{{ route('visa.worksheets.blank', $worksheet) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1.5 text-[11px] font-bold text-primary hover:underline"><i data-lucide="file-pen-line" class="w-3.5 h-3.5"></i> Print blank {{ \App\Support\VisaWorksheets::TITLES[$worksheet] }}</a>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                        <ul class="space-y-1.5 text-sm text-dark/80">
                            <template x-for="row in (checklistPreview[checklistKey] || [])" :key="row.label">
                                <li class="flex items-start gap-2" :class="row.indent ? 'pl-6 text-dark/60' : ''"><span class="w-4 h-4 shrink-0 flex items-center justify-center mt-0.5" aria-hidden="true"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span></span><span x-text="row.label"></span></li>
                            </template>
                        </ul>
                        <p class="text-[11px] text-dark/50">The applicant's documents. A sponsor's and a minor's lists are on the file.</p>
                    </div>
                    <p x-show="typeOptions.length && ! checklistKey" x-cloak class="text-xs text-dark/50">Choose the visa type to see its documents.</p>

                </div>
            </div>
        </section>

        {{-- Step 1: Client --}}
        <section x-show="step === 1" @if ($startStep !== 1) style="display: none" @endif class="space-y-5" aria-labelledby="step-client" @click.outside="open = false">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-client" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Client</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Search registered clients by name, email, phone or passport, or type a new client's details: they are added to the client list when the file is saved.</p>
            </div>
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input type="search" x-model="term" @input.debounce.250ms="search()" @focus="open = clients.length > 0" @keydown.escape="open = false" autocomplete="off"
                       placeholder="Search registered clients" aria-label="Search registered clients" class="{{ $field }} pl-11">
                <div x-show="open" x-cloak class="absolute z-20 left-0 right-0 mt-1.5 rounded-2xl border border-gray-200 bg-white shadow-lg overflow-hidden divide-y divide-gray-100">
                    <template x-for="found in clients" :key="found.id">
                        <button type="button" @click="pick(found)" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 flex items-center justify-between gap-3">
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-dark truncate" x-text="found.name"></span>
                                <span class="block text-xs text-dark/50 truncate" x-text="[found.email, found.phone, found.passport ? 'Passport ' + found.passport : null].filter(Boolean).join(' · ')"></span>
                            </span>
                            <span class="text-xs font-bold text-primary shrink-0">Use</span>
                        </button>
                    </template>
                    <p x-show="searched && ! clients.length" class="px-4 py-3 text-sm text-dark/60">No registered client matches &ldquo;<span x-text="term.trim()"></span>&rdquo;. Type their details below.</p>
                </div>
            </div>
            <div x-show="pickedId" x-cloak class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5">
                <p class="text-sm text-emerald-900"><span class="font-bold" x-text="name"></span> &middot; registered client</p>
                <button type="button" @click="clearClient()" class="text-xs font-bold text-primary hover:underline">Clear</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="client_name" class="{{ $label }}">Full name *</label>
                    <input id="client_name" type="text" name="client_name" x-model="name" maxlength="255" class="{{ $field }}" :class="problems.client_name && 'border-rose-400'">
                    <p x-show="problems.client_name" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.client_name"></p>
                </div>
                <div>
                    <label for="client_email" class="{{ $label }}">Email</label>
                    <input id="client_email" type="email" name="client_email" x-model="email" maxlength="255" class="{{ $field }}">
                    <p class="mt-1 text-[11px] text-dark/50">Needed to email the client updates.</p>
                </div>
                <div>
                    <label for="client_phone" class="{{ $label }}">Phone</label>
                    <input id="client_phone" type="text" name="client_phone" x-model="phone" maxlength="50" class="{{ $field }}">
                </div>
            </div>
        </section>

        {{-- Step 2: Country & Visa Type --}}
        <section x-show="step === 1" @if ($startStep !== 1) style="display: none" @endif class="space-y-5 pt-6 border-t border-gray-100" aria-labelledby="step-country">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-country" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Country &amp; Visa Type</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">The destination sets everything after, and the visa type decides the requirement list.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="destination_choice" class="{{ $label }}">Destination country *</label>
                    <select id="destination_choice" x-model="choice" @change="applyRates()" class="{{ $field }}" :class="problems.destination && 'border-rose-400'">
                        <option value="">Select a country</option>
                        <template x-for="rate in countryRates" :key="rate.country">
                            <option :value="rate.country" x-text="rate.country" :selected="choice === rate.country"></option>
                        </template>
                        <option value="__other" :selected="choice === '__other'">Other country&hellip;</option>
                    </select>
                    <input x-show="choice === '__other'" x-cloak type="text" x-model="otherDestination" placeholder="Type the country" aria-label="Other destination country" class="{{ $field }} mt-2">
                    <input type="hidden" name="destination_country" :value="destinationValue">
                    <p x-show="problems.destination" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.destination"></p>
                    <p class="mt-1 text-[11px] text-dark/50">Australia and New Zealand need no embassy appearance.</p>
                </div>
                <div>
                    <label class="{{ $label }}" x-text="typeOptions.length ? 'Visa type *' : 'Purpose *'">Purpose *</label>
                    {{-- A destination with checklists offers its visa types (which set the purpose); any other asks for a plain purpose. --}}
                    <select name="checklist_key" x-model="checklistKey" x-show="typeOptions.length" :disabled="! typeOptions.length" aria-label="Visa type" class="{{ $field }}" :class="problems.type && 'border-rose-400'">
                        <option value="">Select the visa type</option>
                        <template x-for="option in typeOptions" :key="option.key">
                            <option :value="option.key" x-text="option.label" :selected="option.key === checklistKey"></option>
                        </template>
                    </select>
                    <select name="purpose" x-model="purpose" x-show="! typeOptions.length" :disabled="typeOptions.length > 0" aria-label="Purpose" class="{{ $field }}" :class="problems.type && 'border-rose-400'">
                        <option value="">Select a purpose</option>
                        @foreach (\App\Models\VisaApplication::PURPOSES as $purpose)
                            <option value="{{ $purpose }}">{{ ucfirst($purpose) }}</option>
                        @endforeach
                    </select>
                    <p x-show="problems.type" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.type"></p>
                </div>
            </div>

            <div x-show="destinationValue" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-2xl bg-gray-50 border border-gray-100 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Checklist</p>
                        <a x-show="checklistKey" x-cloak :href="{{ Js::from(route('visa.checklists.blank', '__KEY__')) }}.replace('__KEY__', checklistKey)" target="_blank" rel="noopener"
                           class="text-[11px] font-bold text-primary hover:underline">Print blank checklist</a>
                    </div>
                    {{-- The country's own application forms, blank, to hand the client --}}
                    @foreach (array_keys(\App\Support\VisaWorksheets::COUNTRIES) as $worksheetCountry)
                        @foreach (\App\Support\VisaWorksheets::formsForCountry($worksheetCountry) as $worksheet)
                            <a x-show="destinationValue === {{ Js::from($worksheetCountry) }}" x-cloak href="{{ route('visa.worksheets.blank', $worksheet) }}" target="_blank" rel="noopener"
                               class="mt-1 mr-3 inline-flex items-center gap-1.5 text-[11px] font-bold text-primary hover:underline">
                                <i data-lucide="file-pen-line" class="w-3.5 h-3.5"></i> Print blank {{ \App\Support\VisaWorksheets::TITLES[$worksheet] }}
                            </a>
                        @endforeach
                    @endforeach
                    <p x-show="typeOptions.length && ! checklistKey" class="mt-2 text-xs text-dark/60">Choose the visa type to see its checklist.</p>
                    <ul class="mt-2 space-y-1.5 text-xs text-dark/80">
                        <li x-show="! typeOptions.length" class="flex items-start gap-2"><i data-lucide="file-check" class="w-4 h-4 shrink-0 text-primary"></i><span>Passport copy, for each traveller</span></li>
                        <template x-for="row in (checklistPreview[checklistKey] || [])" :key="row.label">
                            <li class="flex items-start gap-2" :class="row.indent ? 'pl-5 text-dark/60' : ''"><span class="w-4 h-4 shrink-0 flex items-center justify-center" aria-hidden="true"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span></span><span x-text="row.label"></span></li>
                        </template>
                    </ul>
                </div>
                <div class="rounded-2xl bg-gray-50 border border-gray-100 p-4" x-show="(assessments[destinationValue] || []).length">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Assessment checks</p>
                    <ul class="mt-2 space-y-1.5 text-xs text-dark/80">
                        <template x-for="check in (assessments[destinationValue] || [])" :key="check">
                            <li class="flex items-start gap-2"><span class="w-4 h-4 shrink-0 flex items-center justify-center" aria-hidden="true"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span><span x-text="check"></span></li>
                        </template>
                    </ul>
                    <p class="mt-2 text-[11px] text-dark/50">Ticked on the file itself, at its first stage.</p>
                </div>
            </div>
        </section>

        {{-- Step 3: Fees & Payment --}}
        <section x-show="step === 3" @if ($startStep !== 3) style="display: none" @endif class="space-y-5" aria-labelledby="step-fees">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-fees" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Fees &amp; Payment</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Filled from the price list &times; the travellers. Adjust any amount if needed.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label for="pax" class="{{ $label }}">Travellers</label>
                    <input id="pax" type="number" min="1" max="50" name="pax" x-model.number="pax" @input="applyRates()" class="{{ $field }}">
                </div>
                <div>
                    <label for="processing_speed" class="{{ $label }}">Processing</label>
                    <select id="processing_speed" name="processing_speed" x-model="speed" class="{{ $field }}">
                        <option value="regular">Regular, 1-3 weeks</option>
                        <option value="rush">Rush, 1-5 days</option>
                    </select>
                </div>
                <div>
                    <label for="service_fee" class="{{ $label }}">Service fee (PHP)</label>
                    <input id="service_fee" type="number" step="0.01" min="0" name="service_fee" x-model.number="serviceFee" class="{{ $field }}">
                </div>
                <div>
                    <label for="visa_fee" class="{{ $label }}">Visa fee (PHP)</label>
                    <input id="visa_fee" type="number" step="0.01" min="0" name="visa_fee" x-model.number="visaFee" class="{{ $field }}">
                </div>
            </div>
            <input type="hidden" name="insurance_fee" value="0">
            <input type="hidden" name="etravel_fee" value="0">

            <div>
                <label for="remarks" class="{{ $label }}">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" maxlength="2000" class="{{ $field }} h-auto py-2.5">{{ old('remarks') }}</textarea>
            </div>
        </section>

        {{-- Step 4: Draft Form (the destination's application form; it must be filled in before the file can be sent) --}}
        <section x-show="step === 4" @if ($startStep !== 4) style="display: none" @endif class="space-y-5" aria-labelledby="step-draft">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-draft" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Draft Form</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Fill in the destination's application form now: it is saved as the client's draft form, and must be done before the file is sent to the admin.</p>
            </div>
            <p x-show="problems.draftForm" x-cloak class="text-xs font-semibold text-rose-600" x-text="problems.draftForm"></p>

            <div x-ref="draftForm">
                <div x-show="! draftWorksheetKey" x-cloak class="rounded-2xl bg-gray-50 border border-gray-100 p-6 text-sm text-dark/70">
                    This destination has no application form yet. There is nothing to fill in here; continue to Review &amp; Send to Admin.
                </div>
                @foreach ($worksheetKeys as $worksheetKey)
                    {{-- Each possible destination form gets its own field prefix (form_KEY), so an unseen,
                         hidden panel's blank fields can never overwrite what was typed into the visible one. --}}
                    <div x-show="draftWorksheetKey === {{ Js::from($worksheetKey) }}" x-cloak>
                        @include('shared._form-details', [
                            'prefix' => 'form_'.$worksheetKey,
                            'sections' => \App\Support\VisaWorksheets::sections($worksheetKey),
                            'details' => [],
                            'title' => 'Draft form: '.\App\Support\VisaWorksheets::TITLES[$worksheetKey],
                            'intro' => 'Enter what the application form asks. This is saved as the client\'s draft form now, and prints once the admin approves the file.',
                        ])
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Step 5: Send for Admin Approval (an admin opens the file) --}}
        <section x-show="step === 5" @if ($startStep !== 5) style="display: none" @endif class="space-y-5" aria-labelledby="step-send">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-send" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">{{ $lastTitle }}</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Check the details, then send the draft form to the admin. The admin approves it or returns it with remarks; once approved, the booking agreement prints and the file goes to the cashier.</p>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Client</dt><dd class="font-bold text-dark" x-text="name || '—'"></dd><dd class="text-xs text-dark/50" x-text="[email, phone].filter(Boolean).join(' · ')"></dd></div>
                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Destination</dt><dd class="font-bold text-dark" x-text="destinationValue || '—'"></dd><dd class="text-xs text-dark/50" x-text="typeLabel"></dd></div>
                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Travellers</dt><dd class="font-bold text-dark" x-text="travellers + (speed === 'rush' ? ' · Rush' : ' · Regular')"></dd></div>
                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Estimated total</dt><dd class="font-bold text-dark" x-text="'PHP ' + money(total)"></dd></div>
            </dl>

            <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/60 p-4 cursor-pointer">
                <input type="checkbox" x-model="acknowledged" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span class="text-xs font-semibold text-dark leading-relaxed">
                    I acknowledge that the client's details, the destination, the visa type and the fees are correct.
                    <span class="block font-normal text-dark/60 mt-0.5">It goes to the admin for approval. Its forms print once it is approved.</span>
                </span>
            </label>

            <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-3 pt-4 border-t border-gray-100">
                @include('partials._save-pending', ['desk' => 'visa', 'title' => $serviceLabel.' file', 'class' => 'px-6 py-3 rounded-full bg-white border border-amber-300 text-amber-800 font-bold text-xs hover:bg-amber-50 inline-flex items-center justify-center gap-2'])
                <button type="submit" :disabled="! acknowledged" :class="! acknowledged && 'opacity-50 cursor-not-allowed'"
                        class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md inline-flex items-center justify-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Send to Admin
                </button>
            </div>
        </section>

        <!-- Back / Continue, as on the Immigration counter -->
        <div x-show="step < titles.length - 1" class="flex items-center justify-between gap-3 pt-6 border-t border-gray-100">
            <button type="button" @click="go(step - 1)" x-show="step > 0" style="display: none"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
            <span x-show="step === 0"></span>
            <button type="button" @click="go(step + 1)"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
                <span x-text="'Continue to ' + titles[step === 1 ? 3 : step + 1]">Continue</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>
        <div x-show="step === titles.length - 1" style="display: none" class="flex items-center justify-start pt-6 border-t border-gray-100">
            <button type="button" @click="go(step - 1)"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
        </div>
        </div>
    </form>
</div>

<script>
    // The Non-Immigrant wizard: the client lookup, the country's visa types and price, and a check before each step.
    function visaWizard(state, startStep, titles) {
        const rates = state.countryRates || [];
        const listed = rates.some(rate => rate.country === state.destination);

        return {
            ...state,
            titles,
            step: startStep,
            choice: state.destination ? (listed ? state.destination : '__other') : '',
            otherDestination: listed ? '' : state.destination,
            speed: state.rush ? 'rush' : 'regular',
            term: '',
            clients: [],
            open: false,
            searched: false,
            requestId: 0,
            acknowledged: false,
            problems: {},

            init() {
                if (this.serviceFee === null || this.visaFee === null) {
                    this.serviceFee = 0;
                    this.visaFee = 0;
                    this.applyRates();
                }
                this.$watch('destinationValue', () => {
                    if (! this.typeOptions.some(option => option.key === this.checklistKey)) this.checklistKey = '';
                });
            },

            get typeOptions() { return (this.variants || {})[this.destinationValue] || []; },
            get typeLabel() {
                const option = this.typeOptions.find(o => o.key === this.checklistKey);
                return option ? option.label : (this.purpose ? this.purpose.charAt(0).toUpperCase() + this.purpose.slice(1) : '');
            },
            get rate() { return rates.find(rate => rate.country === this.choice) || null; },
            get destinationValue() { return this.choice === '__other' ? this.otherDestination.trim() : this.choice; },
            // The destination's application form, Australia's business visa form for a business trip: mirrors
            // VisaWorksheets::resolve() so the Draft Form step shows the same form the file is saved with.
            get draftWorksheetKey() {
                const key = (this.countryWorksheet || {})[this.destinationValue];
                if (! key) return null;
                const business = this.checklistKey.includes('business') || this.purpose === 'business';

                return key === 'australia_tourist' && business ? 'australia_business' : key;
            },
            get travellers() { return Math.max(1, parseInt(this.pax, 10) || 1); },
            get total() {
                return (parseFloat(this.serviceFee) || 0) + (parseFloat(this.visaFee) || 0) + (this.speed === 'rush' ? this.rushFee : 0);
            },

            applyRates() {
                if (! this.rate) return;
                this.serviceFee = this.rate.service_fee * this.travellers;
                this.visaFee = this.rate.visa_fee * this.travellers;
            },

            // What stops this step, by field; empty when it may move on.
            check(step) {
                const problems = {};
                if (step === 1 && ! this.name.trim()) problems.client_name = 'Enter the client\'s full name.';
                if (step === 1 && ! this.destinationValue) problems.destination = 'Choose the destination country.';
                if (step === 1 && this.destinationValue && ! (this.typeOptions.length ? this.checklistKey : this.purpose)) {
                    problems.type = this.typeOptions.length ? 'Choose the visa type.' : 'Choose the purpose of the trip.';
                }
                // The draft form must be filled in (at least one answer) before the file can move on to be sent.
                if (step === 4 && this.draftWorksheetKey && this.$refs.draftForm) {
                    const filled = Array.from(this.$refs.draftForm.querySelectorAll('[name^="form_"]'))
                        .some(field => field.offsetParent !== null && (field.value || '').trim() !== '');
                    if (! filled) problems.draftForm = 'Fill in the draft form before continuing: it is required before the file can be sent to the admin.';
                }
                return problems;
            },

            go(target) {
                if (target < 0 || target >= this.titles.length) return;
                // Country & Visa Type (2) is part of the Client step: skip over it.
                if (target === 2) target = this.step < 2 ? 3 : 1;
                // Forward: every step before the target must be complete.
                for (let s = this.step; s < target; s++) {
                    const problems = this.check(s);
                    if (Object.keys(problems).length) {
                        this.problems = problems;
                        this.step = s;
                        return;
                    }
                }
                this.problems = {};
                this.step = target;
            },

            async search() {
                const term = this.term.trim();
                const id = ++this.requestId;
                if (term.length < 2) { this.clients = []; this.open = false; this.searched = false; return; }
                try {
                    const response = await fetch(this.lookupUrl + (this.lookupUrl.includes('?') ? '&q=' : '?q=') + encodeURIComponent(term), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    if (! response.ok || response.redirected) return;
                    const data = await response.json();
                    if (id !== this.requestId) return;
                    this.clients = data.clients || [];
                    this.searched = true;
                    this.open = true;
                } catch (e) {
                    // Network hiccup: the details can still be typed by hand.
                }
            },

            pick(found) {
                this.name = found.name || '';
                this.email = found.email || '';
                this.phone = found.phone || '';
                this.pickedId = found.id;
                this.term = '';
                this.clients = [];
                this.open = false;
                this.problems = {};
            },

            clearClient() {
                this.name = this.email = this.phone = '';
                this.pickedId = null;
            },

            money(value) {
                return (parseFloat(value) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>
@endsection
