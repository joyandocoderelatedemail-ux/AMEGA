@props(['source', 'options', 'heading'])

{{--
    Free / Paid choice for each service or request picked in the wizard, with a price box when it is charged.
    `source` is the list of picked keys on the form, `options` the list the labels come from. The values are
    submitted as extras_pricing[key][mode|price] and the price counts toward the quotation total.
--}}
<div x-show="{{ $source }}.length" class="space-y-2 pt-3 border-t border-gray-100">
    <span class="text-xs font-bold text-dark/70 uppercase tracking-wider block">{{ $heading }}</span>
    <template x-for="key in {{ $source }}" :key="key">
        <div class="p-3 rounded-xl bg-gray-50 border border-gray-200 space-y-1.5">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-bold text-dark min-w-[10rem]" x-text="extraLabel({{ $options }}, key)"></span>

                <div class="inline-flex rounded-lg overflow-hidden border border-gray-200 bg-white text-xs font-bold" role="radiogroup">
                    <label class="cursor-pointer px-3.5 py-1.5 transition-colors"
                           :class="extraMode(key) === 'free' ? 'bg-primary text-white' : 'text-dark/70 hover:bg-gray-50'">
                        <input type="radio" class="sr-only" :name="'extras_pricing[' + key + '][mode]'" value="free"
                               :checked="extraMode(key) === 'free'" @change="setExtraMode(key, 'free')">
                        <span>Free</span>
                    </label>
                    <label class="cursor-pointer px-3.5 py-1.5 transition-colors"
                           :class="extraMode(key) === 'paid' ? 'bg-primary text-white' : 'text-dark/70 hover:bg-gray-50'">
                        <input type="radio" class="sr-only" :name="'extras_pricing[' + key + '][mode]'" value="paid"
                               :checked="extraMode(key) === 'paid'" @change="setExtraMode(key, 'paid')">
                        <span>Paid</span>
                    </label>
                </div>

                <div x-show="extraMode(key) === 'paid'" class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-dark/60">&#8369;</span>
                    <input type="number" step="0.01" min="0" placeholder="Price"
                           :name="'extras_pricing[' + key + '][price]'" :disabled="extraMode(key) !== 'paid'"
                           :value="extraPrice(key)" @input="setExtraPrice(key, $event.target.value)"
                           :data-error-key="'extras.' + key" :aria-invalid="errors['extras.' + key] ? 'true' : null"
                           :class="errors['extras.' + key] ? '!border-rose-400 !bg-rose-50/60' : ''"
                           class="w-32 px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>
            <x-ticketing.field-error key="'extras.' + key" />
        </div>
    </template>
</div>
