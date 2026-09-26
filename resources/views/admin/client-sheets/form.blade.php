@php
    use App\Models\ImmigrationClient;
    use App\Models\ImmigrationClientDocument;
    use App\Models\ImmigrationClientExtension;

    $field = 'w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5';
    $cell = 'w-full px-2.5 py-2 rounded-lg bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';

    /*
     * The counter process, one step at a time: personal details, passport
     * details, the immigration documents (ACR I-Card, CRTV, Annual Report),
     * the extension ledger, and finally save and print. New and returning
     * clients go through the same steps. Every step is part of one form, so
     * staff can jump between steps and nothing is lost.
     */
    $steps = [
        1 => 'Personal Details',
        2 => 'Passport Details',
        3 => ImmigrationClientDocument::TYPES['acr_icard'],
        4 => ImmigrationClientDocument::TYPES['crtv'],
        5 => ImmigrationClientDocument::TYPES['annual_report'],
        6 => 'Extension Ledger',
        7 => 'Save & Print',
    ];
    $documentSteps = [3 => 'acr_icard', 4 => 'crtv', 5 => 'annual_report'];
    $ledgerStep = 6;
    $saveStep = 7;

    // After a failed save, open the step holding the first problem.
    $passportFields = ['passport_number', 'nationality', 'visa_expiry_date', 'status_note', 'is_expired', 'has_penalty', 'needs_attention'];
    $firstError = collect($errors->keys())->first();
    $startStep = match (true) {
        $firstError === null => 1,
        in_array($firstError, $passportFields, true) => 2,
        str_starts_with($firstError, 'documents.acr_icard') => 3,
        str_starts_with($firstError, 'documents.crtv') => 4,
        str_starts_with($firstError, 'documents.annual_report') => 5,
        str_starts_with($firstError, 'extensions') => $ledgerStep,
        str_starts_with($firstError, 'notes') => $saveStep,
        default => 1,
    };

    $documentRows = [
        'Ref. Number' => ['reference_number', 'text'],
        'Date Paid' => ['date_paid', 'date'],
        'SSRN #' => ['ssrn_number', 'text'],
        'Validity' => ['validity', 'text'],
    ];
@endphp

<div x-data="{ step: {{ $startStep }}, last: {{ count($steps) }}, visited: [{{ $startStep }}] }"
     x-init="$watch('step', value => { if (! visited.includes(value)) visited.push(value); window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })"
     class="space-y-6">

    @if ($errors->any())
        <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4">
            <p class="text-xs font-bold text-rose-800 mb-1">Please correct the following:</p>
            <ul class="list-disc list-inside text-[11px] text-rose-700 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Step bar: the counter process in order; any step can be reopened -->
    <nav aria-label="Client sheet steps">
        <ol class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
            @foreach ($steps as $number => $stepLabel)
                <li>
                    <button type="button" @click="step = {{ $number }}"
                            :aria-current="step === {{ $number }} ? 'step' : null"
                            class="w-full text-left rounded-xl border px-3 py-2 transition-colors"
                            :class="step === {{ $number }} ? 'border-primary bg-primary/5' : (visited.includes({{ $number }}) ? 'border-emerald-200 bg-emerald-50/50 hover:border-emerald-300' : 'border-gray-200 bg-white hover:border-gray-300')">
                        <span class="flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0"
                                  :class="step === {{ $number }} ? 'bg-primary text-white' : (visited.includes({{ $number }}) ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-dark/60')">
                                <span x-show="step === {{ $number }} || ! visited.includes({{ $number }})">{{ $number }}</span>
                                <i x-show="step !== {{ $number }} && visited.includes({{ $number }})" style="display: none" data-lucide="check" class="w-3 h-3"></i>
                            </span>
                            <span class="text-[11px] font-bold leading-tight"
                                  :class="step === {{ $number }} ? 'text-primary' : 'text-dark/70'">{{ $stepLabel }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    <!-- Step 1: Personal Details -->
    <section x-show="step === 1" class="space-y-5" aria-labelledby="step-personal">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-personal" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Personal Details</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">Enter or check the client's name, birth date and contact details.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Last Name *</label>
                <input type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}" required class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Given Name *</label>
                <input type="text" name="given_name" value="{{ old('given_name', $client->given_name) }}" required class="{{ $field }}">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Date of Birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $client->date_of_birth?->format('Y-m-d')) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Civil Status</label>
                <input type="text" name="civil_status" value="{{ old('civil_status', $client->civil_status) }}" class="{{ $field }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Height</label>
                <input type="text" name="height" value="{{ old('height', $client->height) }}" placeholder="e.g. 175 cm" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Weight</label>
                <input type="text" name="weight" value="{{ old('weight', $client->weight) }}" placeholder="e.g. 70 kg" class="{{ $field }}">
            </div>
        </div>

        <div>
            <label class="{{ $label }}">Address</label>
            <textarea name="address" rows="2" class="{{ $field }}">{{ old('address', $client->address) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Email</label>
                <input type="email" name="email" value="{{ old('email', $client->email) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Mobile #</label>
                <input type="text" name="mobile_number" value="{{ old('mobile_number', $client->mobile_number) }}" class="{{ $field }}">
            </div>
        </div>

    </section>

    <!-- Step 2: Passport Details, with the visa status and flags -->
    <section x-show="step === 2" style="display: none" class="space-y-5" aria-labelledby="step-passport">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-passport" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Passport Details</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">Check these against the client's passport and update anything that changed.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Passport Number</label>
                <input type="text" name="passport_number" value="{{ old('passport_number', $client->passport_number) }}"
                       class="{{ $field }} font-mono tracking-wider">
                <p class="text-[11px] text-dark/40 mt-1.5">This is how the counter finds this client again.</p>
            </div>
            <div>
                <label class="{{ $label }}">Nationality</label>
                <input type="text" name="nationality" value="{{ old('nationality', $client->nationality) }}" class="{{ $field }}">
            </div>
        </div>

        <!-- Visa status marks -->
        <div class="space-y-4 pt-4">
            <div class="border-b border-gray-100 pb-2">
                <h3 class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Visa Status</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Marks here are stamped on the printed sheet and shown beside the client's name.</p>
            </div>

            @php $band = $client->exists ? $client->validity_band : null; @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Current Visa Expiry</label>
                    <input type="date" name="visa_expiry_date" value="{{ old('visa_expiry_date', $client->visa_expiry_date?->format('Y-m-d')) }}" class="{{ $field }}">
                    @if ($band)
                        <p class="text-[11px] mt-1.5 font-bold {{ $band['key'] === 'expired' ? 'text-rose-600' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">
                            {{ $band['label'] }} — {{ $band['detail'] }}
                        </p>
                    @else
                        <p class="text-[11px] text-dark/40 mt-1.5">Optional. With a date here, the system works out the processing route.</p>
                    @endif
                </div>
                <div>
                    <label class="{{ $label }}">Status Note</label>
                    <input type="text" name="status_note" value="{{ old('status_note', $client->status_note) }}"
                           placeholder="e.g. penalty settled on release" class="{{ $field }}">
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-rose-300 transition-colors">
                    <input type="checkbox" name="is_expired" value="1" class="w-4 h-4 mt-0.5 rounded text-rose-500"
                           {{ old('is_expired', $client->is_expired) ? 'checked' : '' }}>
                    <span>
                        <span class="block text-xs font-bold text-dark">Visa expired</span>
                        <span class="block text-[11px] text-dark/50">Client's visa has already lapsed.</span>
                    </span>
                </label>

                <label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-amber-300 transition-colors">
                    <input type="checkbox" name="has_penalty" value="1" class="w-4 h-4 mt-0.5 rounded text-amber-500"
                           {{ old('has_penalty', $client->has_penalty) ? 'checked' : '' }}>
                    <span>
                        <span class="block text-xs font-bold text-dark">With penalty</span>
                        <span class="block text-[11px] text-dark/50">BI penalties apply to this client's processing.</span>
                    </span>
                </label>

                <label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-amber-300 transition-colors">
                    <input type="checkbox" name="needs_attention" value="1" class="w-4 h-4 mt-0.5 rounded text-amber-500"
                           {{ old('needs_attention', $client->needs_attention) ? 'checked' : '' }}>
                    <span>
                        <span class="block text-xs font-bold text-dark">Other attention required</span>
                        <span class="block text-[11px] text-dark/50">Anything else to follow up &mdash; say what in the status note.</span>
                    </span>
                </label>
            </div>
        </div>
    </section>

    <!-- Steps 3-5: immigration documents (ACR I-Card, CRTV, Annual Report) -->
    @foreach ($documentSteps as $number => $type)
        <section x-show="step === {{ $number }}" style="display: none" class="space-y-5" aria-labelledby="step-{{ $type }}">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-{{ $type }}" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">{{ ImmigrationClientDocument::TYPES[$type] }}</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Check the reference number, payment date, SSRN and validity. Leave it all blank if the client has none.</p>
            </div>

            @php $document = $client->exists ? $client->documentFor($type) : null; @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($documentRows as $rowLabel => [$attribute, $inputType])
                    @php
                        $value = $attribute === 'date_paid'
                            ? $document?->date_paid?->format('Y-m-d')
                            : $document?->{$attribute};
                    @endphp
                    <div>
                        <label class="{{ $label }}">{{ $rowLabel }}</label>
                        <input type="{{ $inputType }}"
                               name="documents[{{ $type }}][{{ $attribute }}]"
                               value="{{ old("documents.$type.$attribute", $value) }}"
                               class="{{ $field }}">
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <!-- Step 6: Visa Extension ledger -->
    <section x-show="step === {{ $ledgerStep }}" style="display: none" class="space-y-4" aria-labelledby="step-ledger">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-ledger" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Visa Extension Ledger</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">
                The ten-row ledger from the paper sheet. Add today's extension on the next empty row. Empty rows aren't stored.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="pb-2 pr-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/50">#</th>
                        <th class="pb-2 px-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">SOA / OR #</th>
                        <th class="pb-2 px-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">Date</th>
                        <th class="pb-2 px-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">Details</th>
                        <th class="pb-2 px-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">Amount Paid</th>
                        <th class="pb-2 px-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">A.R</th>
                        <th class="pb-2 pl-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">Refund</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($sequence = 1; $sequence <= ImmigrationClient::LEDGER_ROWS; $sequence++)
                        @php $row = $client->exists ? $client->extensionAt($sequence) : null; @endphp
                        <tr>
                            <td class="py-1.5 pr-2 text-[11px] font-bold text-dark/70 whitespace-nowrap">
                                {{ $sequence }}<sup>{{ ImmigrationClientExtension::ordinalSuffix($sequence) }}</sup>
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="text" name="extensions[{{ $sequence }}][soa_or_number]"
                                       value="{{ old("extensions.$sequence.soa_or_number", $row?->soa_or_number) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="date" name="extensions[{{ $sequence }}][extension_date]"
                                       value="{{ old("extensions.$sequence.extension_date", $row?->extension_date?->format('Y-m-d')) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="text" name="extensions[{{ $sequence }}][details]"
                                       value="{{ old("extensions.$sequence.details", $row?->details) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="number" step="0.01" min="0" name="extensions[{{ $sequence }}][amount_paid]"
                                       value="{{ old("extensions.$sequence.amount_paid", $row?->amount_paid) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="text" name="extensions[{{ $sequence }}][annual_report]"
                                       value="{{ old("extensions.$sequence.annual_report", $row?->annual_report) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 pl-2">
                                <input type="number" step="0.01" min="0" name="extensions[{{ $sequence }}][refund]"
                                       value="{{ old("extensions.$sequence.refund", $row?->refund) }}" class="{{ $cell }}">
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </section>

    <!-- Step 7: Save & Print -->
    <section x-show="step === {{ $saveStep }}" style="display: none" class="space-y-5" aria-labelledby="step-save">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-save" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Save &amp; Print</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">Save the reviewed record, then print the client sheet for signing.</p>
        </div>

        <ul class="rounded-2xl border border-gray-200 divide-y divide-gray-100">
            @foreach (array_slice($steps, 0, -1, true) as $number => $stepLabel)
                <li class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="flex items-center gap-2 text-xs font-bold text-dark">
                        <span x-show="visited.includes({{ $number }})"><i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-500"></i></span>
                        <span x-show="! visited.includes({{ $number }})" style="display: none"><i data-lucide="circle" class="w-4 h-4 text-amber-500"></i></span>
                        {{ $stepLabel }}
                        <span x-show="! visited.includes({{ $number }})" style="display: none" class="text-[11px] font-semibold text-amber-700">Not reviewed yet</span>
                    </span>
                    <button type="button" @click="step = {{ $number }}" class="text-[11px] font-bold text-primary hover:underline"
                            x-text="visited.includes({{ $number }}) ? 'Review again' : 'Review'"></button>
                </li>
            @endforeach
        </ul>

        <div>
            <label class="{{ $label }}">Internal Notes</label>
            <textarea name="notes" rows="2" placeholder="Not printed on the client sheet." class="{{ $field }}">{{ old('notes', $client->notes) }}</textarea>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ route('admin.client-sheets.index') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs text-center hover:bg-gray-200">Cancel</a>
            <button type="submit" name="then" value="stay"
                    class="px-6 py-3 rounded-full bg-white border border-primary/30 text-primary font-bold text-xs hover:bg-primary/5">
                Save only
            </button>
            <button type="submit" name="then" value="print"
                    class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md inline-flex items-center justify-center gap-2">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Save &amp; print sheet
            </button>
        </div>
    </section>

    <!-- Back / Next -->
    <div x-show="step < last" class="flex items-center justify-between gap-3 pt-4 border-t border-gray-100">
        <button type="button" @click="step--" x-show="step > 1" style="display: none"
                class="px-5 py-2.5 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200 inline-flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back
        </button>
        <span x-show="step === 1"></span>
        <button type="button" @click="step++"
                class="px-6 py-2.5 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md inline-flex items-center gap-1.5">
            <span x-text="'Next: ' + @js(array_values($steps))[step]"></span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
    </div>
</div>
