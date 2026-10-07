@php
    /** @var \Illuminate\Support\Collection $corporates */
    /** @var int|null $selectedCompany */
    $selectedCompany = old('corporate_account_id', $selectedCompany ?? null);
    $mode = old('corporate_mode', $corporates->isEmpty() ? 'new' : 'existing');
    $inputClass = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

{{-- Shown while the Account Category above is Corporate: pick the company this client belongs to, or register a new one. --}}
<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4" x-cloak
     x-data="{
        corporate: false,
        mode: @js($mode),
        init() {
            const category = document.getElementById('account_category');
            this.corporate = category.value === 'Corporate';
            category.addEventListener('change', () => this.corporate = category.value === 'Corporate');
        },
     }"
     x-show="corporate">
    <div>
        <span class="text-xs font-bold uppercase tracking-wider text-primary block">Corporate Account</span>
        <p class="text-[11px] text-dark/50 mt-0.5">This client travels under a company. Pick the company, or register a new one.</p>
    </div>

    <div class="flex flex-wrap gap-4 text-xs font-bold text-dark/70">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="corporate_mode" value="existing" x-model="mode" class="text-primary focus:ring-primary" @disabled($corporates->isEmpty())>
            <span>Existing company</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="corporate_mode" value="new" x-model="mode" class="text-primary focus:ring-primary">
            <span>New company</span>
        </label>
    </div>

    <div x-show="mode === 'existing'" @if($mode !== 'existing') style="display: none" @endif>
        <label for="corporate_account_id" class="block text-[11px] font-bold text-dark/70 mb-1">Company</label>
        <select id="corporate_account_id" name="corporate_account_id" class="{{ $inputClass }}" :disabled="mode !== 'existing'">
            <option value="">-- Select a company --</option>
            @foreach($corporates as $company)
                <option value="{{ $company->id }}" @selected((string) $selectedCompany === (string) $company->id)>{{ $company->company_name }}</option>
            @endforeach
        </select>
    </div>

    <div x-show="mode === 'new'" @if($mode !== 'new') style="display: none" @endif>
        @include('admin.corporates._fields', ['prefix' => 'corporate', 'account' => null])
    </div>
</div>
