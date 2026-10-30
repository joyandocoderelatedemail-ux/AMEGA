@php
    use App\Models\TouristVisaApplication as TVA;

    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $label = 'block text-xs font-semibold text-slate-600 mb-1.5';
    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500';
    $choice = 'flex items-center gap-2.5 h-10 px-3 rounded-lg border border-slate-200 bg-white text-sm text-slate-700 cursor-pointer hover:border-slate-300 has-[:checked]:border-navy-700 has-[:checked]:ring-1 has-[:checked]:ring-navy-700 has-[:checked]:text-slate-900';
    $radio = 'w-4 h-4 text-navy-700 border-slate-300 focus:ring-navy-500';
    $sectionHead = 'px-4 sm:px-5 py-4 border-b border-slate-200';
    $sectionTitle = 'text-base font-bold text-slate-900';
    $sectionNote = 'mt-0.5 text-sm text-slate-500';

    $app = $application;
    $date = fn (string $field) => old($field, $app->{$field}?->format('Y-m-d'));
    $value = fn (string $field) => old($field, $app->{$field});
    $checked = old('checklist', $app->checklist ?? []);
@endphp

@if ($errors->any())
    <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
        <p class="text-sm font-semibold text-rose-900">Please correct the following:</p>
        <ul class="mt-1.5 space-y-0.5 text-sm text-rose-800 list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-6"
     x-data="touristVisaForm({{ Js::from([
         'method' => $value('application_method') ?: 'personal',
         'overstayed' => (bool) old('overstayed', $app->overstayed),
         'mode' => $value('application_mode') ?: 'regular',
         'processing' => $value('processing_time') ?: TVA::PROCESSING_TIMES[$value('application_mode') ?: 'regular'],
         'defaults' => TVA::PROCESSING_TIMES,
         'payType' => $value('payment_type') ?: 'full',
         'terms' => (int) $value('terms_count') ?: '',
         'total' => (float) $value('total_fee') ?: '',
     ]) }})">

    <!-- Checklist of Documentary Requirements (IRD04.QF.007) -->
    <section id="checklist" class="scroll-mt-32 {{ $card }}">
        <div class="{{ $sectionHead }}">
            <h2 class="{{ $sectionTitle }}">Checklist of Documentary Requirements</h2>
            <p class="{{ $sectionNote }}">Tick each document as the client hands it over, in the order the BI lists them, and upload the client's file for it. A file in hand ticks its requirement.</p>
        </div>
        <div class="p-4 sm:p-5 space-y-3">
            @foreach (TVA::CHECKLIST as $key => $item)
                @include('visa-assistance.tourist-visa._requirement', ['key' => $key, 'text' => $item, 'number' => $loop->iteration, 'optional' => in_array($key, TVA::CHECKLIST_IF_APPLICABLE, true)])
            @endforeach

            <div class="pt-2">
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="overstayed" value="1" x-model="overstayed"
                           class="w-4 h-4 rounded text-navy-700 border-slate-300 focus:ring-navy-500">
                    <span class="text-sm font-semibold text-slate-800">The applicant overstayed for more than six (6) months or more than the maximum allowable stay</span>
                </label>
            </div>

            <div x-show="overstayed" x-cloak class="space-y-3">
                <p class="text-sm italic text-slate-600">Additional requirement(s) if applicant overstayed for more than six (6) months or more than the maximum allowable stay:</p>
                @foreach (TVA::OVERSTAY_CHECKLIST as $key => $item)
                    @include('visa-assistance.tourist-visa._requirement', ['key' => $key, 'text' => $item])
                @endforeach
                <div class="rounded-lg border border-slate-200 p-3 space-y-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">2x2 photo</p>
                        <p class="mt-0.5 text-sm text-slate-600">Colored, white background, close-up full-face, taken within the past three months. Computer-generated or photocopied photos are not accepted. It prints in the form's photo box.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($app->photo_path)
                            <img src="{{ route('visa.tourist-visa.photo', $app) }}?v={{ $app->updated_at?->timestamp }}" alt="Applicant photo" class="w-24 h-24 rounded-lg border border-slate-200 object-cover bg-white">
                        @endif
                        <div class="min-w-0 flex-1 space-y-2">
                            <label for="photo" class="{{ $label }}">{{ $app->photo_path ? 'Replace the photo' : 'Upload the photo' }}</label>
                            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                                   class="block w-full text-sm text-slate-700 file:mr-3 file:h-10 file:px-4 file:rounded-lg file:border-0 file:bg-slate-100 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                            <p class="text-xs text-slate-500">JPG, PNG or WebP, up to 5 MB.</p>
                            @if ($app->photo_path)
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                    <input type="checkbox" name="remove_photo" value="1" class="w-4 h-4 rounded text-navy-700 border-slate-300 focus:ring-navy-500">
                                    Remove the current photo
                                </label>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <p x-show="method === 'representative'" x-cloak class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5 text-sm text-amber-900">
                <i data-lucide="user-round-check" class="w-4 h-4 mt-0.5 shrink-0"></i>
                Filed by an authorized representative: attach a photocopy of the BI Accreditation ID Certificate, or an original Special Power of Attorney for each applicant with a photocopy of the attorney-in-fact's valid government-issued ID.
            </p>

            <p class="text-xs text-slate-500"><span class="font-semibold">Note:</span> The BI may require the submission of additional documents for further evaluation of the application.</p>
        </div>
    </section>

    <!-- CGAF: method and I. Application information -->
    <section class="{{ $card }}">
        <div class="{{ $sectionHead }}">
            <h2 class="{{ $sectionTitle }}">Application Form (CGAF)</h2>
            <p class="{{ $sectionNote }}">Consolidated General Application Form for Extension of Stay of Temporary Visitor’s Visa.</p>
        </div>
        <div class="p-4 sm:p-5 space-y-5">
            <fieldset>
                <legend class="{{ $label }}">Method of Application</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach (TVA::METHODS as $key => $text)
                        <label class="{{ $choice }}">
                            <input type="radio" name="application_method" value="{{ $key }}" x-model="method" class="{{ $radio }}">
                            {{ $text }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div x-show="method === 'representative'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="representative_name" class="{{ $label }}">Name of Authorized Representative <span class="font-normal text-slate-400">(Last name, First name, Middle name)</span></label>
                    <input id="representative_name" name="representative_name" value="{{ $value('representative_name') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="bi_accreditation_number" class="{{ $label }}">BI Accreditation Number <span class="font-normal text-slate-400">(if applicable)</span></label>
                    <input id="bi_accreditation_number" name="bi_accreditation_number" value="{{ $value('bi_accreditation_number') }}" class="{{ $input }}">
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_2fr] gap-5">
                <fieldset>
                    <legend class="{{ $label }}">Mode of Application</legend>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (TVA::MODES as $key => $text)
                            <label class="{{ $choice }}">
                                <input type="radio" name="application_mode" value="{{ $key }}" x-model="mode" class="{{ $radio }}">
                                {{ $text }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="{{ $label }}">Number of Months Requested</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach (TVA::MONTHS as $months => $text)
                            <label class="{{ $choice }}">
                                <input type="radio" name="months_requested" value="{{ $months }}" @checked((int) $value('months_requested') === $months) class="{{ $radio }}">
                                {{ $text }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div>
                <label for="reason" class="{{ $label }}">Reason for extension of stay, including reason for overstaying, if applicable</label>
                <textarea id="reason" name="reason" rows="4" maxlength="2000"
                          class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500">{{ $value('reason') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mobile_number" class="{{ $label }}">Mobile No. <span class="font-normal text-slate-400">(in the Philippines)</span></label>
                    <input id="mobile_number" name="mobile_number" value="{{ $value('mobile_number') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="email" class="{{ $label }}">E-mail address</label>
                    <input type="email" id="email" name="email" value="{{ $value('email') }}" class="{{ $input }}">
                </div>
            </div>
        </div>
    </section>

    <!-- II. Personal information -->
    <section class="{{ $card }}">
        <div class="{{ $sectionHead }}">
            <h2 class="{{ $sectionTitle }}">II. Personal Information</h2>
            <p class="{{ $sectionNote }}">Search a registered client to fill this section, the contact details and the passport from their record.</p>
        </div>
        <div class="p-4 sm:p-5 space-y-4">
            <!-- Registered client: fills the form from the CRM record -->
            <div class="relative" x-data="touristClientPicker({{ Js::from(route('visa.tourist-visa.clients')) }})"
                 @click.outside="open = false" @keydown.escape="open = false">
                <label for="client_search" class="{{ $label }}">Registered client</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="search" id="client_search" x-model="term" @input.debounce.250ms="search()" @focus="open = results.length > 0"
                           @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                           @keydown.enter="if (highlighted >= 0) { $event.preventDefault(); pick(results[highlighted]); }"
                           placeholder="Search by name, passport, email or phone" autocomplete="off"
                           role="combobox" aria-autocomplete="list" aria-controls="client-search-results" :aria-expanded="open"
                           class="{{ $input }} pl-9">
                </div>

                <div x-show="open" x-cloak style="display: none" id="client-search-results" role="listbox"
                     class="absolute z-30 left-0 right-0 mt-1.5 bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden">
                    <template x-for="(client, i) in results" :key="client.id">
                        <button type="button" role="option" :aria-selected="highlighted === i" @click="pick(client)" @mouseenter="highlighted = i"
                                class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left" :class="highlighted === i ? 'bg-slate-100' : ''">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900 truncate" x-text="client.label"></span>
                                <span class="block text-xs text-slate-500 truncate" x-text="client.detail || 'No other details on record'"></span>
                            </span>
                            <span class="text-xs font-semibold text-navy-700 shrink-0">Fill form</span>
                        </button>
                    </template>
                    <p x-show="searched && results.length === 0" class="px-4 py-3 text-sm text-slate-500">
                        No registered client matches &ldquo;<span x-text="term.trim()"></span>&rdquo;. Type the details below instead.
                    </p>
                </div>

                <p x-show="filled" x-cloak class="mt-2 text-sm text-emerald-700 font-semibold">
                    Filled <span x-text="filledCount"></span> fields from <span x-text="filled"></span>'s record. Check them, and add what the record does not have.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="last_name" class="{{ $label }}">Last name <span class="text-rose-600">*</span></label>
                    <input id="last_name" name="last_name" value="{{ $value('last_name') }}" required class="{{ $input }}">
                </div>
                <div>
                    <label for="first_name" class="{{ $label }}">First name <span class="text-rose-600">*</span></label>
                    <input id="first_name" name="first_name" value="{{ $value('first_name') }}" required class="{{ $input }}">
                </div>
                <div>
                    <label for="middle_name" class="{{ $label }}">Middle name</label>
                    <input id="middle_name" name="middle_name" value="{{ $value('middle_name') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="alias" class="{{ $label }}">Other name/Alias</label>
                    <input id="alias" name="alias" value="{{ $value('alias') }}" class="{{ $input }}">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="date_of_birth" class="{{ $label }}">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="{{ $date('date_of_birth') }}" class="{{ $input }}">
                </div>
                <fieldset>
                    <legend class="{{ $label }}">Sex</legend>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (['M' => 'Male', 'F' => 'Female'] as $key => $text)
                            <label class="{{ $choice }}">
                                <input type="radio" name="sex" value="{{ $key }}" @checked($value('sex') === $key) class="{{ $radio }}">
                                {{ $text }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="lg:col-span-2">
                    <label for="birthplace" class="{{ $label }}">Birthplace</label>
                    <input id="birthplace" name="birthplace" value="{{ $value('birthplace') }}" class="{{ $input }}">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <div>
                    <label for="citizenship" class="{{ $label }}">Country of Citizenship <span class="text-rose-600">*</span></label>
                    <input id="citizenship" name="citizenship" value="{{ $value('citizenship') }}" required class="{{ $input }}">
                </div>
                <div>
                    <label for="height_cm" class="{{ $label }}">Height (cm)</label>
                    <input id="height_cm" name="height_cm" value="{{ $value('height_cm') }}" inputmode="decimal" class="{{ $input }}">
                </div>
                <div>
                    <label for="weight_kg" class="{{ $label }}">Weight (kg)</label>
                    <input id="weight_kg" name="weight_kg" value="{{ $value('weight_kg') }}" inputmode="decimal" class="{{ $input }}">
                </div>
                <div>
                    <label for="civil_status" class="{{ $label }}">Civil Status</label>
                    <select id="civil_status" name="civil_status" class="{{ $input }}">
                        <option value="">Select</option>
                        @foreach (TVA::CIVIL_STATUSES as $key => $text)
                            <option value="{{ $key }}" @selected($value('civil_status') === $key)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="pt-1">
                <p class="text-sm font-semibold text-slate-900 mb-3">Residential Address in the Philippines</p>
                <div class="space-y-4">
                    <div>
                        <label for="address_street" class="{{ $label }}">House/Unit No., Street, Subdivision/Village</label>
                        <input id="address_street" name="address_street" value="{{ $value('address_street') }}" class="{{ $input }}">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="barangay" class="{{ $label }}">Barangay</label>
                            <input id="barangay" name="barangay" value="{{ $value('barangay') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label for="city" class="{{ $label }}">City/Municipality</label>
                            <input id="city" name="city" value="{{ $value('city') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label for="province" class="{{ $label }}">Province</label>
                            <input id="province" name="province" value="{{ $value('province') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label for="zip_code" class="{{ $label }}">Zip Code</label>
                            <input id="zip_code" name="zip_code" value="{{ $value('zip_code') }}" class="{{ $input }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- III. Travel, IV. Latest extension, V. ACR I-Card -->
    <section class="{{ $card }}">
        <div class="{{ $sectionHead }}">
            <h2 class="{{ $sectionTitle }}">III. Travel Information</h2>
        </div>
        <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="passport_number" class="{{ $label }}">Passport/Travel Document No. <span class="text-rose-600">*</span></label>
                <input id="passport_number" name="passport_number" value="{{ $value('passport_number') }}" required class="{{ $input }} font-mono uppercase">
            </div>
            <div>
                <label for="passport_validity" class="{{ $label }}">Date of Validity</label>
                <input type="date" id="passport_validity" name="passport_validity" value="{{ $date('passport_validity') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="latest_arrival_date" class="{{ $label }}">Date of Latest Arrival</label>
                <input type="date" id="latest_arrival_date" name="latest_arrival_date" value="{{ $date('latest_arrival_date') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="flight_number" class="{{ $label }}">Flight/Voyage Number</label>
                <input id="flight_number" name="flight_number" value="{{ $value('flight_number') }}" class="{{ $input }} uppercase">
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="{{ $card }}">
            <div class="{{ $sectionHead }}">
                <h2 class="{{ $sectionTitle }}">IV. Latest Visit Visa Extension</h2>
            </div>
            <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="latest_extension_validity" class="{{ $label }}">Date of Validity</label>
                    <input type="date" id="latest_extension_validity" name="latest_extension_validity" value="{{ $date('latest_extension_validity') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="latest_extension_place" class="{{ $label }}">Place of Approval</label>
                    <input id="latest_extension_place" name="latest_extension_place" value="{{ $value('latest_extension_place') }}" placeholder="e.g. BI Main Office" class="{{ $input }}">
                </div>
            </div>
        </section>

        <section class="{{ $card }}">
            <div class="{{ $sectionHead }}">
                <h2 class="{{ $sectionTitle }}">V. ACR I-Card</h2>
            </div>
            <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="acr_number" class="{{ $label }}">ACR Number</label>
                    <input id="acr_number" name="acr_number" value="{{ $value('acr_number') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="acr_validity" class="{{ $label }}">Date of Validity</label>
                    <input type="date" id="acr_validity" name="acr_validity" value="{{ $date('acr_validity') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="ssrn" class="{{ $label }}">SSRN</label>
                    <input id="ssrn" name="ssrn" value="{{ $value('ssrn') }}" class="{{ $input }}">
                </div>
            </div>
        </section>
    </div>

    @include('visa-assistance.tourist-visa._payment')

    <!-- Filing details printed on the checklist's certification and claim slip -->
    <section class="{{ $card }}">
        <div class="{{ $sectionHead }}">
            <h2 class="{{ $sectionTitle }}">Filing</h2>
            <p class="{{ $sectionNote }}">The date filed and the evaluator print on the checklist's certification and the claim slip.</p>
        </div>
        <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="filed_on" class="{{ $label }}">Date Filed</label>
                <input type="date" id="filed_on" name="filed_on" value="{{ $date('filed_on') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="filed_time" class="{{ $label }}">Time Filed</label>
                <input type="time" id="filed_time" name="filed_time" value="{{ $value('filed_time') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="processing_time" class="{{ $label }}">Processing Time</label>
                <input id="processing_time" name="processing_time" x-model="processing" list="processing-times" maxlength="100"
                       value="{{ $value('processing_time') ?: TVA::PROCESSING_TIMES[$value('application_mode') ?: 'regular'] }}" class="{{ $input }}">
                <datalist id="processing-times">
                    @foreach (TVA::PROCESSING_TIMES as $time)
                        <option value="{{ $time }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-slate-500">Follows the mode of application: regular or express.</p>
            </div>
            <div>
                <label for="evaluator_name" class="{{ $label }}">Evaluator / Assessor</label>
                <input id="evaluator_name" name="evaluator_name" value="{{ $value('evaluator_name') }}" class="{{ $input }}">
            </div>
        </div>
    </section>

    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2">
        <a href="{{ route('visa.tourist-visa.index') }}" class="inline-flex items-center justify-center h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
        @unless (request()->routeIs('visa.tourist-visa.edit'))
            @include('partials._save-pending', ['desk' => 'visa', 'title' => 'Tourist visa application', 'class' => 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-amber-300 bg-white text-sm font-semibold text-amber-800 hover:bg-amber-50'])
        @endunless
        <button type="submit" name="then" value="stay" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-navy-700 bg-white text-sm font-semibold text-navy-700 hover:bg-navy-50">
            <i data-lucide="save" class="w-4 h-4"></i> Save
        </button>
        <button type="submit" name="then" value="checklist" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-navy-700 bg-white text-sm font-semibold text-navy-700 hover:bg-navy-50">
            <i data-lucide="list-checks" class="w-4 h-4"></i> Save &amp; print checklist
        </button>
        <button type="submit" name="then" value="print" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
            <i data-lucide="printer" class="w-4 h-4"></i> Save &amp; print form
        </button>
    </div>
</div>

<script>
    // The application form's own state: who files it, the mode and its processing
    // time, and the fee split into terms.
    function touristVisaForm(config) {
        return {
            method: config.method,
            overstayed: config.overstayed,
            mode: config.mode,
            processing: config.processing,
            payType: config.payType,
            terms: config.terms,
            total: config.total,

            init() {
                // The processing time follows the mode, unless staff typed their own.
                this.$watch('mode', (mode, previous) => {
                    if (!this.processing || this.processing === config.defaults[previous]) {
                        this.processing = config.defaults[mode];
                    }
                });
            },

            peso(amount) {
                return '₱' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            // "3 payments of ₱1,000.00", or the odd first payment when it does not divide evenly.
            get plan() {
                const total = Number(this.total) || 0;
                const count = Number(this.terms) || 0;
                if (this.payType !== 'terms' || count < 2 || total <= 0) return '';

                const each = Math.floor(total * 100 / count) / 100;
                const first = Math.round((total - each * (count - 1)) * 100) / 100;

                return first === each
                    ? count + ' payments of ' + this.peso(each)
                    : 'First payment ' + this.peso(first) + ', then ' + (count - 1) + ' of ' + this.peso(each);
            },
        };
    }

    // Fills the CGAF from a registered client's record. Plain inputs are set by
    // name; only fields the record has are touched, so nothing typed is wiped.
    function touristClientPicker(url) {
        return {
            term: '',
            results: [],
            open: false,
            searched: false,
            highlighted: -1,
            filled: '',
            filledCount: 0,
            requestId: 0,

            async search() {
                const term = this.term.trim();
                const id = ++this.requestId;
                this.highlighted = -1;

                if (term.length < 2) {
                    this.results = [];
                    this.searched = false;
                    this.open = false;
                    return;
                }

                try {
                    const response = await fetch(url + '?q=' + encodeURIComponent(term), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok || id !== this.requestId) return;

                    this.results = (await response.json()).clients || [];
                    this.searched = true;
                    this.open = true;
                } catch (e) {
                    // Network hiccup: the details can still be typed by hand.
                }
            },

            move(step) {
                if (!this.open || !this.results.length) return;
                const slots = this.results.length + 1;
                this.highlighted = ((this.highlighted + 1 + step) % slots + slots) % slots - 1;
            },

            pick(client) {
                let count = 0;
                const form = this.$el.closest('form');

                Object.entries(client.fields).forEach(([name, value]) => {
                    const fields = form.querySelectorAll('[name="' + name + '"]');
                    fields.forEach(field => {
                        if (field.type === 'radio') {
                            field.checked = field.value === value;
                        } else {
                            field.value = value;
                        }
                    });
                    if (fields.length) count++;
                });

                this.filled = client.label;
                this.filledCount = count;
                this.term = '';
                this.results = [];
                this.open = false;
            },
        };
    }
</script>
