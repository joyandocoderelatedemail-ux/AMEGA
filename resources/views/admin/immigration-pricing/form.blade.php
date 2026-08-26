@php
    $tier = $tier ?? null;
@endphp

@if ($errors->any())
    <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4">
        <p class="text-xs font-bold text-rose-800 mb-1">Please correct the following:</p>
        <ul class="list-disc list-inside text-[11px] text-rose-700 space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Process Category</label>
    <select name="immigration_category_id" required
            class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" {{ (string) old('immigration_category_id', $tier->immigration_category_id ?? '') === (string) $category->id ? 'selected' : '' }}>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Extension Label</label>
        <input type="text" name="extension_label" value="{{ old('extension_label', $tier->extension_label ?? '') }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="e.g. 1st Extension (leave blank if not applicable)">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Duration Label</label>
        <input type="text" name="duration_label" value="{{ old('duration_label', $tier->duration_label ?? '') }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="e.g. 2 months, 1 month, 29 days">
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Process Type</label>
        <select name="process_type" required
                class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="regular" {{ old('process_type', $tier->process_type ?? 'regular') === 'regular' ? 'selected' : '' }}>Regular</option>
            <option value="express" {{ old('process_type', $tier->process_type ?? '') === 'express' ? 'selected' : '' }}>Express</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Payment Method</label>
        <select name="payment_method" required
                class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="cash" {{ old('payment_method', $tier->payment_method ?? 'cash') === 'cash' ? 'selected' : '' }}>Cash</option>
            <option value="card" {{ old('payment_method', $tier->payment_method ?? '') === 'card' ? 'selected' : '' }}>Card</option>
        </select>
    </div>
</div>

<div>
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Condition Notes</label>
    <input type="text" name="condition_notes" value="{{ old('condition_notes', $tier->condition_notes ?? '') }}"
           class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
           placeholder="e.g. Valid ACR I-Card, visa expired">
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Price (₱)</label>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $tier?->price) }}" required
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="e.g. 2930.00">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Processing Time</label>
        <input type="text" name="processing_time" value="{{ old('processing_time', $tier->processing_time ?? '') }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="e.g. 7-10 working days">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Display Order</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $tier->sort_order ?? 1) }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
    </div>
</div>

<div class="space-y-2 pt-2">
    <div class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" id="is_active" class="w-4 h-4 rounded text-primary"
               {{ old('is_active', $tier->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="text-xs font-bold text-dark cursor-pointer">Show this price row on the public price list</label>
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" name="needs_review" value="1" id="needs_review" class="w-4 h-4 rounded text-amber-500"
               {{ old('needs_review', $tier->needs_review ?? false) ? 'checked' : '' }}>
        <label for="needs_review" class="text-xs font-bold text-dark cursor-pointer">
            Needs review - figure not yet confirmed against the source sheet
            <span class="block font-normal text-[11px] text-dark/50">Flagged rows are always withheld from the public price list, even when enabled above.</span>
        </label>
    </div>
</div>
