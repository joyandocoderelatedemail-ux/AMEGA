@extends('layouts.ticketing')

@section('title', 'Edit Booking - ' . $ticket->booking_reference)

@php
    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $textarea = 'w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $fieldLabel = 'block text-xs font-semibold text-slate-600 mb-1';
    $card = 'bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-4 scroll-mt-32';
    $stepTitle = 'flex items-center gap-2.5 text-base font-semibold text-slate-900';
    $stepBadge = 'inline-flex items-center justify-center w-6 h-6 rounded-full bg-navy-700 text-white text-xs font-bold';
    $time = fn (?string $value): string => $value ? \Illuminate\Support\Str::substr($value, 0, 5) : '';
    $money = fn ($value): string => number_format((float) $value, 2, '.', '');

    $steps = [
        'travellers' => 'Travellers',
        'documents' => 'Documents',
        'destination' => 'Destination & Flight',
        'restrictions' => 'Restrictions',
        'passengers' => 'Passengers',
        'contact' => 'Contact & Extras',
        'pricing' => 'Pricing',
    ];
    $fareTypes = $ticket->fareTypeLabels();
    $breakdown = $ticket->fare_breakdown ?: [];
    $legs = old('multi_city_segments', $ticket->multi_city_segments ?: [['from' => '', 'to' => '', 'date' => ''], ['from' => '', 'to' => '', 'date' => '']]);
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6"
     x-data="{
        travelType: {{ Js::from(old('travel_type', $ticket->travel_type)) }},
        tripType: {{ Js::from(old('trip_type', $ticket->trip_type)) }},
        legs: {{ Js::from(array_values($legs)) }},
        restrictions: {{ Js::from(array_values(old('airline_restrictions', $ticket->airline_restrictions ?? []))) }},
        fare: {{ Js::from($breakdown ? null : (float) old('estimated_fare', $ticket->estimated_fare)) }},
        farePrices: {{ Js::from(collect($breakdown)->map(fn ($row, $type) => (float) old('fare_prices.'.$type, $row['price'] ?? 0))) }},
        fareQty: {{ Js::from(collect($breakdown)->map(fn ($row) => (int) ($row['qty'] ?? 0))) }},
        charges: {
            taxes: {{ Js::from((float) old('taxes_amount', $ticket->taxes_amount)) }},
            visa: {{ Js::from((float) old('visa_assistance_fee', $ticket->visa_assistance_fee)) }},
            insurance: {{ Js::from((float) old('insurance_fee', $ticket->insurance_fee)) }},
            other: {{ Js::from((float) old('other_charges', $ticket->other_charges)) }},
            service: {{ Js::from((float) old('service_fee', $ticket->service_fee)) }},
        },
        // The part of the total not itemised below (services and requests, a lump-sum price or an agreed adjustment).
        base: {{ Js::from(round((float) $ticket->total_amount - (float) $ticket->estimated_fare - (float) $ticket->taxes_amount - (float) $ticket->visa_assistance_fee - (float) $ticket->insurance_fee - (float) $ticket->other_charges - (float) $ticket->service_fee, 2)) }},
        paid: {{ Js::from((float) $ticket->amount_paid) }},
        fareTotal() {
            if (this.fare !== null) return parseFloat(this.fare) || 0;
            return Object.keys(this.farePrices).reduce((sum, type) => sum + (parseFloat(this.farePrices[type]) || 0) * (this.fareQty[type] || 0), 0);
        },
        total() {
            return Math.max(0, this.base + this.fareTotal() + Object.values(this.charges).reduce((sum, value) => sum + (parseFloat(value) || 0), 0));
        },
        peso(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        addLeg() {
            const last = this.legs[this.legs.length - 1];
            this.legs.push({ from: last ? last.to : '', to: '', date: '' });
        },
     }">

    <div>
        <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Back to ticket</span>
        </a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Edit Booking</h1>
        <p class="text-sm text-slate-500 mt-1">
            <span class="font-mono font-semibold text-slate-700">{{ $ticket->booking_reference }}</span>
            &middot; {{ $ticket->origin ? $ticket->origin.' to ' : '' }}{{ $ticket->destination }}
        </p>
    </div>

    {{-- Jump to any step --}}
    <nav class="sticky top-28 z-20 -mx-1 px-1 py-2 bg-slate-50/95 backdrop-blur flex flex-wrap gap-2" aria-label="Booking steps">
        @foreach ($steps as $anchor => $title)
            <a href="#step-{{ $anchor }}" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <span class="text-slate-400">{{ $loop->iteration }}</span>
                <span>{{ $title }}</span>
            </a>
        @endforeach
    </nav>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <p class="text-sm font-semibold text-rose-900">This booking was not saved.</p>
            <ul class="mt-1 text-sm text-rose-900 list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ticketing.tickets.update', $ticket) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- 1. Travellers --}}
        <section id="step-travellers" class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">1</span> Travellers</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <fieldset>
                    <legend class="{{ $fieldLabel }}">Travel type</legend>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['domestic' => 'Domestic', 'international' => 'International'] as $value => $text)
                            <label class="flex items-center gap-2 h-10 px-3 rounded-lg border cursor-pointer text-sm"
                                   :class="travelType === '{{ $value }}' ? 'border-navy-700 bg-navy-50 font-semibold text-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50'">
                                <input type="radio" name="travel_type" value="{{ $value }}" x-model="travelType" class="sr-only">
                                <span>{{ $text }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <span class="{{ $fieldLabel }}">Travellers</span>
                    <p class="h-10 px-3 flex items-center rounded-lg bg-slate-50 text-sm text-slate-900">
                        {{ collect(['Adult' => $ticket->adults_count, 'Child' => $ticket->children_count, 'Infant' => $ticket->infants_count])->filter()->map(fn ($n, $t) => $n.' '.\Illuminate\Support\Str::plural($t, $n))->implode(', ') ?: '—' }}
                    </p>
                </div>
            </div>
            <p class="text-xs text-slate-500">The number of travellers stays as booked. To add or remove someone, cancel this booking and make a new one.</p>
        </section>

        {{-- 2. Documents --}}
        <section id="step-documents" class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">2</span> Documents</h2>
            @if ($ticket->passengers->isEmpty())
                <p class="text-sm text-slate-500">No passengers on this booking.</p>
            @else
                <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200">
                    @foreach ($ticket->passengers as $passenger)
                        @php $missing = $passenger->missingDocuments(); @endphp
                        <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 py-3">
                            <span class="text-sm font-semibold text-slate-900">{{ $passenger->passenger_number }}. {{ $passenger->full_name }}</span>
                            @if ($missing)
                                <span class="text-xs font-semibold text-amber-700">Missing: {{ implode(', ', $missing) }}</span>
                            @else
                                <span class="text-xs font-semibold text-emerald-700">All documents uploaded</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('ticketing.tickets.show', $ticket) }}#passengers" class="inline-flex items-center gap-1.5 text-sm font-semibold text-navy-700 hover:underline">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                    <span>Upload or replace documents on the ticket page</span>
                </a>
            @endif
        </section>

        {{-- 3. Destination & flight --}}
        <section id="step-destination" class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">3</span> Destination &amp; Flight</h2>

            <fieldset>
                <legend class="{{ $fieldLabel }}">Trip type</legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach (['round_trip' => 'Round Trip', 'one_way' => 'One Way', 'multi_city' => 'Multi-City'] as $value => $text)
                        <label class="flex items-center gap-2 h-10 px-3 rounded-lg border cursor-pointer text-sm"
                               :class="tripType === '{{ $value }}' ? 'border-navy-700 bg-navy-50 font-semibold text-slate-900' : 'border-slate-300 text-slate-700 hover:bg-slate-50'">
                            <input type="radio" name="trip_type" value="{{ $value }}" x-model="tripType" class="sr-only">
                            <span>{{ $text }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="preferred_flight_time" class="{{ $fieldLabel }}">Preferred flight time</label>
                    <select id="preferred_flight_time" name="preferred_flight_time" class="{{ $input }}">
                        @foreach (['anytime' => 'Anytime', 'morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'] as $value => $text)
                            <option value="{{ $value }}" @selected(old('preferred_flight_time', $ticket->preferred_flight_time ?: 'anytime') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="travel_class" class="{{ $fieldLabel }}">Cabin class</label>
                    <select id="travel_class" name="travel_class" class="{{ $input }}">
                        @foreach (\App\Models\TravelPackage::CABIN_CLASSES as $value => $text)
                            <option value="{{ $value }}" @selected(old('travel_class', $ticket->travel_class ?: 'economy') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="origin" class="{{ $fieldLabel }}">Origin airport / city *</label>
                    <input type="text" id="origin" name="origin" required maxlength="255" value="{{ old('origin', $ticket->origin) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="destination" class="{{ $fieldLabel }}">Destination *</label>
                    <input type="text" id="destination" name="destination" required maxlength="255" value="{{ old('destination', $ticket->destination) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="departure_date" class="{{ $fieldLabel }}">Departure date *</label>
                    <input type="date" id="departure_date" name="departure_date" required min="{{ now()->toDateString() }}" value="{{ old('departure_date', $ticket->departure_date?->toDateString()) }}" class="{{ $input }}">
                </div>
                <div x-show="tripType === 'round_trip'">
                    <label for="return_date" class="{{ $fieldLabel }}">Return date *</label>
                    <input type="date" id="return_date" name="return_date" :required="tripType === 'round_trip'" :disabled="tripType !== 'round_trip'" value="{{ old('return_date', $ticket->return_date?->toDateString()) }}" class="{{ $input }}">
                </div>
            </div>

            {{-- Multi-city legs --}}
            <div x-show="tripType === 'multi_city'" x-cloak class="rounded-lg border border-slate-200 p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900">Multi-city legs</p>
                    <button type="button" @click="addLeg()" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i><span>Add leg</span>
                    </button>
                </div>
                <template x-for="(leg, index) in legs" :key="index">
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr,1fr,10rem,auto] items-center gap-2">
                        <input type="text" :name="tripType === 'multi_city' ? 'multi_city_segments[' + index + '][from]' : null" x-model="leg.from" maxlength="100" placeholder="From" class="{{ $input }}" :aria-label="'Leg ' + (index + 1) + ' from'">
                        <input type="text" :name="tripType === 'multi_city' ? 'multi_city_segments[' + index + '][to]' : null" x-model="leg.to" maxlength="100" placeholder="To" class="{{ $input }}" :aria-label="'Leg ' + (index + 1) + ' to'">
                        <input type="date" :name="tripType === 'multi_city' ? 'multi_city_segments[' + index + '][date]' : null" x-model="leg.date" class="{{ $input }}" :aria-label="'Leg ' + (index + 1) + ' date'">
                        <button type="button" @click="legs.length > 2 && legs.splice(index, 1)" :disabled="legs.length <= 2" class="w-10 h-10 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 disabled:opacity-30" :aria-label="'Remove leg ' + (index + 1)">
                            <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                        </button>
                    </div>
                </template>
            </div>

            {{-- International destination details --}}
            <div x-show="travelType === 'international'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-200">
                <div>
                    <label for="destination_country" class="{{ $fieldLabel }}">Destination country *</label>
                    <input type="text" id="destination_country" name="destination_country" maxlength="255" :required="travelType === 'international'" value="{{ old('destination_country', $ticket->destination_country) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="destination_city" class="{{ $fieldLabel }}">Destination city *</label>
                    <input type="text" id="destination_city" name="destination_city" maxlength="255" :required="travelType === 'international'" value="{{ old('destination_city', $ticket->destination_city) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="arrival_airport" class="{{ $fieldLabel }}">Arrival airport *</label>
                    <input type="text" id="arrival_airport" name="arrival_airport" maxlength="255" :required="travelType === 'international'" value="{{ old('arrival_airport', $ticket->arrival_airport) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="preferred_airline" class="{{ $fieldLabel }}">Preferred airline</label>
                    <input type="text" id="preferred_airline" name="preferred_airline" maxlength="255" value="{{ old('preferred_airline', $ticket->preferred_airline) }}" class="{{ $input }}">
                </div>
            </div>

            {{-- The flight booked on the airline's site --}}
            <div class="pt-4 border-t border-slate-200 space-y-4">
                <p class="text-sm font-semibold text-slate-900">Selected flight</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="airline_id" class="{{ $fieldLabel }}">Airline</label>
                        <select id="airline_id" name="airline_id" class="{{ $input }}">
                            <option value="">Not booked yet</option>
                            @foreach ($airlines as $airline)
                                <option value="{{ $airline->id }}" @selected((string) old('airline_id', $ticket->airline_id) === (string) $airline->id)>{{ $airline->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="airline_pnr" class="{{ $fieldLabel }}">Booking code (PNR)</label>
                        <input type="text" id="airline_pnr" name="airline_pnr" maxlength="20" value="{{ old('airline_pnr', $ticket->airline_pnr) }}" class="{{ $input }} uppercase font-mono">
                    </div>
                    <div class="hidden sm:block"></div>
                    <div>
                        <label for="flight_number" class="{{ $fieldLabel }}">Departing flight no.</label>
                        <input type="text" id="flight_number" name="flight_number" maxlength="20" value="{{ old('flight_number', $ticket->flight_number) }}" class="{{ $input }} uppercase">
                    </div>
                    <div>
                        <label for="departure_time" class="{{ $fieldLabel }}">Departs</label>
                        <input type="time" id="departure_time" name="departure_time" value="{{ old('departure_time', $time($ticket->departure_time)) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="arrival_time" class="{{ $fieldLabel }}">Arrives</label>
                        <input type="time" id="arrival_time" name="arrival_time" value="{{ old('arrival_time', $time($ticket->arrival_time)) }}" class="{{ $input }}">
                    </div>
                    <template x-if="tripType === 'round_trip'">
                        <div class="contents">
                            <div>
                                <label for="return_flight_number" class="{{ $fieldLabel }}">Returning flight no.</label>
                                <input type="text" id="return_flight_number" name="return_flight_number" maxlength="20" value="{{ old('return_flight_number', $ticket->return_flight_number) }}" class="{{ $input }} uppercase">
                            </div>
                            <div>
                                <label for="return_departure_time" class="{{ $fieldLabel }}">Departs</label>
                                <input type="time" id="return_departure_time" name="return_departure_time" value="{{ old('return_departure_time', $time($ticket->return_departure_time)) }}" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="return_arrival_time" class="{{ $fieldLabel }}">Arrives</label>
                                <input type="time" id="return_arrival_time" name="return_arrival_time" value="{{ old('return_arrival_time', $time($ticket->return_arrival_time)) }}" class="{{ $input }}">
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        {{-- 4. Airline restrictions --}}
        <section id="step-restrictions" class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">4</span> Airline Restrictions</h2>
            <p x-show="!restrictions.length" class="text-sm text-slate-500">None added.</p>
            <template x-for="(restriction, index) in restrictions" :key="index">
                <div class="flex items-center gap-2">
                    <input type="text" name="airline_restrictions[]" x-model="restrictions[index]" maxlength="500" class="{{ $input }}" :aria-label="'Restriction ' + (index + 1)">
                    <button type="button" @click="restrictions.splice(index, 1)" class="w-10 h-10 shrink-0 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50" :aria-label="'Remove restriction ' + (index + 1)">
                        <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                    </button>
                </div>
            </template>
            <button type="button" @click="restrictions.push('')" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-dashed border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <i class="fa-solid fa-plus" aria-hidden="true"></i><span>Add restriction</span>
            </button>
        </section>

        {{-- 5. Passengers --}}
        <section id="step-passengers" class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">5</span> Passengers</h2>
            @forelse ($ticket->passengers as $i => $passenger)
                <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                    <input type="hidden" name="passengers[{{ $i }}][id]" value="{{ $passenger->id }}">
                    <p class="text-sm font-semibold text-slate-900">
                        Passenger {{ $passenger->passenger_number }}
                        <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ ucfirst($passenger->passenger_type) }}</span>
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="{{ $fieldLabel }}">First name *</label>
                            <input type="text" name="passengers[{{ $i }}][first_name]" required maxlength="255" value="{{ old("passengers.$i.first_name", $passenger->first_name) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}">Middle name</label>
                            <input type="text" name="passengers[{{ $i }}][middle_name]" maxlength="255" value="{{ old("passengers.$i.middle_name", $passenger->middle_name) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}">Last name *</label>
                            <input type="text" name="passengers[{{ $i }}][last_name]" required maxlength="255" value="{{ old("passengers.$i.last_name", $passenger->last_name) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}">Suffix</label>
                            <input type="text" name="passengers[{{ $i }}][suffix]" maxlength="20" value="{{ old("passengers.$i.suffix", $passenger->suffix) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}">Gender</label>
                            <select name="passengers[{{ $i }}][gender]" class="{{ $input }}">
                                <option value="">Not stated</option>
                                @foreach (\App\Models\User::GENDERS as $value => $text)
                                    <option value="{{ $value }}" @selected(old("passengers.$i.gender", $passenger->gender) === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}">Nationality</label>
                            <select name="passengers[{{ $i }}][nationality_type]" class="{{ $input }}">
                                <option value="filipino" @selected(old("passengers.$i.nationality_type", $passenger->nationality_type) === 'filipino')>Filipino (PH)</option>
                                <option value="foreign_national" @selected(old("passengers.$i.nationality_type", $passenger->nationality_type) === 'foreign_national')>Foreign national</option>
                            </select>
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}">Date of birth</label>
                            <input type="date" name="passengers[{{ $i }}][date_of_birth]" max="{{ now()->subDay()->toDateString() }}" value="{{ old("passengers.$i.date_of_birth", $passenger->date_of_birth?->toDateString()) }}" class="{{ $input }}">
                        </div>
                        <div class="hidden sm:block"></div>
                        <div>
                            <label class="{{ $fieldLabel }}">Passport number</label>
                            <input type="text" name="passengers[{{ $i }}][passport_number]" maxlength="50" value="{{ old("passengers.$i.passport_number", $passenger->passport_number) }}" class="{{ $input }} uppercase">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}">Passport expiration date{{ $ticket->isQuotation() ? '' : ' *' }}</label>
                            <input type="date" name="passengers[{{ $i }}][passport_expiry_date]" value="{{ old("passengers.$i.passport_expiry_date", $passenger->passport_expiry_date?->toDateString()) }}" class="{{ $input }}">
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No passengers on this booking.</p>
            @endforelse
        </section>

        {{-- 6. Contact & extras --}}
        <section id="step-contact" class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">6</span> Contact &amp; Extras</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="contact_name" class="{{ $fieldLabel }}">Booker name *</label>
                    <input type="text" id="contact_name" name="contact_name" required maxlength="255" value="{{ old('contact_name', $ticket->contact_name) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="contact_email" class="{{ $fieldLabel }}">Email{{ $ticket->isQuotation() ? '' : ' *' }}</label>
                    <input type="email" id="contact_email" name="contact_email" @required(! $ticket->isQuotation()) maxlength="255" value="{{ old('contact_email', $ticket->contact_email) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="contact_phone" class="{{ $fieldLabel }}">Phone{{ $ticket->isQuotation() ? '' : ' *' }}</label>
                    <input type="text" id="contact_phone" name="contact_phone" @required(! $ticket->isQuotation()) maxlength="50" value="{{ old('contact_phone', $ticket->contact_phone) }}" class="{{ $input }}">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-slate-200">
                <div>
                    <label for="emergency_contact_name" class="{{ $fieldLabel }}">Emergency contact<span x-show="travelType === 'international'"> *</span></label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" maxlength="255" value="{{ old('emergency_contact_name', $ticket->emergency_contact_name) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="emergency_contact_relationship" class="{{ $fieldLabel }}">Relationship<span x-show="travelType === 'international'"> *</span></label>
                    <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" maxlength="100" value="{{ old('emergency_contact_relationship', $ticket->emergency_contact_relationship) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="emergency_contact_phone" class="{{ $fieldLabel }}">Phone<span x-show="travelType === 'international'"> *</span></label>
                    <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" maxlength="50" value="{{ old('emergency_contact_phone', $ticket->emergency_contact_phone) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="emergency_contact_email" class="{{ $fieldLabel }}">Email</label>
                    <input type="email" id="emergency_contact_email" name="emergency_contact_email" maxlength="255" value="{{ old('emergency_contact_email', $ticket->emergency_contact_email) }}" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label for="special_requests" class="{{ $fieldLabel }}">Special instructions</label>
                <textarea id="special_requests" name="special_requests" rows="3" maxlength="2000" class="{{ $textarea }}">{{ old('special_requests', $ticket->special_requests) }}</textarea>
            </div>
        </section>

        {{-- 7. Pricing --}}
        <section id="step-pricing" class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">7</span> Pricing</h2>

            @if ($breakdown)
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    @foreach ($breakdown as $type => $row)
                        <div>
                            <label for="fare_{{ $type }}" class="{{ $fieldLabel }}">{{ $fareTypes[$type] ?? ucfirst($type) }} fare &times; {{ (int) ($row['qty'] ?? 0) }}</label>
                            <input type="number" step="0.01" min="0" id="fare_{{ $type }}" name="fare_prices[{{ $type }}]" x-model="farePrices['{{ $type }}']" value="{{ $money(old('fare_prices.'.$type, $row['price'] ?? 0)) }}" class="{{ $input }}">
                        </div>
                    @endforeach
                </div>
            @else
                <div class="sm:w-1/2">
                    <label for="estimated_fare" class="{{ $fieldLabel }}">Fare</label>
                    <input type="number" step="0.01" min="0" id="estimated_fare" name="estimated_fare" x-model="fare" value="{{ $money(old('estimated_fare', $ticket->estimated_fare)) }}" class="{{ $input }}">
                </div>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                @foreach (['taxes_amount' => ['Taxes / fees', 'taxes'], 'visa_assistance_fee' => ['Visa assistance', 'visa'], 'insurance_fee' => ['Insurance', 'insurance'], 'other_charges' => ['Other charges', 'other'], 'service_fee' => ['Amega service fee', 'service']] as $name => [$text, $key])
                    <div>
                        <label for="{{ $name }}" class="{{ $fieldLabel }}">{{ $text }}</label>
                        <input type="number" step="0.01" min="0" id="{{ $name }}" name="{{ $name }}" x-model="charges.{{ $key }}" value="{{ $money(old($name, $ticket->{$name})) }}" class="{{ $input }}">
                    </div>
                @endforeach
            </div>

            <dl class="rounded-lg bg-slate-50 divide-y divide-slate-200 text-sm">
                <div x-show="Math.abs(base) >= 0.01" class="flex justify-between px-4 py-2"><dt class="text-slate-500">Services, requests &amp; other items</dt><dd class="font-semibold text-slate-900" x-text="peso(base)"></dd></div>
                <div class="flex justify-between px-4 py-2"><dt class="font-semibold text-slate-900">New total</dt><dd class="font-bold text-slate-900" x-text="peso(total())"></dd></div>
                <div class="flex justify-between px-4 py-2"><dt class="text-slate-500">Already paid</dt><dd class="font-semibold text-slate-900" x-text="peso(paid)"></dd></div>
                <div class="flex justify-between px-4 py-2"><dt class="text-slate-500">Balance after saving</dt><dd class="font-semibold" :class="total() - paid > 0 ? 'text-amber-700' : 'text-emerald-700'" x-text="peso(Math.max(total() - paid, 0))"></dd></div>
            </dl>
            @if ($ticket->bookingAgreement)
                <p class="text-xs text-slate-500">The booking agreement keeps its own prices: edit it too if they change.</p>
            @endif
        </section>

        <div class="sticky bottom-0 z-20 -mx-1 px-1 py-3 bg-slate-50/95 backdrop-blur flex items-center gap-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span>Save changes</span>
            </button>
            <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="inline-flex items-center h-10 px-5 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Discard</a>
        </div>
    </form>
</div>
@endsection
