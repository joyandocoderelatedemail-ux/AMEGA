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
        <input type="text" name="starting_price" value="{{ old('starting_price', $destination?->starting_price) }}" required
               class="{{ $input }}"
               placeholder="e.g. ₱12,000 or $2,499">
    </div>
</div>

<div>
    <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Image Path / URL</label>
    <input type="text" name="image" value="{{ old('image', $destination?->image) }}" required
           class="{{ $input }}"
           placeholder="e.g. newassets/2026-2027 DOMESTIC/2026 AMEGA BORACAY  NEW.jpg">
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
