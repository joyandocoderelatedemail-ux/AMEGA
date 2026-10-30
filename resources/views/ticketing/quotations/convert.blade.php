@extends('layouts.ticketing')

@section('title', 'Convert to booking - ' . $ticket->booking_reference)

@php
    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $fieldLabel = 'block text-xs font-semibold text-slate-600 mb-1';
    $card = 'bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-4';
    $stepTitle = 'flex items-center gap-2.5 text-base font-semibold text-slate-900';
    $stepBadge = 'inline-flex items-center justify-center w-6 h-6 rounded-full bg-navy-700 text-white text-xs font-bold';
    $time = fn (?string $value): string => $value ? \Illuminate\Support\Str::substr($value, 0, 5) : '';
    $international = $ticket->travel_type === 'international';
    $roundTrip = $ticket->trip_type === 'round_trip';
    $error = fn (string $key) => $errors->first($key);
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <a href="{{ route('ticketing.quotations.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Quotations</span>
        </a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Convert {{ $ticket->booking_reference }} to a booking</h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ $ticket->contact_name }} · {{ $ticket->origin }} → {{ $ticket->destination }} · {{ $ticket->departure_date?->format('M d, Y') }}
            · {{ $ticket->total_passengers }} pax · <span class="font-semibold text-slate-900">₱{{ number_format((float) $ticket->total_amount, 2) }}</span>
        </p>
        <p class="mt-1 text-xs text-slate-500">The prices stay as quoted. Fill in what a booking needs; once saved, it goes to the admin for approval, then the cashier takes payment.</p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4" role="alert">
            <p class="text-sm font-semibold text-rose-900">Please correct the following:</p>
            <ul class="mt-1.5 space-y-1 text-xs text-rose-800">
                @foreach ($errors->all() as $message)
                    <li>&bull; {{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ticketing.quotations.store-conversion', $ticket) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- 1. The flight that was booked --}}
        <section class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">1</span> Flight booked</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="airline_id" class="{{ $fieldLabel }}">Airline *</label>
                    <select id="airline_id" name="airline_id" class="{{ $input }}">
                        <option value="">Choose the airline</option>
                        @foreach ($airlines as $airline)
                            <option value="{{ $airline->id }}" @selected((string) old('airline_id', $ticket->airline_id) === (string) $airline->id)>{{ $airline->name }}{{ $airline->code ? ' ('.$airline->code.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="airline_pnr" class="{{ $fieldLabel }}">Airline PNR</label>
                    <input type="text" id="airline_pnr" name="airline_pnr" maxlength="20" value="{{ old('airline_pnr', $ticket->airline_pnr) }}" class="{{ $input }}">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="flight_number" class="{{ $fieldLabel }}">Flight number *</label>
                    <input type="text" id="flight_number" name="flight_number" maxlength="20" value="{{ old('flight_number', $ticket->flight_number) }}" placeholder="PR 1845" class="{{ $input }}">
                </div>
                <div>
                    <label for="departure_time" class="{{ $fieldLabel }}">Departs *</label>
                    <input type="time" id="departure_time" name="departure_time" value="{{ old('departure_time', $time($ticket->departure_time)) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="arrival_time" class="{{ $fieldLabel }}">Arrives *</label>
                    <input type="time" id="arrival_time" name="arrival_time" value="{{ old('arrival_time', $time($ticket->arrival_time)) }}" class="{{ $input }}">
                </div>
            </div>
            @if ($roundTrip)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="return_flight_number" class="{{ $fieldLabel }}">Return flight number *</label>
                        <input type="text" id="return_flight_number" name="return_flight_number" maxlength="20" value="{{ old('return_flight_number', $ticket->return_flight_number) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="return_departure_time" class="{{ $fieldLabel }}">Return departs *</label>
                        <input type="time" id="return_departure_time" name="return_departure_time" value="{{ old('return_departure_time', $time($ticket->return_departure_time)) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="return_arrival_time" class="{{ $fieldLabel }}">Return arrives *</label>
                        <input type="time" id="return_arrival_time" name="return_arrival_time" value="{{ old('return_arrival_time', $time($ticket->return_arrival_time)) }}" class="{{ $input }}">
                    </div>
                </div>
            @endif
        </section>

        {{-- 2. Contact --}}
        <section class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">2</span> Contact</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="contact_email" class="{{ $fieldLabel }}">Email *</label>
                    <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $ticket->contact_email) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="contact_phone" class="{{ $fieldLabel }}">Phone *</label>
                    <input type="text" id="contact_phone" name="contact_phone" maxlength="50" value="{{ old('contact_phone', $ticket->contact_phone) }}" class="{{ $input }}">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-slate-200">
                @foreach (['emergency_contact_name' => 'Emergency contact', 'emergency_contact_relationship' => 'Relationship', 'emergency_contact_phone' => 'Phone', 'emergency_contact_email' => 'Email'] as $field => $text)
                    <div>
                        <label for="{{ $field }}" class="{{ $fieldLabel }}">{{ $text }}{{ $international && $field !== 'emergency_contact_email' ? ' *' : '' }}</label>
                        <input type="{{ $field === 'emergency_contact_email' ? 'email' : 'text' }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $ticket->{$field}) }}" class="{{ $input }}">
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 3. Every passenger, named, with a valid passport --}}
        <section class="{{ $card }}">
            <h2 class="{{ $stepTitle }}"><span class="{{ $stepBadge }}">3</span> Passengers</h2>
            <p class="text-xs text-slate-500">
                Every passenger quoted, by name. The passport must be valid for at least six months after departure.
                {{ $international ? 'International travel needs each passport number and scan.' : 'Foreign nationals need the passport number and scan.' }}
                Other documents can be uploaded on the booking afterwards.
            </p>
            @foreach ($rows as $i => $row)
                @php $old = fn (string $field) => old("passengers.{$i}.{$field}", $row[$field]); @endphp
                <div class="rounded-lg border {{ $errors->has("passengers.{$i}.*") ? 'border-rose-300 bg-rose-50/40' : 'border-slate-200' }} p-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900">Passenger #{{ $i + 1 }} · {{ ucfirst($row['passenger_type']) }}</p>
                        @if (! $row['id'])<span class="text-[11px] font-semibold text-amber-700">Not named on the quotation</span>@endif
                    </div>
                    <input type="hidden" name="passengers[{{ $i }}][id]" value="{{ $row['id'] }}">
                    <input type="hidden" name="passengers[{{ $i }}][passenger_type]" value="{{ $row['passenger_type'] }}">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_first">First name *</label>
                            <input type="text" id="p{{ $i }}_first" name="passengers[{{ $i }}][first_name]" maxlength="100" value="{{ $old('first_name') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_middle">Middle name</label>
                            <input type="text" id="p{{ $i }}_middle" name="passengers[{{ $i }}][middle_name]" maxlength="100" value="{{ $old('middle_name') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_last">Last name *</label>
                            <input type="text" id="p{{ $i }}_last" name="passengers[{{ $i }}][last_name]" maxlength="100" value="{{ $old('last_name') }}" class="{{ $input }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_gender">Gender</label>
                            <select id="p{{ $i }}_gender" name="passengers[{{ $i }}][gender]" class="{{ $input }}">
                                <option value="">--</option>
                                @foreach (\App\Models\User::GENDERS as $value => $text)
                                    <option value="{{ $value }}" @selected($old('gender') === $value)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_dob">Date of birth</label>
                            <input type="date" id="p{{ $i }}_dob" name="passengers[{{ $i }}][date_of_birth]" value="{{ $old('date_of_birth') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_nat">Nationality</label>
                            <select id="p{{ $i }}_nat" name="passengers[{{ $i }}][nationality_type]" class="{{ $input }}">
                                <option value="filipino" @selected($old('nationality_type') === 'filipino')>Filipino</option>
                                <option value="foreign_national" @selected($old('nationality_type') === 'foreign_national')>Foreign national</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_passport">Passport number{{ $international ? ' *' : '' }}</label>
                            <input type="text" id="p{{ $i }}_passport" name="passengers[{{ $i }}][passport_number]" maxlength="50" value="{{ $old('passport_number') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_expiry">Passport expiry *</label>
                            <input type="date" id="p{{ $i }}_expiry" name="passengers[{{ $i }}][passport_expiry_date]" value="{{ $old('passport_expiry_date') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $fieldLabel }}" for="p{{ $i }}_scan">Passport scan{{ $international ? ' *' : '' }}</label>
                            @if ($row['has_scan'])
                                <p class="text-xs font-semibold text-emerald-700 mb-1">On file. Upload only to replace it.</p>
                            @endif
                            <input type="file" id="p{{ $i }}_scan" name="passengers[{{ $i }}][passport_file]" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                   class="block w-full text-xs text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-navy-700 file:text-white file:text-xs file:font-semibold">
                        </div>
                    </div>
                    @foreach (['first_name', 'last_name', 'date_of_birth', 'passport_number', 'passport_expiry_date', 'passport_file'] as $field)
                        @if ($error("passengers.{$i}.{$field}"))<p class="text-xs font-semibold text-rose-600">{{ $error("passengers.{$i}.{$field}") }}</p>@endif
                    @endforeach
                </div>
            @endforeach
        </section>

        {{-- 4. The agent stands behind it --}}
        <label class="flex items-start gap-3 p-4 rounded-xl border {{ $errors->has('agent_acknowledged') ? 'border-rose-400 bg-rose-50/60' : 'border-slate-200 bg-white' }} cursor-pointer">
            <input type="checkbox" name="agent_acknowledged" value="1" @checked(old('agent_acknowledged')) class="mt-0.5 w-4 h-4 rounded text-navy-700">
            <span class="text-sm text-slate-700">
                I acknowledge that I have reviewed all the information in this booking and that it is correct.
                I, <strong>{{ auth()->user()->name }}</strong>, will be held accountable for any incorrect information.
            </span>
        </label>

        <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                <span>Convert and send for approval</span>
            </button>
            <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="inline-flex items-center h-10 px-5 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
