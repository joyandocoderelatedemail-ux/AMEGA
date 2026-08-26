@php
    $category = $category ?? null;
    $existingRows = old('requirements', $category
        ? $category->requirements->map(fn ($requirement) => [
            'id' => $requirement->id,
            'label' => $requirement->label,
            'type' => $requirement->type,
            'needs_review' => $requirement->needs_review ? 1 : 0,
        ])->values()->all()
        : []);
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
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Category Name</label>
    <input type="text" name="name" value="{{ old('name', $category->name ?? '') }}" required
           class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
           placeholder="e.g. Tourist Visa Extension">
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">URL Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $category->slug ?? '') }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="Leave blank to generate from the name">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Lucide Icon Name</label>
        <input type="text" name="icon" value="{{ old('icon', $category->icon ?? 'stamp') }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="e.g. stamp, plane-takeoff, file-badge">
    </div>
</div>

<div>
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Description</label>
    <textarea name="description" rows="3"
              class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              placeholder="Short summary shown above the price table on the public page...">{{ old('description', $category->description ?? '') }}</textarea>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Processing Time</label>
        <input type="text" name="processing_time" value="{{ old('processing_time', $category->processing_time ?? '') }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="e.g. 7-10 working days (regular) / 1 day (express)">
    </div>
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Display Order</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 1) }}"
               class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
    </div>
</div>

<!-- Requirements & Process Notes -->
<div x-data="requirementRows(@js($existingRows))" class="space-y-3 pt-2 border-t border-gray-100">
    <div class="flex items-center justify-between pt-4">
        <div>
            <h3 class="font-heading text-sm font-bold text-dark">Requirements &amp; Process Notes</h3>
            <p class="text-[11px] text-dark/50">Checklist items appear as a numbered list; notes appear as process rules. Flagged entries stay hidden from the public page.</p>
        </div>
        <button type="button" @click="addRow()" class="px-3.5 py-2 bg-primary/10 text-primary font-bold text-[11px] rounded-full hover:bg-primary/20 transition-all flex items-center gap-1.5 shrink-0">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span>Add Entry</span>
        </button>
    </div>

    <template x-for="(row, index) in rows" :key="row.key">
        <div class="rounded-2xl bg-gray-50 border border-gray-200 p-3 space-y-2">
            <input type="hidden" :name="`requirements[${index}][id]`" :value="row.id ?? ''">

            <div class="flex items-start gap-2">
                <textarea :name="`requirements[${index}][label]`" x-model="row.label" rows="2"
                          class="flex-1 px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                          placeholder="e.g. Photocopy of passport biopage"></textarea>
                <button type="button" @click="removeRow(index)" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg shrink-0" title="Remove entry">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <select :name="`requirements[${index}][type]`" x-model="row.type"
                        class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-dark text-[11px] font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="requirement">Checklist requirement</option>
                    <option value="note">Process note</option>
                </select>

                <label class="flex items-center gap-1.5 text-[11px] font-bold text-dark/70 cursor-pointer">
                    <input type="hidden" :name="`requirements[${index}][needs_review]`" :value="row.needs_review ? 1 : 0">
                    <input type="checkbox" x-model="row.needs_review" class="w-3.5 h-3.5 rounded text-amber-500">
                    <span>Needs review (hidden from public page)</span>
                </label>
            </div>
        </div>
    </template>

    <p x-show="rows.length === 0" class="text-[11px] text-dark/40 italic py-3">No requirements or notes yet. Click "Add Entry" to add one.</p>
</div>

<div class="flex items-center gap-2 pt-2">
    <input type="checkbox" name="is_active" value="1" id="is_active" class="w-4 h-4 rounded text-primary"
           {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}>
    <label for="is_active" class="text-xs font-bold text-dark cursor-pointer">Show this category on the public price list</label>
</div>

<script>
function requirementRows(initial) {
    return {
        rows: (initial || []).map((row, index) => ({
            key: 'existing-' + index,
            id: row.id ?? null,
            label: row.label ?? '',
            type: row.type ?? 'requirement',
            needs_review: !!Number(row.needs_review ?? 0),
        })),
        addRow() {
            this.rows.push({
                key: 'new-' + Date.now() + '-' + this.rows.length,
                id: null,
                label: '',
                type: 'requirement',
                needs_review: false,
            });
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },
        removeRow(index) {
            this.rows.splice(index, 1);
        },
    };
}
</script>
