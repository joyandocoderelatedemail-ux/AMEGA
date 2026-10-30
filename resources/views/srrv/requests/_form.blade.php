{{--
    The form for an SRRV Termination of Membership, Conversion into Active Investment or Re-stamping, step by step
    like the other desk forms. Expects $type, $file (null when new) and $preview (every step at once,
    read-only). The checklist shows the PRA form's requirements, and only those that apply to the
    details given, live as they change.
--}}
@use('App\Models\SrrvServiceRequest')
@php
    $showAll = $preview ?? false;
    $isTermination = $type === 'termination';
    $isRestamping = $type === 'restamping';
    $isConversion = $type === 'conversion';
    $details = old('details', $file?->details ?? []);
    $checked = old('checklist', $file?->checklist ?? []);
    $all = SrrvServiceRequest::requirementsFor($type, []);
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;

    $steps = [1 => 'Retiree', 2 => match ($type) { 'termination' => 'Termination Details', 'restamping' => 'Passport Details', default => 'Sublease Details' }, 3 => 'Requirements', 4 => 'Remarks & Save'];
    $firstError = (string) collect($errors->keys())->first();
    $startStep = match (true) {
        $firstError === '' => array_key_exists((int) request('step', -1), $steps) ? (int) request('step') : 1,
        in_array($firstError, ['retiree_name', 'retiree_email', 'retiree_phone', 'srrv_card_number', 'visa_class'], true) => 1,
        str_starts_with($firstError, 'details') => 2,
        str_starts_with($firstError, 'checklist') => 3,
        default => 4,
    };
    $input = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1';
    $card = 'rounded-2xl bg-white border border-gray-200 shadow-card p-6';
    $flag = fn (string $key) => ! empty($details[$key]);
@endphp

@if ($errors->any())
    <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 p-4" role="alert">
        <p class="text-xs font-bold text-rose-900">Please correct the following:</p>
        <ul class="mt-1.5 space-y-1">
            @foreach ($errors->all() as $error)
                <li class="text-[11px] text-rose-800">&bull; {{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<input type="hidden" name="type" value="{{ $type }}">

<div x-data="{
        step: {{ $startStep }}, all: {{ $showAll ? 'true' : 'false' }}, first: 1, last: 4, visited: [{{ $startStep }}], titles: @js($steps),
        reason: @js($details['reason'] ?? 'voluntary'),
        downgrading: {{ $flag('with_downgrading') ? 'true' : 'false' }},
        withdraw: {{ $flag('withdraw_deposit') ? 'true' : 'false' }},
        taxFree: {{ $flag('tax_free_importation') ? 'true' : 'false' }},
        estate: @js($details['estate'] ?? 'testate'),
        rep: {{ $flag('via_representative') ? 'true' : 'false' }},
        oldPassport: @js($details['old_passport'] ?? 'surrendered'),
        nameChanged: {{ $flag('name_changed') ? 'true' : 'false' }},
        nationalityChanged: {{ $flag('nationality_changed') ? 'true' : 'false' }},
        derogatory: {{ $flag('derogatory_hit') ? 'true' : 'false' }},
        members: {{ (int) ($details['members'] ?? 1) }},
        fees: @js(SrrvServiceRequest::TERMINATION_FEES + ['restamping' => SrrvServiceRequest::RESTAMPING_FEE]),
        restamping: {{ $isRestamping ? 'true' : 'false' }},
        applies(key) {
            const death = this.reason === 'death';
            return ({
                valid_passport: this.downgrading, proof_of_departure: ! this.downgrading, certificate_of_payment: this.taxFree,
                death_certificate: death, proof_of_incapacity: this.reason === 'subrogation',
                letter_of_intent_bank: this.withdraw, claimant_ids: this.withdraw && death, estate_tax: this.withdraw && death,
                letters_testamentary: this.withdraw && death && this.estate === 'testate',
                proof_of_relationship: this.withdraw && death && this.estate === 'intestate',
                self_adjudication: this.withdraw && death && this.estate === 'intestate',
                affidavit_of_publication: this.withdraw && death && this.estate === 'intestate',
                quitclaim: this.withdraw, spa: this.rep, representative_id: this.rep,
                old_passport: this.oldPassport === 'surrendered', affidavit_of_loss: this.oldPassport === 'lost',
                affidavit_of_explanation: this.oldPassport === 'not_submitted', change_of_name: this.nameChanged,
                change_of_nationality: this.nationalityChanged, not_same_person: this.derogatory, pra_id_copy: this.rep,
            })[key] ?? true;
        },
        rate() { return this.fees[this.restamping ? 'restamping' : (this.downgrading ? 'downgrading' : 'cancellation')]; },
        money(n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2 }); },
     }"
     x-init="$watch('step', value => { if (! visited.includes(value)) visited.push(value); window.scrollTo({ top: 0, behavior: 'smooth' }); $nextTick(() => window.lucide && lucide.createIcons()); })"
     class="space-y-6">

    @unless ($showAll)
        @include('srrv.partials._stepper', ['pillIcon' => match ($type) { 'termination' => 'log-out', 'restamping' => 'stamp', default => 'trending-up' }, 'pillText' => SrrvServiceRequest::TYPES[$type].' · '.($file?->reference ?? 'New request')])
    @endunless

    {{-- Step 1: the retiree --}}
    <section x-show="all || step === 1" @if (! $showAll && $startStep !== 1) style="display: none" @endif class="space-y-6">
        <div class="{{ $card }}">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Retiree</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block"><span class="{{ $label }}">Full name *</span>
                    <input type="text" name="retiree_name" required value="{{ old('retiree_name', $file?->retiree_name) }}" class="{{ $input }}"></label>
                <label class="block"><span class="{{ $label }}">SRRV number</span>
                    <input type="text" name="srrv_card_number" value="{{ old('srrv_card_number', $file?->srrv_card_number) }}" class="{{ $input }}"></label>
                <label class="block"><span class="{{ $label }}">Email</span>
                    <input type="email" name="retiree_email" value="{{ old('retiree_email', $file?->retiree_email) }}" class="{{ $input }}"></label>
                <label class="block"><span class="{{ $label }}">Phone</span>
                    <input type="text" name="retiree_phone" value="{{ old('retiree_phone', $file?->retiree_phone) }}" class="{{ $input }}"></label>
                <label class="block"><span class="{{ $label }}">SRRV scheme</span>
                    <select name="visa_class" class="{{ $input }}">
                        @unless ($isConversion)<option value="">Not recorded</option>@endunless
                        <option value="classic" @selected(old('visa_class', $file?->visa_class ?? ($isConversion ? 'classic' : '')) === 'classic')>SRRV Classic</option>
                        <option value="courtesy" @selected(old('visa_class', $file?->visa_class) === 'courtesy')>SRRV Courtesy</option>
                    </select>
                    @if ($isConversion)<span class="block text-[10px] text-dark/45 mt-1">Only an SRRV Classic visa deposit can be converted.</span>@endif
                </label>
            </div>
        </div>
    </section>

    {{-- Step 2: what decides the requirements (and the fees) --}}
    <section x-show="all || step === 2" @if (! $showAll && $startStep !== 2) style="display: none" @endif class="space-y-6">
        @if ($isTermination)
            <div class="{{ $card }} space-y-5">
                <h2 class="font-heading text-sm font-extrabold text-dark">Termination details</h2>
                <label class="block"><span class="{{ $label }}">Reason *</span>
                    <select name="details[reason]" x-model="reason" class="{{ $input }}">
                        @foreach (SrrvServiceRequest::REASONS as $key => $text)
                            <option value="{{ $key }}" @selected(($details['reason'] ?? 'voluntary') === $key)>{{ $text }}</option>
                        @endforeach
                    </select></label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[with_downgrading]" value="0">
                        <input type="checkbox" name="details[with_downgrading]" value="1" x-model="downgrading" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span><span class="block text-xs font-bold text-dark">Downgrade to a Temporary Visitor's Visa (59 days)</span>
                            <span class="block text-[10px] text-dark/50">The retiree stays a while: each member then needs an Emigration Clearance Certificate (ECC) from the BI before leaving.</span></span>
                    </label>
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[withdraw_deposit]" value="0">
                        <input type="checkbox" name="details[withdraw_deposit]" value="1" x-model="withdraw" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span><span class="block text-xs font-bold text-dark">Withdraw the visa deposit</span>
                            <span class="block text-[10px] text-dark/50">Paid to the principal's own bank account; subject to the bank's rules.</span></span>
                    </label>
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[tax_free_importation]" value="0">
                        <input type="checkbox" name="details[tax_free_importation]" value="1" x-model="taxFree" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span><span class="block text-xs font-bold text-dark">Availed of tax-free importation</span>
                            <span class="block text-[10px] text-dark/50">And terminating within 3 years of the SRRV.</span></span>
                    </label>
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[via_representative]" value="0">
                        <input type="checkbox" name="details[via_representative]" value="1" x-model="rep" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span><span class="block text-xs font-bold text-dark">Filed by a representative, agent or marketer</span></span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <label class="block"><span class="{{ $label }}">Members (principal + dependents) *</span>
                        <input type="number" name="details[members]" min="1" max="20" x-model.number="members" value="{{ (int) ($details['members'] ?? 1) }}" class="{{ $input }}"></label>
                    <label class="block" x-show="! downgrading"><span class="{{ $label }}">Planned departure</span>
                        <input type="date" name="details[departure_on]" value="{{ $details['departure_on'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block" x-show="withdraw && reason === 'death'"><span class="{{ $label }}">The principal died</span>
                        <select name="details[estate]" x-model="estate" class="{{ $input }}">
                            <option value="testate">Leaving a will (testate)</option>
                            <option value="intestate">Without a will (intestate)</option>
                        </select></label>
                </div>

                <label class="block" x-show="withdraw"><span class="{{ $label }}">Principal's complete bank details *</span>
                    <textarea name="details[bank_details]" rows="3" class="{{ $input }}" placeholder="Bank name and address, account number and name, SWIFT code, account holder's address; IBAN and routing number if any; intermediary bank">{{ $details['bank_details'] ?? '' }}</textarea></label>

                <div class="rounded-xl bg-primary/5 border border-primary/15 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Fees (per member)</p>
                    <p class="text-xs text-dark">
                        PRA service fee <b x-text="'US$ ' + money(rate().usd * members)"></b> and Bureau of Immigration fee <b x-text="'₱' + money(rate().php * members)"></b>
                        <span class="text-dark/50" x-text="'(' + members + ' × US$ ' + money(rate().usd) + ' + ₱' + money(rate().php) + (downgrading ? ', with downgrading' : ', cancellation') + ')'"></span>
                    </p>
                </div>
            </div>
        @elseif ($isRestamping)
            <div class="{{ $card }} space-y-5">
                <div>
                    <h2 class="font-heading text-sm font-extrabold text-dark">Passport details</h2>
                    <p class="text-[11px] text-dark/50 mt-0.5">The SRRV sticker moves from the old passport to the new one. The new passport is kept on the client's record for their next forms.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <label class="block"><span class="{{ $label }}">New passport number *</span>
                        <input type="text" name="details[new_passport_number]" maxlength="50" value="{{ $details['new_passport_number'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">Issued on</span>
                        <input type="date" name="details[new_passport_issued_on]" value="{{ $details['new_passport_issued_on'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">Valid until</span>
                        <input type="date" name="details[new_passport_expiry]" value="{{ $details['new_passport_expiry'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">Place of issue</span>
                        <input type="text" name="details[new_passport_place]" maxlength="255" value="{{ $details['new_passport_place'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">Old passport number</span>
                        <input type="text" name="details[old_passport_number]" maxlength="50" value="{{ $details['old_passport_number'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">The old passport (with the sticker) *</span>
                        <select name="details[old_passport]" x-model="oldPassport" class="{{ $input }}">
                            @foreach (SrrvServiceRequest::OLD_PASSPORT as $key => $text)
                                <option value="{{ $key }}" @selected(($details['old_passport'] ?? 'surrendered') === $key)>{{ $text }}</option>
                            @endforeach
                        </select></label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <label class="block sm:col-span-2"><span class="{{ $label }}">Local address (for the Retiree Request Form)</span>
                        <input type="text" name="details[address_local]" maxlength="500" value="{{ $details['address_local'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">Filed at</span>
                        <select name="details[request_office]" class="{{ $input }}">
                            <option value="">--</option>
                            @foreach (\App\Support\SrrvForms::OFFICES as $key => $text)
                                <option value="{{ $key }}" @selected(($details['request_office'] ?? '') === $key)>PRA {{ $text }}</option>
                            @endforeach
                        </select></label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[name_changed]" value="0">
                        <input type="checkbox" name="details[name_changed]" value="1" x-model="nameChanged" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span class="text-xs font-bold text-dark">The name changed on the new passport</span>
                    </label>
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[nationality_changed]" value="0">
                        <input type="checkbox" name="details[nationality_changed]" value="1" x-model="nationalityChanged" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span><span class="block text-xs font-bold text-dark">The nationality changed</span>
                            <span class="block text-[10px] text-dark/50">Subject to the BI Commissioner's approval.</span></span>
                    </label>
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[via_representative]" value="0">
                        <input type="checkbox" name="details[via_representative]" value="1" x-model="rep" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span class="text-xs font-bold text-dark">Filed by an authorized representative</span>
                    </label>
                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                        <input type="hidden" name="details[derogatory_hit]" value="0">
                        <input type="checkbox" name="details[derogatory_hit]" value="1" x-model="derogatory" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                        <span><span class="block text-xs font-bold text-dark">Not cleared on the BI derogatory check</span>
                            <span class="block text-[10px] text-dark/50">Tick when the PRA says so: a Certificate of Not the Same Person is then needed.</span></span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <label class="block"><span class="{{ $label }}">Members re-stamped (principal + dependents) *</span>
                        <input type="number" name="details[members]" min="1" max="20" x-model.number="members" value="{{ (int) ($details['members'] ?? 1) }}" class="{{ $input }}"></label>
                    <div class="sm:col-span-2 rounded-xl bg-primary/5 border border-primary/15 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Fees (per member)</p>
                        <p class="text-xs text-dark">
                            PRA service fee <b x-text="'US$ ' + money(rate().usd * members)"></b> and Bureau of Immigration fee <b x-text="'₱' + money(rate().php * members)"></b>
                            <span class="text-dark/50" x-text="'(' + members + ' × US$ ' + money(rate().usd) + ' + ₱' + money(rate().php) + ')'"></span>
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="{{ $card }} space-y-5">
                <h2 class="font-heading text-sm font-extrabold text-dark">Sublease of residential property</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block sm:col-span-2"><span class="{{ $label }}">Property (ready for occupancy) *</span>
                        <input type="text" name="details[property_address]" value="{{ $details['property_address'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">Sublessor *</span>
                        <input type="text" name="details[sublessor_name]" value="{{ $details['sublessor_name'] ?? '' }}" class="{{ $input }}"></label>
                    <label class="block"><span class="{{ $label }}">SRRV issued on *</span>
                        <input type="date" name="details[srrv_issued_on]" value="{{ $details['srrv_issued_on'] ?? '' }}" class="{{ $input }}">
                        <span class="block text-[10px] text-dark/45 mt-1">Allowed {{ SrrvServiceRequest::CONVERSION_DAYS_AFTER_SRRV }} days after the SRRV was issued.</span></label>
                    <label class="block"><span class="{{ $label }}">Sublease amount (US$ equivalent) *</span>
                        <input type="number" step="0.01" min="0" name="details[sublease_amount]" value="{{ $details['sublease_amount'] ?? '' }}" class="{{ $input }}">
                        <span class="block text-[10px] text-dark/45 mt-1">At least US${{ number_format(SrrvServiceRequest::CONVERSION_MIN_AMOUNT) }}, paid in Philippine pesos; released to the sublessor in one payment.</span></label>
                    <label class="block"><span class="{{ $label }}">Sublease term (years) *</span>
                        <input type="number" min="1" max="99" name="details[sublease_years]" value="{{ $details['sublease_years'] ?? '' }}" class="{{ $input }}">
                        <span class="block text-[10px] text-dark/45 mt-1">At least {{ SrrvServiceRequest::CONVERSION_MIN_YEARS }} years.</span></label>
                    <label class="block"><span class="{{ $label }}">Ocular inspection (by the PRA)</span>
                        <input type="date" name="details[ocular_inspection_on]" value="{{ $details['ocular_inspection_on'] ?? '' }}" class="{{ $input }}">
                        <span class="block text-[10px] text-dark/45 mt-1">Scheduled by the PRA once every requirement is in.</span></label>
                </div>
                <label class="flex items-start gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                    <input type="hidden" name="details[via_representative]" value="0">
                    <input type="checkbox" name="details[via_representative]" value="1" x-model="rep" class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                    <span class="text-xs font-bold text-dark">Filed by a representative on behalf of the retiree</span>
                </label>
                @if ($file && ($problems = SrrvServiceRequest::conversionProblems($file->details ?? [], $file->visa_class)))
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-900 space-y-0.5">
                        @foreach ($problems as $problem)<p>&bull; {{ $problem }}</p>@endforeach
                    </div>
                @endif
            </div>
        @endif
    </section>

    {{-- Step 3: the PRA checklist, only what applies --}}
    <section x-show="all || step === 3" @if (! $showAll && $startStep !== 3) style="display: none" @endif class="space-y-6">
        <div class="{{ $card }} space-y-4">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Requirements</h2>
                <p class="text-[11px] text-dark/50">The PRA checklist ({{ match ($type) { 'termination' => 'PRA-SD-FORM-0008', 'restamping' => "the PRA Citizen's Charter for re-stamping", default => 'PRA-SD-FORM-0010' } }}). Tick each document you have received, and upload its scan beside it (uploading ticks it); only what applies to these details is listed. PDF, JPG, PNG or WEBP, up to 10 MB each. Documents from abroad must be apostilled or authenticated, and originals shown.</p>
            </div>
            @php $filedByRequirement = $file ? $file->documents->groupBy('requirement') : collect(); @endphp
            @foreach (collect($all)->groupBy(fn ($item) => $item[1], true) as $section => $items)
                {{-- A section shows only while something in it applies. --}}
                <div class="space-y-2" x-show="@js($items->keys()->all()).some(key => applies(key))">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-dark/45">{{ $section }}</p>
                    @foreach ($items as $key => [$text])
                        <div x-show="applies('{{ $key }}')" x-data="{ upload: '' }" class="flex flex-wrap items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-200">
                            <label class="flex items-start gap-2.5 flex-1 min-w-[14rem] cursor-pointer">
                                <input type="checkbox" name="checklist[]" value="{{ $key }}" x-ref="tick" @checked(in_array($key, $checked, true)) class="mt-0.5 w-4 h-4 rounded text-primary border-gray-300">
                                <span class="text-xs text-dark">{{ $text }}</span>
                            </label>
                            @foreach ($filedByRequirement->get($key, collect()) as $document)
                                <a href="{{ route('srrv.requests.documents.download', $document) }}" x-show="! upload"
                                   class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:underline max-w-[14rem] truncate">
                                    <i data-lucide="check-circle-2" class="w-3.5 h-3.5 shrink-0"></i><span class="truncate">{{ $document->original_name }}</span>
                                </a>
                            @endforeach
                            <span x-show="upload" x-cloak x-text="upload" class="text-[11px] font-semibold text-dark/70 max-w-[14rem] truncate"></span>
                            @unless ($showAll)
                                <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-primary text-[11px] font-bold cursor-pointer hover:border-primary focus-within:ring-2 focus-within:ring-primary">
                                    <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                    <span x-text="upload ? 'Change file' : '{{ $filedByRequirement->has($key) ? 'Upload another' : 'Upload' }}'">Upload</span>
                                    <input type="file" name="documents[{{ $key }}]" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only" aria-label="Upload {{ $text }}"
                                           @change="upload = $event.target.files[0]?.name || ''; if (upload) $refs.tick.checked = true">
                                </label>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endforeach
            @error('documents.*') <p class="text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>

        {{-- Anything else: an SPA from the bank, a flight ticket, the signed request form... --}}
        <div class="{{ $card }} space-y-3">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">Other documents</h2>
                <p class="text-[11px] text-dark/50">Anything else for this request (PDF, JPG, PNG or WEBP, up to 10 MB each). Filed when the request is saved.</p>
            </div>
            @if ($filedByRequirement->has('other'))
                <ul class="rounded-xl border border-gray-200 divide-y divide-gray-100">
                    @foreach ($filedByRequirement->get('other') as $document)
                        <li class="px-4 py-2.5"><a href="{{ route('srrv.requests.documents.download', $document) }}" class="text-xs font-semibold text-primary hover:underline">{{ $document->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif
            @unless ($showAll)
                <input type="file" name="other_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"
                       class="block w-full text-xs text-dark/70 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-primary file:text-white file:text-xs file:font-bold">
            @endunless
            @error('other_documents.*') <p class="text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- Step 4: remarks and save --}}
    <section x-show="all || step === 4" @if (! $showAll && $startStep !== 4) style="display: none" @endif class="space-y-6">
        <div class="{{ $card }}">
            <label class="block"><span class="font-heading text-sm font-extrabold text-dark mb-1 block">Remarks</span>
                <textarea name="remarks" rows="3" class="{{ $input }}">{{ old('remarks', $file?->remarks) }}</textarea></label>
        </div>
        @unless ($showAll)
            <div class="flex items-center justify-end gap-3">
                <a href="{{ $file ? route('srrv.requests.show', $file) : route('srrv.services', $type) }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200">Cancel</a>
                @unless ($file)
                    @include('partials._save-pending', ['desk' => 'srrv', 'title' => \App\Models\SrrvServiceRequest::TYPES[$type] ?? 'SRRV request'])
                @endunless
                <button type="submit" class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light shadow-sm inline-flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>{{ $file ? 'Save Changes' : 'Open Request' }}</span>
                </button>
            </div>
        @endunless
    </section>

    @unless ($showAll)
        @include('srrv.partials._stepper-nav')
    @endunless
</div>
