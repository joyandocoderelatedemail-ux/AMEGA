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
     *
     * Visa Overstaying swaps the document steps for the overstay details and
     * the BI requirements; the ledger then records the missed extensions.
     * Re Stamping swaps them for the admission details and the BI requirements,
     * and has no ledger. The 13(A) services do the same with their own details.
     */
    $service ??= \App\Support\ImmigrationServices::find(null);
    $isOverstay = $service['key'] === 'visa_overstaying';
    $isRestamping = $service['key'] === 're_stamping_amendment';
    $petitionType = \App\Models\ImmigrationPetition::typeFor($service['key']);
    $overstayStep = $restampingStep = $petitionStep = $ledgerStep = null;

    if (in_array($petitionType, ['renewal', 'dual', 'downgrade', 'ecc'], true)) {
        $petition ??= new \App\Models\ImmigrationPetition(['type' => $petitionType]);
        $steps = [
            1 => 'Personal Details',
            2 => 'Passport Details',
            3 => ['dual' => 'Petition Details', 'downgrade' => 'Downgrading Details', 'ecc' => 'ECC Details'][$petitionType] ?? 'ACR I-Card Renewal',
            4 => 'BI Requirements',
            5 => 'Save & Print',
        ];
        $documentSteps = [];
        $petitionStep = 3;
        $petitionRequirementsStep = 4;
        $saveStep = 5;
    } elseif ($petitionType) {
        $petition ??= new \App\Models\ImmigrationPetition(['type' => $petitionType]);
        $steps = [
            1 => 'Personal Details',
            2 => 'Passport Details',
            3 => 'Application Details',
            4 => 'Petitioner & ACR',
            5 => 'BI Requirements',
            6 => 'Save & Print',
        ];
        $documentSteps = [];
        $petitionStep = 3;
        $petitionRequirementsStep = 5;
        $saveStep = 6;
    } elseif ($isRestamping) {
        $restamping ??= new \App\Models\ImmigrationRestamping;
        $steps = [
            1 => 'Personal Details',
            2 => 'Passport Details',
            3 => 'Admission Details',
            4 => 'BI Requirements',
            5 => 'Save & Print',
        ];
        $documentSteps = [];
        $restampingStep = 3;
        $saveStep = 5;
    } elseif ($isOverstay) {
        $overstay ??= new \App\Models\ImmigrationOverstay(['expired_on' => $client->visa_expiry_date]);
        $steps = [
            1 => 'Personal Details',
            2 => 'Passport Details',
            3 => 'Overstay Details',
            4 => 'BI Requirements',
            5 => 'Updating Ledger',
            6 => 'Save & Print',
        ];
        $documentSteps = [];
        $overstayStep = 3;
        $ledgerStep = 5;
        $saveStep = 6;
    } else {
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
    }

    // Some services open on an Overview (step 0) that sets out who can apply and how. A case already
    // started opens on step 1: the overview is for the first visit.
    $overviewView = match (true) {
        $petitionType === 'ecc' => 'admin.client-sheets._ecc-overview',
        $petitionType === 'renewal' => 'admin.client-sheets._renewal-overview',
        $petitionType === 'spouse' => 'admin.client-sheets._spouse-overview',
        $petitionType === 'permanent' => 'admin.client-sheets._permanent-overview',
        $petitionType === 'downgrade' => 'admin.client-sheets._downgrade-overview',
        $petitionType === 'dual' => 'admin.client-sheets._dual-overview',
        $isRestamping => 'admin.client-sheets._restamping-overview',
        $isOverstay => 'admin.client-sheets._overstay-overview',
        $service['key'] === 'visa_extension' => 'admin.client-sheets._extension-overview',
        default => null,
    };
    if ($overviewView) {
        $steps = [0 => 'Overview'] + $steps;
    }
    $firstStep = array_key_first($steps);
    // Visa Extension has no case of its own: its file is a first visit until the ledger has a row.
    $caseStarted = match (true) {
        $isRestamping => $restamping->exists,
        $isOverstay => $overstay->exists,
        $petitionType !== null => $petition->exists,
        default => $client->extensions->isNotEmpty(),
    };
    $opensOnOverview = $overviewView && ! $caseStarted;

    // A file already approved (and not waiting on the admin) prints straight away: the last step offers
    // Print until something is changed. Saving it unchanged would only send it back for approval.
    $formCaseFile = $isOverstay ? $overstay : ($isRestamping ? $restamping : ($petitionType ? $petition : null));
    $printReady = $client->exists && ! $errors->any() && ($formCaseFile
        ? $formCaseFile->exists && $formCaseFile->isCleared()
        : $client->extensions->isNotEmpty() && $client->extensions->every(fn ($row) => $row->isCleared()));
    $printUrl = ! $printReady ? null : match (true) {
        $formCaseFile === null => route('admin.client-sheets.print', ['clientSheet' => $client, 'autoprint' => 1]),
        $isOverstay => route('admin.client-sheets.reconsideration', ['clientSheet' => $client, 'case' => $formCaseFile->id, 'autoprint' => 1]),
        $isRestamping => route('admin.client-sheets.restamping-form', ['clientSheet' => $client, 'case' => $formCaseFile->id, 'autoprint' => 1]),
        default => route('admin.client-sheets.petition-form', ['clientSheet' => $client, 'type' => $petitionType, 'case' => $formCaseFile->id, 'autoprint' => 1]),
    };

    // After a failed save, open the step holding the first problem.
    $passportFields = ['passport_number', 'nationality', 'visa_expiry_date', 'status_note', 'is_expired', 'has_penalty', 'needs_attention'];
    $firstError = collect($errors->keys())->first();
    $startStep = match (true) {
        $firstError === null => $opensOnOverview ? 0 : 1,
        in_array($firstError, $passportFields, true) => 2,
        $isOverstay && preg_match('/^overstay\.(checklist|files|remove_files|filed_on|released_on|fee|payment_type|amount_paid|terms_count)/', $firstError) === 1 => $overstayStep + 1,
        $isOverstay && str_starts_with($firstError, 'overstay.') => $overstayStep,
        $isRestamping && preg_match('/^restamping\.(checklist|files|remove_files|filed_on|released_on|fee|payment_type|amount_paid|terms_count)/', $firstError) === 1 => $restampingStep + 1,
        $isRestamping && str_starts_with($firstError, 'restamping.') => $restampingStep,
        $petitionType && preg_match('/^petition\.(checklist|files|remove_files|filed_on|released_on|fee|payment_type|amount_paid|terms_count)/', $firstError) === 1 => $petitionRequirementsStep,
        $petitionType && preg_match('/^petition\.details\.(petitioner_|reference_|acr_|crn)/', $firstError) === 1 && in_array($petitionType, ['spouse', 'permanent'], true) => $petitionStep + 1,
        $petitionType && str_starts_with($firstError, 'petition.') => $petitionStep,
        str_starts_with($firstError, 'documents.acr_icard') => 3,
        str_starts_with($firstError, 'documents.crtv') => 4,
        str_starts_with($firstError, 'documents.annual_report') => 5,
        $ledgerStep && (str_starts_with($firstError, 'extensions') || str_starts_with($firstError, 'extension_payment')) => $ledgerStep,
        $firstError === 'acknowledged' => $saveStep,
        str_starts_with($firstError, 'notes') => $saveStep,
        default => 1,
    };
@endphp

{{-- A preview shows every step on one page, to scroll through instead of stepping. --}}
@php
    $showAll = $preview ?? false;
@endphp
<div x-data="{ step: {{ $startStep }}, first: {{ $firstStep }}, last: {{ array_key_last($steps) }}, all: {{ $showAll ? 'true' : 'false' }}, visited: [{{ $startStep }}], titles: @js($steps), acknowledged: {{ old('acknowledged') ? 'true' : 'false' }}, changed: {{ $printReady ? 'false' : 'true' }} }"
     @input="changed = true" @change="changed = true"
     x-init="$watch('step', value => { if (! visited.includes(value)) visited.push(value); window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })"
     class="space-y-6">

    <!-- Step header and progress, as in the ticketing wizard: one bar per step, olive for the current one -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider whitespace-nowrap bg-primary/10 text-primary">
                    <i data-lucide="{{ $service['icon'] }}" class="w-3.5 h-3.5"></i>
                    <span>{{ $service['label'] }} &middot; {{ $client->exists ? $client->full_name : 'New client sheet' }}</span>
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-dark tracking-tight mt-1.5">
                    @if ($showAll)
                        The whole file
                    @else
                        <span x-text="'Step ' + step + ' - ' + titles[step]">Step {{ $startStep }} - {{ $steps[$startStep] }}</span>
                    @endif
                </h1>
            </div>

            @unless ($showAll)
            <div class="flex items-center gap-2 whitespace-nowrap">
                <span class="text-xs font-bold uppercase tracking-wider text-dark/40">Step</span>
                <span class="w-8 h-8 rounded-full bg-primary text-white font-heading font-extrabold text-sm flex items-center justify-center shadow-md shadow-primary/30" x-text="step">{{ $startStep }}</span>
                <span class="text-xs font-bold text-dark/40">of {{ array_key_last($steps) }}</span>
            </div>
            @endunless
        </div>

        @unless ($showAll)
        <nav aria-label="Client sheet steps" class="grid gap-1.5" style="grid-template-columns: repeat({{ count($steps) }}, minmax(0, 1fr))">
            @foreach ($steps as $number => $stepLabel)
                <button type="button" @click="step = {{ $number }}" class="flex flex-col gap-1.5 cursor-pointer text-left"
                        :aria-current="step === {{ $number }} ? 'step' : null" title="{{ $stepLabel }}">
                    <span class="h-2 rounded-full transition-all duration-300"
                          :class="step === {{ $number }} ? 'bg-accent' : (visited.includes({{ $number }}) ? 'bg-primary' : 'bg-gray-200')"></span>
                    <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block"
                          :class="step === {{ $number }} ? 'text-accent font-extrabold' : (visited.includes({{ $number }}) ? 'text-primary' : 'text-dark/40')">{{ $stepLabel }}</span>
                </button>
            @endforeach
        </nav>
        @endunless
    </div>

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

    <!-- The current step's fields -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">

    @if ($overviewView)
        <!-- Step 0: Overview -->
        <section x-show="all || step === 0" @if ($startStep !== 0) style="display: none" @endif class="space-y-5" aria-labelledby="step-overview">
            @include($overviewView)
        </section>
    @endif

    <!-- Step 1: Personal Details -->
    <section x-show="all || step === 1" @if ($startStep !== 1) style="display: none" @endif class="space-y-5" aria-labelledby="step-personal">
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
                @php
                    // The statuses the BI forms tick; a value typed before the list existed stays selectable.
                    $civilStatus = (string) old('civil_status', $client->civil_status);
                    $civilStatuses = array_values(\App\Models\TouristVisaApplication::CIVIL_STATUSES);
                @endphp
                <select name="civil_status" class="{{ $field }}">
                    <option value="">-- Select --</option>
                    @foreach ($civilStatuses as $status)
                        <option value="{{ $status }}" @selected(strcasecmp($civilStatus, $status) === 0)>{{ $status }}</option>
                    @endforeach
                    @if ($civilStatus !== '' && ! collect($civilStatuses)->contains(fn ($status) => strcasecmp($status, $civilStatus) === 0))
                        <option value="{{ $civilStatus }}" selected>{{ $civilStatus }}</option>
                    @endif
                </select>
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
    <section x-show="all || step === 2" style="display: none" class="space-y-5" aria-labelledby="step-passport">
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
                    {{-- The processing route (regular or express) only matters for a Visa Extension. --}}
                    @if ($service['key'] === 'visa_extension')
                        @if ($band)
                            <p class="text-[11px] mt-1.5 font-bold {{ $band['key'] === 'expired' ? 'text-rose-600' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">
                                {{ $band['label'] }} — {{ $band['detail'] }}
                            </p>
                        @else
                            <p class="text-[11px] text-dark/40 mt-1.5">Optional. With a date here, the system works out the processing route.</p>
                        @endif
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

    <!-- Steps 3-5: immigration documents (ACR I-Card, CRTV, Annual Report): the scan and the date paid -->
    @foreach ($documentSteps as $number => $type)
        @php $document = $client->exists ? $client->documentFor($type) : null; @endphp
        <section x-show="all || step === {{ $number }}" style="display: none" class="space-y-5" aria-labelledby="step-{{ $type }}"
                 x-data="{ fileName: '', dragging: false, removing: false }">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-{{ $type }}" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">{{ ImmigrationClientDocument::TYPES[$type] }}</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Upload the client's {{ ImmigrationClientDocument::TYPES[$type] }} and the date it was paid. Leave both empty if the client has none.</p>
            </div>

            <div>
                <label class="{{ $label }}">{{ ImmigrationClientDocument::TYPES[$type] }} file</label>

                @if ($document?->file_path)
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3" x-show="! removing">
                        <a href="{{ route('admin.client-sheets.documents.file', [$client, $type]) }}" target="_blank" rel="noopener"
                           class="flex items-center gap-2 min-w-0 text-sm font-semibold text-dark hover:text-primary">
                            <i data-lucide="file-text" class="w-4 h-4 text-primary shrink-0"></i>
                            <span class="truncate">{{ $document->original_name ?: 'Uploaded file' }}</span>
                            @if ($document->file_size)
                                <span class="text-xs font-normal text-dark/40 shrink-0">{{ number_format($document->file_size / 1024, 0) }} KB</span>
                            @endif
                        </a>
                        <button type="button" @click="removing = true" class="text-xs font-bold text-rose-600 hover:underline">Remove</button>
                    </div>
                    <input type="hidden" name="documents[{{ $type }}][remove_file]" :value="removing ? 1 : 0">
                    <p x-show="removing" x-cloak class="mb-3 text-xs text-rose-700">
                        The file will be removed when you save. <button type="button" @click="removing = false" class="font-bold underline">Keep it</button>
                    </p>
                @endif

                <label class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-2xl border-2 border-dashed px-5 py-4 cursor-pointer transition-colors"
                       :class="dragging ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-primary'"
                       @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                       @drop.prevent="dragging = false; $refs.file.files = $event.dataTransfer.files; fileName = $event.dataTransfer.files[0]?.name || ''">
                    <i data-lucide="upload" class="w-5 h-5 text-primary shrink-0"></i>
                    <span class="flex-1 min-w-0 text-sm text-dark/70">
                        <span x-show="! fileName"><strong class="text-dark">{{ $document?->file_path ? 'Replace the file' : 'Choose a file' }}</strong> or drop it here &middot; JPG, PNG, WebP or PDF, up to 10 MB</span>
                        <span x-show="fileName" x-cloak class="font-semibold text-dark" x-text="fileName"></span>
                    </span>
                    <input type="file" name="documents[{{ $type }}][file]" x-ref="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                           @change="fileName = $event.target.files[0]?.name || ''">
                </label>
                @error("documents.$type.file") <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:w-1/2">
                <label class="{{ $label }}">Date Paid</label>
                <input type="date" name="documents[{{ $type }}][date_paid]"
                       value="{{ old("documents.$type.date_paid", $document?->date_paid?->format('Y-m-d')) }}"
                       class="{{ $field }}">
            </div>
        </section>
    @endforeach

    <input type="hidden" name="service" value="{{ $service['key'] }}">
    @php $formCase = ($isOverstay ? $overstay : null) ?? ($isRestamping ? $restamping : null) ?? ($petitionType ? $petition : null); @endphp
    @if ($formCase?->exists)
        {{-- Saving writes the case this page is open on, even an earlier one already released --}}
        <input type="hidden" name="case_id" value="{{ $formCase->id }}">
    @endif

    @if ($isOverstay)
        @include('admin.client-sheets._overstay')
    @endif

    @if ($isRestamping)
        @include('admin.client-sheets._restamping')
    @endif

    @if ($petitionType === 'dual')
        @include('admin.client-sheets._dual')
    @elseif ($petitionType === 'downgrade')
        @include('admin.client-sheets._downgrade')
    @elseif ($petitionType === 'ecc')
        @include('admin.client-sheets._ecc')
    @elseif ($petitionType)
        @include('admin.client-sheets._petition')
    @endif

    @if ($ledgerStep)
    <!-- Visa Extension ledger -->
    @php
        // The published Tourist Visa Extension rates, so a ledger row can price itself.
        $extensionRates = \App\Models\ImmigrationPricingTier::published()
            ->whereHas('category', fn ($query) => $query->where('slug', 'visa-extension'))
            ->orderBy('sort_order')
            ->get(['extension_label', 'duration_label', 'process_type', 'payment_method', 'condition_notes', 'price'])
            ->map(fn ($tier) => [
                'extension' => $tier->extension_label,
                'duration' => $tier->duration_label,
                'process' => $tier->process_type,
                'payment' => $tier->payment_method,
                'condition' => (string) $tier->condition_notes,
                'price' => (float) $tier->price,
            ])->values();

        $ledgerRows = [];
        for ($sequence = 1; $sequence <= ImmigrationClient::LEDGER_ROWS; $sequence++) {
            $row = $client->exists ? $client->extensionAt($sequence) : null;
            $amount = old("extensions.$sequence.amount_paid", $row?->amount_paid);
            $ledgerRows[$sequence] = [
                'amount' => $amount === null ? '' : (string) $amount,
                'details' => (string) old("extensions.$sequence.details", $row?->details),
                // A row already saved keeps what was paid; only new rows price themselves.
                'manual' => filled($amount),
                'saved' => (bool) $row?->exists,
                'savedOn' => $row?->updated_at?->format('M d, Y'),
                'changed' => false,
                'active' => filled($amount) || filled(old("extensions.$sequence.extension_date", $row?->extension_date)) || filled(old("extensions.$sequence.soa_or_number", $row?->soa_or_number)),
            ];
        }
    @endphp
    <section x-show="all || step === {{ $ledgerStep }}" style="display: none" class="space-y-4" aria-labelledby="step-ledger"
             x-data="ledgerRates({ rates: {{ Js::from($extensionRates) }}, rows: {{ Js::from($ledgerRows) }} })">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-ledger" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">{{ $isOverstay ? 'Updating Ledger' : 'Visa Extension Ledger' }}</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">
                @if ($isOverstay)
                    Updating the stay means paying each extension missed since it lapsed: add one row per period, on the next empty rows. With the visa marked expired in step 2, they price at the expired rate. Empty rows aren't stored.
                @else
                    The ten-row ledger from the paper sheet. Add today's extension on the next empty row. Empty rows aren't stored.
                @endif
            </p>
        </div>

        <!-- What the suggested amounts are based on -->
        <div class="rounded-2xl bg-gray-50 border border-gray-100 p-4 flex flex-wrap items-center gap-x-6 gap-y-2">
            <div class="text-xs">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50">Visa status (from step 2)</span>
                <span class="mt-0.5 inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold"
                      :class="status === 'expired' ? 'bg-rose-100 text-rose-700' : (status === 'express' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700')"
                      x-text="status === 'expired' ? 'Expired: express with penalties' : (status === 'express' ? 'Under 8 days left: express' : 'Regular processing')"></span>
            </div>
            <div class="text-xs">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Payment</span>
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5">
                    <template x-for="option in ['cash', 'card']" :key="option">
                        <button type="button" @click="payment = option" class="px-3 py-1 rounded-md text-[11px] font-bold capitalize transition-colors"
                                :class="payment === option ? 'bg-primary text-white' : 'text-dark/60 hover:text-dark'" x-text="option"></button>
                    </template>
                </div>
            </div>
            <div class="text-xs">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Length (2nd extension on)</span>
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5">
                    <template x-for="option in ['1 month', '2 months']" :key="option">
                        <button type="button" @click="duration = option" class="px-3 py-1 rounded-md text-[11px] font-bold transition-colors"
                                :class="duration === option ? 'bg-primary text-white' : 'text-dark/60 hover:text-dark'" x-text="option"></button>
                    </template>
                </div>
            </div>
            <div x-show="newExpiry()" x-cloak class="w-full flex items-start gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-800">
                <i data-lucide="calendar-check" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <span>
                    When you save, the visa moves to <strong x-text="newExpiry()"></strong> and the expired / penalty marks are cleared.
                    <span class="text-emerald-700/80">Change the expiry in step 2 yourself to keep your own date.</span>
                </span>
            </div>
            <p class="text-[11px] text-dark/50 w-full">
                A new row fills its amount from the price list once you enter its date or SOA / OR #. It assumes the standard rate (valid ACR I-Card, no I-Card add-on); type over it whenever the client's case is different.
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
                        <th class="pb-2 px-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">Refund</th>
                        <th class="pb-2 pl-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($sequence = 1; $sequence <= ImmigrationClient::LEDGER_ROWS; $sequence++)
                        @php $row = $client->exists ? $client->extensionAt($sequence) : null; @endphp
                        <tr style="vertical-align: top" @input="rows[{{ $sequence }}].changed = rows[{{ $sequence }}].saved"
                            :class="rows[{{ $sequence }}].saved && !rows[{{ $sequence }}].changed ? 'bg-emerald-50/60' : ''">
                            <td class="py-1.5 pr-2 pt-3 text-[11px] font-bold text-dark/70 whitespace-nowrap">
                                {{ $sequence }}<sup>{{ ImmigrationClientExtension::ordinalSuffix($sequence) }}</sup>
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="text" name="extensions[{{ $sequence }}][soa_or_number]" @input="start({{ $sequence }})"
                                       value="{{ old("extensions.$sequence.soa_or_number", $row?->soa_or_number) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="date" name="extensions[{{ $sequence }}][extension_date]" @input="start({{ $sequence }})"
                                       value="{{ old("extensions.$sequence.extension_date", $row?->extension_date?->format('Y-m-d')) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="text" name="extensions[{{ $sequence }}][details]" x-model="rows[{{ $sequence }}].details" class="{{ $cell }}">
                                <input type="hidden" name="extensions[{{ $sequence }}][duration]" :value="rows[{{ $sequence }}].saved ? '' : durationFor({{ $sequence }})">
                            </td>
                            <td class="py-1.5 px-2 w-32">
                                <input type="number" step="0.01" min="0" name="extensions[{{ $sequence }}][amount_paid]"
                                       x-model="rows[{{ $sequence }}].amount" @input="rows[{{ $sequence }}].manual = true" class="{{ $cell }}">
                                {{-- Where the amount came from, or the suggestion when it was typed over --}}
                                <template x-if="rate({{ $sequence }})">
                                    <p class="mt-1 text-[10px] leading-tight">
                                        <span x-show="!rows[{{ $sequence }}].manual && rows[{{ $sequence }}].active" class="text-emerald-700 font-semibold" x-text="'Auto: ' + label({{ $sequence }})"></span>
                                        <button type="button" x-show="rows[{{ $sequence }}].manual && !rows[{{ $sequence }}].saved && Number(rows[{{ $sequence }}].amount) !== rate({{ $sequence }}).price"
                                                @click="use({{ $sequence }})" class="text-primary font-bold hover:underline"
                                                x-text="'Use ₱' + money(rate({{ $sequence }}).price)"></button>
                                    </p>
                                </template>
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="text" name="extensions[{{ $sequence }}][annual_report]"
                                       value="{{ old("extensions.$sequence.annual_report", $row?->annual_report) }}" class="{{ $cell }}">
                            </td>
                            <td class="py-1.5 px-2">
                                <input type="number" step="0.01" min="0" name="extensions[{{ $sequence }}][refund]"
                                       value="{{ old("extensions.$sequence.refund", $row?->refund) }}" class="{{ $cell }}">
                            </td>
                            {{-- On file, started but not saved, or saved and since changed --}}
                            <td class="py-1.5 pl-2 pt-3 whitespace-nowrap">
                                <span x-show="rows[{{ $sequence }}].saved && !rows[{{ $sequence }}].changed"
                                      class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-[10px] font-extrabold uppercase tracking-wider"
                                      :title="'Saved ' + (rows[{{ $sequence }}].savedOn || '')">
                                    <i data-lucide="check-circle-2" class="w-3 h-3"></i> Saved
                                </span>
                                <span x-show="rows[{{ $sequence }}].saved && !rows[{{ $sequence }}].changed && rows[{{ $sequence }}].savedOn"
                                      class="block mt-0.5 text-[10px] text-dark/40" x-text="rows[{{ $sequence }}].savedOn"></span>
                                <span x-show="rows[{{ $sequence }}].saved && rows[{{ $sequence }}].changed" style="display: none"
                                      class="inline-flex px-2 py-1 rounded-lg bg-amber-100 text-amber-800 text-[10px] font-extrabold uppercase tracking-wider">Changed: save to keep</span>
                                <span x-show="!rows[{{ $sequence }}].saved && rows[{{ $sequence }}].active" style="display: none"
                                      class="inline-flex px-2 py-1 rounded-lg bg-amber-100 text-amber-800 text-[10px] font-extrabold uppercase tracking-wider">Not saved yet</span>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        {{-- Rows an admin has not approved yet, or has returned --}}
        @php $waitingRows = $client->exists ? $client->extensions->reject(fn ($row) => $row->isCleared()) : collect(); @endphp
        @foreach ($waitingRows as $waitingRow)
            <div class="rounded-xl border px-4 py-2.5 text-xs font-semibold {{ $waitingRow->isReturned() ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
                Row {{ $waitingRow->sequence }}:
                @if ($waitingRow->isReturned())
                    returned by the admin{{ $waitingRow->review_note ? ': '.$waitingRow->review_note : '' }}. Correct it and save to send it again.
                @else
                    waiting for the admin to approve it. The visa moves on once it is approved.
                @endif
            </div>
        @endforeach

        {{-- How the extension(s) added in this save are paid --}}
        <div class="rounded-2xl border border-gray-200 p-4 space-y-3" x-data="{ type: @js(old('extension_payment.payment_type', 'none')) }">
            <span class="{{ $label }}">Payment for the extension(s) added now</span>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" role="radiogroup" aria-label="Payment option">
                @foreach (\App\Models\ImmigrationPetition::PAYMENT_TYPES as $paymentKey => $paymentText)
                    <label class="flex items-center gap-2 px-3 py-2.5 rounded-xl border bg-gray-50 cursor-pointer text-xs font-semibold transition-colors"
                           :class="type === '{{ $paymentKey }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-dark hover:border-primary'">
                        <input type="radio" name="extension_payment[payment_type]" value="{{ $paymentKey }}" x-model="type" class="text-primary">
                        {{ $paymentText }}
                    </label>
                @endforeach
            </div>
            @error('extension_payment.payment_type') <p class="text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            <div x-show="type === 'downpayment' || type === 'terms'" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Amount received so far (₱)</label>
                    <input type="number" step="0.01" min="0" name="extension_payment[amount_received]" value="{{ old('extension_payment.amount_received') }}" placeholder="0.00" inputmode="decimal" class="{{ $field }}">
                    @error('extension_payment.amount_received') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div x-show="type === 'terms'" style="display: none">
                    <label class="{{ $label }}">Number of terms</label>
                    <input type="number" min="2" max="36" name="extension_payment[terms_count]" value="{{ old('extension_payment.terms_count') }}" placeholder="e.g. 3" class="{{ $field }}">
                    @error('extension_payment.terms_count') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <p x-show="type === 'full'" style="display: none" class="text-[11px] text-dark/50">Full payment records each new row's amount as received.</p>
        </div>
    </section>

    <script>
        // Prices a ledger row from the Tourist Visa Extension price list: the row's place (1st, 2nd...
        // extension), the visa status from step 2, and the payment and length chosen above. The amount
        // stays editable; once typed over, it is left alone.
        function ledgerRates(config) {
            const ordinal = (n) => n + ({ 1: 'st', 2: 'nd', 3: 'rd' }[n] || 'th');
            const addOn = /no valid|needs renewal|plus i-card/i;

            return {
                rates: config.rates,
                rows: config.rows,
                payment: 'cash',
                duration: '2 months',
                expiry: '',
                expired: false,

                init() {
                    const expiryInput = document.querySelector('[name="visa_expiry_date"]');
                    const expiredInput = document.querySelector('[name="is_expired"]');
                    const read = () => {
                        this.expiry = expiryInput?.value || '';
                        this.expired = !!expiredInput?.checked;
                    };
                    read();
                    expiryInput?.addEventListener('input', read);
                    expiredInput?.addEventListener('change', read);

                    // A change to what the price depends on re-prices the rows still on the suggestion.
                    this.$watch(() => [this.status, this.payment, this.duration], () => this.refresh());
                },

                get status() {
                    if (this.expired) return 'expired';
                    if (!this.expiry) return 'regular';
                    const days = Math.floor((new Date(this.expiry + 'T00:00:00') - new Date(new Date().toDateString())) / 86400000);
                    return days < 0 ? 'expired' : (days < 8 ? 'express' : 'regular');
                },

                rate(n) {
                    const expired = this.status === 'expired';
                    const process = this.status === 'regular' ? 'regular' : 'express';
                    const matches = this.rates.filter((rate) => rate.extension === ordinal(n) + ' Extension'
                        && rate.payment === this.payment
                        && rate.process === process
                        && (n === 1 || rate.duration === this.duration)
                        && /expired/i.test(rate.condition) === expired
                        && !addOn.test(rate.condition));
                    return matches[0] || null;
                },

                label(n) {
                    const rate = this.rate(n);
                    return rate ? [rate.extension, rate.duration, rate.process, rate.payment].join(' · ') : '';
                },

                // A row comes alive when its date or SOA / OR # is entered: it takes the suggested price.
                start(n) {
                    this.rows[n].active = true;
                    if (!this.rows[n].manual) this.apply(n);

                    // A new row is an extension done today, unless its date is typed in.
                    const date = document.querySelector('[name="extensions[' + n + '][extension_date]"]');
                    if (date && !date.value && !this.rows[n].saved) {
                        const now = new Date();
                        date.value = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                    }
                },

                use(n) {
                    this.rows[n].manual = false;
                    this.apply(n);
                },

                apply(n) {
                    const rate = this.rate(n);
                    this.rows[n].amount = rate ? String(rate.price) : '';
                    if (rate && !this.rows[n].details) this.rows[n].details = rate.extension + ', ' + rate.duration + ' (' + rate.process + ')';
                },

                refresh() {
                    Object.keys(this.rows).forEach((n) => {
                        if (this.rows[n].active && !this.rows[n].manual) this.apply(Number(n));
                    });
                },

                money(value) {
                    return Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                // How long a row's extension runs: the 1st is always 29 days, later ones the length chosen above.
                durationFor(n) {
                    return n === 1 ? '29 days' : this.duration;
                },

                // Where the visa lands once the new rows (with a date) are saved: each adds its length to the expiry.
                newExpiry() {
                    const rows = Object.keys(this.rows).map(Number).sort((a, b) => a - b)
                        .filter((n) => this.rows[n].active && !this.rows[n].saved && document.querySelector('[name="extensions[' + n + '][extension_date]"]')?.value);
                    if (!rows.length) return '';

                    const firstDate = document.querySelector('[name="extensions[' + rows[0] + '][extension_date]"]').value;
                    let date = new Date((this.expiry || firstDate) + 'T00:00:00');
                    rows.forEach((n) => {
                        const [count, unit] = this.durationFor(n).split(' ');
                        if (unit.startsWith('day')) {
                            date.setDate(date.getDate() + Number(count));
                        } else {
                            const day = date.getDate();
                            date.setDate(1);
                            date.setMonth(date.getMonth() + Number(count));
                            date.setDate(Math.min(day, new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()));
                        }
                    });
                    return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                },
            };
        }
    </script>
    @endif

    <!-- Last step: Save & Print -->
    <section x-show="all || step === {{ $saveStep }}" style="display: none" class="space-y-5" aria-labelledby="step-save">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-save" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Save &amp; Print</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">Save the reviewed record, then print the client sheet for signing.</p>
        </div>

        @if ($isOverstay)
            <div class="flex items-start gap-2 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs text-dark/70">
                <i data-lucide="printer" class="w-4 h-4 text-primary shrink-0 mt-0.5"></i>
                <span>Once saved, print the <strong class="text-dark">Request for Reconsideration</strong>, the <strong class="text-dark">CGAF</strong> and the <strong class="text-dark">BI checklist</strong> from the buttons at the top of the folder. They fill in from what you entered here.</span>
            </div>
        @elseif ($petitionType)
            <div class="flex items-start gap-2 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs text-dark/70">
                <i data-lucide="printer" class="w-4 h-4 text-primary shrink-0 mt-0.5"></i>
                <span>Once saved, print the <strong class="text-dark">{{ \App\Models\ImmigrationPetition::FORM_LABELS[$petitionType] ?? 'application form' }}</strong> and the <strong class="text-dark">{{ $petitionType === 'ecc' ? 'checklist' : 'BI checklist' }}</strong> from the buttons at the top of the folder. They fill in from what you entered here.</span>
            </div>
        @elseif ($isRestamping)
            <div class="flex items-start gap-2 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs text-dark/70">
                <i data-lucide="printer" class="w-4 h-4 text-primary shrink-0 mt-0.5"></i>
                <span>Once saved, print the <strong class="text-dark">IRD application form</strong> and the <strong class="text-dark">checklist</strong> for the application from the buttons at the top of the folder. They fill in from what you entered here.</span>
            </div>
        @endif

        <div>
            <label class="{{ $label }}">Internal Notes</label>
            <textarea name="notes" rows="2" placeholder="Not printed on the client sheet." class="{{ $field }}">{{ old('notes', $client->notes) }}</textarea>
        </div>

        {{-- Before saving, whoever enters the file confirms what they typed. An agent's file then goes to an admin. --}}
        <div data-save-controls x-show="changed" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
            <input type="hidden" name="acknowledgement_required" value="1">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="acknowledged" value="1" x-model="acknowledged" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span class="text-xs font-semibold text-dark leading-relaxed">
                    I acknowledge that the dates, the fee, the payment and all the details entered are correct.
                    <span class="block font-normal text-dark/60 mt-0.5">
                        @if (auth()->user()->isAdmin())
                            You are an admin, so it is approved as you save it.
                        @else
                            It goes to the admin for approval after you save, and can be printed once approved.
                        @endif
                    </span>
                </span>
            </label>
            @error('acknowledged') <p class="mt-2 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>

        @if ($printUrl)
            {{-- Approved and unchanged: print it. Changing anything brings back Save, which sends it to the admin again. --}}
            <div x-show="! changed" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 border-t border-gray-100">
                <p class="text-[11px] text-dark/60">This file is approved. Print it as it is; change something and Save comes back (an agent's change goes to the admin again).</p>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.immigration.cases') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs text-center hover:bg-gray-200">Back</a>
                    <a href="{{ $printUrl }}" class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md inline-flex items-center justify-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Print
                    </a>
                </div>
            </div>
        @endif
        <div data-save-controls x-show="changed" class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ route('admin.immigration.cases') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs text-center hover:bg-gray-200">Cancel</a>
            <button type="submit" name="then" value="stay" :disabled="! acknowledged" :class="! acknowledged && 'opacity-50 cursor-not-allowed'"
                    class="px-6 py-3 rounded-full bg-white border border-primary/30 text-primary font-bold text-xs hover:bg-primary/5">
                Save only
            </button>
            <button type="submit" name="then" value="print" :disabled="! acknowledged" :class="! acknowledged && 'opacity-50 cursor-not-allowed'"
                    class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md inline-flex items-center justify-center gap-2">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Save &amp; print {{ match (true) {
                    $isOverstay => 'request',
                    $isRestamping => 'IRD form',
                    $petitionType === 'renewal' => 'renewal form',
                    $petitionType === 'dual' => 'petition',
                    $petitionType === 'downgrade' => 'letter',
                    $petitionType === 'ecc' => 'ECC form',
                    (bool) $petitionType => 'CGAF',
                    default => 'sheet',
                } }}
            </button>
        </div>
    </section>

    <!-- Back / Continue, as in the ticketing wizard -->
    @unless ($showAll)
    <div x-show="step < last" class="flex items-center justify-between gap-3 pt-6 border-t border-gray-100">
        <button type="button" @click="step--" x-show="step > first" style="display: none"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back</span>
        </button>
        <span x-show="step === first"></span>
        <button type="button" @click="step++"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
            <span x-text="'Continue to ' + titles[step + 1]"></span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
        </button>
    </div>
    <div x-show="step === last" style="display: none" class="flex items-center justify-start pt-6 border-t border-gray-100">
        <button type="button" @click="step--"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back</span>
        </button>
    </div>
    @endunless
    </div>
</div>
