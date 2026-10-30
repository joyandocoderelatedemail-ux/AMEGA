@props([
    // What the dates are, e.g. "Created", "Paid", "Departure".
    'label' => 'Date',
    // Classes for the controls, to match the page's other filters.
    'inputClass' => 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600',
    'buttonClass' => 'h-10 px-3 rounded-lg bg-navy-700 text-white text-xs font-semibold hover:bg-navy-800',
])
{{--
    The date range for a list page, inside the page's own GET filter form: a period (Today, Last 7
    days, This month...) or From / To dates, read by App\Support\DateRange::fromRequest(). Picking a
    period applies it at once; dates are applied with Apply. A lone date is that one day.
--}}
@php $range = \App\Support\DateRange::fromRequest(request()); @endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }} role="group" aria-label="{{ $label }} date range">
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 whitespace-nowrap">
        <i data-lucide="calendar-range" class="w-3.5 h-3.5"></i>{{ $label }}
    </span>
    <select name="range" aria-label="{{ $label }}: period" class="{{ $inputClass }} w-auto pr-8"
            onchange="if (this.value !== 'custom') { this.form.from.value = ''; this.form.to.value = ''; this.form.submit(); }">
        @foreach (\App\Support\DateRange::PRESETS as $key => $presetLabel)
            <option value="{{ $key }}" @selected($range->preset === $key)>{{ $presetLabel }}</option>
        @endforeach
        <option value="custom" @selected($range->preset === 'custom')>Custom dates</option>
    </select>
    <input type="date" name="from" value="{{ $range->preset === 'custom' ? $range->from->toDateString() : '' }}"
           aria-label="{{ $label }} from" oninput="this.form.range.value = 'custom'" class="{{ $inputClass }} w-[9.5rem]">
    <span class="text-xs text-slate-400" aria-hidden="true">to</span>
    <input type="date" name="to" value="{{ $range->preset === 'custom' ? $range->to->toDateString() : '' }}"
           aria-label="{{ $label }} to" oninput="this.form.range.value = 'custom'" class="{{ $inputClass }} w-[9.5rem]">
    <button type="submit" class="{{ $buttonClass }}">Apply</button>
</div>
