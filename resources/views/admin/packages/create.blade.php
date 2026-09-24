@extends('layouts.admin')

@section('title', 'Add Travel Package - AMEGA Admin')
@section('page_title', 'Create Travel Package')

@section('content')
<div class="max-w-3xl bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Create Travel Package</h2>
            <p class="text-xs text-dark/50">Add a new package offer with pricing, inclusions, exclusions, and itineraries</p>
        </div>
        <a href="{{ route('admin.packages.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Back to List</a>
    </div>

    <form method="POST" action="{{ route('admin.packages.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Title -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Title</label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="e.g. Japan Hokkaido Snow Festival">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Destination</label>
                <select name="destination_id" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">None / Standalone</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->id }}">{{ $dest->name }} ({{ $dest->location }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Category</label>
                <select name="category" required class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="short_haul">Short Haul (Asia)</option>
                    <option value="long_haul">Long Haul (Europe/USA)</option>
                    <option value="domestic">Domestic Island</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Status</label>
                <select name="status" required class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                    <option value="sold_out">Sold Out</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Duration</label>
                <input type="text" name="duration" value="{{ old('duration') }}" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                       placeholder="e.g. 6 Days / 5 Nights">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Starting Price (per person)</label>
                <div class="flex gap-2">
                    <select name="price_currency"
                            class="w-28 px-3 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="PHP" {{ old('price_currency', 'PHP') === 'PHP' ? 'selected' : '' }}>₱ PHP</option>
                        <option value="USD" {{ old('price_currency', 'PHP') === 'USD' ? 'selected' : '' }}>$ USD</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="price_amount" value="{{ old('price_amount') }}" required
                           class="flex-1 px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="15000">
                </div>
                <p class="text-[11px] text-dark/45 mt-1.5">Per person. Bookings are billed at this rate times the party size.</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Rating (1-5)</label>
                <input type="number" name="rating" value="5" min="1" max="5" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>

        <div x-data="{
                 preview: null,
                 pick(event) {
                     const file = event.target.files[0];
                     if (file) {
                         this.preview = URL.createObjectURL(file);
                     }
                 }
             }">
            <label for="image_file" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Photo</label>

            <div class="flex flex-col sm:flex-row gap-4">
                <div class="w-full sm:w-48 h-32 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center shrink-0">
                    <template x-if="preview">
                        <img :src="preview" alt="Selected package photo preview" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!preview">
                        <span class="text-[11px] font-semibold text-dark/40 text-center px-3">Photo preview appears here</span>
                    </template>
                </div>

                <div class="flex-1 space-y-2">
                    <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp"
                           @change="pick($event)"
                           class="w-full text-xs text-dark/70 bg-gray-50 border border-gray-200 rounded-xl p-2 cursor-pointer file:mr-3 file:px-4 file:py-2.5 file:rounded-full file:border-0 file:bg-primary file:text-white file:font-bold file:text-xs file:cursor-pointer">

                    <p class="text-[11px] text-dark/45">JPG, PNG or WebP up to 5 MB. Landscape photos around 1600&times;900 look best on package cards.</p>

                    <details class="pt-1">
                        <summary class="text-[11px] font-bold text-dark/50 hover:text-dark cursor-pointer">Or paste an existing image path instead</summary>
                        <input type="text" name="image" value="{{ old('image') }}"
                               class="mt-2 w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                               placeholder="e.g. newassets/2026-2027 SHORT HAUL/JPG/2026 AMEGA JAPAN HOKKAIDO SNOW FESTIVAL NEW.jpg">
                    </details>

                    @error('image_file')
                        <p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                    @error('image')
                        <p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Available Tour Dates</label>
            <input type="text" name="available_dates" value="{{ old('available_dates') }}"
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="e.g. Dec 15 - Dec 20, Jan 10 - Jan 15">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Short Overview</label>
            <textarea name="description" rows="3" required
                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                      placeholder="Brief promo description displayed on package card...">{{ old('description') }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Inclusions</label>
                <textarea name="inclusions" rows="4"
                          class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                          placeholder="• Roundtrip Flights&#10;• Hotel stay & daily breakfast&#10;• Guided tours">{{ old('inclusions') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Exclusions</label>
                <textarea name="exclusions" rows="4"
                          class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                          placeholder="• Personal expenses&#10;• Philippine Travel Tax&#10;• Optional tours">{{ old('exclusions') }}</textarea>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Day-by-Day Itinerary</label>
            <textarea name="itinerary" rows="5"
                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                      placeholder="Day 1: Arrival & Hotel Check-in&#10;Day 2: City Sightseeing Tour&#10;Day 3: Departure">{{ old('itinerary') }}</textarea>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" checked id="is_featured" class="w-4 h-4 rounded text-primary">
            <label for="is_featured" class="text-xs font-bold text-dark cursor-pointer">Show as Featured Tour Package on Public Pages</label>
        </div>

        <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-100">
            <a href="{{ route('admin.packages.index') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">Cancel</a>
            <button type="submit" class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md">
                Save Travel Package
            </button>
        </div>
    </form>
</div>
@endsection
