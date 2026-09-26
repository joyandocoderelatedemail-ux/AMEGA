@props([
    'name',
    'id' => null,
    'value' => null,
    'required' => false,
    'placeholder' => 'Search country...',
])

@php
    $id ??= $name;
    $selected = \App\Support\Countries::normalize($value) ?? '';
@endphp

{{-- Searchable country picker. The typed text only filters; the submitted
     value lives in the hidden input and is always a picked country (or the
     record's existing value, kept until staff choose another). --}}
<div class="relative"
     x-data="{
        countries: @js(\App\Support\Countries::all()),
        selected: @js($selected),
        query: @js($selected),
        isOpen: false,
        active: 0,
        get filtered() {
            const term = this.query.trim().toLowerCase();
            if (term === '' || this.query === this.selected) {
                return this.countries;
            }
            const starts = this.countries.filter(c => c.toLowerCase().startsWith(term));
            const contains = this.countries.filter(c => ! c.toLowerCase().startsWith(term) && c.toLowerCase().includes(term));
            return starts.concat(contains);
        },
        open() {
            if (! this.isOpen) {
                this.isOpen = true;
                this.active = Math.max(0, this.filtered.indexOf(this.selected));
                this.scrollToActive();
            }
        },
        close() {
            this.isOpen = false;
            this.query = this.selected;
        },
        choose(country) {
            if (country) {
                this.selected = country;
            }
            this.close();
        },
        move(step) {
            this.open();
            const count = this.filtered.length;
            if (count) {
                this.active = (this.active + step + count) % count;
                this.scrollToActive();
            }
        },
        scrollToActive() {
            this.$nextTick(() => this.$refs.list?.querySelector(`[data-index='${this.active}']`)?.scrollIntoView({ block: 'nearest' }));
        },
     }"
     @click.outside="if (isOpen) close()">
    <input type="hidden" name="{{ $name }}" :value="selected" value="{{ $selected }}">

    <input id="{{ $id }}" type="text" x-model="query" value="{{ $selected }}" autocomplete="off"
           role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-listbox" :aria-expanded="isOpen.toString()"
           @focus="open(); $el.select()"
           @click="open()"
           @input="isOpen = true; active = 0"
           @keydown.arrow-down.prevent="move(1)"
           @keydown.arrow-up.prevent="move(-1)"
           @keydown.enter="if (isOpen) { $event.preventDefault(); choose(filtered[active]); }"
           @keydown.escape.prevent="close()"
           @keydown.tab="choose(query.trim() && filtered.length ? filtered[active] : null)"
           @blur="if (isOpen && ! $refs.list.contains($event.relatedTarget)) close()"
           @if($required) required @endif
           {{ $attributes->merge(['class' => 'w-full pl-3.5 pr-9 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary']) }}
           placeholder="{{ $placeholder }}">

    <svg class="w-4 h-4 text-dark/40 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m6 9 6 6 6-6"/>
    </svg>

    <ul id="{{ $id }}-listbox" x-ref="list" x-show="isOpen" x-cloak role="listbox"
        class="absolute z-30 left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded-lg bg-white border border-gray-200 shadow-lg py-1 text-xs">
        <template x-for="(country, index) in filtered" :key="country">
            <li role="option" :data-index="index" :aria-selected="(country === selected).toString()"
                @mousedown.prevent="choose(country)"
                @mouseenter="active = index"
                :class="index === active ? 'bg-primary/10 text-primary' : 'text-dark'"
                class="px-3.5 py-2 cursor-pointer flex items-center justify-between gap-2">
                <span x-text="country"></span>
                <svg x-show="country === selected" class="w-3.5 h-3.5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
            </li>
        </template>
        <li x-show="filtered.length === 0" class="px-3.5 py-2 text-dark/50">No country matches "<span x-text="query"></span>"</li>
    </ul>
</div>
