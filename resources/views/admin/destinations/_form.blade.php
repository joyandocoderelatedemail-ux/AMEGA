@php
    /** @var \App\Models\Destination|null $destination */
    $destination = $destination ?? null;
    $input = 'w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

@if ($errors->any())
    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 space-y-1">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<div>
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Destination Name</label>
    <input type="text" name="name" value="{{ old('name', $destination?->name) }}" required
           class="{{ $input }}"
           placeholder="e.g. Boracay Island">
</div>

<div>
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Location / Country</label>
    <input type="text" name="location" value="{{ old('location', $destination?->location) }}" required
           class="{{ $input }}"
           placeholder="e.g. Aklan, Philippines or Japan">
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Type</label>
        <select name="type" required class="{{ $input }}">
            <option value="domestic" @selected(old('type', $destination?->type) === 'domestic')>Domestic (Local)</option>
            <option value="international" @selected(old('type', $destination?->type) === 'international')>International</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Starting Price</label>
        @php
            $saved = trim((string) $destination?->starting_price);
            $savedCurrency = str_starts_with($saved, '$') ? 'USD' : 'PHP';
            $savedAmount = preg_match('/^[₱$]\s*([\d,]+(?:\.\d+)?)$/u', $saved, $m) ? str_replace(',', '', $m[1]) : '';
        @endphp
        <div class="flex gap-2">
            <select name="price_currency" aria-label="Currency" class="{{ $input }} !w-28 shrink-0">
                <option value="PHP" @selected(old('price_currency', $savedCurrency) === 'PHP')>₱ PHP</option>
                <option value="USD" @selected(old('price_currency', $savedCurrency) === 'USD')>$ USD</option>
            </select>
            <input type="number" name="price_amount" value="{{ old('price_amount', $savedAmount) }}" required min="0" step="any"
                   class="{{ $input }}" aria-label="Amount" placeholder="e.g. 12000">
        </div>
    </div>
</div>

<div x-data="{
         preview: @js($destination?->image ? \App\Support\WebImage::url($destination->image) : null),
         pick(event) {
             const file = event.target.files[0];
             if (file) {
                 this.preview = URL.createObjectURL(file);
             }
         }
     }">
    <label for="image_file" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Destination Photo</label>

    <div class="flex flex-col sm:flex-row gap-4">
        <div class="w-full sm:w-48 h-32 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center shrink-0">
            <template x-if="preview">
                <img :src="preview" alt="Destination photo preview" class="w-full h-full object-cover">
            </template>
            <template x-if="!preview">
                <span class="text-[11px] font-semibold text-dark/40 text-center px-3">Photo preview appears here</span>
            </template>
        </div>

        <div class="flex-1 space-y-2">
            <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp" @if (! $destination?->image) required @endif
                   @change="pick($event)"
                   class="w-full text-xs text-dark/70 bg-gray-50 border border-gray-200 rounded-xl p-2 cursor-pointer file:mr-3 file:px-4 file:py-2.5 file:rounded-full file:border-0 file:bg-primary file:text-white file:text-xs file:font-bold hover:file:bg-navy">
            <p class="text-[11px] text-dark/45">
                JPG, PNG or WebP up to 5 MB.
                @if ($destination?->image)
                    Choose a new photo to replace the current one, or leave this empty to keep it.
                @else
                    Landscape photos look best on the home page cards.
                @endif
            </p>
        </div>
    </div>
</div>

<div>
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Description</label>
    <textarea name="description" rows="4" required
              class="{{ $input }}"
              placeholder="Enter destination highlights and package description...">{{ old('description', $destination?->description) }}</textarea>
</div>

<div class="flex items-center gap-2">
    <input type="checkbox" name="is_featured" value="1" id="is_featured" class="w-4 h-4 rounded text-primary"
           @checked(old('is_featured', $destination?->is_featured ?? true))>
    <label for="is_featured" class="text-xs font-bold text-dark cursor-pointer">Show as Featured on Homepage</label>
</div>
