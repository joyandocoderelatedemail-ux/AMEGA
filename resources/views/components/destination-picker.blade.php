@props([
    'countryName',
    'cityName',
    'country' => null,
    'city' => null,
])

@php
    $input = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5';

    // The cities the desk already knows by country: those with an airport on the list.
    $citiesByCountry = collect(config('airports'))
        ->groupBy(fn (array $airport) => $airport[2])
        ->map(fn ($airports) => $airports->pluck(0)->unique()->sort()->values()->all())
        ->all();
@endphp

{{-- Where the client goes next: the country picked from the list, then its city picked from the
     cities the desk flies to, or typed when it is not listed. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
     x-data="destinationPicker({ cities: @js($citiesByCountry), country: @js((string) $country), city: @js((string) $city) })"
     @country-selected.window="if ($event.detail.name === @js($countryName)) countryChanged($event.detail.value)">
    <div>
        <label class="{{ $label }}">Next country of destination</label>
        <x-country-select :name="$countryName" :value="$country" />
    </div>

    <div>
        <label class="{{ $label }}">Next city of destination</label>
        <input type="hidden" name="{{ $cityName }}" :value="value">
        <select x-show="options.length" x-model="choice" :disabled="! country" class="{{ $input }}" aria-label="Next city of destination">
            <option value="" x-text="country ? '-- Select city --' : 'Pick the country first'"></option>
            <template x-for="name in options" :key="name">
                <option :value="name" x-text="name" :selected="name === choice"></option>
            </template>
            <option value="__other">Other city (type it)</option>
        </select>
        <input x-show="typed" type="text" x-model="otherText" autocomplete="off" placeholder="Type the city"
               :class="options.length ? 'mt-2' : ''" class="{{ $input }}" aria-label="City not on the list">
    </div>
</div>

@once
    <script>
        // Country and city of the next destination.
        function destinationPicker(config) {
            const known = (cities, country, city) => (cities[country] || []).includes(city);

            return {
                cities: config.cities,
                country: config.country,
                choice: known(config.cities, config.country, config.city) ? config.city : (config.city ? '__other' : ''),
                otherText: known(config.cities, config.country, config.city) ? '' : config.city,

                get options() {
                    return this.cities[this.country] || [];
                },

                // A country with no listed cities, or "Other", is typed.
                get typed() {
                    return Boolean(this.country) && (this.options.length === 0 || this.choice === '__other');
                },

                get value() {
                    return this.typed ? this.otherText.trim() : this.choice;
                },

                countryChanged(value) {
                    if (value === this.country) return;
                    this.country = value;
                    this.choice = '';
                    this.otherText = '';
                },
            };
        }
    </script>
@endonce
