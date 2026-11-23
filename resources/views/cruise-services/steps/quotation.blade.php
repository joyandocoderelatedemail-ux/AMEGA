{{--
    Step 2, Prepare the Quotation: the package cost (base fare, tipping, insurance, travel taxes and
    add-ons, adding up to the total) and the day-by-day itinerary, one row per day of the sailing.
    Save keeps it; "Save and print quotation" also puts it on the Quotations page and opens it to print.
    Expects $booking, $canSave, $control and $label from cruise-services.booking.
--}}
@use('App\Models\CruiseBooking')
@php
    $amount = fn (string $field) => old($field, (float) $booking->{$field} > 0 ? number_format((float) $booking->{$field}, 2, '.', '') : '');
    $quote = [
        'currency' => (string) old('currency', $booking->currency ?: 'PHP'),
        'signs' => CruiseBooking::CURRENCIES,
        'costs' => collect(array_keys(CruiseBooking::COST_LINES))->mapWithKeys(fn ($field) => [$field => (string) $amount($field)])->all(),
        'addOns' => array_values(old('add_ons', $booking->add_ons ?? [])),
        'days' => array_values(old('itinerary', $booking->itineraryRows())),
    ];
    $area = 'w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600 disabled:bg-slate-100';
@endphp

<form method="POST" action="{{ route('cruise-services.bookings.quotation.update', $booking) }}"
      class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-6"
      x-data="{
        ...@js($quote),
        get total() {
            const costs = Object.values(this.costs).reduce((sum, v) => sum + (parseFloat(v) || 0), 0);
            return costs + this.addOns.reduce((sum, a) => sum + (parseFloat(a.amount) || 0), 0);
        },
        money(n) { return (this.signs[this.currency] || '') + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
      }">
    @csrf
    @method('PUT')

    @if ($booking->hasQuotation())
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-emerald-900">Quotation prepared on {{ $booking->quotation_at->format('M j, Y g:i A') }}{{ $booking->quotationBy ? ' by '.$booking->quotationBy->name : '' }}. It is on the Quotations page.</p>
            <a href="{{ route('cruise-services.bookings.quotation', $booking) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-800 hover:underline">
                <i data-lucide="printer" class="w-4 h-4"></i> Print it again
            </a>
        </div>
    @endif

    <fieldset class="space-y-6" @disabled(! $canSave)>
        <div class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Package cost</h2>
                <div class="w-40">
                    <label for="currency" class="{{ $label }}">Currency</label>
                    <select id="currency" name="currency" x-model="currency" class="{{ $control }}">
                        @foreach (array_keys(CruiseBooking::CURRENCIES) as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach (CruiseBooking::COST_LINES as $field => $name)
                    <div>
                        <label for="{{ $field }}" class="{{ $label }}">{{ $name }}{{ $field === 'base_fare' ? ' *' : '' }}</label>
                        <input id="{{ $field }}" type="number" name="{{ $field }}" min="0" step="0.01" x-model="costs.{{ $field }}" class="{{ $control }}">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-3 pt-5 border-t border-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Add-ons</h2>
            <template x-for="(addOn, i) in addOns" :key="i">
                <div class="grid grid-cols-12 gap-2 items-end">
                    <div class="col-span-7 sm:col-span-8">
                        <label :for="'addon-desc-' + i" class="{{ $label }}">Add-on</label>
                        <input :id="'addon-desc-' + i" type="text" :name="'add_ons[' + i + '][description]'" x-model="addOn.description" maxlength="255" placeholder="e.g. Shore excursion in Penang" class="{{ $control }}">
                    </div>
                    <div class="col-span-4 sm:col-span-3">
                        <label :for="'addon-amount-' + i" class="{{ $label }}">Amount</label>
                        <input :id="'addon-amount-' + i" type="number" :name="'add_ons[' + i + '][amount]'" x-model="addOn.amount" min="0" step="0.01" class="{{ $control }}">
                    </div>
                    <div class="col-span-1">
                        <button type="button" @click="addOns.splice(i, 1)" class="h-10 w-full rounded-lg border border-slate-300 text-slate-500 hover:bg-slate-50" :aria-label="'Remove add-on ' + (i + 1)">
                            <i data-lucide="x" class="w-4 h-4 mx-auto"></i>
                        </button>
                    </div>
                </div>
            </template>
            <p x-show="! addOns.length" class="text-sm text-slate-500">No add-ons.</p>
            <button type="button" @click="addOns.push({ description: '', amount: '' })" class="h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Add an add-on
            </button>
        </div>

        <div class="space-y-3 pt-5 border-t border-slate-200">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Day-by-day itinerary</h2>
                <p class="text-sm text-slate-500">One row per day from the sailing date to the return date. Write where the ship is and what the guests do.</p>
            </div>
            <template x-for="(day, i) in days" :key="i">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-3 items-start rounded-lg border border-slate-200 p-3">
                    <div class="sm:col-span-3">
                        <p class="text-sm font-semibold text-slate-900" x-text="'Day ' + (i + 1)"></p>
                        <input type="date" :name="'itinerary[' + i + '][date]'" x-model="day.date" :aria-label="'Date of day ' + (i + 1)" class="mt-1 {{ $control }}">
                    </div>
                    <div class="sm:col-span-8">
                        <label :for="'day-' + i" class="sr-only" x-text="'What happens on day ' + (i + 1)"></label>
                        <textarea :id="'day-' + i" :name="'itinerary[' + i + '][description]'" x-model="day.description" rows="2" maxlength="1000"
                                  placeholder="e.g. Embark in Singapore; the ship sails at 5:00 PM" class="{{ $area }}"></textarea>
                    </div>
                    <div class="sm:col-span-1">
                        <button type="button" @click="days.splice(i, 1)" class="h-10 w-full rounded-lg border border-slate-300 text-slate-500 hover:bg-slate-50" :aria-label="'Remove day ' + (i + 1)">
                            <i data-lucide="x" class="w-4 h-4 mx-auto"></i>
                        </button>
                    </div>
                </div>
            </template>
            <button type="button" @click="days.push({ day: days.length + 1, date: '', description: '' })" class="h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Add a day
            </button>
        </div>

        <div class="pt-5 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-600">Total package cost</p>
            <p class="text-2xl font-bold text-slate-900" x-text="money(total)"></p>
        </div>
    </fieldset>

    <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-100">
        <a href="{{ route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'availability']) }}"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-gray-100 text-slate-700 font-bold text-xs hover:bg-gray-200 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back</span>
        </a>
        @if ($canSave)
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" name="action" value="save" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition-colors">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Save</span>
                </button>
                <button type="submit" name="action" value="print" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-bold text-xs hover:bg-navy transition-colors shadow-md">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Save and print quotation</span>
                </button>
            </div>
        @endif
    </div>
</form>
