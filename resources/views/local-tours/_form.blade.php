{{--
    The details of a local tour booking: the tour, the guests, the trip and the quotation. Used to open
    a booking and to correct one. Expects $booking (a new, unsaved one when opening).

    The supplier card follows the tour picked, with what that supplier needs to know about the guests.
--}}
@use('App\Support\LocalTours')
@php
    $control = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $area = 'w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
    $card = 'bg-white rounded-xl border border-slate-200 p-5 sm:p-6';
    $tourLocked = $booking->exists && $booking->hasReached('supplier');
    $value = fn (string $field, $default = null) => old($field, $booking->{$field} ?? $default);
@endphp

@if ($errors->any())
    <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
        <p class="text-sm font-semibold text-rose-900">Please correct the following:</p>
        <ul class="mt-1 text-sm text-rose-900 space-y-0.5">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start"
     x-data="{ tour: @js((string) $value('tour', '')), tours: @js(LocalTours::TOURS), children: {{ (int) $value('children', 0) }},
               get info() { return this.tours[this.tour] || null },
               asks(field) { return !! (this.info && this.info.asks.includes(field)) } }">

    <div class="lg:col-span-2 space-y-6">
        <section class="{{ $card }} space-y-4" aria-labelledby="tour-heading">
            <h2 id="tour-heading" class="text-base font-semibold text-slate-900">Tour</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="tour" class="{{ $label }}">Tour *</label>
                    @if ($tourLocked)
                        <input type="hidden" name="tour" value="{{ $booking->tour }}">
                        <p class="h-10 px-3 inline-flex items-center w-full rounded-lg bg-slate-100 border border-slate-200 text-sm font-semibold text-slate-700">{{ $booking->tourLabel() }}</p>
                        <p class="mt-1 text-xs text-slate-500">The tour is fixed once the booking has gone for approval.</p>
                    @else
                        <select id="tour" name="tour" required x-model="tour" class="{{ $control }}">
                            <option value="">Choose the tour</option>
                            @foreach (LocalTours::labels() as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div>
                    <label for="travel_date" class="{{ $label }}">Preferred travel date</label>
                    <input id="travel_date" type="date" name="travel_date" value="{{ $value('travel_date') ? \Illuminate\Support\Carbon::parse($value('travel_date'))->format('Y-m-d') : '' }}" class="{{ $control }}">
                </div>
                <div>
                    <label for="pickup_time" class="{{ $label }}">Pick-up time</label>
                    <input id="pickup_time" type="time" name="pickup_time" value="{{ $value('pickup_time') }}" class="{{ $control }}">
                </div>
            </div>
        </section>

        <section class="{{ $card }} space-y-4" aria-labelledby="guests-heading">
            <h2 id="guests-heading" class="text-base font-semibold text-slate-900">Guests</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="lead_guest_name" class="{{ $label }}">Complete name of the lead guest *</label>
                    <input id="lead_guest_name" type="text" name="lead_guest_name" required maxlength="255" value="{{ $value('lead_guest_name') }}" class="{{ $control }}">
                </div>
                <div>
                    <label for="contact_phone" class="{{ $label }}">Contact number</label>
                    <input id="contact_phone" type="tel" name="contact_phone" maxlength="50" value="{{ $value('contact_phone') }}" class="{{ $control }}">
                </div>
                <div>
                    <label for="contact_email" class="{{ $label }}">Email</label>
                    <input id="contact_email" type="email" name="contact_email" maxlength="255" value="{{ $value('contact_email') }}" class="{{ $control }}">
                </div>
                <div>
                    <label for="adults" class="{{ $label }}">Adults *</label>
                    <input id="adults" type="number" name="adults" required min="1" max="500" value="{{ $value('adults', 1) }}" class="{{ $control }}">
                </div>
                <div>
                    <label for="children" class="{{ $label }}">Children</label>
                    <input id="children" type="number" name="children" min="0" max="500" x-model.number="children" value="{{ $value('children', 0) }}" class="{{ $control }}">
                </div>
                <div x-show="children > 0 || asks('children_ages')" class="sm:col-span-2">
                    <label for="children_ages" class="{{ $label }}">Ages of the children</label>
                    <input id="children_ages" type="text" name="children_ages" maxlength="255" placeholder="e.g. 4, 9" value="{{ $value('children_ages') }}" class="{{ $control }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="nationality" class="{{ $label }}">Nationality of the guests <span x-show="info && info.nationality === 'if_required'" class="font-normal text-slate-400">(if the operator requires it)</span></label>
                    <input id="nationality" type="text" name="nationality" maxlength="100" placeholder="e.g. Filipino; 2 Korean, 1 Filipino" value="{{ $value('nationality') }}" class="{{ $control }}">
                </div>
            </div>
        </section>

        <section class="{{ $card }} space-y-4" aria-labelledby="trip-heading">
            <h2 id="trip-heading" class="text-base font-semibold text-slate-900">Pick-up and transport</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="pickup_location" class="{{ $label }}">Pick-up location</label>
                    <input id="pickup_location" type="text" name="pickup_location" maxlength="255" value="{{ $value('pickup_location') }}" class="{{ $control }}">
                </div>
                <div>
                    <label for="dropoff_location" class="{{ $label }}">Drop-off location</label>
                    <input id="dropoff_location" type="text" name="dropoff_location" maxlength="255" value="{{ $value('dropoff_location') }}" class="{{ $control }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="transport" class="{{ $label }}">Transportation *</label>
                    <select id="transport" name="transport" required class="{{ $control }}">
                        @foreach (LocalTours::TRANSPORT as $key => $name)
                            <option value="{{ $key }}" @selected($value('transport', 'amega') === $key)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Always clarify with the client whether transportation is included or charged separately.</p>
                </div>
                <div x-show="asks('attractions')" class="sm:col-span-2">
                    <label for="attractions" class="{{ $label }}">Preferred attractions or places to visit</label>
                    <textarea id="attractions" name="attractions" rows="3" maxlength="2000" class="{{ $area }}">{{ $value('attractions') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label for="special_requests" class="{{ $label }}">Special requests or requirements</label>
                    <textarea id="special_requests" name="special_requests" rows="3" maxlength="2000" class="{{ $area }}">{{ $value('special_requests') }}</textarea>
                </div>
            </div>
        </section>

        <section class="{{ $card }} space-y-4" aria-labelledby="quote-heading">
            <h2 id="quote-heading" class="text-base font-semibold text-slate-900">Quotation</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="total_amount" class="{{ $label }}">Amount quoted to the client (₱)</label>
                    <input id="total_amount" type="number" name="total_amount" min="0" step="0.01" value="{{ $value('total_amount') !== null && (float) $value('total_amount') > 0 ? number_format((float) $value('total_amount'), 2, '.', '') : '' }}" class="{{ $control }}">
                    <p class="mt-1 text-xs text-slate-500">Leave blank until the supplier has given the rate.</p>
                </div>
                <div>
                    <label for="supplier_cost" class="{{ $label }}">Supplier's rate (₱)</label>
                    <input id="supplier_cost" type="number" name="supplier_cost" min="0" step="0.01" value="{{ $value('supplier_cost') !== null ? number_format((float) $value('supplier_cost'), 2, '.', '') : '' }}" class="{{ $control }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="remarks" class="{{ $label }}">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3" maxlength="3000" class="{{ $area }}">{{ $value('remarks') }}</textarea>
                </div>
            </div>
        </section>
    </div>

    {{-- The supplier of the tour picked, and what they need to know about the guests. --}}
    <aside class="{{ $card }} space-y-3 lg:sticky lg:top-36" aria-live="polite">
        <template x-if="! info">
            <p class="text-sm text-slate-500">Choose a tour to see its supplier and the guest information they need.</p>
        </template>
        <template x-if="info">
            <div class="space-y-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500" x-text="info.contact_label"></p>
                    <p class="text-sm font-semibold text-slate-900" x-text="info.supplier"></p>
                    <p x-show="info.phone" class="text-sm text-slate-700" x-text="info.phone"></p>
                    <p x-show="info.accreditation" class="text-xs text-slate-500" x-text="info.accreditation"></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 mb-1">Required guest information</p>
                    <ul class="text-sm text-slate-700 space-y-1 list-disc pl-4">
                        <template x-for="item in info.guest_info" :key="item"><li x-text="item"></li></template>
                    </ul>
                </div>
                <p x-show="info.important" class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-900"><strong>Important:</strong> <span x-text="info.important"></span></p>
            </div>
        </template>
    </aside>
</div>
