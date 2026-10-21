@php
    use App\Models\ImmigrationOverstay;

    /*
     * Visa Overstaying, steps 3 and 4: what the BI's Request for Reconsideration on the
     * Updating and Extension of Authorized Stay asks, then its documentary requirements.
     * One Alpine scope spans both, so the checklist follows the dates typed in step 3.
     */
    $old = fn (string $key, $value) => old("overstay.$key", $value);
    $previous = $client->exists ? $client->overstays->where('released_on', '!=', null) : collect();
    $checked = old('overstay.checklist', $overstay->checklist ?? []);
@endphp

<div x-data="overstayCase({
        expiredOn: {{ Js::from($old('expired_on', $overstay->expired_on?->format('Y-m-d'))) }},
        filedOn: {{ Js::from($old('filed_on', $overstay->filed_on?->format('Y-m-d'))) }},
        departure: {{ Js::from($old('departure_date', $overstay->departure_date?->format('Y-m-d'))) }},
        representative: {{ Js::from((bool) $old('by_representative', $overstay->by_representative)) }},
        letterAfter: {{ ImmigrationOverstay::LETTER_AFTER_MONTHS }},
     })">

    <!-- Step 3: Overstay Details, as the BI's request for reconsideration asks them -->
    <section x-show="step === {{ $overstayStep }}" style="display: none" class="space-y-5" aria-labelledby="step-overstay">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-overstay" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overstay Details</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">What the BI's Request for Reconsideration on the Updating and Extension of Authorized Stay asks. These print on the request.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Authorized stay expired on</label>
                <input type="date" name="overstay[expired_on]" x-model="expiredOn" class="{{ $field }}">
                <p class="text-[11px] text-dark/40 mt-1.5">The last day of the client's last extension (or of the stay granted on arrival). Taken from step 2 for a new case.</p>
            </div>
            <div class="rounded-2xl bg-gray-50 border border-gray-100 p-4" x-show="expiredOn">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50">Overstayed</span>
                <span class="block text-lg font-black text-dark mt-0.5" x-text="length() || 'Not overstayed yet'"></span>
                <span class="block text-[11px] mt-1 font-bold" :class="needsLetter() ? 'text-rose-600' : 'text-dark/50'"
                      x-text="needsLetter() ? 'More than ' + letterAfter + ' months: the notarized letter of explanation is required.' : 'Counted to ' + (filedOn ? 'the filing date' : 'today') + '.'"></span>
            </div>
        </div>

        <div>
            <label class="{{ $label }}">Why the client failed to extend their stay</label>
            <textarea name="overstay[reason_failed]" rows="3" maxlength="2000" class="{{ $field }}"
                      placeholder="e.g. was hospitalized from March to May and could not visit the BI">{{ $old('reason_failed', $overstay->reason_failed) }}</textarea>
        </div>

        <div>
            <label class="{{ $label }}">Why the client would like to stay longer</label>
            <textarea name="overstay[reason_stay]" rows="3" maxlength="2000" class="{{ $field }}"
                      placeholder="e.g. to finish treatment and travel with family">{{ $old('reason_stay', $overstay->reason_stay) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">Leaving the Philippines on</label>
                <input type="date" name="overstay[departure_date]" x-model="departure" class="{{ $field }}">
                <p class="text-[11px] text-dark/40 mt-1.5">Only if the client leaves within a month or two. The BI then wants the ticket and an affidavit.</p>
            </div>
            <div>
                <label class="{{ $label }}">Bound to</label>
                <input type="text" name="overstay[destination]" value="{{ $old('destination', $overstay->destination) }}" placeholder="Final destination" class="{{ $field }}">
            </div>
        </div>

        <div class="space-y-4">
            <label class="flex items-start gap-2.5 px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer hover:border-primary transition-colors">
                <input type="hidden" name="overstay[by_representative]" value="0">
                <input type="checkbox" name="overstay[by_representative]" value="1" x-model="representative" class="w-4 h-4 mt-0.5 rounded text-primary">
                <span>
                    <span class="block text-xs font-bold text-dark">Filed by a representative</span>
                    <span class="block text-[11px] text-dark/50">The BI then wants an SPA and a copy of the representative's ID.</span>
                </span>
            </label>

            <div x-show="representative" style="display: none" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="{{ $label }}">Representative's name</label>
                    <input type="text" name="overstay[representative_name]" value="{{ $old('representative_name', $overstay->representative_name) }}" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">Accreditation No.</label>
                    <input type="text" name="overstay[representative_accreditation_no]" value="{{ $old('representative_accreditation_no', $overstay->representative_accreditation_no) }}" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">Relationship to the client</label>
                    <input type="text" name="overstay[representative_relationship]" value="{{ $old('representative_relationship', $overstay->representative_relationship) }}" class="{{ $field }}">
                </div>
            </div>
        </div>

        @if ($previous->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 p-4">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-2">Earlier overstays</span>
                <ul class="space-y-1 text-xs text-dark/70">
                    @foreach ($previous as $earlier)
                        <li>Stay lapsed {{ $earlier->expired_on?->format('M d, Y') ?? 'on an unrecorded date' }} &middot; released {{ $earlier->released_on->format('M d, Y') }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <!-- Step 4: BI Requirements: tick what is in hand, or upload a copy -->
    <section x-show="step === {{ $overstayStep + 1 }}" style="display: none" class="space-y-5" aria-labelledby="step-overstay-requirements">
        <div class="border-b border-gray-100 pb-2">
            <h3 id="step-overstay-requirements" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">BI Requirements</h3>
            <p class="text-[11px] text-dark/50 mt-0.5">The BI's checklist for extension and updating of stay. Tick what the client handed over, or upload a copy. The list follows step 3.</p>
        </div>

        <ul class="rounded-2xl border border-gray-200 divide-y divide-gray-100">
            @foreach (ImmigrationOverstay::CHECKLIST as $item => $itemLabel)
                @php $file = $overstay->documentFor($item); @endphp
                <li class="px-4 py-3 space-y-2" x-show="applies('{{ $item }}')" x-data="{ fileName: '', removing: false }">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="overstay[checklist][]" value="{{ $item }}" id="overstay-{{ $item }}"
                               class="w-4 h-4 mt-0.5 rounded text-primary" {{ in_array($item, $checked, true) ? 'checked' : '' }}>
                        <label for="overstay-{{ $item }}" class="flex-1 min-w-0 text-xs text-dark cursor-pointer">
                            <span class="font-semibold">{{ $itemLabel }}</span>
                            @if ($item === 'downgrading_order')
                                <span class="text-dark/50 italic">(if applicable)</span>
                            @elseif ($item === 'overstay_letter')
                                <span class="block text-[11px] text-dark/50">Overstayed more than {{ ImmigrationOverstay::LETTER_AFTER_MONTHS }} months, or past the maximum allowable stay.</span>
                            @elseif ($item === 'departure_ticket')
                                <span class="block text-[11px] text-dark/50">The client is leaving soon (step 3).</span>
                            @elseif ($item === 'spa')
                                <span class="block text-[11px] text-dark/50">Filed by a representative (step 3).</span>
                            @endif
                        </label>
                        <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-primary cursor-pointer hover:border-primary">
                            <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                            <span x-text="fileName ? 'Change' : '{{ $file ? 'Replace' : 'Upload' }}'"></span>
                            <input type="file" name="overstay[files][{{ $item }}]" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                                   @change="fileName = $event.target.files[0]?.name || ''">
                        </label>
                    </div>

                    @if ($file && $client->exists && $overstay->exists)
                        <div class="pl-7 flex flex-wrap items-center gap-3 text-[11px]" x-show="! removing">
                            <a href="{{ route('admin.client-sheets.overstay-file', [$client, $overstay, $item]) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 font-semibold text-dark hover:text-primary min-w-0">
                                <i data-lucide="file-text" class="w-3.5 h-3.5 text-primary shrink-0"></i>
                                <span class="truncate">{{ $file['name'] }}</span>
                            </a>
                            <button type="button" @click="removing = true" class="font-bold text-rose-600 hover:underline">Remove</button>
                        </div>
                        <template x-if="removing">
                            <p class="pl-7 text-[11px] text-rose-700">
                                <input type="hidden" name="overstay[remove_files][]" value="{{ $item }}">
                                The file will be removed when you save. <button type="button" @click="removing = false" class="font-bold underline">Keep it</button>
                            </p>
                        </template>
                    @endif
                    <p x-show="fileName" x-cloak class="pl-7 text-[11px] font-semibold text-dark" x-text="'New file: ' + fileName"></p>
                    @error("overstay.files.$item") <p class="pl-7 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </li>
            @endforeach
        </ul>

        <div class="space-y-4 pt-2">
            <div class="border-b border-gray-100 pb-2">
                <h3 class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Filing with the BI</h3>
                <p class="text-[11px] text-dark/50 mt-0.5">Mark it released from the Cases list when the BI hands it over.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">Date filed</label>
                    <input type="date" name="overstay[filed_on]" x-model="filedOn" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">Fee (₱)</label>
                    <input type="number" step="0.01" min="0" name="overstay[fee]" value="{{ $old('fee', $overstay->fee) }}" placeholder="0.00" inputmode="decimal" class="{{ $field }}">
                    @error('overstay.fee') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Overstay length and which requirements apply, from the dates typed in step 3.
    function overstayCase(config) {
        const day = (value) => new Date(value + 'T00:00:00');
        const addMonths = (date, months) => {
            const result = new Date(date);
            const dayOfMonth = result.getDate();
            result.setDate(1);
            result.setMonth(result.getMonth() + months);
            result.setDate(Math.min(dayOfMonth, new Date(result.getFullYear(), result.getMonth() + 1, 0).getDate()));
            return result;
        };

        return {
            ...config,

            // A new case starts from the visa expiry in step 2, until a date is typed here.
            init() {
                const expiry = document.querySelector('[name="visa_expiry_date"]');
                let following = !this.expiredOn;
                if (following && expiry?.value) this.expiredOn = expiry.value;
                expiry?.addEventListener('input', () => { if (following) this.expiredOn = expiry.value; });
                this.$watch('expiredOn', (value) => { if (value !== expiry?.value) following = false; });
            },

            countedTo() {
                return this.filedOn ? day(this.filedOn) : new Date(new Date().toDateString());
            },

            length() {
                if (!this.expiredOn) return '';
                const from = day(this.expiredOn);
                const to = this.countedTo();
                if (to <= from) return '';
                let months = (to.getFullYear() - from.getFullYear()) * 12 + to.getMonth() - from.getMonth();
                if (addMonths(from, months) > to) months--;
                const days = Math.round((to - addMonths(from, months)) / 86400000);
                return [months ? months + (months === 1 ? ' month' : ' months') : '', days ? days + (days === 1 ? ' day' : ' days') : '']
                    .filter(Boolean).join(', ');
            },

            needsLetter() {
                return !!this.expiredOn && this.countedTo() > addMonths(day(this.expiredOn), this.letterAfter);
            },

            applies(item) {
                if (item === 'overstay_letter') return this.needsLetter();
                if (item === 'departure_ticket') return !!this.departure;
                if (item === 'spa') return this.representative;
                return true;
            },
        };
    }
</script>
