@php
    use App\Models\ImmigrationPetition;

    /*
     * Visa Downgrading: the visa and why it is downgraded, which the letter request to the
     * Commissioner states, then the BI checklist (IRD01.QF.001). One Alpine scope spans
     * both steps, so the checklist shows only the extra document the visa category needs.
     */
    $value = fn (string $key) => old("petition.details.$key", $petition->detail($key));
    $section = 'font-heading text-sm font-bold text-dark uppercase tracking-wider';

    $input = function (string $key, string $text, string $placeholder = '') use ($value, $field, $label): string {
        return '<div><label class="'.$label.'">'.e($text).'</label>'
            .'<input type="text" name="petition[details]['.$key.']" value="'.e((string) $value($key)).'"'
            .($placeholder !== '' ? ' placeholder="'.e($placeholder).'"' : '').' class="'.$field.'"></div>';
    };
@endphp

<div x-data="{
        category: {{ Js::from($value('visa_category') ?: '') }},
        letterBy: {{ Js::from($value('letter_by') ?: 'applicant') }},
        rep: {{ Js::from((bool) $value('by_representative')) }},
     }">

    <!-- Step 3: Downgrading Details -->
    <section x-show="step === {{ $petitionStep }}" style="display: none" class="space-y-5" aria-labelledby="step-downgrade">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-downgrade" class="{{ $section }}">Downgrading Details</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">The visa being downgraded and why. These fill in the letter request to the Commissioner and decide which extra document the BI checklist asks for.</p>
        </div>

        <div>
            <label class="{{ $label }}">Visa being downgraded</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach (ImmigrationPetition::DOWNGRADE_CATEGORIES as $code => $categoryLabel)
                    <label class="flex items-center gap-2 px-3 py-3 rounded-xl border cursor-pointer text-xs font-semibold text-dark transition-colors"
                           :class="category === '{{ $code }}' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-gray-50'">
                        <input type="radio" name="petition[details][visa_category]" value="{{ $code }}" x-model="category" class="text-primary"> {{ $categoryLabel }}
                    </label>
                @endforeach
            </div>
            @error('petition.details.visa_category') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('present_status', 'Present visa status (as admitted)', 'e.g. 9(G) Pre-Arranged Employee') !!}
            {!! $input('acr_number', 'ACR I-Card No. (if any)') !!}
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="{{ $label }}">Reason for downgrading</label>
                <select name="petition[details][downgrade_reason]" class="{{ $field }}">
                    <option value=""></option>
                    @foreach (ImmigrationPetition::DOWNGRADE_REASONS as $code => $reasonLabel)
                        <option value="{{ $code }}" @selected($value('downgrade_reason') === $code)>{{ $reasonLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                {!! $input('reason_detail', 'In the letter, explain', 'e.g. resigned from ABC Corp. effective 30 September 2026') !!}
            </div>
        </div>

        <div class="border-b border-gray-100 pb-2 pt-2">
            <h3 class="{{ $section }}">Who Files the Letter Request</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">The applicant writes it with their address and numbers, or the petitioning company, congregation, agency or law office writes it on its letterhead.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach (['applicant' => 'The applicant', 'company' => 'The petitioning company / congregation'] as $code => $byLabel)
                <label class="flex items-center gap-2 px-3 py-3 rounded-xl border cursor-pointer text-xs font-semibold text-dark transition-colors"
                       :class="letterBy === '{{ $code }}' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-gray-50'">
                    <input type="radio" name="petition[details][letter_by]" value="{{ $code }}" x-model="letterBy" class="text-primary"> {{ $byLabel }}
                </label>
            @endforeach
        </div>
        <div x-show="letterBy === 'company'" style="display: none" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {!! $input('company_name', 'Company / congregation') !!}
            {!! $input('company_address', 'Its address') !!}
            {!! $input('company_signatory', 'Signed by (name, position)', 'e.g. Maria Santos, HR Manager') !!}
        </div>
        <div x-show="['marriage', 'trv'].includes(category)" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('spouse_name', 'Spouse (signs the letter too)', "Spouse's full name") !!}
            {!! $input('landline', 'Landline (for the letter)') !!}
        </div>

        <label class="flex items-start gap-2.5 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
            <input type="hidden" name="petition[details][by_representative]" value="0">
            <input type="checkbox" name="petition[details][by_representative]" value="1" x-model="rep" class="w-4 h-4 mt-0.5 rounded text-primary">
            <span><span class="block text-xs font-bold text-dark">Filed by a representative</span><span class="block text-[11px] text-dark/50">The BI then wants the SPA or accreditation ID.</span></span>
        </label>
        <div x-show="rep" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {!! $input('representative_name', "Representative's Name") !!}
            {!! $input('accreditation_no', 'BI Accreditation No.') !!}
        </div>
    </section>

    <!-- Step 4: BI Requirements -->
    <section x-show="step === {{ $petitionRequirementsStep }}" style="display: none" class="space-y-5" aria-labelledby="step-downgrade-requirements">
        @include('admin.client-sheets._case-requirements', [
            'heading' => 'BI Requirements',
            'intro' => 'Checklist of Documentary Requirements for Downgrading of Visa (IRD01.QF.001). Pick the visa in step 3 to see its extra document. Tick what the client handed over, or upload a copy.',
            'show' => [
                'employment_cert' => "['employment', 'missionary'].includes(category)",
                'marriage_docs' => "['marriage', 'trv'].includes(category)",
                'doj_indorsement' => "category === '47a2'",
                'cancelation_order' => "category === 'special'",
                'embassy_docs' => "category === '9e'",
                'spa' => 'rep',
            ],
        ])
    </section>
</div>
