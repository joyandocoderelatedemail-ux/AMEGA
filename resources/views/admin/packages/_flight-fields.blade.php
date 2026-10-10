{{-- Flight terms the package is sold with; the ticket wizard fills them in when the package is picked. $package is null on create. --}}
@php
    $package = $package ?? null;
    $field = 'w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5';
@endphp

<div class="space-y-4 pt-4 border-t border-gray-100"
     x-data="{
         tripType: {{ Js::from((string) old('trip_type', $package?->trip_type ?? '')) }},
         segments: {{ Js::from(array_values(old('multi_city_segments', $package?->multi_city_segments ?: [['from' => '', 'to' => ''], ['from' => '', 'to' => '']]))) }},
         addSegment() {
             const last = this.segments[this.segments.length - 1];
             this.segments.push({ from: last ? last.to : '', to: '' });
         },
         removeSegment(index) {
             if (this.segments.length > 2) this.segments.splice(index, 1);
         },
     }">
    <h3 class="text-xs font-bold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
        <i data-lucide="plane" class="w-3.5 h-3.5"></i>
        <span>Flight</span>
    </h3>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="airfare_inclusion" class="{{ $label }}">Airfare</label>
            <select id="airfare_inclusion" name="airfare_inclusion" class="{{ $field }}">
                <option value="">Not specified</option>
                @foreach (\App\Models\TravelPackage::AIRFARE_OPTIONS as $value => $text)
                    <option value="{{ $value }}" @selected(old('airfare_inclusion', $package?->airfare_inclusion) === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="airline_id" class="{{ $label }}">Airline</label>
            <select id="airline_id" name="airline_id" class="{{ $field }}">
                <option value="">Any airline</option>
                @foreach ($airlines as $airline)
                    <option value="{{ $airline->id }}" @selected((string) old('airline_id', $package?->airline_id) === (string) $airline->id)>{{ $airline->name }}{{ $airline->is_active ? '' : ' (off in the wizard)' }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label for="trip_type" class="{{ $label }}">Trip Type</label>
        <select id="trip_type" name="trip_type" x-model="tripType" class="{{ $field }}">
            <option value="">Not specified</option>
            @foreach (\App\Models\TravelPackage::TRIP_TYPES as $value => $text)
                <option value="{{ $value }}">{{ $text }}</option>
            @endforeach
        </select>
    </div>

    {{-- Multi-city route: the legs only; the dates are set on each booking. --}}
    <div x-show="tripType === 'multi_city'" x-cloak class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-dark">Route Legs</span>
            <button type="button" @click="addSegment()" class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-bold text-dark hover:border-primary">+ Add Leg</button>
        </div>
        @error('multi_city_segments')
            <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
        @enderror
        <template x-for="(segment, index) in segments" :key="index">
            <div class="grid grid-cols-[auto,1fr,1fr,auto] items-center gap-2">
                <span class="w-5 text-xs font-bold text-dark/50" x-text="index + 1"></span>
                <input type="text" :name="tripType === 'multi_city' ? 'multi_city_segments[' + index + '][from]' : null" x-model="segment.from" maxlength="100" class="{{ $field }}" placeholder="From, e.g. Manila (MNL)">
                <input type="text" :name="tripType === 'multi_city' ? 'multi_city_segments[' + index + '][to]' : null" x-model="segment.to" maxlength="100" class="{{ $field }}" placeholder="To, e.g. Tokyo (NRT)">
                <button type="button" @click="removeSegment(index)" :disabled="segments.length <= 2" class="w-9 h-9 rounded-lg text-dark/40 hover:text-rose-600 disabled:opacity-30" :aria-label="'Remove leg ' + (index + 1)"><span aria-hidden="true" class="text-lg">&times;</span></button>
            </div>
        </template>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="origin_airport" class="{{ $label }}">Origin Airport</label>
            <input type="text" id="origin_airport" name="origin_airport" value="{{ old('origin_airport', $package?->origin_airport) }}" maxlength="255" class="{{ $field }}" placeholder="e.g. Manila (MNL)">
        </div>
        <div>
            <label for="cabin_class" class="{{ $label }}">Cabin Class</label>
            <select id="cabin_class" name="cabin_class" class="{{ $field }}">
                <option value="">Not specified</option>
                @foreach (\App\Models\TravelPackage::CABIN_CLASSES as $value => $text)
                    <option value="{{ $value }}" @selected(old('cabin_class', $package?->cabin_class) === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="baggage_allowance" class="{{ $label }}">Baggage Allowance</label>
            <input type="text" id="baggage_allowance" name="baggage_allowance" value="{{ old('baggage_allowance', $package?->baggage_allowance) }}" maxlength="255" class="{{ $field }}" placeholder="e.g. 20 kg checked + 7 kg hand-carry">
        </div>
    </div>
</div>
