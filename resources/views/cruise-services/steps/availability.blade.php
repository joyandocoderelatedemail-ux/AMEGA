{{--
    Step 1, Check Availability: the client, the destination, the dates, the guests (adults, children,
    infants), the preferred cruise line and ship, and the slot confirmation. Save keeps the step as it
    is; Continue needs the slot confirmed (also checked on the server) and opens the quotation.
    Expects $booking, $canSave, $control and $label from cruise-services.booking.
--}}
@use('App\Models\CruiseBooking')
@php
    $value = fn (string $field, $default = null) => old($field, $booking->{$field} ?? $default);
    $date = fn (string $field) => old($field, $booking->{$field}?->format('Y-m-d'));
    $slotTicked = (bool) old('slot_confirmed', $booking->isSlotConfirmed());

    // The dropdowns: a value not on the list shows under "Other", typed in.
    $destinationValue = (string) $value('destination', '');
    $destinationKnown = in_array($destinationValue, CruiseBooking::DESTINATIONS, true);
    $destinationPicker = [
        'choice' => $destinationKnown ? $destinationValue : ($destinationValue !== '' ? '__other' : ''),
        'other' => $destinationKnown ? '' : $destinationValue,
    ];
    $lines = CruiseBooking::LINES;
    $lineValue = (string) $value('cruise_line', '');
    $lineKnown = array_key_exists($lineValue, $lines);
    $shipValue = (string) $value('ship', '');
    $shipKnown = in_array($shipValue, $lineKnown ? $lines[$lineValue] : array_merge(...array_values($lines)), true);
    $picker = [
        'lines' => $lines,
        'line' => $lineKnown ? $lineValue : ($lineValue !== '' ? '__other' : ''),
        'lineOther' => $lineKnown ? '' : $lineValue,
        'ship' => $shipKnown ? $shipValue : ($shipValue !== '' ? '__other' : ''),
        'shipOther' => $shipKnown ? '' : $shipValue,
    ];
    $client = [
        'url' => route('cruise-services.clients.search'),
        'name' => (string) $value('client_name', ''),
        'phone' => (string) $value('contact_number', ''),
        'clientId' => (string) old('client_id', $booking->user_id ?? ''),
    ];
@endphp

    <form method="POST" action="{{ $booking->exists ? route('cruise-services.bookings.update', $booking) : route('cruise-services.bookings.store') }}"
          class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-6" x-data="{ slot: @js($slotTicked) }">
        @csrf
        @if ($booking->exists)
            @method('PUT')
        @endif

        <fieldset class="space-y-6" @disabled(! $canSave)>
            {{-- Client name searches the registered client accounts; picking one links the booking to it.
                 A name typed in that matches no one is added to the client list on save. --}}
            <div class="space-y-4"
                 x-data="{
                    ...@js($client), results: [], open: false, searched: false, highlighted: -1, loading: false,
                    search() {
                        this.clientId = '';
                        const term = this.name.trim();
                        if (term.length < 2) { this.results = []; this.open = false; return; }
                        this.loading = true;
                        fetch(this.url + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } })
                            .then(r => r.json())
                            .then(d => { this.results = d.clients; this.searched = true; this.open = true; this.highlighted = -1; })
                            .finally(() => this.loading = false);
                    },
                    move(step) { if (this.results.length) this.highlighted = (this.highlighted + step + this.results.length) % this.results.length },
                    pick(c) { this.name = c.name; if (c.phone) this.phone = c.phone; this.clientId = String(c.id); this.open = false; },
                 }">
                <h2 class="text-base font-semibold text-slate-900">Client</h2>
                <input type="hidden" name="client_id" :value="clientId">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="relative" @click.outside="open = false">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label for="client_name" class="block text-xs font-semibold text-slate-600">Client name *</label>
                            <span x-show="clientId" x-cloak class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/20">Registered client</span>
                        </div>
                        <input id="client_name" type="text" name="client_name" required maxlength="255" autocomplete="off" placeholder="Search by name, email, phone or passport"
                               x-model="name" @input.debounce.250ms="search()" @focus="open = results.length > 0"
                               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.escape="open = false"
                               @keydown.enter="if (open && highlighted >= 0) { $event.preventDefault(); pick(results[highlighted]); }"
                               role="combobox" aria-autocomplete="list" aria-controls="client-results" :aria-expanded="open.toString()"
                               class="{{ $control }}">
                        <span x-show="loading" x-cloak class="absolute right-3 top-8 text-slate-400" aria-hidden="true"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i></span>
                        <div x-show="open" x-cloak id="client-results" role="listbox"
                             class="absolute z-20 mt-1 w-full rounded-lg bg-white border border-slate-200 shadow-lg overflow-hidden">
                            <template x-for="(c, i) in results" :key="c.id">
                                <button type="button" role="option" :aria-selected="highlighted === i" @click="pick(c)" @mouseenter="highlighted = i"
                                        class="w-full text-left px-3 py-2 border-b border-slate-100 last:border-0" :class="highlighted === i ? 'bg-slate-50' : ''">
                                    <span class="block text-sm font-semibold text-slate-900" x-text="c.name"></span>
                                    <span class="block text-xs text-slate-500" x-text="c.detail || 'No contact details on file'"></span>
                                </button>
                            </template>
                            <p x-show="searched && ! results.length" class="px-3 py-2 text-xs text-slate-500">No registered client matches. Saving adds them to the client list.</p>
                        </div>
                    </div>
                    <div>
                        <label for="contact_number" class="{{ $label }}">Contact number</label>
                        <input id="contact_number" type="tel" name="contact_number" maxlength="50" x-model="phone" class="{{ $control }}">
                    </div>
                </div>
            </div>

            <div class="space-y-4 pt-5 border-t border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Client preferences</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- A route AMEGA sells, or one typed in under "Other". --}}
                    <div class="sm:col-span-2 space-y-2" x-data="@js($destinationPicker)">
                        <input type="hidden" name="destination" :value="choice === '__other' ? other : choice">
                        <label for="destination_choice" class="{{ $label }}">Destination *</label>
                        <select id="destination_choice" x-model="choice" class="{{ $control }}">
                            <option value="">Choose the destination</option>
                            @foreach (CruiseBooking::DESTINATIONS as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                            <option value="__other">Other (type it)</option>
                        </select>
                        <input x-show="choice === '__other'" x-cloak type="text" x-model="other" maxlength="255" placeholder="Destination" aria-label="Other destination" class="{{ $control }}">
                    </div>
                    <div>
                        <label for="departure_date" class="{{ $label }}">Sailing date *</label>
                        <input id="departure_date" type="date" name="departure_date" value="{{ $date('departure_date') }}" class="{{ $control }}">
                    </div>
                    <div>
                        <label for="return_date" class="{{ $label }}">Return date *</label>
                        <input id="return_date" type="date" name="return_date" value="{{ $date('return_date') }}" class="{{ $control }}">
                    </div>
                    <fieldset class="sm:col-span-2">
                        <legend class="{{ $label }}">Number of guests *</legend>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="adults" class="block text-xs text-slate-500 mb-1">Adults *</label>
                                <input id="adults" type="number" name="adults" min="1" max="500" value="{{ $value('adults') }}" class="{{ $control }}">
                            </div>
                            <div>
                                <label for="children" class="block text-xs text-slate-500 mb-1">Children</label>
                                <input id="children" type="number" name="children" min="0" max="500" value="{{ $value('children', 0) }}" class="{{ $control }}">
                            </div>
                            <div>
                                <label for="infants" class="block text-xs text-slate-500 mb-1">Infants</label>
                                <input id="infants" type="number" name="infants" min="0" max="500" value="{{ $value('infants', 0) }}" class="{{ $control }}">
                            </div>
                        </div>
                    </fieldset>
                </div>

                {{-- Preferred cruise line, then a ship on that line; "Other" types in one not on the list. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                     x-data="{
                        ...@js($picker),
                        get ships() {
                            if (this.line === '__other') return [];
                            return this.line ? (this.lines[this.line] || []) : Object.values(this.lines).flat();
                        },
                        shipPicked() {
                            if (this.line || ! this.ship || this.ship === '__other') return;
                            this.line = Object.keys(this.lines).find(l => this.lines[l].includes(this.ship)) || '';
                        },
                     }">
                    <input type="hidden" name="cruise_line" :value="line === '__other' ? lineOther : line">
                    <input type="hidden" name="ship" :value="ship === '__other' ? shipOther : ship">
                    <div class="space-y-2">
                        <label for="cruise_line_choice" class="{{ $label }}">Preferred cruise line</label>
                        <select id="cruise_line_choice" x-model="line" @change="ship = ''; shipOther = ''" class="{{ $control }}">
                            <option value="">No preference</option>
                            @foreach (array_keys($lines) as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                            <option value="__other">Other (type it)</option>
                        </select>
                        <input x-show="line === '__other'" x-cloak type="text" x-model="lineOther" maxlength="100" placeholder="Cruise line" aria-label="Other cruise line" class="{{ $control }}">
                    </div>
                    <div class="space-y-2">
                        <label for="ship_choice" class="{{ $label }}">Preferred ship</label>
                        <select id="ship_choice" x-model="ship" @change="shipPicked()" class="{{ $control }}">
                            <option value="">No preference</option>
                            <template x-for="name in ships" :key="name">
                                <option :value="name" x-text="name" :selected="name === ship"></option>
                            </template>
                            <option value="__other">Other (type it)</option>
                        </select>
                        <input x-show="ship === '__other'" x-cloak type="text" x-model="shipOther" maxlength="100" placeholder="Ship" aria-label="Other ship" class="{{ $control }}">
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-slate-200">
                <label class="flex items-start gap-3 rounded-lg border px-4 py-3 cursor-pointer transition-colors" :class="slot ? 'border-emerald-300 bg-emerald-50' : 'border-slate-300 hover:bg-slate-50'">
                    <input type="hidden" name="slot_confirmed" value="0">
                    <input type="checkbox" name="slot_confirmed" value="1" x-model="slot" class="w-4 h-4 mt-0.5 rounded border-slate-300">
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Slot confirmed</span>
                        <span class="block text-xs text-slate-600">
                            @if ($booking->isSlotConfirmed())
                                Confirmed by {{ $booking->slotConfirmedBy?->name ?? 'an agent' }} on {{ $booking->slot_confirmed_at->format('M j, Y g:i A') }}.
                            @else
                                Tick once the cruise line has confirmed there is a slot for these dates and guests.
                            @endif
                        </span>
                    </span>
                </label>
            </div>
        </fieldset>

        @if ($canSave)
            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-100">
                <button type="submit" name="action" value="save" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-gray-100 text-slate-700 font-bold text-xs hover:bg-gray-200 transition-colors">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Save</span>
                </button>
                <div class="flex items-center gap-3">
                    <p x-show="! slot" class="text-xs font-semibold text-amber-700">Confirm the slot to continue.</p>
                    <button type="submit" name="action" value="continue" :disabled="! slot"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-bold text-xs hover:bg-navy transition-colors shadow-md disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-primary">
                        <span>Continue to {{ CruiseBooking::STAGES['quotation'] }}</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        @endif
    </form>
