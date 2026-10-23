@php
    use App\Models\ImmigrationPetition;

    /*
     * ECC: what the Emigration Clearance Certificate application form (ARD04.QF.003) asks
     * beyond steps 1 and 2, the trip in particular, then the checklist.
     */
    $value = fn (string $key) => old("petition.details.$key", $petition->detail($key));
    $section = 'font-heading text-sm font-bold text-dark uppercase tracking-wider';

    $input = function (string $key, string $text, string $placeholder = '') use ($value, $field, $label): string {
        $isDate = in_array($key, ImmigrationPetition::DATE_DETAILS, true);
        $current = $value($key);
        $current = $isDate && filled($current) ? \Illuminate\Support\Carbon::parse($current)->format('Y-m-d') : $current;

        return '<div><label class="'.$label.'">'.e($text).'</label>'
            .'<input type="'.($isDate ? 'date' : 'text').'" name="petition[details]['.$key.']" value="'.e((string) $current).'"'
            .($placeholder !== '' ? ' placeholder="'.e($placeholder).'"' : '').' class="'.$field.'"></div>';
    };
    $sex = $value('sex');
@endphp

<div x-data="{
        kind: {{ Js::from($value('ecc_kind') ?: 'ecc') }},
        alien: {{ Js::from((bool) $value('registered_alien')) }},
        rep: {{ Js::from((bool) $value('by_representative')) }},
     }">

    <!-- Step 3: ECC Details -->
    <section x-show="all || step === {{ $petitionStep }}" style="display: none" class="space-y-5" aria-labelledby="step-ecc">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-ecc" class="{{ $section }}">ECC Details</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">What the ECC application form (ARD04.QF.003) asks beyond steps 1 and 2. These print on the form.</p>
        </div>

        <div>
            <label class="{{ $label }}">Application</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach (ImmigrationPetition::ECC_KINDS as $code => $kindLabel)
                    <label class="flex items-center gap-2 px-3 py-3 rounded-xl border cursor-pointer text-xs font-semibold text-dark transition-colors"
                           :class="kind === '{{ $code }}' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-gray-50'">
                        <input type="radio" name="petition[details][ecc_kind]" value="{{ $code }}" x-model="kind" class="text-primary"> {{ $kindLabel }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">{!! $input('other_name_1', 'Other name(s) / alias(es)', 'Leave empty if none') !!}</div>
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
        {!! $input('spouse_name', 'Spouse name (if married)', 'Last name, given name, middle name') !!}

        <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">Place of Birth</h3></div>
        @if ($value('place_of_birth') && ! $value('birth_city') && ! $value('birth_province') && ! $value('country_of_birth'))
            <p class="text-[11px] text-dark/60">On file: <span class="font-semibold text-dark">{{ $value('place_of_birth') }}</span>. Pick the place below to replace it.</p>
        @endif
        <x-place-picker country-name="petition[details][country_of_birth]" province-name="petition[details][birth_province]" city-name="petition[details][birth_city]"
                        :country="$value('country_of_birth')" :province="$value('birth_province')" :city="$value('birth_city')" country-label="Country of birth" />
        <div x-show="kind === 'exemption'" style="display: none">
            {!! $input('guardian_name', 'Parent/guardian travelling with the minor', 'Last name, given name, middle name') !!}
        </div>

        <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">Latest Admission</h3></div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {!! $input('present_status', 'Visa status per latest admission', 'e.g. 9(A) Temporary Visitor') !!}
            {!! $input('arrival_date', 'Date of latest admission') !!}
            {!! $input('last_day_of_stay', 'Valid authorized stay until') !!}
        </div>

        <div class="border-b border-gray-100 pb-2 pt-2"><h3 class="{{ $section }}">Departure</h3></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('departure_date', 'Date of intended departure') !!}
            {!! $input('airline', 'Airline / vessel details', 'e.g. PR 468, NAIA T1') !!}
        </div>
        @if ($value('destination') && ! $value('destination_city') && ! $value('destination_country'))
            <p class="text-[11px] text-dark/60">On file: <span class="font-semibold text-dark">{{ $value('destination') }}</span>. Pick the destination below to replace it.</p>
        @endif
        <x-destination-picker country-name="petition[details][destination_country]" city-name="petition[details][destination_city]"
                              :country="$value('destination_country')" :city="$value('destination_city')" />
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">{!! $input('leaving_reason', 'Reason for leaving', 'e.g. Returning home after vacation') !!}</div>
            {!! $input('landline', 'Landline in the Philippines') !!}
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                <input type="hidden" name="petition[details][registered_alien]" value="0">
                <input type="checkbox" name="petition[details][registered_alien]" value="1" x-model="alien" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span><span class="block text-xs font-bold text-dark">Holds an ACR I-Card</span><span class="block text-[11px] text-dark/50">A copy of the card (front and back) goes with the application.</span></span>
            </label>
            <label class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                <input type="hidden" name="petition[details][by_representative]" value="0">
                <input type="checkbox" name="petition[details][by_representative]" value="1" x-model="rep" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span><span class="block text-xs font-bold text-dark">Filed by a representative</span><span class="block text-[11px] text-dark/50">Their name prints in the data privacy consent.</span></span>
            </label>
        </div>
        <div x-show="rep" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('representative_name', "Representative's Name", 'Last name, given name, middle name') !!}
            {!! $input('accreditation_no', 'BI Accreditation No.') !!}
        </div>
    </section>

    <!-- Step 4: Requirements -->
    <section x-show="all || step === {{ $petitionRequirementsStep }}" style="display: none" class="space-y-5" aria-labelledby="step-ecc-requirements">
        @include('admin.client-sheets._case-requirements', [
            'heading' => 'Requirements',
            'intro' => 'What the client brings for the ECC. Tick what was handed over, or upload a copy.',
            'show' => ['acr_card' => 'alien'],
        ])
    </section>
</div>
