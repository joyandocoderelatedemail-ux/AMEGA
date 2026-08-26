@php
    use App\Models\ImmigrationClient;
    use App\Models\ImmigrationClientDocument;
    use App\Models\ImmigrationClientExtension;

    $field = 'w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5';
    $cell = 'w-full px-2.5 py-2 rounded-lg bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

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

<!-- Personal Information -->
<div class="space-y-5">
    <h3 class="font-heading text-sm font-bold text-dark uppercase tracking-wider border-b border-gray-100 pb-2">Personal Information</h3>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="{{ $label }}">Last Name</label>
            <input type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}" required class="{{ $field }}">
        </div>
        <div>
            <label class="{{ $label }}">Given Name</label>
            <input type="text" name="given_name" value="{{ old('given_name', $client->given_name) }}" required class="{{ $field }}">
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

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
            <label class="{{ $label }}">Height</label>
            <input type="text" name="height" value="{{ old('height', $client->height) }}" placeholder="e.g. 175 cm" class="{{ $field }}">
        </div>
        <div>
            <label class="{{ $label }}">Weight</label>
            <input type="text" name="weight" value="{{ old('weight', $client->weight) }}" placeholder="e.g. 70 kg" class="{{ $field }}">
        </div>
        <div>
            <label class="{{ $label }}">Civil Status</label>
            <input type="text" name="civil_status" value="{{ old('civil_status', $client->civil_status) }}" class="{{ $field }}">
        </div>
        <div>
            <label class="{{ $label }}">Nationality</label>
            <input type="text" name="nationality" value="{{ old('nationality', $client->nationality) }}" class="{{ $field }}">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="{{ $label }}">Date of Birth</label>
            <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $client->date_of_birth?->format('Y-m-d')) }}" class="{{ $field }}">
        </div>
        <div>
            <label class="{{ $label }}">Passport Number</label>
            <input type="text" name="passport_number" value="{{ old('passport_number', $client->passport_number) }}"
                   class="{{ $field }} font-mono tracking-wider">
            <p class="text-[11px] text-dark/40 mt-1.5">This is how the counter finds this client again.</p>
        </div>
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
    </div>
</div>

<!-- Travel Information grid -->
<div class="space-y-4 pt-4">
    <div class="border-b border-gray-100 pb-2">
        <h3 class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Travel Information</h3>
        <p class="text-[11px] text-dark/50 mt-0.5">Leave a column completely blank and it won't be stored.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="pb-2 pr-3 text-[10px] font-extrabold uppercase tracking-wider text-dark/50"></th>
                    @foreach (ImmigrationClientDocument::TYPES as $type => $typeLabel)
                        <th class="pb-2 px-2 text-[10px] font-extrabold uppercase tracking-wider text-dark/70">{{ $typeLabel }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ([
                    'Ref. Number' => ['reference_number', 'text'],
                    'Date Paid' => ['date_paid', 'date'],
                    'SSRN #' => ['ssrn_number', 'text'],
                    'Validity' => ['validity', 'text'],
                ] as $rowLabel => [$attribute, $inputType])
                    <tr>
                        <td class="py-1.5 pr-3 text-[11px] font-bold text-dark/70 whitespace-nowrap">{{ $rowLabel }}</td>
                        @foreach (array_keys(ImmigrationClientDocument::TYPES) as $type)
                            @php
                                $document = $client->exists ? $client->documentFor($type) : null;
                                $value = $attribute === 'date_paid'
                                    ? $document?->date_paid?->format('Y-m-d')
                                    : $document?->{$attribute};
                            @endphp
                            <td class="py-1.5 px-2">
                                <input type="{{ $inputType }}"
                                       name="documents[{{ $type }}][{{ $attribute }}]"
                                       value="{{ old("documents.$type.$attribute", $value) }}"
                                       class="{{ $cell }}">
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Visa Extension ledger -->
<div class="space-y-4 pt-4">
    <div class="border-b border-gray-100 pb-2">
        <h3 class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Visa Extension Information</h3>
        <p class="text-[11px] text-dark/50 mt-0.5">The ten-row ledger from the paper sheet. Empty rows aren't stored.</p>
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
                        <td class="py-1.5 px-2 min-w-[220px]">
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
</div>

<div class="pt-4">
    <label class="{{ $label }}">Internal Notes</label>
    <textarea name="notes" rows="2" placeholder="Not printed on the client sheet." class="{{ $field }}">{{ old('notes', $client->notes) }}</textarea>
</div>
