@props([
    'countryName',
    'provinceName',
    'cityName',
    'country' => null,
    'province' => null,
    'city' => null,
    'countryLabel' => 'Country',
])

@php
    $input = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-gray-100 disabled:text-dark/40';
    $label = 'block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5';
@endphp

{{-- A place: the country, then its province and city. For the Philippines the province and city
     are picked from the PSGC list (each narrowing the next); there is no such list for other
     countries, so there they are typed. --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4"
     x-data="placePicker({ url: @js(\App\Support\PhilippineLocations::url()), country: @js((string) $country), province: @js((string) $province), city: @js((string) $city) })"
     @country-selected.window="if ($event.detail.name === @js($countryName)) countryChanged($event.detail.value)">
    <div>
        <label class="{{ $label }}">{{ $countryLabel }}</label>
        <x-country-select :name="$countryName" :value="$country" />
    </div>

    <div>
        <label class="{{ $label }}">Province / State</label>
        <template x-if="isPh">
            <select name="{{ $provinceName }}" x-model="province" @change="city = ''" class="{{ $input }}">
                <option value="">-- Select province --</option>
                <template x-for="item in provinces" :key="item.name">
                    <option :value="item.name" x-text="item.name" :selected="item.name === province"></option>
                </template>
            </select>
        </template>
        <template x-if="! isPh">
            <input type="text" name="{{ $provinceName }}" x-model="province" autocomplete="off" placeholder="Type the province or state" class="{{ $input }}">
        </template>
    </div>

    <div>
        <label class="{{ $label }}">City / Municipality</label>
        <template x-if="isPh">
            <select name="{{ $cityName }}" x-model="city" :disabled="! province" class="{{ $input }}">
                <option value="" x-text="province ? '-- Select city / municipality --' : 'Pick the province first'"></option>
                <template x-for="item in cities" :key="item.name">
                    <option :value="item.name" x-text="item.name" :selected="item.name === city"></option>
                </template>
            </select>
        </template>
        <template x-if="! isPh">
            <input type="text" name="{{ $cityName }}" x-model="city" autocomplete="off" placeholder="Type the city" class="{{ $input }}">
        </template>
    </div>
</div>

@once
    <script>
        // Country, province and city of a place. The Philippine list loads once per page.
        function placePicker(config) {
            return {
                country: config.country,
                province: config.province,
                city: config.city,
                provinces: [],

                async init() {
                    window.phLocations ??= fetch(config.url).then((response) => response.json());
                    const data = await window.phLocations;
                    // Each option marks itself selected against the saved province and city.
                    this.provinces = data.provinces || [];
                },

                get isPh() {
                    return this.country === 'Philippines';
                },

                get cities() {
                    const current = this.provinces.find((item) => item.name === this.province);
                    return current ? current.cities : [];
                },

                countryChanged(value) {
                    if (value === this.country) return;
                    this.country = value;
                    this.province = '';
                    this.city = '';
                },
            };
        }
    </script>
@endonce
