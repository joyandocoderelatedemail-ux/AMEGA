{{--
    One payment or refund entry. Expects: $action, $amountLabel, $submitLabel and $amountDefault.
    The method, reference and date are kept with the entry so the desk can print a receipt for it.
    Optional $acknowledgement: a statement the person recording must tick before submitting
    (sent as cashier_acknowledged). Optional $receiptUpload: offer to attach the actual receipt.
    Optional $currency: the money may be pesos or US dollars at an exchange rate (payments, not refunds).
--}}
@php
    $acknowledgement = $acknowledgement ?? null;
    $receiptUpload = $receiptUpload ?? false;
    $currency = $currency ?? false;
@endphp
<form method="POST" action="{{ $action }}" class="space-y-3" @if ($receiptUpload) enctype="multipart/form-data" @endif
      x-data="{ acknowledged: {{ $acknowledgement ? 'false' : 'true' }}, currency: @js(old('currency', 'PHP')), usd: @js(old('usd_amount', '')), rate: @js(old('exchange_rate', '')),
                pesos() { const v = (parseFloat(this.usd) || 0) * (parseFloat(this.rate) || 0); return v > 0 ? '₱' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'; } }">
    @csrf
    @if ($currency)
        <div>
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Paid in</span>
            <input type="hidden" name="currency" :value="currency">
            <div class="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-0.5" role="group" aria-label="Currency of the payment">
                <button type="button" @click="currency = 'PHP'" :aria-pressed="currency === 'PHP'"
                        class="px-3 py-1.5 rounded-md text-sm font-semibold" :class="currency === 'PHP' ? 'bg-navy-700 text-white' : 'text-slate-600'">₱ Peso</button>
                <button type="button" @click="currency = 'USD'" :aria-pressed="currency === 'USD'"
                        class="px-3 py-1.5 rounded-md text-sm font-semibold" :class="currency === 'USD' ? 'bg-navy-700 text-white' : 'text-slate-600'">US$ Dollar</button>
            </div>
        </div>
        <div x-show="currency === 'USD'" x-cloak class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 border border-slate-200 p-3">
            <div class="col-span-2 sm:col-span-1">
                <label for="ledger_usd" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Amount received (US$)</label>
                <input type="number" step="0.01" min="0.01" id="ledger_usd" name="usd_amount" x-model="usd" :required="currency === 'USD'"
                       class="w-full rounded-lg border-slate-300 text-sm tabular-nums focus:border-navy-500 focus:ring-navy-500">
                @error('usd_amount')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label for="ledger_rate" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Exchange rate (₱ per US$1)</label>
                <input type="number" step="0.0001" min="1" id="ledger_rate" name="exchange_rate" x-model="rate" :required="currency === 'USD'" placeholder="e.g. 56.50"
                       class="w-full rounded-lg border-slate-300 text-sm tabular-nums focus:border-navy-500 focus:ring-navy-500">
                @error('exchange_rate')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <p class="col-span-2 text-sm text-slate-700">Counts as <strong class="tabular-nums" x-text="pesos()"></strong> against the peso balance.</p>
            @error('amount')
                <p class="col-span-2 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    @endif
    <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2 sm:col-span-1" @if ($currency) x-show="currency === 'PHP'" @endif>
            <label for="ledger_amount" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">{{ $amountLabel }}{{ $currency ? ' (₱)' : '' }}</label>
            <input type="number" step="0.01" min="0.01" id="ledger_amount" name="amount" @if ($currency) :required="currency === 'PHP'" :disabled="currency !== 'PHP'" @else required @endif
                   value="{{ old('amount', $amountDefault) }}"
                   class="w-full rounded-lg border-slate-300 text-sm tabular-nums focus:border-navy-500 focus:ring-navy-500">
            @error('amount')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="col-span-2 sm:col-span-1">
            <label for="ledger_method" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Method</label>
            <select id="ledger_method" name="method" required
                    class="w-full rounded-lg border-slate-300 text-sm focus:border-navy-500 focus:ring-navy-500">
                @foreach (\App\Models\TicketPayment::METHODS as $value => $label)
                    <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('method')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="col-span-2 sm:col-span-1">
            <label for="ledger_reference" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Reference <span class="normal-case font-normal">(optional)</span></label>
            <input type="text" id="ledger_reference" name="reference" maxlength="100" value="{{ old('reference') }}"
                   placeholder="Transaction or receipt no."
                   class="w-full rounded-lg border-slate-300 text-sm focus:border-navy-500 focus:ring-navy-500">
            @error('reference')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="col-span-2 sm:col-span-1">
            <label for="ledger_received_at" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Date</label>
            <input type="date" id="ledger_received_at" name="received_at" max="{{ now()->toDateString() }}"
                   value="{{ old('received_at', now()->toDateString()) }}"
                   class="w-full rounded-lg border-slate-300 text-sm focus:border-navy-500 focus:ring-navy-500">
            @error('received_at')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="col-span-2">
            <label for="ledger_note" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Note <span class="normal-case font-normal">(optional)</span></label>
            <input type="text" id="ledger_note" name="note" maxlength="255" value="{{ old('note') }}"
                   class="w-full rounded-lg border-slate-300 text-sm focus:border-navy-500 focus:ring-navy-500">
            @error('note')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        @if ($receiptUpload)
            <div class="col-span-2">
                <label for="ledger_receipt_file" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">Actual receipt <span class="normal-case font-normal">(scan or photo, optional)</span></label>
                <input type="file" id="ledger_receipt_file" name="receipt_file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-navy-700 file:text-white file:text-xs file:font-semibold">
                <p class="text-xs text-slate-500 mt-1">JPG, PNG, WEBP or PDF, up to 5 MB. It can also be attached later from the payments list.</p>
                @error('receipt_file')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>
    @if ($acknowledgement)
        <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer"
               :class="acknowledged ? 'border-navy-700 bg-navy-50' : '{{ $errors->has('cashier_acknowledged') ? 'border-rose-400 bg-rose-50' : 'border-slate-200 bg-slate-50' }}'">
            <input type="checkbox" name="cashier_acknowledged" value="1" x-model="acknowledged"
                   class="mt-0.5 w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-600">
            <span class="text-xs text-slate-700 leading-relaxed">{!! $acknowledgement !!}</span>
        </label>
        @error('cashier_acknowledged')
            <p class="text-xs text-rose-600">{{ $message }}</p>
        @enderror
    @endif
    <button type="submit" :disabled="!acknowledged"
            class="px-4 py-2.5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
        {{ $submitLabel }}
    </button>
</form>
