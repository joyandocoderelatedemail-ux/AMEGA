@php
    use App\Models\ImmigrationPetition;

    /*
     * The 13(A) steps: what the BI form asks beyond the client sheet (the immigrant visa
     * CGAF for a probationary or permanent case, the ACR I-Card renewal form for a
     * renewal), then the BI checklist. One Alpine scope spans the steps, so the checklist
     * follows the boxes ticked earlier.
     */
    $type = $petitionType;
    $renewal = $type === 'renewal';
    $value = fn (string $key) => old("petition.details.$key", $petition->detail($key));
    $previous = $client->exists ? $client->petitions->where('type', $type)->where('released_on', '!=', null) : collect();
    $checked = old('petition.checklist', $petition->checklist ?? []);

    // One labelled input; dates use a date picker, the rest free text.
    $input = function (string $key, string $text, string $placeholder = '') use ($value, $field, $label): string {
        $isDate = in_array($key, ImmigrationPetition::DATE_DETAILS, true);
        $current = $value($key);
        $current = $isDate && filled($current) ? \Illuminate\Support\Carbon::parse($current)->format('Y-m-d') : $current;

        return '<div><label class="'.$label.'">'.e($text).'</label>'
            .'<input type="'.($isDate ? 'date' : 'text').'" name="petition[details]['.$key.']" value="'.e((string) $current).'"'
            .($placeholder !== '' ? ' placeholder="'.e($placeholder).'"' : '').' class="'.$field.'"></div>';
    };

    $flagBox = function (string $key, string $title, string $note, string $model) use ($value): string {
        return '<label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">'
            .'<input type="hidden" name="petition[details]['.$key.']" value="0">'
            .'<input type="checkbox" name="petition[details]['.$key.']" value="1" x-model="'.$model.'" class="w-4 h-4 mt-0.5 rounded text-primary">'
            .'<span><span class="block text-xs font-bold text-dark">'.e($title).'</span><span class="block text-[11px] text-dark/50">'.e($note).'</span></span></label>';
    };

    $kind = $value('application_kind') ?? ($type === 'permanent' ? 'amendment' : 'conversion');
    $sex = $value('sex');
    $transactions = (array) ($value('transactions') ?? ['renewal']);
    $section = 'font-heading text-sm font-bold text-dark uppercase tracking-wider';
@endphp

<div x-data="{ rep: {{ Js::from((bool) $value('by_representative')) }}, nbi: {{ Js::from((bool) $value('nbi_needed')) }}, annex: {{ Js::from((bool) $value('annex_a')) }} }">

    @if ($renewal)
        <!-- Step 3: ACR I-Card Renewal, as BI Form 2014-08-010 asks -->
        <section x-show="step === {{ $petitionStep }}" style="display: none" class="space-y-5" aria-labelledby="step-petition">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-petition" class="{{ $section }}">ACR I-Card Renewal</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">What the BI's Application for Renewal of the ACR I-Card asks beyond steps 1 and 2. These print on the form.</p>
            </div>

            <div>
                <label class="{{ $label }}">Transaction(s)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach (ImmigrationPetition::TRANSACTIONS as $key => $transactionLabel)
                        <label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                            <input type="checkbox" name="petition[details][transactions][]" value="{{ $key }}" class="w-4 h-4 rounded text-primary" {{ in_array($key, $transactions, true) ? 'checked' : '' }}>
                            <span class="text-xs font-semibold text-dark">{{ $transactionLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                {!! $input('acr_number', 'ACR Number') !!}
                {!! $input('crn', 'Certificate of Residence No.') !!}
                {!! $input('visa_status', 'Visa Status', '13(A) Permanent') !!}
                {!! $input('valid_stay', 'Valid Authorized Stay') !!}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                {!! $input('middle_name', 'Middle Name') !!}
                <div>
                    <label class="{{ $label }}">Gender</label>
                    <div class="flex gap-2">
                        @foreach (['M' => 'Male', 'F' => 'Female'] as $code => $sexLabel)
                            <label class="flex-1 flex items-center gap-2 px-3 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer text-xs font-semibold text-dark">
                                <input type="radio" name="petition[details][sex]" value="{{ $code }}" class="text-primary" {{ $sex === $code ? 'checked' : '' }}> {{ $sexLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>
                {!! $input('landline', 'Landline') !!}
                {!! $input('passport_place', 'Passport Place of Issuance') !!}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {!! $input('passport_issued_on', 'Passport Date of Issuance') !!}
                {!! $input('passport_expires_on', 'Passport Expiry Date') !!}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">New Address (change of address only)</label>
                    <textarea name="petition[details][new_address]" rows="2" maxlength="500" class="{{ $field }}">{{ $value('new_address') }}</textarea>
                </div>
                <div>
                    <label class="{{ $label }}">Remarks</label>
                    <textarea name="petition[details][remarks]" rows="2" maxlength="500" class="{{ $field }}">{{ $value('remarks') }}</textarea>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                    <input type="hidden" name="petition[details][leaving_soon]" value="0">
                    <input type="checkbox" name="petition[details][leaving_soon]" value="1" class="w-4 h-4 mt-0.5 rounded text-primary" {{ $value('leaving_soon') ? 'checked' : '' }}>
                    <span>
                        <span class="block text-xs font-bold text-dark">Leaving the country within 30 days</span>
                        <span class="block text-[11px] text-dark/50">The form asks whether the client leaves within 30 days of the card's issuance.</span>
                    </span>
                </label>
                {!! $flagBox('by_representative', 'Filed by a representative', 'The BI then wants the SPA or accreditation ID.', 'rep') !!}
            </div>
            <div x-show="rep" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {!! $input('representative_name', "Representative's Name") !!}
                {!! $input('accreditation_no', 'BI Accreditation No.') !!}
            </div>
        </section>
    @else
        <!-- Step 3: Application Details, as the immigrant visa CGAF asks them -->
        <section x-show="step === {{ $petitionStep }}" style="display: none" class="space-y-5" aria-labelledby="step-petition">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-petition" class="{{ $section }}">Application Details</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">What the Consolidated General Application Form for Immigrant Visa asks beyond steps 1 and 2. These print on the form.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-3">
                    <label class="{{ $label }}">Type of Visa Application</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (ImmigrationPetition::APPLICATION_KINDS as $code => $kindLabel)
                            <label class="flex items-center gap-1.5 px-3 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer text-xs font-semibold text-dark">
                                <input type="radio" name="petition[details][application_kind]" value="{{ $code }}" class="text-primary" {{ $kind === $code ? 'checked' : '' }}> {{ $kindLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>
                {!! $input('present_status', 'Present Immigration Status', $type === 'permanent' ? '13(A) Probationary' : '9(a) Temporary Visitor') !!}
                {!! $input('visa_type', 'Type of Visa', '13(A) Non-Quota Immigrant') !!}
            </div>

            <div class="space-y-3">
                <div class="flex flex-col sm:flex-row gap-3">
                    {!! $flagBox('by_representative', 'Filed by an authorized representative', 'Otherwise the form is marked as a personal application.', 'rep') !!}
                </div>
                <div x-show="rep" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {!! $input('representative_name', "Representative's Name (Last, First, Middle)") !!}
                    {!! $input('accreditation_no', 'BI Accreditation Number') !!}
                </div>
            </div>

            <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">Travel Information</h3></div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {!! $input('arrival_date', 'Date of Latest Arrival') !!}
                {!! $input('last_day_of_stay', 'Last Day of Authorized Stay') !!}
                {!! $input('passport_place', 'Passport Place of Issuance') !!}
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {!! $input('passport_issued_on', 'Passport Date of Issuance') !!}
                {!! $input('passport_expires_on', 'Passport Expiry Date') !!}
            </div>

            <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">More About the Applicant</h3></div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {!! $input('middle_name', 'Middle Name (if applicable)') !!}
                {!! $input('other_name_1', 'Other Name / Alias 1') !!}
                {!! $input('other_name_2', 'Other Name / Alias 2') !!}
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="{{ $label }}">Sex</label>
                    <div class="flex gap-2">
                        @foreach (['M' => 'M', 'F' => 'F'] as $code => $sexLabel)
                            <label class="flex-1 flex items-center gap-2 px-3 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer text-xs font-semibold text-dark">
                                <input type="radio" name="petition[details][sex]" value="{{ $code }}" class="text-primary" {{ $sex === $code ? 'checked' : '' }}> {{ $sexLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>
                {!! $input('country_of_birth', 'Country of Birth') !!}
                {!! $input('occupation', 'Profession / Occupation') !!}
                {!! $input('landline', 'Landline') !!}
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {!! $input('abroad_street', 'Address Abroad: House No., Street', 'Residential address abroad') !!}
                {!! $input('abroad_city', 'City, State') !!}
                {!! $input('abroad_country', 'Country, Zip Code') !!}
            </div>

            <div class="border-b border-gray-100 pb-2 pt-2">
                <h3 class="{{ $section }}">Children</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Up to two print on the form. A child joining the application needs their own folder for their requirements.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="sm:col-span-3">{!! $input('child_1_name', 'Child 1 (Last, First, Middle)') !!}</div>
                {!! $input('child_1_dob', 'Date of Birth') !!}
                <div class="sm:col-span-3">{!! $input('child_2_name', 'Child 2 (Last, First, Middle)') !!}</div>
                {!! $input('child_2_dob', 'Date of Birth') !!}
            </div>
        </section>

        <!-- Step 4: Petitioner, character reference and ACR I-Card -->
        <section x-show="step === {{ $petitionStep + 1 }}" style="display: none" class="space-y-5" aria-labelledby="step-petitioner">
            <div class="border-b border-gray-100 pb-2">
                <h3 id="step-petitioner" class="{{ $section }}">Petitioner (Filipino Spouse)</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">The spouse's name also prints as the applicant's spouse on page 1.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {!! $input('petitioner_last', 'Last Name') !!}
                {!! $input('petitioner_first', 'First / Given Name') !!}
                {!! $input('petitioner_middle', 'Middle Name') !!}
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {!! $input('petitioner_street', 'House No., Street, Subdivision') !!}
                {!! $input('petitioner_city', 'Barangay, Municipality / City') !!}
                {!! $input('petitioner_province', 'Province, Zip Code') !!}
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {!! $input('petitioner_landline', 'Landline') !!}
                {!! $input('petitioner_mobile', 'Mobile') !!}
                {!! $input('petitioner_email', 'E-mail Address') !!}
            </div>

            <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">Character Reference in the Philippines</h3></div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-3">{!! $input('reference_name', 'Name (Last, First, Middle)') !!}</div>
                {!! $input('reference_street', 'House No., Street, Barangay') !!}
                {!! $input('reference_city', 'Municipality / City') !!}
                {!! $input('reference_province', 'Province, Zip Code') !!}
                {!! $input('reference_landline', 'Landline') !!}
                {!! $input('reference_mobile', 'Mobile') !!}
                {!! $input('reference_email', 'E-mail Address') !!}
            </div>

            <div class="border-b border-gray-100 pb-2 pt-2">
                <h3 class="{{ $section }}">ACR I-Card</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">{{ $type === 'permanent' ? 'The card issued with the probationary visa.' : 'Leave empty if the client has none yet.' }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                {!! $input('acr_number', 'ACR Number') !!}
                {!! $input('acr_issued_on', 'Date of Issuance') !!}
                {!! $input('acr_expires_on', 'Expiry Date') !!}
                {!! $input('crn', 'Certificate of Residence No.') !!}
            </div>
        </section>
    @endif

    <!-- BI Requirements -->
    <section x-show="step === {{ $petitionRequirementsStep }}" style="display: none" class="space-y-5" aria-labelledby="step-petition-requirements">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-petition-requirements" class="{{ $section }}">BI Requirements</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">{{ ImmigrationPetition::TITLES[$type] }}. Tick what the client handed over, or upload a copy.</p>
        </div>

        @unless ($renewal)
            <div class="flex flex-col sm:flex-row gap-3">
                @if ($type === 'spouse')
                    {!! $flagBox('nbi_needed', 'Filed 6 months or more after first arrival', 'The BI then wants a valid NBI Clearance.', 'nbi') !!}
                @endif
                {!! $flagBox('annex_a', 'National of an Annex "A" country', 'Arrived on or after June 2014: the BI wants a Bureau of Quarantine medical clearance.', 'annex') !!}
            </div>
        @else
            <input type="hidden" name="petition[details][annex_a]" value="0">
        @endunless

        <ul class="rounded-2xl border border-gray-200 divide-y divide-gray-100">
            @foreach (ImmigrationPetition::CHECKLISTS[$type] as $item => $itemLabel)
                @php
                    $file = $petition->documentFor($item);
                    $show = match ($item) {
                        'nbi_clearance' => $type === 'spouse' ? 'nbi' : 'true',
                        'quarantine_clearance' => 'annex',
                        'spa' => 'rep',
                        default => 'true',
                    };
                @endphp
                <li class="px-4 py-3 space-y-2" x-show="{{ $show }}" x-data="{ fileName: '', removing: false }">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="petition[checklist][]" value="{{ $item }}" id="petition-{{ $item }}"
                               class="w-4 h-4 mt-0.5 rounded text-primary" {{ in_array($item, $checked, true) ? 'checked' : '' }}>
                        <label for="petition-{{ $item }}" class="flex-1 min-w-0 text-xs text-dark cursor-pointer font-semibold">{{ $itemLabel }}</label>
                        <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-primary cursor-pointer hover:border-primary">
                            <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                            <span x-text="fileName ? 'Change' : '{{ $file ? 'Replace' : 'Upload' }}'"></span>
                            <input type="file" name="petition[files][{{ $item }}]" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                                   @change="fileName = $event.target.files[0]?.name || ''">
                        </label>
                    </div>

                    @if ($file && $client->exists && $petition->exists)
                        <div class="pl-7 flex flex-wrap items-center gap-3 text-[11px]" x-show="! removing">
                            <a href="{{ route('admin.client-sheets.petition-file', [$client, $petition, $item]) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 font-semibold text-dark hover:text-primary min-w-0">
                                <i data-lucide="file-text" class="w-3.5 h-3.5 text-primary shrink-0"></i>
                                <span class="truncate">{{ $file['name'] }}</span>
                            </a>
                            <button type="button" @click="removing = true" class="font-bold text-rose-600 hover:underline">Remove</button>
                        </div>
                        <template x-if="removing">
                            <p class="pl-7 text-[11px] text-rose-700">
                                <input type="hidden" name="petition[remove_files][]" value="{{ $item }}">
                                The file will be removed when you save. <button type="button" @click="removing = false" class="font-bold underline">Keep it</button>
                            </p>
                        </template>
                    @endif
                    <p x-show="fileName" x-cloak class="pl-7 text-[11px] font-semibold text-dark" x-text="'New file: ' + fileName"></p>
                    @error("petition.files.$item") <p class="pl-7 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </li>
            @endforeach
        </ul>

        @if ($previous->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 p-4">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-2">Earlier cases</span>
                <ul class="space-y-1 text-xs text-dark/70">
                    @foreach ($previous as $earlier)
                        <li>Filed {{ $earlier->filed_on?->format('M d, Y') ?? 'on an unrecorded date' }} &middot; released {{ $earlier->released_on->format('M d, Y') }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-4 pt-2">
            <div class="border-b border-gray-100 pb-2">
                <h3 class="{{ $section }}">Filing with the BI</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">A release date closes this case, so the next one starts fresh.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Date filed</label>
                    <input type="date" name="petition[filed_on]" value="{{ old('petition.filed_on', $petition->filed_on?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">Date released</label>
                    <input type="date" name="petition[released_on]" value="{{ old('petition.released_on', $petition->released_on?->format('Y-m-d')) }}" class="{{ $field }}">
                    @error('petition.released_on') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>
</div>
