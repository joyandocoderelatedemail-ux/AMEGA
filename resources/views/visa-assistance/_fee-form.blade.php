@php
    /**
     * One fee's form on the Fees page, for a new fee or an existing one.
     *
     * @var ?\App\Models\VisaPricingTier $fee
     * @var string $formKey  'new-<service>' or the fee's id; sent back as _fee so a failed save reopens this form
     * @var string $action
     * @var string $service
     * @var int $sortOrder
     * @var bool $useOld  this form failed validation: refill it with what was typed
     */
    $value = function (string $field) use ($fee, $useOld) {
        $current = $fee?->{$field};
        if (in_array($field, ['service_fee', 'visa_fee', 'insurance_fee', 'amount'], true) && $current !== null) {
            $current = rtrim(rtrim(number_format((float) $current, 2, '.', ''), '0'), '.');
        }

        return $useOld ? old($field, $current) : $current;
    };
    $checked = fn (string $field, bool $default) => $useOld ? (bool) old($field) : $default;
    $id = 'fee-'.$formKey.'-';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500';
    $peso = 'absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400 pointer-events-none';
@endphp
<form method="POST" action="{{ $action }}"
      x-data="{ s: {{ Js::from((string) $value('service_fee')) }}, v: {{ Js::from((string) $value('visa_fee')) }}, a: {{ Js::from((string) $value('amount')) }} }"
      x-effect="if (s !== '' || v !== '') a = String(Number(s || 0) + Number(v || 0))"
      class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5">
    @csrf
    @if ($fee)
        @method('PUT')
    @endif
    <input type="hidden" name="_fee" value="{{ $formKey }}">
    <input type="hidden" name="service_type" value="{{ $service }}">
    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="sm:col-span-2">
            <label for="{{ $id }}label" class="{{ $label }}">Fee name</label>
            <input id="{{ $id }}label" name="label" value="{{ $value('label') }}" required placeholder="e.g. Visit Visa — Japan" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $id }}country" class="{{ $label }}">Country</label>
            <input id="{{ $id }}country" name="country" value="{{ $value('country') }}" placeholder="Optional" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $id }}processing" class="{{ $label }}">Processing time</label>
            <input id="{{ $id }}processing" name="processing_time" value="{{ $value('processing_time') }}" placeholder="e.g. 1-3 weeks" class="{{ $input }}">
        </div>

        @foreach (['service_fee' => ['Service fee', 's'], 'visa_fee' => ['Visa fee', 'v']] as $field => [$text, $model])
            <div>
                <label for="{{ $id }}{{ $field }}" class="{{ $label }}">{{ $text }}</label>
                <div class="relative">
                    <span class="{{ $peso }}">₱</span>
                    <input id="{{ $id }}{{ $field }}" name="{{ $field }}" type="number" step="0.01" min="0" x-model="{{ $model }}" class="{{ $input }} pl-7 text-right tabular-nums">
                </div>
            </div>
        @endforeach
        <div>
            <label for="{{ $id }}insurance_fee" class="{{ $label }}">Insurance</label>
            <div class="relative">
                <span class="{{ $peso }}">₱</span>
                <input id="{{ $id }}insurance_fee" name="insurance_fee" type="number" step="0.01" min="0" value="{{ $value('insurance_fee') }}" placeholder="Optional" class="{{ $input }} pl-7 text-right tabular-nums">
            </div>
        </div>
        <div>
            <label for="{{ $id }}amount" class="{{ $label }}">Price</label>
            <div class="relative">
                <span class="{{ $peso }}">₱</span>
                <input id="{{ $id }}amount" name="amount" type="number" step="0.01" min="0" x-model="a" :readonly="s !== '' || v !== ''"
                       class="{{ $input }} pl-7 text-right tabular-nums font-bold" :class="(s !== '' || v !== '') ? 'bg-slate-50' : ''">
            </div>
            <p class="mt-1 text-xs text-slate-500" x-show="s !== '' || v !== ''">Service fee + visa fee</p>
        </div>

        <div class="sm:col-span-2">
            <label for="{{ $id }}inclusions" class="{{ $label }}">Inclusions</label>
            <input id="{{ $id }}inclusions" name="inclusions" value="{{ $value('inclusions') }}" placeholder="e.g. Biometric, briefing and interview" class="{{ $input }}">
        </div>
        <div class="sm:col-span-2">
            <label for="{{ $id }}notes" class="{{ $label }}">Notes</label>
            <input id="{{ $id }}notes" name="condition_notes" value="{{ $value('condition_notes') }}" placeholder="Optional" class="{{ $input }}">
        </div>
    </div>

    <div class="mt-5 pt-4 border-t border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked($checked('is_active', $fee ? (bool) $fee->is_active : true)) class="rounded border-slate-300 text-navy-700 focus:ring-navy-500">
                Shown
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="confirmed" value="1" @checked($checked('confirmed', $fee ? ! $fee->needs_review : true)) class="rounded border-slate-300 text-navy-700 focus:ring-navy-500">
                Confirmed
            </label>
        </div>
        <div class="flex items-center gap-2 sm:justify-end">
            <button type="button" @click="editing = null" class="inline-flex items-center justify-center h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
            <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                {{ $fee ? 'Save changes' : 'Add fee' }}
            </button>
        </div>
    </div>
</form>
