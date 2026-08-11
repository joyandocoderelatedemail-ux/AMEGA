@extends('layouts.admin')

@section('title', 'Edit Travel Package - AMEGA Admin')
@section('page_title', 'Edit Travel Package')

@section('content')
<div class="max-w-3xl bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Edit Package: {{ $package->title }}</h2>
            <p class="text-xs text-dark/50">Update pricing, itinerary, inclusions, and package settings</p>
        </div>
        <a href="{{ route('admin.packages.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Back to List</a>
    </div>

    <form method="POST" action="{{ route('admin.packages.update', $package) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Title -->
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Title</label>
            <input type="text" name="title" value="{{ old('title', $package->title) }}" required
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Destination</label>
                <select name="destination_id" class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">None / Standalone</option>
                    @foreach ($destinations as $dest)
                        <option value="{{ $dest->id }}" {{ $package->destination_id == $dest->id ? 'selected' : '' }}>{{ $dest->name }} ({{ $dest->location }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Category</label>
                <select name="category" required class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="short_haul" {{ $package->category === 'short_haul' ? 'selected' : '' }}>Short Haul (Asia)</option>
                    <option value="long_haul" {{ $package->category === 'long_haul' ? 'selected' : '' }}>Long Haul (Europe/USA)</option>
                    <option value="domestic" {{ $package->category === 'domestic' ? 'selected' : '' }}>Domestic Island</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Status</label>
                <select name="status" required class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="active" {{ $package->status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="draft" {{ $package->status === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sold_out" {{ $package->status === 'sold_out' ? 'selected' : '' }}>Sold Out</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Duration</label>
                <input type="text" name="duration" value="{{ old('duration', $package->duration) }}" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Starting Price</label>
                <input type="text" name="price" value="{{ old('price', $package->price) }}" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Rating (1-5)</label>
                <input type="number" name="rating" value="{{ old('rating', $package->rating) }}" min="1" max="5" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Image Path / URL</label>
            <input type="text" name="image" value="{{ old('image', $package->image) }}" required
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Available Tour Dates</label>
            <input type="text" name="available_dates" value="{{ old('available_dates', $package->available_dates) }}"
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Short Overview</label>
            <textarea name="description" rows="3" required
                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">{{ old('description', $package->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Inclusions</label>
                <textarea name="inclusions" rows="4"
                          class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('inclusions', $package->inclusions) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Exclusions</label>
                <textarea name="exclusions" rows="4"
                          class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('exclusions', $package->exclusions) }}</textarea>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Day-by-Day Itinerary</label>
            <textarea name="itinerary" rows="5"
                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('itinerary', $package->itinerary) }}</textarea>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" {{ $package->is_featured ? 'checked' : '' }} id="is_featured" class="w-4 h-4 rounded text-primary">
            <label for="is_featured" class="text-xs font-bold text-dark cursor-pointer">Show as Featured Tour Package on Public Pages</label>
        </div>

        <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-100">
            <a href="{{ route('admin.packages.index') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">Cancel</a>
            <button type="submit" class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md">
                Update Package
            </button>
        </div>
    </form>
</div>
@endsection
