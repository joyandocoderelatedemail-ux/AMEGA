@php
    /**
     * What the PRA forms ask beyond the desk's own fields, in folding sections.
     * Expects $details (the file's form_details) and $forRenewal.
     */
    $details = $details ?? [];
    $input = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

<div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
    <h2 class="font-heading text-sm font-extrabold text-dark mb-1">PRA Form Details</h2>
    <p class="text-[11px] text-dark/50 mb-4">
        {{ $forRenewal ? 'Printed on the Retiree Request Form. Anything left blank is taken from the linked retiree file.' : 'Printed on the PRA application form, checklist and request form. Fill in what the retiree gives you; blanks print empty.' }}
    </p>

    <div class="space-y-3">
        @foreach (\App\Support\SrrvForms::sections($forRenewal) as $title => $fields)
            <details class="group rounded-xl border border-gray-200 bg-gray-50/60" @if ($loop->first && $errors->has('form.*')) open @endif>
                <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none text-xs font-bold text-dark">
                    <span>{{ $title }}</span>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 transition-transform group-open:rotate-180"></i>
                </summary>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 px-4 pb-4">
                    @foreach ($fields as $field)
                        @php
                            [$key, $label, $type] = $field;
                            $options = $field[3] ?? [];
                            $value = old("form.{$key}", $details[$key] ?? ($type === 'checks' ? [] : ''));
                        @endphp
                        <div @class([
                            'sm:col-span-2 lg:col-span-4' => $type === 'checks',
                            'sm:col-span-2' => $type === 'text' && str_contains($key, 'address'),
                            // Each numbered row (residence, school, job, child) starts on its own line.
                            'lg:col-start-1' => (bool) preg_match('/^(residence|education|employment|child)_\d_(period|level|company|name)$/', $key),
                        ])>
                            @if ($type === 'checks')
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1.5">{{ $label }}</span>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($options as $code => $optionLabel)
                                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white border border-gray-200 text-[11px] font-semibold text-dark cursor-pointer">
                                            <input type="checkbox" name="form[{{ $key }}][]" value="{{ $code }}" @checked(in_array($code, (array) $value, true))
                                                   class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                            {{ $optionLabel }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <label for="form_{{ $key }}" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">{{ $label }}</label>
                                @if ($type === 'select' || $type === 'yesno')
                                    <select id="form_{{ $key }}" name="form[{{ $key }}]" class="{{ $input }}">
                                        <option value="">&mdash;</option>
                                        @foreach ($type === 'yesno' ? ['yes' => 'Yes', 'no' => 'No'] : $options as $code => $optionLabel)
                                            <option value="{{ $code }}" @selected((string) $value === (string) $code)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input id="form_{{ $key }}" type="{{ $type === 'date' ? 'date' : 'text' }}" name="form[{{ $key }}]" value="{{ $value }}" class="{{ $input }}">
                                @endif
                            @endif
                            @error("form.{$key}")
                                <p class="text-[10px] text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</div>
