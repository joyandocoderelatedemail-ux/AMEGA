@props([
    'street' => null,
    'province' => null,
    'city' => null,
    'postal' => null,
    'required' => false,
])

@php
    $input = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-[11px] font-bold text-dark/70 mb-1';
    $mark = $required ? ' *' : '';
@endphp

{{-- A Philippine residential address: the street as typed, then the province, the city or
     municipality within it, and its ZIP code, picked from the PSGC list. Each choice narrows
     the next. The list loads once from public/data/ph-locations.json. --}}
<div class="space-y-3"
     x-data="phAddress({
        url: @js(\App\Support\PhilippineLocations::url()),
        province: @js((string) $province),
        city: @js((string) $city),
        postal: @js((string) $postal),
     })">
    <div>
        <label for="address" class="{{ $label }}">Residential Address{{ $mark }}</label>
        <input id="address" type="text" name="address" value="{{ $street }}" @if($required) required @endif
               class="{{ $input }}" placeholder="House/Unit No., Street, Subdivision, Barangay">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label for="province" class="{{ $label }}">Province / Region{{ $mark }}</label>
            <select id="province" name="province" x-model="province" @change="city = ''; postal = ''" @if($required) required @endif class="{{ $input }}">
                <option value="">-- Select province --</option>
                <template x-for="item in provinces" :key="item.name">
                    <option :value="item.name" x-text="item.name" :selected="item.name === province"></option>
                </template>
            </select>
        </div>
        <div>
            <label for="city" class="{{ $label }}">City / Municipality{{ $mark }}</label>
            <select id="city" name="city" x-model="city" @change="pickPostal()" :disabled="! province" @if($required) required @endif class="{{ $input }}">
                <option value="" x-text="province ? '-- Select city / municipality --' : 'Pick the province first'">-- Select city / municipality --</option>
                <template x-for="item in cities" :key="item.name">
                    <option :value="item.name" x-text="item.name" :selected="item.name === city"></option>
                </template>
            </select>
        </div>
        <div>
            <label for="postal_code" class="{{ $label }}">Postal Code</label>
            <select id="postal_code" name="postal_code" x-model="postal" :disabled="! city" class="{{ $input }}">
                <option value="" x-text="city ? '-- Select ZIP code --' : 'Pick the city first'">-- Select ZIP code --</option>
                <template x-for="code in postalCodes" :key="code">
                    <option :value="code" x-text="code + (zipNames[code] ? ' · ' + zipNames[code] : '')" :selected="code === postal"></option>
                </template>
            </select>
        </div>
    </div>
</div>

@once
    <script>
        // Loads the Philippine locations once per page and narrows province → city → ZIP.
        window.phLocations ??= null;
        function phAddress(config) {
            return {
                province: config.province,
                city: config.city,
                postal: config.postal,
                provinces: [],
                zipNames: {},

                async init() {
                    window.phLocations ??= fetch(config.url).then((response) => response.json());
                    const saved = { province: this.province, city: this.city, postal: this.postal };
                    const data = await window.phLocations;
                    this.zipNames = data.zipNames || {};
                    this.provinces = data.provinces || [];
                    // Re-apply the saved choices once each list has its options, top down.
                    this.province = '';
                    this.city = '';
                    this.postal = '';
                    await this.$nextTick();
                    this.province = saved.province;
                    await this.$nextTick();
                    this.city = saved.city;
                    await this.$nextTick();
                    this.postal = saved.postal;
                },

                get current() {
                    return this.provinces.find((item) => item.name === this.province) || null;
                },

                get cities() {
                    return this.current ? this.current.cities : [];
                },

                // The city's own ZIP codes; a town without one lists its province's.
                get postalCodes() {
                    const town = this.cities.find((item) => item.name === this.city);
                    if (! town) return [];
                    return town.zips.length ? town.zips : (this.current ? this.current.zips : []);
                },

                pickPostal() {
                    const town = this.cities.find((item) => item.name === this.city);
                    this.postal = town && town.zips.length === 1 ? town.zips[0] : '';
                },
            };
        }
    </script>
@endonce
