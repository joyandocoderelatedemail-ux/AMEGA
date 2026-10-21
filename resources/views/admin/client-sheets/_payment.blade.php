{{--
    How the client pays for a counter case: full, a downpayment, terms or nothing yet, beside the fee.
    Expects $prefix (the form key: petition, restamping or overstay) and $model (the case).
    A downpayment and terms also take what has been paid so far; terms take the number of terms.
--}}
@php
    $paymentType = old("{$prefix}.payment_type", $model->payment_type ?? 'none');
@endphp
<div class="sm:col-span-2 space-y-3" x-data="{ type: @js($paymentType) }">
    <div>
        <span class="{{ $label }}">Payment option</span>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" role="radiogroup" aria-label="Payment option">
            @foreach (\App\Models\ImmigrationPetition::PAYMENT_TYPES as $paymentKey => $paymentText)
                <label class="flex items-center gap-2 px-3 py-2.5 rounded-xl border bg-gray-50 cursor-pointer text-xs font-semibold transition-colors"
                       :class="type === '{{ $paymentKey }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-dark hover:border-primary'">
                    <input type="radio" name="{{ $prefix }}[payment_type]" value="{{ $paymentKey }}" x-model="type" class="text-primary">
                    {{ $paymentText }}
                </label>
            @endforeach
        </div>
        @error("{$prefix}.payment_type") <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div x-show="type === 'downpayment' || type === 'terms'" style="display: none" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="{{ $label }}">Amount paid so far (₱)</label>
            <input type="number" step="0.01" min="0" name="{{ $prefix }}[amount_paid]" value="{{ old("{$prefix}.amount_paid", $model->amount_paid) }}" placeholder="0.00" inputmode="decimal" class="{{ $field }}">
            @error("{$prefix}.amount_paid") <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div x-show="type === 'terms'" style="display: none">
            <label class="{{ $label }}">Number of terms</label>
            <input type="number" min="2" max="36" name="{{ $prefix }}[terms_count]" value="{{ old("{$prefix}.terms_count", $model->terms_count) }}" placeholder="e.g. 3" class="{{ $field }}">
            @error("{$prefix}.terms_count") <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <p x-show="type === 'full'" style="display: none" class="text-[11px] text-dark/50">Full payment records the whole fee as paid.</p>
    @if ($model->exists && $model->paymentBalance() !== null && $model->payment_type)
        <p class="text-[11px] font-semibold text-dark/60">On file: paid ₱{{ number_format((float) $model->amount_paid, 2) }} of ₱{{ number_format((float) $model->fee, 2) }} &middot; balance ₱{{ number_format($model->paymentBalance(), 2) }}</p>
    @endif
</div>
