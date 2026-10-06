@extends('layouts.ticketing')

@section('title', 'Edit Booking - ' . $ticket->booking_reference)

@php
    $field = 'w-full rounded-lg border-slate-300 text-sm focus:border-navy-500 focus:ring-navy-500';
    $label = 'text-xs font-semibold uppercase tracking-wide text-slate-500 block mb-1.5';
    $time = fn (?string $value): string => $value ? \Illuminate\Support\Str::substr($value, 0, 5) : '';
    $isRoundTrip = $ticket->trip_type === 'round_trip';
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="inline-flex items-center gap-1.5 text-xs font-heading font-bold text-dark/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to ticket</span>
        </a>
    </div>

    <div>
        <h1 class="text-xl font-heading font-bold text-slate-900">Edit Booking</h1>
        <p class="text-sm text-slate-500 mt-1">
            <span class="font-mono font-semibold text-slate-700">{{ $ticket->booking_reference }}</span>
            &middot; {{ $ticket->origin ? $ticket->origin.' to ' : '' }}{{ $ticket->destination }}
        </p>
        <p class="text-xs text-slate-500 mt-2 max-w-2xl">
            Fix the contact, dates, booked flight and passenger details. The route, fare, number of passengers and
            uploaded documents stay as booked: for those, cancel this booking and make a new one.
        </p>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 ring-1 ring-rose-200 text-sm text-rose-800">
            <p class="font-bold mb-1">This booking was not saved.</p>
            <ul class="list-disc pl-5 space-y-0.5 text-xs">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ticketing.tickets.update', $ticket) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Contact --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
            <h2 class="font-heading text-base font-bold text-slate-900 mb-4">Contact</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="contact_name" class="{{ $label }}">Name</label>
                    <input type="text" id="contact_name" name="contact_name" required maxlength="255" value="{{ old('contact_name', $ticket->contact_name) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="contact_email" class="{{ $label }}">Email</label>
                    <input type="email" id="contact_email" name="contact_email" @required(! $ticket->isQuotation()) maxlength="255" value="{{ old('contact_email', $ticket->contact_email) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="contact_phone" class="{{ $label }}">Phone</label>
                    <input type="text" id="contact_phone" name="contact_phone" @required(! $ticket->isQuotation()) maxlength="50" value="{{ old('contact_phone', $ticket->contact_phone) }}" class="{{ $field }}">
                </div>
            </div>
        </section>

        {{-- Dates --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
            <h2 class="font-heading text-base font-bold text-slate-900 mb-4">Travel Dates</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="departure_date" class="{{ $label }}">Departure</label>
                    <input type="date" id="departure_date" name="departure_date" required min="{{ now()->toDateString() }}" value="{{ old('departure_date', $ticket->departure_date?->toDateString()) }}" class="{{ $field }}">
                </div>
                @if ($isRoundTrip)
                    <div>
                        <label for="return_date" class="{{ $label }}">Return</label>
                        <input type="date" id="return_date" name="return_date" required value="{{ old('return_date', $ticket->return_date?->toDateString()) }}" class="{{ $field }}">
                    </div>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-3">Changing the departure date re-checks each passenger's fare category and passport validity.</p>
        </section>

        {{-- Booked flight --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
            <h2 class="font-heading text-base font-bold text-slate-900 mb-4">Booked Flight</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="airline_id" class="{{ $label }}">Airline</label>
                    <select id="airline_id" name="airline_id" class="{{ $field }}">
                        <option value="">Not booked yet</option>
                        @foreach ($airlines as $airline)
                            <option value="{{ $airline->id }}" @selected((string) old('airline_id', $ticket->airline_id) === (string) $airline->id)>{{ $airline->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="airline_pnr" class="{{ $label }}">Booking Code (PNR)</label>
                    <input type="text" id="airline_pnr" name="airline_pnr" maxlength="20" value="{{ old('airline_pnr', $ticket->airline_pnr) }}" class="{{ $field }} uppercase font-mono">
                </div>
                <div></div>

                <div>
                    <label for="flight_number" class="{{ $label }}">Flight Number</label>
                    <input type="text" id="flight_number" name="flight_number" maxlength="20" value="{{ old('flight_number', $ticket->flight_number) }}" class="{{ $field }} uppercase">
                </div>
                <div>
                    <label for="departure_time" class="{{ $label }}">Departs</label>
                    <input type="time" id="departure_time" name="departure_time" value="{{ old('departure_time', $time($ticket->departure_time)) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="arrival_time" class="{{ $label }}">Arrives</label>
                    <input type="time" id="arrival_time" name="arrival_time" value="{{ old('arrival_time', $time($ticket->arrival_time)) }}" class="{{ $field }}">
                </div>

                @if ($isRoundTrip)
                    <div>
                        <label for="return_flight_number" class="{{ $label }}">Return Flight Number</label>
                        <input type="text" id="return_flight_number" name="return_flight_number" maxlength="20" value="{{ old('return_flight_number', $ticket->return_flight_number) }}" class="{{ $field }} uppercase">
                    </div>
                    <div>
                        <label for="return_departure_time" class="{{ $label }}">Return Departs</label>
                        <input type="time" id="return_departure_time" name="return_departure_time" value="{{ old('return_departure_time', $time($ticket->return_departure_time)) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="return_arrival_time" class="{{ $label }}">Return Arrives</label>
                        <input type="time" id="return_arrival_time" name="return_arrival_time" value="{{ old('return_arrival_time', $time($ticket->return_arrival_time)) }}" class="{{ $field }}">
                    </div>
                @endif
            </div>
        </section>

        {{-- Passengers --}}
        @if ($ticket->passengers->isNotEmpty())
            <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <h2 class="font-heading text-base font-bold text-slate-900 mb-1">Passengers</h2>
                <p class="text-xs text-slate-500 mb-4">Names must match the passport or valid ID exactly.</p>

                <div class="space-y-5">
                    @foreach ($ticket->passengers as $i => $passenger)
                        <div class="rounded-xl border border-slate-200 p-4 space-y-3">
                            <input type="hidden" name="passengers[{{ $i }}][id]" value="{{ $passenger->id }}">
                            <p class="text-xs font-bold text-slate-700">
                                Passenger {{ $passenger->passenger_number }}
                                <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-semibold">{{ ucfirst($passenger->passenger_type) }}</span>
                            </p>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="{{ $label }}">First name</label>
                                    <input type="text" name="passengers[{{ $i }}][first_name]" required maxlength="255" value="{{ old("passengers.$i.first_name", $passenger->first_name) }}" class="{{ $field }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Middle name</label>
                                    <input type="text" name="passengers[{{ $i }}][middle_name]" maxlength="255" value="{{ old("passengers.$i.middle_name", $passenger->middle_name) }}" class="{{ $field }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Last name</label>
                                    <input type="text" name="passengers[{{ $i }}][last_name]" required maxlength="255" value="{{ old("passengers.$i.last_name", $passenger->last_name) }}" class="{{ $field }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Suffix</label>
                                    <input type="text" name="passengers[{{ $i }}][suffix]" maxlength="20" value="{{ old("passengers.$i.suffix", $passenger->suffix) }}" class="{{ $field }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Gender</label>
                                    <select name="passengers[{{ $i }}][gender]" class="{{ $field }}">
                                        <option value="">Not stated</option>
                                        @foreach (\App\Models\User::GENDERS as $value => $text)
                                            <option value="{{ $value }}" @selected(old("passengers.$i.gender", $passenger->gender) === $value)>{{ $text }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="{{ $label }}">Date of birth</label>
                                    <input type="date" name="passengers[{{ $i }}][date_of_birth]" max="{{ now()->subDay()->toDateString() }}" value="{{ old("passengers.$i.date_of_birth", $passenger->date_of_birth?->toDateString()) }}" class="{{ $field }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Passport number</label>
                                    <input type="text" name="passengers[{{ $i }}][passport_number]" maxlength="50" value="{{ old("passengers.$i.passport_number", $passenger->passport_number) }}" class="{{ $field }} uppercase">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Passport expires</label>
                                    <input type="date" name="passengers[{{ $i }}][passport_expiry_date]" value="{{ old("passengers.$i.passport_expiry_date", $passenger->passport_expiry_date?->toDateString()) }}" class="{{ $field }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="flex items-center gap-2">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800 transition-colors">
                Save changes
            </button>
            <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="px-5 py-2.5 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors">
                Discard
            </a>
        </div>
    </form>
</div>
@endsection
