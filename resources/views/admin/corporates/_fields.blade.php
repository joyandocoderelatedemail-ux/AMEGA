@php
    /** @var \App\Models\CorporateAccount|null $account */
    $account = $account ?? null;
    $prefix = $prefix ?? '';
    $nameRequired = $nameRequired ?? true;
    $inputClass = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    // Nested under "corporate" on the client form, bare on the company's own form.
    $field = fn (string $key) => $prefix === '' ? $key : "{$prefix}[{$key}]";
    $value = fn (string $key) => old($prefix === '' ? $key : "{$prefix}.{$key}", $account?->{$key});
    $id = fn (string $key) => ($prefix === '' ? '' : "{$prefix}_").$key;
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
        <label for="{{ $id('company_name') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Company Name{{ $nameRequired ? ' *' : '' }}</label>
        <input id="{{ $id('company_name') }}" type="text" name="{{ $field('company_name') }}" value="{{ $value('company_name') }}" maxlength="255"
               class="{{ $inputClass }}" placeholder="Registered company name">
    </div>
    <div>
        <label for="{{ $id('registration_number') }}" class="block text-[11px] font-bold text-dark/70 mb-1">SEC / DTI Registration No. <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('registration_number') }}" type="text" name="{{ $field('registration_number') }}" value="{{ $value('registration_number') }}" maxlength="100"
               class="{{ $inputClass }}">
    </div>
    <div>
        <label for="{{ $id('tin') }}" class="block text-[11px] font-bold text-dark/70 mb-1">TIN <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('tin') }}" type="text" name="{{ $field('tin') }}" value="{{ $value('tin') }}" maxlength="50"
               class="{{ $inputClass }}" placeholder="000-000-000-000">
    </div>
    <div>
        <label for="{{ $id('industry') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Industry <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('industry') }}" type="text" name="{{ $field('industry') }}" value="{{ $value('industry') }}" maxlength="150"
               class="{{ $inputClass }}" placeholder="e.g. Manufacturing, BPO, Education">
    </div>
    <div>
        <label for="{{ $id('address') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Business Address <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('address') }}" type="text" name="{{ $field('address') }}" value="{{ $value('address') }}" maxlength="500"
               class="{{ $inputClass }}">
    </div>
    <div>
        <label for="{{ $id('contact_person') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Person <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('contact_person') }}" type="text" name="{{ $field('contact_person') }}" value="{{ $value('contact_person') }}" maxlength="255"
               class="{{ $inputClass }}" placeholder="Who arranges the company's travel">
    </div>
    <div>
        <label for="{{ $id('contact_position') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Position <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('contact_position') }}" type="text" name="{{ $field('contact_position') }}" value="{{ $value('contact_position') }}" maxlength="150"
               class="{{ $inputClass }}" placeholder="e.g. HR Manager">
    </div>
    <div>
        <label for="{{ $id('contact_email') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Email <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('contact_email') }}" type="email" name="{{ $field('contact_email') }}" value="{{ $value('contact_email') }}" maxlength="255"
               class="{{ $inputClass }}">
    </div>
    <div>
        <label for="{{ $id('contact_phone') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Contact Phone <span class="font-normal text-dark/40">(optional)</span></label>
        <input id="{{ $id('contact_phone') }}" type="text" name="{{ $field('contact_phone') }}" value="{{ $value('contact_phone') }}" maxlength="50"
               class="{{ $inputClass }}">
    </div>
    <div class="sm:col-span-2">
        <label for="{{ $id('notes') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Notes <span class="font-normal text-dark/40">(optional)</span></label>
        <textarea id="{{ $id('notes') }}" name="{{ $field('notes') }}" rows="2" maxlength="2000"
                  class="{{ $inputClass }}" placeholder="Billing terms, travel policy, anything staff should know">{{ $value('notes') }}</textarea>
    </div>
</div>
