@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;
    $birthDateRequired = $birthDateRequired ?? false;
    $inputClass = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

<!-- Traveler Details (used to fill in ticket bookings) -->
<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
    <div>
        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Traveler Details</span>
        <p class="text-[11px] text-dark/50 mt-0.5">Saved to the client's profile so ticket bookings can be filled in from it.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="gender" class="block text-[11px] font-bold text-dark/70 mb-1">Gender</label>
            <select id="gender" name="gender" class="{{ $inputClass }}">
                <option value="">-- Select --</option>
                @foreach(\App\Models\User::GENDERS as $value => $label)
                    <option value="{{ $value }}" @selected(old('gender', $user?->gender) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="date_of_birth" class="block text-[11px] font-bold text-dark/70 mb-1">Date of Birth{{ $user ? '' : ' *' }}</label>
            <input id="date_of_birth" type="date" name="date_of_birth" max="{{ now()->subDay()->format('Y-m-d') }}"
                   value="{{ old('date_of_birth', $user?->date_of_birth?->format('Y-m-d')) }}"
                   @required($birthDateRequired)
                   class="{{ $inputClass }}">
            <p class="text-[10px] text-dark/40 mt-1">Sets Adult, Child or Infant on ticket bookings.</p>
        </div>
        <div>
            <label for="frequent_flyer_membership" class="block text-[11px] font-bold text-dark/70 mb-1">Frequent Flyer Membership <span class="font-normal text-dark/40">(optional)</span></label>
            <input id="frequent_flyer_membership" type="text" name="frequent_flyer_membership" value="{{ old('frequent_flyer_membership', $user?->frequent_flyer_membership) }}"
                   class="{{ $inputClass }}" placeholder="Airline and membership number">
        </div>
    </div>
</div>

<!-- Passport & Government ID -->
<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
    <div>
        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Passport & Government ID</span>
        <p class="text-[11px] text-dark/50 mt-0.5">Passport is needed for international flights and for foreign nationals; a government ID for Filipino adults on domestic flights.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="passport_number" class="block text-[11px] font-bold text-dark/70 mb-1">Passport Number</label>
            <input id="passport_number" type="text" name="passport_number" value="{{ old('passport_number', $user?->passport_number) }}"
                   class="{{ $inputClass }}" placeholder="P1234567A">
        </div>
        <div>
            <label for="passport_expiry" class="block text-[11px] font-bold text-dark/70 mb-1">Passport Expiry Date</label>
            <input id="passport_expiry" type="date" name="passport_expiry"
                   value="{{ old('passport_expiry', $user?->passport_expiry?->format('Y-m-d')) }}"
                   class="{{ $inputClass }}">
        </div>
        <div>
            <label for="passport_country" class="block text-[11px] font-bold text-dark/70 mb-1">Issuing Country</label>
            <input id="passport_country" type="text" name="passport_country" value="{{ old('passport_country', $user?->passport_country) }}"
                   class="{{ $inputClass }}" placeholder="Philippines">
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="government_id_type" class="block text-[11px] font-bold text-dark/70 mb-1">Government ID Type</label>
            <select id="government_id_type" name="government_id_type" class="{{ $inputClass }}">
                <option value="">-- Select --</option>
                @foreach(\App\Models\User::GOVERNMENT_ID_TYPES as $idType)
                    <option value="{{ $idType }}" @selected(old('government_id_type', $user?->government_id_type) === $idType)>{{ $idType }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="government_id_number" class="block text-[11px] font-bold text-dark/70 mb-1">Government ID Number</label>
            <input id="government_id_number" type="text" name="government_id_number" value="{{ old('government_id_number', $user?->government_id_number) }}"
                   class="{{ $inputClass }}" placeholder="ID number as printed on the card">
        </div>
    </div>
    <div>
        <label for="government_id_remarks" class="block text-[11px] font-bold text-dark/70 mb-1">Remarks <span class="font-normal text-dark/40">(optional)</span></label>
        <textarea id="government_id_remarks" name="government_id_remarks" rows="2" maxlength="1000"
                  class="{{ $inputClass }}" placeholder="Any other ID held, or notes about the ID, e.g. the ID name when the type is Other">{{ old('government_id_remarks', $user?->government_id_remarks) }}</textarea>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach([
            'passport_photo' => ['Passport Scan / Photo', 'Photo page with the picture and details', $user?->passport_photo_url],
            'government_id_photo' => ['Government ID Scan / Photo', 'Front of the ID card', $user?->government_id_photo_url],
        ] as $field => [$label, $hint, $currentUrl])
            <div>
                <label for="{{ $field }}" class="block text-[11px] font-bold text-dark/70 mb-1">{{ $label }}</label>
                <div class="border-2 border-dashed rounded-xl p-3 bg-white {{ $currentUrl ? 'border-emerald-300' : 'border-gray-300' }}">
                    <input id="{{ $field }}" type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp,application/pdf"
                           class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                    <p class="text-[10px] text-dark/40 mt-1.5">{{ $hint }} &middot; JPG, PNG, WEBP or PDF, up to 5 MB</p>
                    @if($currentUrl)
                        <a href="{{ $currentUrl }}" target="_blank" class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:underline">
                            <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                            <span>File on record &mdash; view</span>
                        </a>
                        <span class="text-[10px] text-dark/40 block">Uploading a new file replaces it.</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Foreigner: shown only while the nationality picked on this page is not Filipino -->
<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4" x-cloak
     x-data="{
        foreign: false,
        init() {
            const field = document.querySelector('input[name=nationality]');
            this.foreign = field ? this.isForeign(field.value) : true;
        },
        isForeign(nationality) {
            return nationality.trim() !== '' && ! /filipin|philippin|pinoy/i.test(nationality);
        },
     }"
     x-show="foreign"
     @country-selected.window="if ($event.detail.name === 'nationality') foreign = isForeign($event.detail.value)">
    <div>
        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Foreigner</span>
        <p class="text-[11px] text-dark/50 mt-0.5">For foreign nationals only. Both uploads are optional.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach([
            'stamps_photo' => ['Passport: Multiple Stamps', 'Passport page(s) carrying the stamps', $user?->stamps_photo_url],
            'arrival_stamp_photo' => ['Passport: Exception / Arrival Stamp', 'Page with the exception or arrival stamp', $user?->arrival_stamp_photo_url],
        ] as $field => [$label, $hint, $currentUrl])
            <div>
                <label for="{{ $field }}" class="block text-[11px] font-bold text-dark/70 mb-1">{{ $label }} <span class="font-normal text-dark/40">(optional)</span></label>
                <div class="border-2 border-dashed rounded-xl p-3 bg-white {{ $currentUrl ? 'border-emerald-300' : 'border-gray-300' }}">
                    <input id="{{ $field }}" type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp,application/pdf"
                           class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                    <p class="text-[10px] text-dark/40 mt-1.5">{{ $hint }} &middot; JPG, PNG, WEBP or PDF, up to 5 MB</p>
                    @error($field)
                        <p class="text-[11px] font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                    @if($currentUrl)
                        <a href="{{ $currentUrl }}" target="_blank" class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:underline">
                            <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                            <span>File on record &mdash; view</span>
                        </a>
                        <span class="text-[10px] text-dark/40 block">Uploading a new file replaces it.</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Emergency Contact -->
<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
    <div>
        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Emergency Contact</span>
        <p class="text-[11px] text-dark/50 mt-0.5">Required on international ticket bookings.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="emergency_contact_name" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Name</label>
            <input id="emergency_contact_name" type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $user?->emergency_contact_name) }}"
                   class="{{ $inputClass }}" placeholder="e.g. Maria Santos">
        </div>
        <div>
            <label for="emergency_contact_relationship" class="block text-[11px] font-bold text-dark/70 mb-1">Relationship</label>
            <input id="emergency_contact_relationship" type="text" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $user?->emergency_contact_relationship) }}"
                   class="{{ $inputClass }}" placeholder="e.g. Spouse, Parent, Sibling">
        </div>
        <div>
            <label for="emergency_contact_phone" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Phone</label>
            <input id="emergency_contact_phone" type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $user?->emergency_contact_phone) }}"
                   class="{{ $inputClass }}" placeholder="e.g. +63 917 123 4567">
        </div>
        <div>
            <label for="emergency_contact_email" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Email</label>
            <input id="emergency_contact_email" type="email" name="emergency_contact_email" value="{{ old('emergency_contact_email', $user?->emergency_contact_email) }}"
                   class="{{ $inputClass }}" placeholder="optional">
        </div>
    </div>
</div>
