@php
    use App\Models\ImmigrationPetition;

    /*
     * Dual Citizenship (R.A. 9225): what the sworn Petition for Retention/Re-acquisition of
     * Philippine Citizenship (BI Form 2014-01-005) asks beyond the client sheet, then the
     * BI checklist (BSI03.QF.001). One Alpine scope spans both steps, so the checklist
     * follows the proof of birth and the boxes ticked.
     */
    $value = fn (string $key) => old("petition.details.$key", $petition->detail($key));
    $previous = $client->exists ? $client->petitions->where('type', 'dual')->where('released_on', '!=', null) : collect();
    $checked = old('petition.checklist', $petition->checklist ?? []);
    $section = 'font-heading text-sm font-bold text-dark uppercase tracking-wider';

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

    // Radios for how a parent held their citizenship at the petitioner's birth.
    $acquired = function (string $key) use ($value, $label): string {
        $html = '<div><label class="'.$label.'">Citizen by</label><div class="grid grid-cols-2 sm:grid-cols-4 gap-2">';
        foreach (ImmigrationPetition::ACQUIRED_BY as $code => $text) {
            $html .= '<label class="flex items-center gap-1.5 px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer text-xs font-semibold text-dark">'
                .'<input type="radio" name="petition[details]['.$key.']" value="'.$code.'" class="text-primary"'.($value($key) === $code ? ' checked' : '').'> '.$text.'</label>';
        }

        return $html.'</div></div>';
    };

    $sex = $value('sex');
@endphp

<div x-data="{
        rep: {{ Js::from((bool) $value('by_representative')) }},
        alien: {{ Js::from((bool) $value('registered_alien')) }},
        names: {{ Js::from((bool) $value('name_variance')) }},
        proof: {{ Js::from($value('birth_proof') ?: 'psa') }},
        deps: {{ Js::from((bool) $value('with_dependents')) }},
        depAlien: {{ Js::from((bool) $value('dependent_alien')) }},
     }">

    <!-- Step 3: Petition Details, as the sworn R.A. 9225 petition asks them -->
    <section x-show="step === {{ $petitionStep }}" style="display: none" class="space-y-5" aria-labelledby="step-dual">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-dual" class="{{ $section }}">Petition Details</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">What the Petition for Retention/Re-acquisition of Philippine Citizenship (BI Form 2014-01-005) asks beyond steps 1 and 2. These print on the petition.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                {!! $input('name_ph', 'Name in Philippine Document', 'As on the PSA birth certificate, e.g. the maiden name') !!}
                <p class="text-[11px] text-dark/40 mt-1.5">The name in the foreign passport ({{ $client->full_name ?: 'steps 1 and 2' }}) prints as "also known as".</p>
            </div>
            <div>
                <label class="{{ $label }}">Sex</label>
                <div class="flex gap-2">
                    @foreach (['M' => 'Male', 'F' => 'Female'] as $code => $sexLabel)
                        <label class="flex-1 flex items-center gap-2 px-3 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer text-xs font-semibold text-dark">
                            <input type="radio" name="petition[details][sex]" value="{{ $code }}" class="text-primary" {{ $sex === $code ? 'checked' : '' }}> {{ $sexLabel }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('spouse_name', 'Married to (if married)', "Spouse's full name") !!}
            {!! $input('birth_place', 'Place of Birth (as in the Philippine document)', 'City / municipality, province') !!}
        </div>

        <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">Parents at the Time of Birth</h3></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('father_name', "Father's Name") !!}
            {!! $input('father_citizenship', "Father's Citizenship", 'e.g. Filipino') !!}
        </div>
        {!! $acquired('father_acquired') !!}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('mother_name', "Mother's Name") !!}
            {!! $input('mother_citizenship', "Mother's Citizenship", 'e.g. Filipino') !!}
        </div>
        {!! $acquired('mother_acquired') !!}
        <div class="flex flex-col sm:flex-row gap-3">
            <label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                <input type="hidden" name="petition[details][parents_married]" value="0">
                <input type="checkbox" name="petition[details][parents_married]" value="1" class="w-4 h-4 mt-0.5 rounded text-primary" {{ $value('parents_married') ? 'checked' : '' }}>
                <span>
                    <span class="block text-xs font-bold text-dark">The parents were married</span>
                    <span class="block text-[11px] text-dark/50">The petition reads "who was married / not married to" the mother.</span>
                </span>
            </label>
        </div>

        <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">Foreign Citizenship and Passport</h3></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('lost_country', 'Became a citizen of', 'e.g. United States of America') !!}
            {!! $input('lost_on', 'On (date of naturalization)') !!}
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {!! $input('passport_country', 'Holder of a valid ... passport', $client->nationality ?: 'e.g. American') !!}
            {!! $input('passport_issued_on', 'Passport issued on') !!}
            {!! $input('passport_place', 'Passport issued at') !!}
        </div>

        <div class="border-b border-gray-100 pb-2 pt-2">
            <h3 class="{{ $section }}">Oath of Allegiance</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">Leave empty until the oath is taken.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('oath_date', 'Took the Oath on') !!}
            {!! $input('oath_place', 'At', 'e.g. Bureau of Immigration, Manila') !!}
        </div>

        <div class="space-y-3">
            <div class="flex flex-col sm:flex-row gap-3">
                {!! $flagBox('registered_alien', 'Registered alien with the BI', 'The ACR I-Card and ICR/CRTV are surrendered for cancellation.', 'alien') !!}
                {!! $flagBox('by_representative', 'Filed by a representative', 'The BI then wants the SPA or accreditation ID.', 'rep') !!}
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                {!! $flagBox('with_dependents', 'Joined by a dependent child', 'Unmarried and under 18: the BI wants the dependent child checklist for EACH child.', 'deps') !!}
                {!! $flagBox('dependent_alien', 'A dependent child is a registered alien', 'The ACR I-Card and ICR/CRTV of the child are surrendered for cancellation.', 'depAlien') !!}
            </div>
            <div x-show="alien" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {!! $input('acr_number', 'ACR No.') !!}
                {!! $input('icr_number', 'ICR / CRTV No.') !!}
            </div>
            <div x-show="rep" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {!! $input('representative_name', "Representative's Name") !!}
                {!! $input('accreditation_no', 'BI Accreditation No.') !!}
            </div>
        </div>

        <div class="border-b border-gray-100 pb-2 pt-2">
            <h3 class="{{ $section }}">Dependents</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">Unmarried children below 18 included in the petition. Each needs BI Form 2014-01-006 and their own requirements.</p>
        </div>
        @foreach ([1, 2, 3] as $dependent)
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                <div class="sm:col-span-3">{!! $input("dep_{$dependent}_name", "Dependent {$dependent}: Name") !!}</div>
                <div>
                    <label class="{{ $label }}">Gender</label>
                    <select name="petition[details][dep_{{ $dependent }}_sex]" class="{{ $field }}">
                        <option value=""></option>
                        @foreach (['M' => 'Male', 'F' => 'Female'] as $code => $sexLabel)
                            <option value="{{ $code }}" @selected($value("dep_{$dependent}_sex") === $code)>{{ $sexLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">{!! $input("dep_{$dependent}_dob", 'Date of Birth') !!}</div>
            </div>
        @endforeach
    </section>

    <!-- Step 4: BI Requirements -->
    <section x-show="step === {{ $petitionRequirementsStep }}" style="display: none" class="space-y-5" aria-labelledby="step-dual-requirements">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-dual-requirements" class="{{ $section }}">BI Requirements</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">{{ ImmigrationPetition::TITLES['dual'] }}. Tick what the client handed over, or upload a copy.</p>
        </div>

        <div>
            <label class="{{ $label }}">Proof of natural-born citizenship</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                @foreach (ImmigrationPetition::BIRTH_PROOFS as $code => $proofLabel)
                    <label class="flex items-center gap-2 px-3 py-3 rounded-xl border cursor-pointer text-xs font-semibold text-dark transition-colors"
                           :class="proof === '{{ $code }}' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-gray-50'">
                        <input type="radio" name="petition[details][birth_proof]" value="{{ $code }}" x-model="proof" class="text-primary"> {{ $proofLabel }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            {!! $flagBox('name_variance', 'The name differs between documents', 'Birth certificate vs. foreign passport or naturalization papers: the BI wants an Affidavit of One and the Same Person.', 'names') !!}
        </div>

        <ul class="rounded-2xl border border-gray-200 divide-y divide-gray-100">
            @foreach (ImmigrationPetition::CHECKLISTS['dual'] as $item => $itemLabel)
                @php
                    $file = $petition->documentFor($item);
                    $show = match ($item) {
                        'birth_psa' => "proof === 'psa'",
                        'birth_lcr' => "proof === 'lcr'",
                        'birth_other' => "proof === 'other'",
                        'acr_surrender' => 'alien',
                        'name_variance' => 'names',
                        'dep_acr' => 'deps && depAlien',
                        'dep_supplement', 'dep_photos', 'dep_birth_certificate', 'dep_passport' => 'deps',
                        'spa' => 'rep',
                        default => 'true',
                    };
                @endphp
                <li class="px-4 py-3 space-y-2" x-show="{{ $show }}" x-data="{ fileName: '', removing: false }">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="petition[checklist][]" value="{{ $item }}" id="dual-{{ $item }}"
                               class="w-4 h-4 mt-0.5 rounded text-primary" {{ in_array($item, $checked, true) ? 'checked' : '' }}>
                        <label for="dual-{{ $item }}" class="flex-1 min-w-0 text-xs text-dark cursor-pointer font-semibold">{{ $itemLabel }}</label>
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
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-2">Earlier petitions</span>
                <ul class="space-y-1 text-xs text-dark/70">
                    @foreach ($previous as $earlier)
                        <li>{{ $earlier->reference() }} &middot; filed {{ $earlier->filed_on?->format('M d, Y') ?? 'on an unrecorded date' }} &middot; released {{ $earlier->released_on->format('M d, Y') }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-4 pt-2">
            <div class="border-b border-gray-100 pb-2">
                <h3 class="{{ $section }}">Filing with the BI</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Mark it released from the Cases list when the BI hands it over.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Date filed</label>
                    <input type="date" name="petition[filed_on]" value="{{ old('petition.filed_on', $petition->filed_on?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">Fee (₱)</label>
                    <input type="number" step="0.01" min="0" name="petition[fee]" value="{{ old('petition.fee', $petition->fee) }}" placeholder="0.00" inputmode="decimal" class="{{ $field }}">
                    @error('petition.fee') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>
</div>
