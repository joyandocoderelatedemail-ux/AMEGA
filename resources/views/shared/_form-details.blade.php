@php
    /**
     * A printed form's extra fields, in folding sections. Expects $sections (see
     * App\Support\FormSpec), $details (the saved values), $title and $intro.
     * Optional $open: true opens every section (the admin's read-only preview).
     * Optional $prefix (default 'form'): the request key the fields post under, e.g. "form[key]" or, when more
     * than one of these appear on the same page (the Non-Immigrant wizard's Draft Form step, one per possible
     * destination), a prefix of its own per instance so the fields and their ids never collide or overwrite
     * each other's values.
     */
    $details = $details ?? [];
    $prefix = $prefix ?? 'form';
    $input = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

<div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6">
    <h2 class="font-heading text-sm font-extrabold text-dark mb-1">{{ $title }}</h2>
    <p class="text-[11px] text-dark/50 mb-4">{{ $intro }}</p>

    <div class="space-y-3">
        @foreach ($sections as $sectionTitle => $fields)
            <details class="group rounded-xl border border-gray-200 bg-gray-50/60" @if (($open ?? false) || ($loop->first && $errors->has("{$prefix}.*"))) open @endif>
                <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none text-xs font-bold text-dark">
                    <span>{{ $sectionTitle }}</span>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 transition-transform group-open:rotate-180"></i>
                </summary>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 px-4 pb-4">
                    @foreach ($fields as $field)
                        @php
                            [$key, $label, $type] = $field;
                            $options = $field[3] ?? [];
                            $value = old("{$prefix}.{$key}", $details[$key] ?? ($type === 'checks' ? [] : ''));
                            $fieldId = "{$prefix}_{$key}";
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
                                            <input type="checkbox" name="{{ $prefix }}[{{ $key }}][]" value="{{ $code }}" @checked(in_array($code, (array) $value, true))
                                                   class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                                            {{ $optionLabel }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <label for="{{ $fieldId }}" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">{{ $label }}</label>
                                @if ($type === 'select' || $type === 'yesno')
                                    <select id="{{ $fieldId }}" name="{{ $prefix }}[{{ $key }}]" class="{{ $input }}">
                                        <option value="">&mdash;</option>
                                        @foreach ($type === 'yesno' ? ['yes' => 'Yes', 'no' => 'No'] : $options as $code => $optionLabel)
                                            <option value="{{ $code }}" @selected((string) $value === (string) $code)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input id="{{ $fieldId }}" type="{{ $type === 'date' ? 'date' : 'text' }}" name="{{ $prefix }}[{{ $key }}]" value="{{ $value }}" class="{{ $input }}">
                                @endif
                            @endif
                            @error("{$prefix}.{$key}")
                                <p class="text-[10px] text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</div>
