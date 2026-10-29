@php
    /**
     * Visa & Residency: what the visa desks record about a client (the SRRV, the ACR I-Card, the SSRN,
     * where they were born, where their passport was issued). Saved on the client's record so every
     * later form is filled from it; a newer form at any desk updates it.
     *
     * @var \App\Models\User|null $user
     */
    $user = $user ?? null;
    $inputClass = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $value = fn (string $field) => old("residency.{$field}", $user?->getAttribute($field) instanceof \Illuminate\Support\Carbon ? $user->getAttribute($field)->format('Y-m-d') : $user?->getAttribute($field));
@endphp

<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
    <div>
        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Visa & Residency</span>
        <p class="text-[11px] text-dark/50 mt-0.5">Filled in by the desks as the client's files are saved, and used to fill their next forms. Correct anything here.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">SRRV number</span>
            <input type="text" name="residency[srrv_number]" maxlength="100" value="{{ $value('srrv_number') }}" class="{{ $inputClass }}"></label>
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">SRRV class</span>
            <select name="residency[srrv_visa_class]" class="{{ $inputClass }}">
                <option value="">--</option>
                <option value="classic" @selected($value('srrv_visa_class') === 'classic')>SRRV Classic</option>
                <option value="courtesy" @selected($value('srrv_visa_class') === 'courtesy')>SRRV Courtesy</option>
            </select></label>
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">SRRV issued on</span>
            <input type="date" name="residency[srrv_issued_on]" value="{{ $value('srrv_issued_on') }}" class="{{ $inputClass }}"></label>
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">ACR I-Card number</span>
            <input type="text" name="residency[acr_number]" maxlength="100" value="{{ $value('acr_number') }}" class="{{ $inputClass }}"></label>
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">ACR I-Card valid until</span>
            <input type="date" name="residency[acr_valid_until]" value="{{ $value('acr_valid_until') }}" class="{{ $inputClass }}"></label>
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">SSRN</span>
            <input type="text" name="residency[ssrn]" maxlength="100" value="{{ $value('ssrn') }}" class="{{ $inputClass }}"></label>
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">Place of birth</span>
            <input type="text" name="residency[place_of_birth]" maxlength="255" value="{{ $value('place_of_birth') }}" class="{{ $inputClass }}"></label>
        <label class="block"><span class="block text-[11px] font-bold text-dark/70 mb-1">Passport place of issue</span>
            <input type="text" name="residency[passport_place]" maxlength="255" value="{{ $value('passport_place') }}" class="{{ $inputClass }}"></label>
    </div>
</div>
