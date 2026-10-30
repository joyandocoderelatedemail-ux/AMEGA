@php
    use App\Models\ImmigrationRestamping;

    /*
     * Re Stamping / Amendment, steps 3 and 4: what the IRD Consolidated General
     * Application Form asks beyond the client sheet, then the BI checklist for each
     * application ticked. One Alpine scope spans both, so the checklists follow step 3.
     */
    $old = fn (string $key, $value) => old("restamping.$key", $value);
    $previous = $client->exists ? $client->restampings->where('released_on', '!=', null) : collect();
    $checked = old('restamping.checklist', $restamping->checklist ?? []);
    $groups = [
        'transfer' => ['Transfer of Admission Status', 'BI Form IRD01.QF.002: from an old, lost or cancelled passport to a new one', ImmigrationRestamping::TRANSFER_CHECKLIST],
        'amendment' => ['Amendment / Correction of Admission', 'BI Form IRD01.QF.003: also for a failure to check, encode or stamp', ImmigrationRestamping::AMENDMENT_CHECKLIST],
    ];
@endphp

<div x-data="restampingCase({
        applications: {{ Js::from(array_values((array) $old('applications', $restamping->applications ?? []))) }},
        lost: {{ Js::from((bool) $old('passport_lost', $restamping->passport_lost)) }},
        balikbayan: {{ Js::from((bool) $old('balikbayan', $restamping->balikbayan)) }},
        spouse: {{ Js::from((bool) $old('with_filipino_spouse', $restamping->with_filipino_spouse)) }},
        amendments: {{ Js::from(ImmigrationRestamping::AMENDMENT_APPLICATIONS) }},
     })">

    <!-- Step 3: Admission Details, as the IRD application form asks them -->
    <section x-show="all || step === {{ $restampingStep }}" style="display: none" class="space-y-5" aria-labelledby="step-restamping">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-restamping" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Admission Details</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">What the IRD Consolidated General Application Form asks beyond steps 1 and 2. These print on the form.</p>
        </div>

        <div>
            <label class="{{ $label }}">Application *</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach (ImmigrationRestamping::APPLICATIONS as $key => $applicationLabel)
                    <label class="flex items-center gap-2.5 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                           :class="applications.includes('{{ $key }}') ? 'border-primary bg-primary/5' : 'border-gray-200 bg-gray-50 hover:border-primary'">
                        <input type="checkbox" name="restamping[applications][]" value="{{ $key }}" x-model="applications" class="w-4 h-4 rounded text-primary">
                        <span class="text-xs font-bold text-dark">{{ $applicationLabel }}</span>
                    </label>
                @endforeach
            </div>
            @error('restamping.applications') <p class="mt-1.5 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="{{ $label }}">Middle Name</label>
                <input type="text" name="restamping[middle_name]" value="{{ $old('middle_name', $restamping->middle_name) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Old Passport Number</label>
                <input type="text" name="restamping[old_passport_number]" value="{{ $old('old_passport_number', $restamping->old_passport_number) }}" class="{{ $field }} font-mono tracking-wider">
                <p class="text-[11px] text-dark/40 mt-1.5">The new passport is the one in step 2.</p>
            </div>
            <div>
                <label class="{{ $label }}">Landline</label>
                <input type="text" name="restamping[landline]" value="{{ $old('landline', $restamping->landline) }}" class="{{ $field }}">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="{{ $label }}">Latest Date of Arrival</label>
                <input type="date" name="restamping[arrival_date]" value="{{ $old('arrival_date', $restamping->arrival_date?->format('Y-m-d')) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Flight Number</label>
                <input type="text" name="restamping[flight_number]" value="{{ $old('flight_number', $restamping->flight_number) }}" maxlength="20" placeholder="e.g. PR 101" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">BIBS No.</label>
                <input type="text" name="restamping[bibs_number]" value="{{ $old('bibs_number', $restamping->bibs_number) }}" maxlength="20" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Status of Admission</label>
                <input type="text" name="restamping[admission_status]" value="{{ $old('admission_status', $restamping->admission_status) }}" maxlength="40" placeholder="e.g. 9(a) Temporary Visitor" class="{{ $field }}">
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <label x-show="applications.includes('transfer')" style="display: none"
                   class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                <input type="hidden" name="restamping[passport_lost]" value="0">
                <input type="checkbox" name="restamping[passport_lost]" value="1" x-model="lost" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span>
                    <span class="block text-xs font-bold text-dark">The old passport was lost</span>
                    <span class="block text-[11px] text-dark/50">The BI then wants the original new passport.</span>
                </span>
            </label>
            <label x-show="isAmendment()" style="display: none"
                   class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                <input type="hidden" name="restamping[balikbayan]" value="0">
                <input type="checkbox" name="restamping[balikbayan]" value="1" x-model="balikbayan" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span>
                    <span class="block text-xs font-bold text-dark">Balikbayan</span>
                    <span class="block text-[11px] text-dark/50">A former Filipino: the birth certificate replaces the visa page.</span>
                </span>
            </label>
            <label x-show="isAmendment() && balikbayan" style="display: none"
                   class="flex items-start gap-2.5 flex-1 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                <input type="hidden" name="restamping[with_filipino_spouse]" value="0">
                <input type="checkbox" name="restamping[with_filipino_spouse]" value="1" x-model="spouse" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span>
                    <span class="block text-xs font-bold text-dark">Travelling with a Filipino spouse</span>
                    <span class="block text-[11px] text-dark/50">The BI then wants the marriage contract.</span>
                </span>
            </label>
        </div>

        <div>
            <label class="{{ $label }}">Reason / Explanation</label>
            <textarea name="restamping[reason]" rows="3" maxlength="2000" class="{{ $field }}"
                      placeholder="e.g. arrived on 02 OCT 2026 on PR 101 but the passport was not stamped at the counter">{{ $old('reason', $restamping->reason) }}</textarea>
        </div>

        @if ($previous->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 p-4">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-2">Earlier re-stamping transactions</span>
                <ul class="space-y-1 text-xs text-dark/70">
                    @foreach ($previous as $earlier)
                        <li>
                            {{ collect($earlier->applications)->map(fn ($key) => ImmigrationRestamping::APPLICATIONS[$key] ?? $key)->implode(', ') }}
                            &middot; released {{ $earlier->released_on->format('M d, Y') }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <!-- Step 4: BI Requirements, one checklist per application ticked -->
    <section x-show="all || step === {{ $restampingStep + 1 }}" style="display: none" class="space-y-5" aria-labelledby="step-restamping-requirements">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-restamping-requirements" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">BI Requirements</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">The BI's checklist for each application ticked in step 3. Tick what the client handed over, or upload a copy.</p>
        </div>

        <p x-show="! applications.includes('transfer') && ! isAmendment()" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs font-semibold text-amber-800">
            Tick what the client is applying for in step 3 to see the requirements.
        </p>

        @foreach ($groups as $group => [$groupTitle, $groupNote, $items])
            <div class="space-y-2" x-show="{{ $group === 'transfer' ? "applications.includes('transfer')" : 'isAmendment()' }}" style="display: none">
                <div>
                    <span class="block text-xs font-bold text-dark">{{ $groupTitle }}</span>
                    <span class="block text-[11px] text-dark/50">{{ $groupNote }}</span>
                </div>
                <ul class="rounded-2xl border border-gray-200 divide-y divide-gray-100">
                    @foreach ($items as $item => $itemLabel)
                        @php $file = $restamping->documentFor($item); @endphp
                        <li class="px-4 py-3 space-y-2" x-show="applies('{{ $item }}')" x-data="{ fileName: '', removing: false }">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" name="restamping[checklist][]" value="{{ $item }}" id="restamping-{{ $item }}"
                                       class="w-4 h-4 mt-0.5 rounded text-primary" {{ in_array($item, $checked, true) ? 'checked' : '' }}>
                                <label for="restamping-{{ $item }}" class="flex-1 min-w-0 text-xs text-dark cursor-pointer">
                                    <span class="font-semibold">{{ $itemLabel }}</span>
                                </label>
                                <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-primary cursor-pointer hover:border-primary">
                                    <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                    <span x-text="fileName ? 'Change' : '{{ $file ? 'Replace' : 'Upload' }}'"></span>
                                    <input type="file" name="restamping[files][{{ $item }}]" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                                           @change="fileName = $event.target.files[0]?.name || ''">
                                </label>
                            </div>

                            @if ($file && $client->exists && $restamping->exists)
                                <div class="pl-7 flex flex-wrap items-center gap-3 text-[11px]" x-show="! removing">
                                    <a href="{{ route('admin.client-sheets.restamping-file', [$client, $restamping, $item]) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1.5 font-semibold text-dark hover:text-primary min-w-0">
                                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-primary shrink-0"></i>
                                        <span class="truncate">{{ $file['name'] }}</span>
                                    </a>
                                    <button type="button" @click="removing = true" class="font-bold text-rose-600 hover:underline">Remove</button>
                                </div>
                                <template x-if="removing">
                                    <p class="pl-7 text-[11px] text-rose-700">
                                        <input type="hidden" name="restamping[remove_files][]" value="{{ $item }}">
                                        The file will be removed when you save. <button type="button" @click="removing = false" class="font-bold underline">Keep it</button>
                                    </p>
                                </template>
                            @endif
                            <p x-show="fileName" x-cloak class="pl-7 text-[11px] font-semibold text-dark" x-text="'New file: ' + fileName"></p>
                            @error("restamping.files.$item") <p class="pl-7 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        <div class="space-y-4 pt-2">
            <div class="border-b border-gray-100 pb-2">
                <h3 class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Filing with the BI</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Mark it released from the Transactions list when the BI hands it over.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Date filed</label>
                    <input type="date" name="restamping[filed_on]" value="{{ $old('filed_on', $restamping->filed_on?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">Fee (₱)</label>
                    <input type="number" step="0.01" min="0" name="restamping[fee]" value="{{ $old('fee', $restamping->fee) }}" placeholder="0.00" inputmode="decimal" class="{{ $field }}">
                    @error('restamping.fee') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </div>
@include('admin.client-sheets._payment', ['prefix' => 'restamping', 'model' => $restamping])
            </div>
        </div>
    </section>
</div>

<script>
    // Which checklist items apply, from the applications and boxes ticked in step 3.
    function restampingCase(config) {
        return {
            ...config,

            isAmendment() {
                return this.applications.some((application) => this.amendments.includes(application));
            },

            applies(item) {
                if (item === 'new_passport') return this.lost;
                if (item === 'visa_page') return !this.balikbayan;
                if (item === 'birth_certificate') return this.balikbayan;
                if (item === 'marriage_contract') return this.balikbayan && this.spouse;
                return true;
            },
        };
    }
</script>
