{{--
    One payment or refund entry. Expects: $action, $amountLabel, $submitLabel and $amountDefault.
    The method, reference and date are kept with the entry so the desk can print a receipt for it.
--}}
<form method="POST" action="{{ $action }}" class="space-y-3">
    @csrf
    <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2 sm:col-span-1">
            <label for="ledger_amount" class="text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5">{{ $amountLabel }}</label>
            <input type="number" step="0.01" min="0.01" id="ledger_amount" name="amount" required
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
    </div>
    <button type="submit"
            class="px-4 py-2.5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors">
        {{ $submitLabel }}
    </button>
</form>
