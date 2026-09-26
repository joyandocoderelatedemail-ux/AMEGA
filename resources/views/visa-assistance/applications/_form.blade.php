@php
    $app = $application ?? null;
    $currentService = old('service_type', $app->service_type ?? $serviceType ?? 'visit_visa');
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;

    // The fee breakdown the form fills from (per person, all ages the same).
    $countryRates = ($countryRates ?? collect())->map(fn ($rate) => [
        'country' => $rate->country,
        'service_fee' => (float) $rate->service_fee,
        'visa_fee' => (float) $rate->visa_fee,
        'insurance_fee' => (float) $rate->insurance_fee,
        'inclusions' => $rate->inclusions,
        'notes' => $rate->condition_notes,
        'processing_time' => $rate->processing_time,
    ])->values();

    $formState = [
        'service' => $currentService,
        'countryRates' => $countryRates,
        'eVisaRate' => (float) ($eVisaRate ?? 0),
        'destination' => old('destination_country', $app->destination_country ?? ''),
        'pax' => (int) old('pax', max(1, $app?->applicants()->count() ?? 1)),
        'insured' => (bool) old('insurance_included', $app->insurance_included ?? false),
        'serviceFee' => (float) old('service_fee', $app->service_fee ?? 0),
        'visaFee' => (float) old('visa_fee', $app->visa_fee ?? 0),
        'insuranceFee' => (float) old('insurance_fee', $app->insurance_fee ?? 0),
        'etravelFee' => (float) old('etravel_fee', $app->etravel_fee ?? 0),
        'rushFee' => (float) ($rushFee ?? 0),
        'rush' => old('processing_speed', $app->processing_speed ?? 'regular') === 'rush',
    ];
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

<div x-data="visaFileForm({{ Js::from($formState) }})" class="space-y-6">

    <!-- Service -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Service</h2>
        <p class="text-[11px] text-dark/50 mb-4">Three services share this counter. The choice decides the pipeline.</p>

        @if($app)
            <input type="hidden" name="service_type" value="{{ $app->service_type }}">
            <div class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-primary/10 border border-primary/20">
                <i data-lucide="lock" class="w-4 h-4 text-primary"></i>
                <span class="text-xs font-bold text-primary uppercase tracking-wider">{{ str_replace('_', ' ', $app->service_type) }}</span>
            </div>
            <p class="text-[10px] text-dark/45 mt-2">The service is fixed once a file is open.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach([
                    'visit_visa' => ['Visit Visa', 'Counter-handled, foreign nationals', 'globe'],
                    'e_visa' => ['e-Visa', 'No appearance, no queue', 'zap'],
                    'passporting' => ['Passporting', 'Foreign or local passport', 'book-user'],
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

    <!-- Client: pick a registered client, or type a walk-in's details -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6"
         x-data="visaClientPicker({
             lookupUrl: {{ Js::from(route('visa.lookup')) }},
             name: {{ Js::from(old('client_name', $app->client_name ?? ($prefillClient['client_name'] ?? ''))) }},
             email: {{ Js::from(old('client_email', $app->client_email ?? ($prefillClient['client_email'] ?? ''))) }},
             phone: {{ Js::from(old('client_phone', $app->client_phone ?? ($prefillClient['client_phone'] ?? ''))) }},
         })"
         @click.outside="open = false" @keydown.escape="open = false">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Client</h2>
        <p class="text-[11px] text-dark/50 mb-4">Search registered clients by name, email, phone or passport, or type a new client's details below.</p>

        <div class="relative mb-4">
            <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3.5 top-3" aria-hidden="true"></i>
            <input type="search" x-model="term" @input.debounce.250ms="search()" @focus="open = clients.length > 0" autocomplete="off"
                   placeholder="Search registered clients" aria-label="Search registered clients"
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            <div x-show="open" x-cloak style="display: none"
                 class="absolute z-20 left-0 right-0 mt-1.5 rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden divide-y divide-gray-100">
                <template x-for="client in clients" :key="client.id">
                    <button type="button" @click="pick(client)"
                            class="w-full text-left px-4 py-2.5 hover:bg-primary/5 transition-colors flex items-center justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block text-xs font-bold text-dark truncate" x-text="client.name"></span>
                            <span class="block text-[11px] text-dark/50 truncate"
                                  x-text="[client.email, client.phone, client.passport ? 'Passport ' + client.passport : null].filter(Boolean).join(' · ')"></span>
                        </span>
                        <span class="text-[11px] font-bold text-primary shrink-0">Use</span>
                    </button>
                </template>
                <p x-show="searched && clients.length === 0" class="px-4 py-3 text-[11px] text-dark/60">
                    No registered client matches &ldquo;<span x-text="term.trim()"></span>&rdquo; &mdash; type their details below; they're added to the client list when the file is saved.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="client_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Full Name *</label>
                <input id="client_name" type="text" name="client_name" required x-model="name"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="client_email" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Email</label>
                <input id="client_email" type="email" name="client_email" x-model="email"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="client_phone" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Phone</label>
                <input id="client_phone" type="text" name="client_phone" x-model="phone"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>
    </div>

    <!-- Visit visa -->
    <div x-show="service === 'visit_visa'" x-cloak class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Visit Visa</h2>
        <p class="text-[11px] text-dark/50 mb-4">The destination sets everything after, and the purpose decides the requirement list.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="destination_choice" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Destination Country</label>
                <select id="destination_choice" x-model="choice" @change="pickCountry()"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Select a country</option>
                    <template x-for="rate in countryRates" :key="rate.country">
                        <option :value="rate.country" x-text="rate.country" :selected="choice === rate.country"></option>
                    </template>
                    <option value="__other" :selected="choice === '__other'">Other country&hellip;</option>
                </select>
                <input x-show="choice === '__other'" x-cloak type="text" x-model="otherDestination"
                       placeholder="Type the country" aria-label="Other destination country"
                       class="w-full mt-2 px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <input type="hidden" name="destination_country" :value="destinationValue">

                <!-- What the chosen country's price includes -->
                <div x-show="rate" x-cloak class="mt-2 rounded-xl bg-primary/5 border border-primary/15 px-3 py-2 text-[11px] text-dark/70 space-y-0.5">
                    <p>
                        <span class="font-bold text-dark" x-text="'PHP ' + money(rate ? rate.service_fee + rate.visa_fee : 0)"></span> per person
                        <span class="text-dark/50" x-text="rate ? '(service ' + money(rate.service_fee) + ' + visa fee ' + money(rate.visa_fee) + ')' : ''"></span>
                    </p>
                    <p x-show="rate && rate.inclusions" x-text="rate ? 'Includes: ' + rate.inclusions : ''"></p>
                    <p x-show="rate && rate.notes" x-text="rate ? rate.notes : ''"></p>
                </div>
                <p x-show="choice === '__other'" x-cloak class="text-[10px] text-amber-700 mt-1">Not on the fee breakdown &mdash; enter the fees below by hand.</p>
                <p class="text-[10px] text-dark/45 mt-1">Australia and New Zealand need no embassy appearance.</p>
            </div>
            <div>
                <label for="purpose" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Purpose</label>
                <select id="purpose" name="purpose"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Select a purpose</option>
                    @foreach(\App\Models\VisaApplication::PURPOSES as $purpose)
                        <option value="{{ $purpose }}" {{ old('purpose', $app->purpose ?? '') === $purpose ? 'selected' : '' }}>
                            {{ ucfirst($purpose) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="processing_speed" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Processing</label>
                <select id="processing_speed" name="processing_speed" @change="rush = $event.target.value === 'rush'"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="regular" {{ old('processing_speed', $app->processing_speed ?? '') === 'regular' ? 'selected' : '' }}>Regular &mdash; 1-3 weeks</option>
                    <option value="rush" {{ old('processing_speed', $app->processing_speed ?? '') === 'rush' ? 'selected' : '' }}>Rush &mdash; 1-5 days</option>
                </select>
                <p class="text-[10px] text-dark/45 mt-1">
                    Rush adds PHP {{ number_format($rushFee ?? 0, 2) }} to the bill.
                </p>
            </div>
            <div>
                <label for="etravel_reference" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">e-Travel Reference</label>
                <input id="etravel_reference" type="text" name="etravel_reference"
                       value="{{ old('etravel_reference', $app->etravel_reference ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>

        <label class="flex items-center gap-2.5 mt-4 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
            <input type="checkbox" name="insurance_included" value="1" x-model="insured" @change="fillInsurance()"
                   class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
            <span class="text-xs font-semibold text-dark">Travel insurance taken through us</span>
            <span x-show="rate && rate.insurance_fee" x-cloak class="text-[11px] text-dark/50"
                  x-text="rate ? '(adds PHP ' + money(rate.insurance_fee) + ' per person)' : ''"></span>
        </label>
    </div>

    <!-- e-Visa -->
    <div x-show="service === 'e_visa'" x-cloak class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">e-Visa</h2>
        <p class="text-[11px] text-dark/50 mb-4">The only thing that differs is how many people the file covers.</p>

        <div>
            <label for="applicant_type" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Application Type</label>
            <select id="applicant_type" name="applicant_type"
                    class="w-full sm:w-72 px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="individual" {{ old('applicant_type', $app->applicant_type ?? '') === 'individual' ? 'selected' : '' }}>Individual &mdash; one applicant, one set of documents</option>
                <option value="group" {{ old('applicant_type', $app->applicant_type ?? '') === 'group' ? 'selected' : '' }}>Group package &mdash; several pax under one package</option>
            </select>
            <p class="text-[10px] text-dark/45 mt-1">Same steps either way. Priced per applicant; back in 1-3 days.</p>
        </div>
    </div>

    <!-- Passporting -->
    <div x-show="service === 'passporting'" x-cloak class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Passporting</h2>
        <p class="text-[11px] text-dark/50 mb-4">Two different jobs from here on.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="passport_type" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Passport</label>
                <select id="passport_type" name="passport_type"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="foreign" {{ old('passport_type', $app->passport_type ?? '') === 'foreign' ? 'selected' : '' }}>Foreign passport</option>
                    <option value="local" {{ old('passport_type', $app->passport_type ?? '') === 'local' ? 'selected' : '' }}>Local passport (DFA)</option>
                </select>
            </div>
            <div>
                <label for="embassy_country" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Embassy</label>
                <select id="embassy_country" name="embassy_country"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Not applicable</option>
                    @foreach(\App\Models\VisaApplication::FOREIGN_PASSPORT_COUNTRIES as $country)
                        <option value="{{ $country }}" {{ old('embassy_country', $app->embassy_country ?? '') === $country ? 'selected' : '' }}>
                            {{ $country }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-dark/45 mt-1">Required for a foreign passport.</p>
            </div>
            <div>
                <label for="appointment_at" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">DFA Appointment</label>
                <input id="appointment_at" type="date" name="appointment_at"
                       value="{{ old('appointment_at', optional($app->appointment_at ?? null)->format('Y-m-d')) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">The step the timeline hangs on.</p>
            </div>
        </div>
    </div>

    <!-- Money -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Fees</h2>
        <p class="text-[11px] text-dark/50 mb-4">
            Filled from the fee breakdown &times; the number of travellers (same price for adults, children and infants).
            Adjust any amount if needed. The rush surcharge is applied from the published schedule.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="pax" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Travellers</label>
                <input id="pax" type="number" min="1" max="50" name="pax" x-model.number="pax" @input="applyRates()"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="service_fee" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Service Fee</label>
                <input id="service_fee" type="number" step="0.01" min="0" name="service_fee" x-model.number="serviceFee"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div x-show="service === 'visit_visa'">
                <label for="visa_fee" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Visa Fee / Expenses</label>
                <input id="visa_fee" type="number" step="0.01" min="0" name="visa_fee" x-model.number="visaFee"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="insurance_fee" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Insurance</label>
                <input id="insurance_fee" type="number" step="0.01" min="0" name="insurance_fee" x-model.number="insuranceFee"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="etravel_fee" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">e-Travel</label>
                <input id="etravel_fee" type="number" step="0.01" min="0" name="etravel_fee" x-model.number="etravelFee"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="amount_paid" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Amount Paid</label>
                <input id="amount_paid" type="number" step="0.01" min="0" name="amount_paid"
                       value="{{ old('amount_paid', $app->amount_paid ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>

        <!-- Running total, as the file will be billed -->
        <div class="mt-4 flex flex-wrap items-baseline justify-between gap-2 rounded-xl bg-gray-50 border border-gray-200 px-4 py-3">
            <span class="text-[11px] text-dark/60">
                Total to bill
                <span x-show="service === 'visit_visa' && rush" x-cloak x-text="'(includes rush PHP ' + money(rushFee) + ')'"></span>
            </span>
            <span class="font-heading text-base font-extrabold text-dark" x-text="'PHP ' + money(total)"></span>
        </div>

        <div class="mt-4">
            <label for="payment_type" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Payment</label>
            <select id="payment_type" name="payment_type"
                    class="w-full sm:w-72 px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="full" {{ old('payment_type', $app->payment_type ?? '') === 'full' ? 'selected' : '' }}>Paid in full</option>
                <option value="deposit" {{ old('payment_type', $app->payment_type ?? '') === 'deposit' ? 'selected' : '' }}>Deposit</option>
            </select>
        </div>
    </div>

    <!-- Remarks -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <label for="remarks" class="block font-heading text-sm font-extrabold text-dark mb-1">Remarks</label>
        <p class="text-[11px] text-dark/50 mb-3">Recorded against the file.</p>
        <textarea id="remarks" name="remarks" rows="3"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('remarks', $app->remarks ?? '') }}</textarea>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $app ? route('visa.applications.show', $app) : route('visa.applications.index') }}"
           class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
        <button type="submit"
                class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm inline-flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>{{ $app ? 'Save Changes' : 'Open Counter File' }}</span>
        </button>
    </div>
</div>

<script>
    // Visa file intake: the fee breakdown fills the fees from the destination
    // and the number of travellers; staff can still adjust every amount.
    function visaFileForm(state) {
        const rates = state.countryRates || [];
        const listed = rates.some(rate => rate.country === state.destination);

        return {
            ...state,
            choice: state.destination ? (listed ? state.destination : '__other') : '',
            otherDestination: listed ? '' : state.destination,

            init() {
                // A new file starts from the price list; a saved one keeps its amounts.
                if (!this.serviceFee) this.applyRates();
                this.$watch('service', () => this.applyRates());
            },

            get rate() {
                return rates.find(rate => rate.country === this.choice) || null;
            },

            get destinationValue() {
                return this.choice === '__other' ? this.otherDestination.trim() : this.choice;
            },

            get travellers() {
                return Math.max(1, parseInt(this.pax, 10) || 1);
            },

            get total() {
                const amounts = [this.serviceFee, this.service === 'visit_visa' ? this.visaFee : 0, this.insuranceFee, this.etravelFee];
                const rush = this.service === 'visit_visa' && this.rush ? this.rushFee : 0;
                return amounts.reduce((sum, value) => sum + (parseFloat(value) || 0), 0) + rush;
            },

            pickCountry() {
                this.applyRates();
            },

            applyRates() {
                if (this.service === 'visit_visa' && this.rate) {
                    this.serviceFee = this.rate.service_fee * this.travellers;
                    this.visaFee = this.rate.visa_fee * this.travellers;
                    this.fillInsurance();
                } else if (this.service === 'e_visa' && this.eVisaRate > 0) {
                    this.serviceFee = this.eVisaRate * this.travellers;
                    this.visaFee = 0;
                }
            },

            fillInsurance() {
                if (this.service !== 'visit_visa' || !this.rate || !this.rate.insurance_fee) return;
                this.insuranceFee = this.insured ? this.rate.insurance_fee * this.travellers : 0;
            },

            money(value) {
                return (parseFloat(value) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>

<script>
    // Client picker on a visa file: registered clients by name, email, phone
    // or passport; picking one fills the client fields.
    function visaClientPicker(config) {
        return {
            name: config.name || '',
            email: config.email || '',
            phone: config.phone || '',
            term: '',
            clients: [],
            open: false,
            searched: false,
            requestId: 0,

            async search() {
                const term = this.term.trim();
                const id = ++this.requestId;

                if (term.length < 2) {
                    this.clients = [];
                    this.open = false;
                    this.searched = false;
                    return;
                }

                try {
                    const response = await fetch(config.lookupUrl + '?q=' + encodeURIComponent(term), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok || response.redirected) return;
                    const data = await response.json();
                    if (id !== this.requestId) return;

                    this.clients = data.clients || [];
                    this.searched = true;
                    this.open = true;
                } catch (e) {
                    // Network hiccup: staff can still type the details by hand.
                }
            },

            pick(client) {
                this.name = client.name || '';
                this.email = client.email || '';
                this.phone = client.phone || '';
                this.term = '';
                this.clients = [];
                this.open = false;
                this.searched = false;
            },
        };
    }
</script>
