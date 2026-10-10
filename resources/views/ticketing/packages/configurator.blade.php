@extends('layouts.ticketing')

@section('title', 'Package Configurator - AMEGA')

@section('content')
@php
    // Editing an existing package, or adding a new one ($package is null).
    $package = $package ?? null;

    // A field's value: what was just submitted, else the package's, else the default.
    $v = function (string $key, mixed $default = null) use ($package): mixed {
        if (session()->hasOldInput()) {
            return old($key, $default);
        }
        $value = $package?->{$key};

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ?? $default);
    };

    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $textarea = 'w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $fieldLabel = 'block text-xs font-semibold text-slate-600 mb-1';
    $sectionTitle = 'flex items-center gap-2 text-sm font-semibold text-slate-900';
    $checkbox = 'w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-600';
    $radio = 'w-4 h-4 border-slate-300 text-navy-700 focus:ring-navy-600';
    $primaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors';
    $secondaryButton = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors';
    $previewRow = 'flex items-center justify-between gap-3 py-2 border-b border-slate-200 text-sm';

    $bedOptions = [
        'Single Bed' => '1x Single Bed',
        'Twin Beds' => '2x Twin Beds',
        'Double Bed' => '1x Double Bed',
        'Queen Bed' => '1x Queen Bed',
        'King Bed' => '1x King Bed',
        'Family Bed (2 Queen Beds)' => 'Family Suite (2x Queen Beds)',
    ];
@endphp

<div x-data="{
    readyMade: {
        title: {{ Js::from($v('title', '')) }},
        category: {{ Js::from($v('category', 'domestic')) }},
        destination_id: {{ Js::from((string) $v('destination_id', '')) }},
        remarks: {{ Js::from($v('remarks', '')) }},
        durationChoice: {{ Js::from(in_array($v('duration', '3 Days / 2 Nights'), \App\Models\TravelPackage::DURATIONS, true) ? $v('duration', '3 Days / 2 Nights') : 'other') }},
        durationCustom: {{ Js::from(in_array($v('duration', '3 Days / 2 Nights'), \App\Models\TravelPackage::DURATIONS, true) ? '' : $v('duration')) }},
        price_amount: {{ Js::from($v('price_amount', '')) }},
        airfare_amount: {{ Js::from($v('airfare_amount', '')) }},
        price_currency: {{ Js::from($v('price_currency', 'PHP')) }},
        hotel_name: {{ Js::from($v('hotel_name', '')) }},
        has_breakfast: {{ Js::from((bool) $v('has_breakfast')) }},
        bed_config: {{ Js::from($v('bed_config', 'Queen Bed')) }},
        smoking_preference: {{ Js::from($v('smoking_preference', 'non_smoking')) }},
        pet_friendly: {{ Js::from((bool) $v('pet_friendly')) }},
        has_transportation: {{ Js::from((bool) $v('has_transportation')) }},
        transportation_type: {{ Js::from($v('transportation_type', 'Roundtrip Airport Transfer')) }},
        number_of_pax: {{ Js::from((int) $v('number_of_pax', 2)) }},
        airfare_inclusion: {{ Js::from($v('airfare_inclusion', '')) }},
        trip_type: {{ Js::from($v('trip_type', '')) }},
        preferred_flight_time: {{ Js::from($v('preferred_flight_time', '')) }},
        flight_destination: {{ Js::from($v('flight_destination', '')) }},
        departure_date: {{ Js::from($v('departure_date', '')) }},
        return_date: {{ Js::from($v('return_date', '')) }},
        segments: {{ Js::from(array_values($v('multi_city_segments', [['from' => '', 'to' => ''], ['from' => '', 'to' => '']]))) }},
        airline_id: {{ Js::from((string) $v('airline_id', '')) }},
        origin_airport: {{ Js::from($v('origin_airport', '')) }},
        cabin_class: {{ Js::from($v('cabin_class', '')) }},
        baggage_allowance: {{ Js::from($v('baggage_allowance', '')) }},
    },

    airlines: {{ Js::from($airlines->mapWithKeys(fn ($a) => [(string) $a->id => $a->name])) }},
    destinations: {{ Js::from($destinations->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name, 'type' => $d->type])->values()) }},
    categoryType: {{ Js::from(\App\Models\TravelPackage::CATEGORY_DESTINATION_TYPE) }},

    /** Domestic lists domestic destinations only; short and long haul list international ones. */
    destinationsForCategory() {
        const type = this.categoryType[this.readyMade.category];
        return this.destinations.filter(d => d.type === type);
    },

    onCategoryChange() {
        if (!this.destinationsForCategory().some(d => d.id === this.readyMade.destination_id)) {
            this.readyMade.destination_id = '';
        }
    },

    get duration() {
        return this.readyMade.durationChoice === 'other' ? this.readyMade.durationCustom : this.readyMade.durationChoice;
    },

    /** The hotel, tours and transfers share: the total less the airfare. */
    landPortion() {
        const total = parseFloat(this.readyMade.price_amount) || 0;
        const airfare = parseFloat(this.readyMade.airfare_amount) || 0;
        return Math.max(total - airfare, 0);
    },

    onAirfareInput() {
        if ((parseFloat(this.readyMade.airfare_amount) || 0) > 0 && !this.readyMade.airfare_inclusion) {
            this.readyMade.airfare_inclusion = 'included';
        }
    },
    airfareLabels: {{ Js::from(\App\Models\TravelPackage::AIRFARE_OPTIONS) }},
    tripLabels: {{ Js::from(\App\Models\TravelPackage::TRIP_TYPES) }},
    today: new Date().toISOString().split('T')[0],

    addSegment() {
        // The next leg usually starts where the last one ended.
        const last = this.readyMade.segments[this.readyMade.segments.length - 1];
        this.readyMade.segments.push({ from: last ? last.to : '', to: '' });
    },

    removeSegment(index) {
        if (this.readyMade.segments.length > 2) {
            this.readyMade.segments.splice(index, 1);
        }
    },

    routeSummary() {
        return this.readyMade.segments.filter(s => s.from || s.to).map(s => (s.from || '?') + ' → ' + (s.to || '?')).join(', ');
    },
    cabinLabels: {{ Js::from(\App\Models\TravelPackage::CABIN_CLASSES) }},

    money(currency, amount) {
        return (currency === 'USD' ? '$' : '₱') + Number(amount || 0).toLocaleString();
    }
}" class="space-y-6">

    <div>
        <a href="{{ route('ticketing.packages.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Packages</span>
        </a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $package ? 'Edit '.$package->title : 'Ready-Made Package Configurator' }}</h1>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <p class="text-sm font-semibold text-rose-900">Please correct the following:</p>
            <ul class="mt-1 text-sm text-rose-900 list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===================================================== Ready-made package --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form method="POST" action="{{ $package ? route('ticketing.packages.update', $package) : route('ticketing.packages.configurator.ready-made') }}" enctype="multipart/form-data"
              class="lg:col-span-2 bg-white rounded-xl border border-slate-200 divide-y divide-slate-200">
            @csrf
            @if ($package)
                @method('PUT')
            @endif

            <div class="p-5 sm:p-6">
                <h2 class="text-base font-semibold text-slate-900">Ready-made package</h2>
                <p class="text-sm text-slate-500 mt-0.5">Saved to the catalog and selectable in Step 3 of the ticket wizard.</p>
            </div>

            <section class="p-5 sm:p-6 space-y-4">
                <h3 class="{{ $sectionTitle }}"><i data-lucide="info" class="w-4 h-4 text-slate-400"></i> Package details</h3>

                <div>
                    <label class="{{ $fieldLabel }}" for="rm-title">Package title *</label>
                    <input id="rm-title" type="text" name="title" x-model="readyMade.title" required maxlength="255" class="{{ $input }}" placeholder="e.g. Boracay Island 4D3N Beach Escape">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-category">Category *</label>
                        <select id="rm-category" name="category" x-model="readyMade.category" @change="onCategoryChange()" required class="{{ $input }}">
                            <option value="domestic">Domestic</option>
                            <option value="short_haul">International: short haul (Asia)</option>
                            <option value="long_haul">International: long haul (Europe / USA)</option>
                        </select>
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-destination">Destination</label>
                        <select id="rm-destination" name="destination_id" x-model="readyMade.destination_id" class="{{ $input }}">
                            <option value="">None / standalone tour</option>
                            <template x-for="dest in destinationsForCategory()" :key="dest.id">
                                <option :value="dest.id" x-text="dest.name" :selected="dest.id === readyMade.destination_id"></option>
                            </template>
                        </select>
                        @error('destination_id')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-remarks">Remarks</label>
                        <input id="rm-remarks" type="text" name="remarks" x-model="readyMade.remarks" maxlength="2000" class="{{ $input }}" placeholder="e.g. Peak season rate, min. 2 pax">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-duration">Duration *</label>
                        <select id="rm-duration" x-model="readyMade.durationChoice" class="{{ $input }}">
                            @foreach (\App\Models\TravelPackage::DURATIONS as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                            <option value="other">Other (type it)</option>
                        </select>
                        <input type="text" x-show="readyMade.durationChoice === 'other'" x-cloak x-model="readyMade.durationCustom" :required="readyMade.durationChoice === 'other'"
                               maxlength="255" class="{{ $input }} mt-2" placeholder="e.g. 15 Days / 14 Nights" aria-label="Custom duration">
                        <input type="hidden" name="duration" :value="duration">
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-pax">Standard capacity (pax) *</label>
                        <input id="rm-pax" type="number" min="1" max="50" name="number_of_pax" x-model="readyMade.number_of_pax" required class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-status">Status *</label>
                        <select id="rm-status" name="status" class="{{ $input }}">
                            <option value="active" @selected($v('status', 'active') === 'active')>Active (in the wizard)</option>
                            <option value="draft" @selected($v('status') === 'draft')>Draft (hidden)</option>
                            <option value="sold_out" @selected($v('status') === 'sold_out')>Sold out</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-airfare-amount">Airfare per person</label>
                        <div class="flex gap-2">
                            <select name="price_currency" x-model="readyMade.price_currency" class="{{ $input }} !w-28 shrink-0" aria-label="Currency">
                                <option value="PHP">₱ PHP</option>
                                <option value="USD">$ USD</option>
                            </select>
                            <input id="rm-airfare-amount" type="number" step="0.01" min="0" name="airfare_amount" x-model="readyMade.airfare_amount" @input="onAirfareInput()" class="{{ $input }}" placeholder="0.00">
                        </div>
                        @error('airfare_amount')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-price">Total price per person *</label>
                        <input id="rm-price" type="number" step="0.01" min="0" name="price_amount" x-model="readyMade.price_amount" required class="{{ $input }}" placeholder="15000">
                    </div>
                    <div>
                        <span class="{{ $fieldLabel }}">Hotel, tours &amp; transfers</span>
                        <p class="h-10 px-3 flex items-center rounded-lg bg-slate-50 text-sm font-semibold text-slate-900" x-text="money(readyMade.price_currency, landPortion())"></p>
                    </div>
                </div>
            </section>

            <section class="p-5 sm:p-6 space-y-4">
                <h3 class="{{ $sectionTitle }}"><i data-lucide="plane" class="w-4 h-4 text-slate-400"></i> Trip &amp; flight</h3>

                <fieldset>
                    <legend class="{{ $fieldLabel }}">Trip type</legend>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <label class="flex items-center gap-2 h-10 px-3 rounded-lg border cursor-pointer text-sm transition-colors"
                               :class="readyMade.trip_type === '' ? 'border-navy-700 bg-navy-50 font-semibold text-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50'">
                            <input type="radio" name="trip_type" value="" x-model="readyMade.trip_type" class="sr-only">
                            <span>Not specified</span>
                        </label>
                        @foreach (['round_trip' => 'repeat', 'one_way' => 'arrow-right', 'multi_city' => 'git-branch'] as $value => $icon)
                            <label class="flex items-center gap-2 h-10 px-3 rounded-lg border cursor-pointer text-sm transition-colors"
                                   :class="readyMade.trip_type === '{{ $value }}' ? 'border-navy-700 bg-navy-50 font-semibold text-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50'">
                                <input type="radio" name="trip_type" value="{{ $value }}" x-model="readyMade.trip_type" class="sr-only">
                                <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
                                <span>{{ \App\Models\TravelPackage::TRIP_TYPES[$value] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Multi-city route: the legs only; the dates are set on each booking. --}}
                <div x-show="readyMade.trip_type === 'multi_city'" x-cloak class="rounded-lg border border-slate-200 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900">Route legs</p>
                        <button type="button" @click="addSegment()" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            <span>Add leg</span>
                        </button>
                    </div>
                    @error('multi_city_segments')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                    <template x-for="(segment, index) in readyMade.segments" :key="index">
                        <div class="grid grid-cols-[auto,1fr,1fr,auto] items-center gap-2">
                            <span class="w-6 text-sm font-semibold text-slate-500" x-text="index + 1"></span>
                            <input type="text" :name="readyMade.trip_type === 'multi_city' ? 'multi_city_segments[' + index + '][from]' : null" x-model="segment.from" maxlength="100"
                                   class="{{ $input }}" placeholder="From, e.g. Manila (MNL)" :aria-label="'Leg ' + (index + 1) + ' from'">
                            <input type="text" :name="readyMade.trip_type === 'multi_city' ? 'multi_city_segments[' + index + '][to]' : null" x-model="segment.to" maxlength="100"
                                   class="{{ $input }}" placeholder="To, e.g. Tokyo (NRT)" :aria-label="'Leg ' + (index + 1) + ' to'">
                            <button type="button" @click="removeSegment(index)" :disabled="readyMade.segments.length <= 2"
                                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 disabled:opacity-30 disabled:pointer-events-none"
                                    :aria-label="'Remove leg ' + (index + 1)">
                                <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                            </button>
                        </div>
                    </template>
                </div>

                <fieldset>
                    <legend class="{{ $fieldLabel }}">Preferred flight time</legend>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <label class="flex items-center gap-2 h-10 px-3 rounded-lg border cursor-pointer text-sm transition-colors"
                               :class="readyMade.preferred_flight_time === '' ? 'border-navy-700 bg-navy-50 font-semibold text-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50'">
                            <input type="radio" name="preferred_flight_time" value="" x-model="readyMade.preferred_flight_time" class="sr-only">
                            <span>Not specified</span>
                        </label>
                        @foreach (['anytime' => 'clock', 'morning' => 'sunrise', 'afternoon' => 'sun', 'evening' => 'moon'] as $value => $icon)
                            <label class="flex items-center gap-2 h-10 px-3 rounded-lg border cursor-pointer text-sm transition-colors"
                                   :class="readyMade.preferred_flight_time === '{{ $value }}' ? 'border-navy-700 bg-navy-50 font-semibold text-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50'">
                                <input type="radio" name="preferred_flight_time" value="{{ $value }}" x-model="readyMade.preferred_flight_time" class="sr-only">
                                <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
                                <span>{{ \App\Models\TravelPackage::FLIGHT_TIMES[$value] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-origin">Origin airport / city</label>
                        <input id="rm-origin" type="text" name="origin_airport" x-model="readyMade.origin_airport" maxlength="255" class="{{ $input }}" placeholder="e.g. Manila (MNL)">
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-flight-destination">Destination</label>
                        <input id="rm-flight-destination" type="text" name="flight_destination" x-model="readyMade.flight_destination" maxlength="255" class="{{ $input }}" placeholder="e.g. Tokyo, Coron, Paris">
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-departure">Departure date</label>
                        <input id="rm-departure" type="date" name="departure_date" x-model="readyMade.departure_date" :min="today" class="{{ $input }}">
                        @error('departure_date')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div x-show="readyMade.trip_type === 'round_trip' || readyMade.trip_type === ''">
                        <label class="{{ $fieldLabel }}" for="rm-return">Return date</label>
                        <input id="rm-return" type="date" name="return_date" x-model="readyMade.return_date" :min="readyMade.departure_date || today" class="{{ $input }}">
                        @error('return_date')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-airfare">Airfare</label>
                        <select id="rm-airfare" name="airfare_inclusion" x-model="readyMade.airfare_inclusion" class="{{ $input }}">
                            <option value="">Not specified</option>
                            @foreach (\App\Models\TravelPackage::AIRFARE_OPTIONS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-airline">Airline</label>
                        <select id="rm-airline" name="airline_id" x-model="readyMade.airline_id" class="{{ $input }}">
                            <option value="">Any airline</option>
                            @foreach ($airlines as $airline)
                                <option value="{{ $airline->id }}">{{ $airline->name }}{{ $airline->code ? ' ('.$airline->code.')' : '' }}{{ $airline->is_active ? '' : ' (off in the wizard)' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-cabin">Cabin class</label>
                        <select id="rm-cabin" name="cabin_class" x-model="readyMade.cabin_class" class="{{ $input }}">
                            <option value="">Not specified</option>
                            @foreach (\App\Models\TravelPackage::CABIN_CLASSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-baggage">Baggage allowance</label>
                        <input id="rm-baggage" type="text" name="baggage_allowance" x-model="readyMade.baggage_allowance" maxlength="255" class="{{ $input }}" placeholder="e.g. 20 kg checked + 7 kg hand-carry">
                    </div>
                </div>
            </section>

            <section class="p-5 sm:p-6 space-y-4">
                <h3 class="{{ $sectionTitle }}"><i data-lucide="hotel" class="w-4 h-4 text-slate-400"></i> Hotel &amp; room</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-hotel">Partner hotel</label>
                        <input id="rm-hotel" type="text" name="hotel_name" x-model="readyMade.hotel_name" maxlength="255" class="{{ $input }}" placeholder="e.g. Henann Regency Resort &amp; Spa">
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-preferred">Preferred hotel / location</label>
                        <input id="rm-preferred" type="text" name="preferred_hotel" value="{{ $v('preferred_hotel') }}" maxlength="255" class="{{ $input }}" placeholder="e.g. Station 2 beachfront, deluxe sea view">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-bed">Bed configuration</label>
                        <select id="rm-bed" name="bed_config" x-model="readyMade.bed_config" class="{{ $input }}">
                            @foreach ($bedOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-check-in">Check-in <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="rm-check-in" type="date" name="check_in_date" value="{{ $v('check_in_date') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-check-out">Check-out <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="rm-check-out" type="date" name="check_out_date" value="{{ $v('check_out_date') }}" class="{{ $input }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                           :class="readyMade.has_breakfast ? 'border-navy-700 bg-navy-50' : 'border-slate-200 hover:bg-slate-50'">
                        <input type="checkbox" name="has_breakfast" value="1" x-model="readyMade.has_breakfast" class="{{ $checkbox }}">
                        <span class="text-sm font-semibold text-slate-900">With breakfast</span>
                    </label>
                    <fieldset class="p-3 rounded-lg border border-slate-200">
                        <legend class="sr-only">Smoking policy</legend>
                        <div class="flex items-center gap-4 text-sm text-slate-700">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="smoking_preference" value="non_smoking" x-model="readyMade.smoking_preference" class="{{ $radio }}"> Non-smoking
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="smoking_preference" value="smoking" x-model="readyMade.smoking_preference" class="{{ $radio }}"> Smoking
                            </label>
                        </div>
                    </fieldset>
                    <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                           :class="readyMade.pet_friendly ? 'border-navy-700 bg-navy-50' : 'border-slate-200 hover:bg-slate-50'">
                        <input type="checkbox" name="pet_friendly" value="1" x-model="readyMade.pet_friendly" class="{{ $checkbox }}">
                        <span class="text-sm font-semibold text-slate-900">Pet friendly</span>
                    </label>
                </div>
            </section>

            <section class="p-5 sm:p-6 space-y-4">
                <h3 class="{{ $sectionTitle }}"><i data-lucide="car" class="w-4 h-4 text-slate-400"></i> Transportation &amp; requests</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                               :class="readyMade.has_transportation ? 'border-navy-700 bg-navy-50' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="checkbox" name="has_transportation" value="1" x-model="readyMade.has_transportation" class="{{ $checkbox }}">
                            <span class="text-sm font-semibold text-slate-900">With transportation</span>
                        </label>
                        <div x-show="readyMade.has_transportation">
                            <label class="{{ $fieldLabel }}" for="rm-transport">Transportation type</label>
                            <select id="rm-transport" name="transportation_type" x-model="readyMade.transportation_type" class="{{ $input }}">
                                <option value="Roundtrip Airport Transfer">Roundtrip airport transfer (shared / van)</option>
                                <option value="Private Airport Transfer">Private VIP airport transfer</option>
                                <option value="Private Chauffeur Van (Daily)">Private chauffeur van (daily tour)</option>
                                <option value="Tour Bus / Coach">Tour bus / coach</option>
                                <option value="Self-drive Car Rental">Self-drive car rental</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-requests">Special requests &amp; room notes</label>
                        <textarea id="rm-requests" name="special_requests" rows="3" class="{{ $textarea }}" placeholder="e.g. High floor, connecting rooms, early check-in subject to availability">{{ $v('special_requests') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="p-5 sm:p-6 space-y-4">
                <h3 class="{{ $sectionTitle }}"><i data-lucide="list-checks" class="w-4 h-4 text-slate-400"></i> Inclusions &amp; photo</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-inclusions">Inclusions</label>
                        <textarea id="rm-inclusions" name="inclusions" rows="4" class="{{ $textarea }}" placeholder="• Hotel accommodation&#10;• Daily breakfast&#10;• Roundtrip airport transfer">{{ $v('inclusions') }}</textarea>
                    </div>
                    <div>
                        <label class="{{ $fieldLabel }}" for="rm-exclusions">Exclusions</label>
                        <textarea id="rm-exclusions" name="exclusions" rows="4" class="{{ $textarea }}" placeholder="• Personal expenses &amp; tips&#10;• Travel insurance&#10;• Environmental fees">{{ $v('exclusions') }}</textarea>
                    </div>
                </div>

                <div>
                    <label class="{{ $fieldLabel }}" for="rm-photo">Package photo</label>
                    <input id="rm-photo" type="file" name="image_file" accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:h-9 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" @checked($v('is_featured', true)) class="{{ $checkbox }}">
                    Feature on the public Tours &amp; Packages page
                </label>
            </section>

            <div class="p-5 sm:p-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                @if ($package)
                    <a href="{{ route('ticketing.packages.index') }}" class="{{ $secondaryButton }}">Cancel</a>
                @else
                    <button type="reset" class="{{ $secondaryButton }}">Reset</button>
                @endif
                <button type="submit" class="{{ $primaryButton }}">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $package ? 'Save changes' : 'Save to catalog' }}</span>
                </button>
            </div>
        </form>

        {{-- Live summary --}}
        <aside class="lg:sticky lg:top-36 self-start bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Summary</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-600"
                      x-text="{ domestic: 'Domestic', short_haul: 'Short haul', long_haul: 'Long haul' }[readyMade.category]"></span>
            </div>
            <div>
                <p class="text-base font-semibold text-slate-900 leading-snug" x-text="readyMade.title || 'Untitled package'"></p>
                <p class="text-sm text-slate-500 mt-0.5" x-text="(duration || 'No duration') + ' · ' + readyMade.number_of_pax + ' pax'"></p>
            </div>
            <div class="flex items-baseline justify-between rounded-lg bg-slate-50 px-3 py-2.5">
                <span class="text-sm text-slate-600">Total</span>
                <span><span class="text-lg font-bold text-slate-900" x-text="money(readyMade.price_currency, readyMade.price_amount)"></span><span class="text-xs text-slate-500"> / pax</span></span>
            </div>
            <dl>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Airfare</dt><dd class="font-semibold" :class="readyMade.airfare_inclusion === 'included' ? 'text-emerald-700' : 'text-slate-900'" x-text="[airfareLabels[readyMade.airfare_inclusion], parseFloat(readyMade.airfare_amount) > 0 ? money(readyMade.price_currency, readyMade.airfare_amount) : ''].filter(Boolean).join(' · ') || '—'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Trip</dt><dd class="font-semibold text-slate-900 text-right truncate max-w-[60%]" x-text="(tripLabels[readyMade.trip_type] || '—') + (readyMade.trip_type === 'multi_city' && routeSummary() ? ': ' + routeSummary() : '')"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Route</dt><dd class="font-semibold text-slate-900 text-right truncate max-w-[60%]" x-text="[readyMade.origin_airport, readyMade.flight_destination].filter(Boolean).join(' → ') || '—'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Dates</dt><dd class="font-semibold text-slate-900 text-right" x-text="[readyMade.departure_date, readyMade.trip_type === 'round_trip' ? readyMade.return_date : ''].filter(Boolean).join(' – ') || 'Per booking'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Flight</dt><dd class="font-semibold text-slate-900 truncate max-w-[60%]" x-text="[airlines[readyMade.airline_id], readyMade.origin_airport ? 'from ' + readyMade.origin_airport : '', cabinLabels[readyMade.cabin_class]].filter(Boolean).join(' · ') || '—'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Baggage</dt><dd class="font-semibold text-slate-900 truncate max-w-[60%]" x-text="readyMade.baggage_allowance || '—'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Hotel</dt><dd class="font-semibold text-slate-900 truncate max-w-[60%]" x-text="readyMade.hotel_name || '—'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Breakfast</dt><dd class="font-semibold" :class="readyMade.has_breakfast ? 'text-emerald-700' : 'text-slate-400'" x-text="readyMade.has_breakfast ? 'Included' : 'Not included'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Bed</dt><dd class="font-semibold text-slate-900" x-text="readyMade.bed_config"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Smoking</dt><dd class="font-semibold text-slate-900" x-text="readyMade.smoking_preference === 'non_smoking' ? 'Non-smoking' : 'Smoking'"></dd></div>
                <div class="{{ $previewRow }}"><dt class="text-slate-500">Pets</dt><dd class="font-semibold" :class="readyMade.pet_friendly ? 'text-emerald-700' : 'text-slate-400'" x-text="readyMade.pet_friendly ? 'Allowed' : 'Not allowed'"></dd></div>
                <div class="flex items-center justify-between gap-3 py-2 text-sm"><dt class="text-slate-500">Transport</dt><dd class="font-semibold truncate max-w-[60%]" :class="readyMade.has_transportation ? 'text-emerald-700' : 'text-slate-400'" x-text="readyMade.has_transportation ? readyMade.transportation_type : 'None'"></dd></div>
            </dl>
        </aside>
    </div>

</div>
@endsection
