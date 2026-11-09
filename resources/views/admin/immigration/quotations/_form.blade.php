@php
    /**
     * The quotation form: who it is for (found in the client folders or typed for a walk-in), the lines (a counter
     * service, a BI rate, or anything else), a discount, notes and how long it is valid.
     *
     * @var \App\Models\ImmigrationQuotation $quotation
     * @var \Illuminate\Support\Collection $services
     * @var \Illuminate\Support\Collection $rates
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
    $field = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-500';

    $initialItems = old('items', $quotation->exists
        ? $quotation->items->map(fn ($item) => ['service_key' => $item->service_key ?? '', 'description' => $item->description, 'quantity' => $item->quantity, 'unit_price' => (float) $item->unit_price])->all()
        : []);
    $client = [
        'immigration_client_id' => old('immigration_client_id', $quotation->immigration_client_id),
        'user_id' => old('user_id', $quotation->user_id),
        'client_name' => old('client_name', $quotation->client_name),
        'client_email' => old('client_email', $quotation->client_email),
        'client_phone' => old('client_phone', $quotation->client_phone),
        'nationality' => old('nationality', $quotation->nationality),
        'passport_number' => old('passport_number', $quotation->passport_number),
    ];
@endphp

@if ($errors->any())
    <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 space-y-1" role="alert">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<div x-data="quotationForm({
        items: {{ Js::from(array_values($initialItems)) }},
        client: {{ Js::from($client) }},
        discount: {{ Js::from((float) old('discount', $quotation->discount ?? 0)) }},
        services: {{ Js::from($services) }},
        rates: {{ Js::from($rates) }},
        lookupUrl: {{ Js::from(route('admin.immigration.quotations.clients')) }},
     })" class="space-y-6">

    {{-- 1. Who it is for --}}
    <section class="{{ $card }}">
        <div>
            <h2 class="text-base font-bold text-slate-900">Client</h2>
            <p class="text-sm text-slate-500">Find a client already on file, or type a walk-in's details.</p>
        </div>

        <div class="relative" @click.outside="open = false">
            <label for="client-search" class="{{ $label }}">Find a client</label>
            <input id="client-search" type="search" x-model="term" @input.debounce.250ms="lookup()" @focus="open = results.length > 0" autocomplete="off"
                   placeholder="Name, passport, email or phone" class="{{ $field }}">
            <div x-show="open" x-cloak class="absolute z-30 left-0 right-0 mt-1 bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden">
                <template x-for="(match, i) in results" :key="match.kind + match.id">
                    <button type="button" @click="pick(match)" class="w-full text-left flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900 truncate" x-text="match.client_name"></span>
                            <span class="block text-xs text-slate-500 truncate" x-text="[match.passport_number ? 'Passport ' + match.passport_number : null, match.nationality, match.client_email].filter(Boolean).join(' · ')"></span>
                        </span>
                        <span class="text-xs font-semibold shrink-0" :class="match.kind === 'folder' ? 'text-navy-700' : 'text-slate-500'" x-text="match.kind === 'folder' ? 'Has a folder' : 'Registered client'"></span>
                    </button>
                </template>
            </div>
            <p x-show="client.immigration_client_id || client.user_id" x-cloak class="mt-2 text-xs font-semibold text-emerald-700">
                <span x-text="client.immigration_client_id ? 'Linked to the client\'s folder.' : 'Linked to the registered client.'"></span>
                <button type="button" @click="unlink()" class="ml-2 text-slate-500 underline">Unlink</button>
            </p>
        </div>

        <input type="hidden" name="immigration_client_id" :value="client.immigration_client_id || ''">
        <input type="hidden" name="user_id" :value="client.user_id || ''">

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="client_name" class="{{ $label }}">Client name *</label>
                <input id="client_name" name="client_name" x-model="client.client_name" required maxlength="255" class="{{ $field }}" placeholder="e.g. John Smith">
            </div>
            <div>
                <label for="client_email" class="{{ $label }}">Email <span class="font-normal text-slate-400">(to email the quotation)</span></label>
                <input id="client_email" type="email" name="client_email" x-model="client.client_email" maxlength="255" class="{{ $field }}">
            </div>
            <div>
                <label for="client_phone" class="{{ $label }}">Phone</label>
                <input id="client_phone" name="client_phone" x-model="client.client_phone" maxlength="50" class="{{ $field }}">
            </div>
            <div>
                <label for="nationality" class="{{ $label }}">Nationality</label>
                <input id="nationality" name="nationality" x-model="client.nationality" maxlength="100" class="{{ $field }}">
            </div>
            <div>
                <label for="passport_number" class="{{ $label }}">Passport number</label>
                <input id="passport_number" name="passport_number" x-model="client.passport_number" maxlength="50" class="{{ $field }} font-mono">
            </div>
        </div>
    </section>

    {{-- 2. What is quoted --}}
    <section class="{{ $card }}">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">Services and fees</h2>
                <p class="text-sm text-slate-500">Pick a BI rate to fill in the price, or add a line and type it.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <select x-ref="rate" @change="addRate($event.target.value); $event.target.value = ''" aria-label="Add a BI rate" class="{{ $field }} sm:w-72">
                    <option value="">+ Add from the BI rates…</option>
                    <template x-for="group in rateGroups()" :key="group">
                        <optgroup :label="group">
                            <template x-for="rate in rates.filter(r => r.group === group)" :key="rate.id">
                                <option :value="rate.id" x-text="rate.label + ' - ₱' + money(rate.price) + (rate.check ? ' (check rate)' : '')"></option>
                            </template>
                        </optgroup>
                    </template>
                </select>
                <button type="button" @click="addLine()" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-white border border-slate-300 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <i data-lucide="plus" class="w-4 h-4"></i><span>Add line</span>
                </button>
            </div>
        </div>

        <p x-show="! items.length" class="rounded-lg bg-slate-50 border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">No lines yet. Add a BI rate or a line.</p>

        <div class="space-y-3">
            <template x-for="(item, index) in items" :key="index">
                <div class="rounded-lg border border-slate-200 p-3 grid gap-3 sm:grid-cols-12 sm:items-end">
                    <div class="sm:col-span-3">
                        <label class="{{ $label }}" :for="'service-' + index">Service</label>
                        <select :id="'service-' + index" :name="'items[' + index + '][service_key]'" x-model="item.service_key" class="{{ $field }}">
                            <option value="">Other / AMEGA fee</option>
                            <template x-for="service in services" :key="service.key">
                                <option :value="service.key" x-text="service.label" :selected="service.key === item.service_key"></option>
                            </template>
                        </select>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="{{ $label }}" :for="'description-' + index">Description *</label>
                        <input :id="'description-' + index" :name="'items[' + index + '][description]'" x-model="item.description" required maxlength="255" class="{{ $field }}" placeholder="e.g. AMEGA service fee">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="{{ $label }}" :for="'quantity-' + index">Qty</label>
                        <input :id="'quantity-' + index" type="number" min="1" max="99" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" class="{{ $field }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}" :for="'price-' + index">Price (₱) *</label>
                        <input :id="'price-' + index" type="number" min="0" step="0.01" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" required class="{{ $field }}">
                    </div>
                    <div class="sm:col-span-2 flex items-center justify-between gap-2 h-10">
                        <span class="text-sm font-semibold text-slate-900" x-text="'₱' + money(lineTotal(item))"></span>
                        <button type="button" @click="items.splice(index, 1)" class="w-9 h-9 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center" :aria-label="'Remove line ' + (index + 1)">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex flex-col items-end gap-2 pt-2 border-t border-slate-100">
            <div class="flex items-center gap-6 text-sm"><span class="text-slate-500">Subtotal</span><span class="w-32 text-right font-semibold text-slate-900" x-text="'₱' + money(subtotal())"></span></div>
            <div class="flex items-center gap-6 text-sm">
                <label for="discount" class="text-slate-500">Discount (₱)</label>
                <input id="discount" type="number" min="0" step="0.01" name="discount" x-model.number="discount" class="w-32 h-9 px-3 rounded-lg border border-slate-300 text-sm text-right">
            </div>
            <div class="flex items-center gap-6 text-base"><span class="font-semibold text-slate-700">Total</span><span class="w-32 text-right font-bold text-slate-900" x-text="'₱' + money(total())"></span></div>
            <p x-show="discount > subtotal()" x-cloak class="text-xs font-semibold text-rose-600">The discount is more than the subtotal.</p>
        </div>
    </section>

    {{-- 3. Terms --}}
    <section class="{{ $card }}">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="valid_until" class="{{ $label }}">Valid until *</label>
                <input id="valid_until" type="date" name="valid_until" required value="{{ old('valid_until', $quotation->valid_until?->toDateString()) }}" class="{{ $field }}">
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="{{ $label }}">Notes for the client <span class="font-normal text-slate-400">(printed on the quotation)</span></label>
                <textarea id="notes" name="notes" rows="3" maxlength="5000" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-500" placeholder="e.g. Processing takes 2-3 weeks after filing.">{{ old('notes', $quotation->notes) }}</textarea>
            </div>
        </div>
    </section>
</div>

@once
    <script>
        function quotationForm(config) {
            return {
                items: config.items.length ? config.items : [],
                client: config.client,
                discount: Number(config.discount) || 0,
                services: config.services,
                rates: config.rates,
                term: '',
                results: [],
                open: false,

                init() {
                    this.$watch('items', () => this.$nextTick(() => window.lucide && lucide.createIcons()));
                },

                money(value) {
                    return (Number(value) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                lineTotal(item) {
                    return Math.round((Number(item.quantity) || 0) * (Number(item.unit_price) || 0) * 100) / 100;
                },
                subtotal() {
                    return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
                },
                total() {
                    return Math.max(0, this.subtotal() - (Number(this.discount) || 0));
                },
                rateGroups() {
                    return [...new Set(this.rates.map(r => r.group))];
                },

                addLine(line = {}) {
                    this.items.push({ service_key: '', description: '', quantity: 1, unit_price: 0, ...line });
                },
                addRate(id) {
                    const rate = this.rates.find(r => String(r.id) === String(id));
                    if (rate) this.addLine({ service_key: rate.service || '', description: rate.label, unit_price: rate.price });
                },

                async lookup() {
                    if (this.term.trim().length < 2) { this.results = []; this.open = false; return; }
                    try {
                        const response = await fetch(config.lookupUrl + '?q=' + encodeURIComponent(this.term.trim()), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        this.results = response.ok ? await response.json() : [];
                    } catch (e) {
                        this.results = [];
                    }
                    this.open = this.results.length > 0;
                },
                pick(match) {
                    this.client = {
                        immigration_client_id: match.immigration_client_id,
                        user_id: match.user_id,
                        client_name: match.client_name || '',
                        client_email: match.client_email || '',
                        client_phone: match.client_phone || '',
                        nationality: match.nationality || '',
                        passport_number: match.passport_number || '',
                    };
                    this.term = '';
                    this.open = false;
                },
                unlink() {
                    this.client.immigration_client_id = null;
                    this.client.user_id = null;
                },
            };
        }
    </script>
@endonce
