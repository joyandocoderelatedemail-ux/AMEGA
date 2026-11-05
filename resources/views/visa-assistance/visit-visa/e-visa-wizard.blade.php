@extends('layouts.visa-assistance')

@section('title', 'New '.$serviceLabel.' File - AMEGA')

{{--
    A new E-Visa, Passporting or Citizenship file: Step 0 Who Can Apply & General Requirements (read with the client), Step 1 Client Information.
    Saving Step 1 opens the file (visa.applications.store) on Step 2, the service agreement and fees (sent to the admin). Expects $picked (?array, a client from the lookup), $prefillCountry, $countryRates, $serviceType.
--}}
@php
    $isPassport = $serviceType === 'passporting';
    $isCitizen = $serviceType === 'citizenship';
    $processSteps = match ($serviceType) {
        'passporting' => \App\Models\VisaApplication::PASSPORT_STEPS,
        'citizenship' => \App\Models\VisaApplication::CITIZENSHIP_STEPS,
        default => \App\Models\VisaApplication::E_VISA_STEPS,
    };
    $startStep = $errors->any() ? 1 : 0;
    $label = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1';
    $field = 'w-full h-11 px-3.5 rounded-xl bg-white border border-gray-200 text-dark text-sm placeholder:text-dark/40 focus:outline-none focus:ring-2 focus:ring-primary';
    $state = [
        'name' => old('client_name', $picked['name'] ?? ''),
        'email' => old('client_email', $picked['email'] ?? ''),
        'phone' => old('client_phone', $picked['phone'] ?? ''),
        'pickedId' => $picked['id'] ?? null,
        'destination' => old('destination_country', $prefillCountry ?? ''),
        'passportType' => old('passport_type', ''),
        'embassy' => old('embassy_country', ''),
        'claimType' => old('claim_type', ''),
        'parent' => old('us_citizen_parent', ''),
    ];
@endphp

@section('content')
<div class="max-w-5xl mx-auto">
    <form method="POST" action="{{ route('visa.applications.store') }}" novalidate
          x-data="eVisaWizard({{ Js::from($state) }}, {{ $startStep }})"
          x-init="$watch('step', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })"
          class="space-y-6">
        @csrf
        <input type="hidden" name="service_type" value="{{ $serviceType }}">
        @unless ($isPassport || $isCitizen)
            <input type="hidden" name="applicant_type" value="individual">
        @endunless

        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card">
            @include('visa-assistance.visit-visa._stepper', [
                'current' => $startStep,
                'alpine' => 'step',
                'processSteps' => $processSteps,
                'processLabel' => $serviceLabel,
                'pill' => $serviceLabel.' · '.($picked['name'] ?? 'New client'),
                'pillAlpine' => "'".$serviceLabel." · ' + (name.trim() || 'New client')",
            ])
        </div>

        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4" role="alert">
                <p class="text-xs font-bold text-rose-800 mb-1">Please correct the following:</p>
                <ul class="list-disc list-inside text-[11px] text-rose-700 space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">

            {{-- Step 0: Who can apply, and the general requirements --}}
            <section x-show="step === 0" @if ($startStep !== 0) style="display: none" @endif class="space-y-6" aria-labelledby="ev-step-info">
                <div class="border-b border-gray-100 pb-2">
                    <h3 id="ev-step-info" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Who Can Apply &amp; General Requirements</h3>
                    <p class="text-[11px] text-dark/50 mt-0.5">Go through this with the client first. {{ $isCitizen ? 'The US Embassy sets the rules, so check its current checklist before the file is opened.' : "Every country sets its own rules, so check the destination's official e-visa site before the file is opened." }}</p>
                </div>

                @if ($isCitizen)
                <div>
                    <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
                    <p class="mt-2 text-sm text-dark/80 leading-relaxed">A person <strong>born outside the US to a US citizen parent</strong> whose parent met the physical-presence rules on the day of the birth.</p>
                    <ul class="mt-2 space-y-2 text-sm text-dark/80 leading-relaxed list-disc list-inside">
                        <li><strong>Under 18:</strong> a CRBA (Consular Report of Birth Abroad), through eCRBA. It must be filed before the 18th birthday, and sooner is better.</li>
                        <li><strong>18 and over:</strong> no CRBA. The citizenship is documented with a first-time US passport (DS-11, fee paid at Pay.gov).</li>
                        <li>The applicant is present at the interview, whatever their age. For a minor, both parents come; if the US citizen parent cannot, the other parent brings a notarized DS-5507.</li>
                        <li>Someone other than a parent or child applying brings a certified copy of the legal guardianship.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-dark">The US citizen parent's time in the US before the birth</h4>
                    <ul class="mt-2 space-y-2 text-sm text-dark/80 leading-relaxed list-disc list-inside">
                        <li>Born in wedlock to a US citizen father and a non-citizen parent, or out of wedlock to one US citizen parent and one non-citizen parent, on or after 14 Nov 1986: <strong>5 years</strong> in the US, at least 2 of them after age 14.</li>
                        <li>Born to two US citizen parents married at the birth: one of them had lived in the US before the birth.</li>
                        <li>Born out of wedlock to a US citizen mother: <strong>1 continuous year</strong> if born on or before 11 Jun 2017, or <strong>5 years</strong> (2 after age 14) if born on or after 12 Jun 2017.</li>
                        <li>Born out of wedlock to a US citizen father: legitimation evidence is needed too.</li>
                        <li>Any other case: check the Embassy's eligibility page (ph.usembassy.gov, eligibility to transmit US citizenship).</li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-dark">General requirements</h4>
                    <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-dark/80">
                        @foreach ([
                            "Applicant's original PSA birth certificate (not LCR or NSO)",
                            'Two 2x2 passport photos of the applicant, and one for each parent, white background, within 6 months',
                            "US citizen parent's proof of citizenship (US passport, birth certificate, CRBA or naturalization)",
                            'Two valid IDs of the parent or parents',
                            'Marriage certificate, and any divorce, annulment or death certificate',
                            "Proof of the US citizen parent's physical presence in the US (passports, school records, W2, DD214, SSA statement)",
                            "Evidence of the mother's pregnancy and of both parents being together at conception, where it applies",
                            'Photos of the applicant growing up; adults bring old and current passports and IDs',
                            'Minor passport as well: DS-11 with barcode and a photo, DS-3053 if a parent cannot attend, a statement of no SSN, and the fee',
                        ] as $requirement)
                            <li class="flex items-start gap-2"><span class="w-4 h-4 shrink-0 flex items-center justify-center mt-0.5" aria-hidden="true"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span></span><span>{{ $requirement }}</span></li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-[11px] text-dark/50">Do not start DNA testing unless the consular officer asks for it. Paper applications are no longer accepted: Manila takes an email request with the payment confirmation (ManilaCRBAappt@state.gov), Cebu uses its appointment system.</p>
                </div>
                @elseif ($isPassport)
                <div>
                    <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
                    <ul class="mt-2 space-y-2 text-sm text-dark/80 leading-relaxed list-disc list-inside">
                        <li><strong>Philippine passport:</strong> a Filipino citizen applying or renewing at the DFA, by online appointment.</li>
                        <li><strong>Foreign passport:</strong> a foreign passport applied for or renewed through the country's embassy or consulate (USA, Canada, Australia, United Kingdom).</li>
                        <li>A minor applies with a parent or guardian who brings their own valid ID.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-dark">General requirements</h4>
                    <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-dark/80">
                        @foreach ([
                            'Philippine passport: valid ID and a PSA birth certificate',
                            'Renewal: the old passport',
                            'Foreign passport: a photo that meets the embassy specification',
                            "The embassy's own forms and fee, where they apply",
                            'Accurate details, matching the documents exactly',
                            'A contact number and email address',
                        ] as $requirement)
                            <li class="flex items-start gap-2"><span class="w-4 h-4 shrink-0 flex items-center justify-center mt-0.5" aria-hidden="true"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span></span><span>{{ $requirement }}</span></li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-[11px] text-dark/50">The DFA and each embassy set the exact list and the fees. Check the current requirements before booking an appointment.</p>
                </div>

                @else
                <div>
                    <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
                    <ul class="mt-2 space-y-2 text-sm text-dark/80 leading-relaxed list-disc list-inside">
                        <li>Eligibility depends on the applicant's <strong>nationality</strong> (the country that issued the passport) and on the <strong>purpose</strong> of the trip: tourism, business, medical, training or a conference.</li>
                        <li>Many countries accept e-visas only from a list of eligible nationalities. Check that list on the official site first.</li>
                        <li>A spouse, minor child, parent or sibling can usually be applied for by a family member, with that country's conditions.</li>
                        <li>An agency can file on the client's behalf, through the country's official e-visa portal only.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-bold text-dark">General requirements</h4>
                    <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-dark/80">
                        @foreach ([
                            'Passport valid for at least 6 months beyond the planned exit date, not damaged',
                            'Clear scan of the passport data page (photo, name, date of birth, validity)',
                            'Recent colour ID photo on a plain background (taken within 3 to 6 months)',
                            'Round-trip flight itinerary',
                            "Hotel booking, or a host's invitation letter",
                            'Proof of funds, such as recent bank statements',
                            'Email address and a card to pay the fee online',
                            'Accurate details, matching the passport exactly',
                        ] as $requirement)
                            <li class="flex items-start gap-2"><span class="w-4 h-4 shrink-0 flex items-center justify-center mt-0.5" aria-hidden="true"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span></span><span>{{ $requirement }}</span></li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-[11px] text-dark/50">Applications are often refused for wrong details, an unpaid fee, a poor photo or an applicant who is not eligible. Processing usually takes several working days, so file at least three weeks before the flight.</p>
                </div>

                @endif

                <div>
                    <h4 class="text-sm font-bold text-dark">How the file goes</h4>
                    <ol class="mt-2 space-y-3">
                        @foreach ($processSteps as $number => $stepTitle)
                            <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $number }}</span>
                                <span class="pt-0.5"><strong class="text-dark">{{ $stepTitle }}</strong>: {{ [
                                    0 => 'we check that the client can apply and what is needed.',
                                    1 => $isCitizen ? "we take the client's details, whether it is a CRBA or a first-time passport, and the US citizen parent." : "we take the client's details and the destination.",
                                    2 => 'the service agreement is printed and signed by the client; we enter the fee and send the file to the admin, and once approved it goes to the cashier for payment.',
                                    3 => 'we collect the documents and the client signs the Acknowledgment of Documents.',
                                    4 => $isCitizen ? 'we file the application (eCRBA, or the DS-11 with the fee paid), request the Embassy appointment, and record its date.' : ($isPassport ? 'we record the DFA appointment (Philippine passport) or the embassy submission (foreign passport).' : 'we enter the details on the e-visa site and keep the reference number it gives, or a copy of what was submitted.'),
                                    5 => 'the result of the application is recorded and the file is released.',
                                ][$number] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- Step 1: Client information --}}
            <section x-show="step === 1" @if ($startStep !== 1) style="display: none" @endif class="space-y-5" aria-labelledby="ev-step-client">
                <div class="border-b border-gray-100 pb-2">
                    <h3 id="ev-step-client" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Client Information</h3>
                    <p class="text-[11px] text-dark/50 mt-0.5">Type the client's details: they are added to the client list when the file is saved.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="client_name" class="{{ $label }}">Full name *</label>
                        <input id="client_name" type="text" name="client_name" x-model="name" maxlength="255" class="{{ $field }}" :class="problems.name && 'border-rose-400'">
                        <p x-show="problems.name" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.name"></p>
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
                @if ($isCitizen)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="claim_type" class="{{ $label }}">Application *</label>
                        <select id="claim_type" name="claim_type" x-model="claimType" class="{{ $field }}" :class="problems.claim && 'border-rose-400'">
                            <option value="">Select the application</option>
                            @foreach (\App\Models\VisaApplication::CLAIM_TYPES as $value => $text)
                                <option value="{{ $value }}">{{ $text }}</option>
                            @endforeach
                        </select>
                        <p x-show="problems.claim" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.claim"></p>
                    </div>
                    <div>
                        <label for="us_citizen_parent" class="{{ $label }}">US citizen parent *</label>
                        <input id="us_citizen_parent" type="text" name="us_citizen_parent" x-model="parent" maxlength="255" class="{{ $field }}" :class="problems.parent && 'border-rose-400'">
                        <p x-show="problems.parent" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.parent"></p>
                    </div>
                @elseif ($isPassport)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="passport_type" class="{{ $label }}">Passport *</label>
                        <select id="passport_type" name="passport_type" x-model="passportType" class="{{ $field }}" :class="problems.passport && 'border-rose-400'">
                            <option value="">Select the passport</option>
                            @foreach (\App\Models\VisaApplication::PASSPORT_TYPES as $type)
                                <option value="{{ $type }}">{{ $type === 'local' ? 'Philippine passport (DFA)' : 'Foreign passport (embassy)' }}</option>
                            @endforeach
                        </select>
                        <p x-show="problems.passport" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.passport"></p>
                    </div>
                    <div x-show="passportType === 'foreign'" x-cloak>
                        <label for="embassy_country" class="{{ $label }}">Embassy country *</label>
                        <select id="embassy_country" name="embassy_country" x-model="embassy" :disabled="passportType !== 'foreign'" class="{{ $field }}" :class="problems.embassy && 'border-rose-400'">
                            <option value="">Select the embassy</option>
                            @foreach (\App\Models\VisaApplication::FOREIGN_PASSPORT_COUNTRIES as $country)
                                <option value="{{ $country }}">{{ $country }}</option>
                            @endforeach
                        </select>
                        <p x-show="problems.embassy" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.embassy"></p>
                    </div>
                @else
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-1">
                        <label for="destination_country" class="{{ $label }}">Destination country *</label>
                        <input id="destination_country" type="text" name="destination_country" x-model="destination" list="ev-countries" maxlength="255" class="{{ $field }}" :class="problems.destination && 'border-rose-400'">
                        <datalist id="ev-countries">
                            @foreach ($countryRates as $rate)<option value="{{ $rate->country }}"></option>@endforeach
                        </datalist>
                        <p x-show="problems.destination" x-cloak class="mt-1 text-[11px] font-semibold text-rose-600" x-text="problems.destination"></p>
                    </div>
                @endif
                    <div class="sm:col-span-2">
                        <label for="remarks" class="{{ $label }}">Remarks</label>
                        <input id="remarks" type="text" name="remarks" maxlength="2000" value="{{ old('remarks') }}" class="{{ $field }}">
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="submit($event)"
                            class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md inline-flex items-center justify-center gap-2">
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        Save &amp; continue to Service Agreement &amp; Fees
                    </button>
                </div>
            </section>

            <div class="flex items-center justify-between gap-3 pt-6 border-t border-gray-100">
                <button type="button" @click="step = 0" x-show="step === 1" style="display: none"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i><span>Back</span>
                </button>
                <span x-show="step === 0"></span>
                <button type="button" @click="step = 1" x-show="step === 0"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
                    <span>Continue to Client Information</span><i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    // The E-Visa wizard: a check of Step 1 before the file is opened.
    function eVisaWizard(state, startStep) {
        return {
            ...state,
            step: startStep,
            problems: {},
            submit(event) {
                this.problems = {};
                if (!this.name.trim()) this.problems.name = "Enter the client's full name.";
                @if ($isCitizen)
                if (!this.claimType) this.problems.claim = 'Choose the application.';
                if (!this.parent.trim()) this.problems.parent = "Enter the US citizen parent's name.";
                @elseif ($isPassport)
                if (!this.passportType) this.problems.passport = 'Choose the passport.';
                if (this.passportType === 'foreign' && !this.embassy) this.problems.embassy = 'Choose the embassy.';
                @else
                if (!this.destination.trim()) this.problems.destination = 'Choose or type the destination country.';
                @endif
                if (Object.keys(this.problems).length) return;
                event.target.closest('form').submit();
            },
        };
    }
</script>
@endsection
