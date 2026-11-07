@php
    $rows = old('departures');
    if ($rows === null) {
        $rows = isset($package)
            ? $package->departures->map(fn ($d) => [
                'departure_date' => $d->departure_date->format('Y-m-d'),
                'return_date' => $d->return_date?->format('Y-m-d'),
                'slots' => $d->slots,
                'booked' => $d->bookedSlots(),
            ])->all()
            : [];
    }
@endphp

{{-- Travel dates and slots: these are what the public package page offers for booking. --}}
<div x-data="{ rows: @js(array_values($rows)) }" class="rounded-2xl border border-gray-200 p-4 space-y-3">
    <div class="flex items-center justify-between gap-3">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70">Departure Dates &amp; Slots</label>
            <p class="text-[11px] text-dark/50 mt-0.5">Each date shows on the website with its slots left. Once a date fills up it closes. Leave empty to take any date.</p>
        </div>
        <button type="button" @click="rows.push({departure_date: '', return_date: '', slots: 10})"
                class="px-3 py-2 rounded-lg bg-navy-700 text-white text-xs font-bold shrink-0">+ Add date</button>
    </div>

    <template x-for="(row, i) in rows" :key="i">
        <div class="grid grid-cols-2 sm:grid-cols-[1fr_1fr_6rem_auto] gap-2 items-end">
            <div>
                <span class="block text-[10px] font-bold uppercase text-dark/50 mb-1">Departs</span>
                <input type="date" :name="`departures[${i}][departure_date]`" x-model="row.departure_date"
                       class="w-full px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200 text-xs">
            </div>
            <div>
                <span class="block text-[10px] font-bold uppercase text-dark/50 mb-1">Returns</span>
                <input type="date" :name="`departures[${i}][return_date]`" x-model="row.return_date"
                       class="w-full px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200 text-xs">
            </div>
            <div>
                <span class="block text-[10px] font-bold uppercase text-dark/50 mb-1">Slots</span>
                <input type="number" min="0" :name="`departures[${i}][slots]`" x-model="row.slots"
                       class="w-full px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200 text-xs">
            </div>
            <div class="flex items-center gap-2">
                <span x-show="row.booked" class="text-[11px] font-bold text-amber-600" x-text="row.booked + ' booked'"></span>
                <button type="button" @click="rows.splice(i, 1)" class="px-3 py-2 rounded-lg border border-gray-200 text-xs font-bold text-rose-600">Remove</button>
            </div>
        </div>
    </template>

    <p x-show="rows.length === 0" class="text-xs text-dark/40">No dates yet.</p>

    @foreach ($errors->get('departures.*') as $messages)
        @foreach ($messages as $message)
            <p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>
        @endforeach
    @endforeach
</div>
