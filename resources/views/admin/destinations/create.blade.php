@extends('layouts.admin')

@section('title', 'Add New Destination - AMEGA Admin')
@section('page_title', 'Add Destination')

@section('content')
<div class="max-w-2xl bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Add New Destination</h2>
            <p class="text-xs text-dark/50">Create a new local or international destination card</p>
        </div>
        <a href="{{ route('admin.destinations.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Back to List</a>
    </div>

    <form method="POST" action="{{ route('admin.destinations.store') }}" class="space-y-5">
        @csrf

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Destination Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="e.g. Boracay Island">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Location / Country</label>
            <input type="text" name="location" value="{{ old('location') }}" required
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="e.g. Aklan, Philippines or Japan">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Type</label>
                <select name="type" required class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="domestic">Domestic (Local)</option>
                    <option value="international">International</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Starting Price</label>
                <input type="text" name="starting_price" value="{{ old('starting_price') }}" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                       placeholder="e.g. ₱12,000 or $2,499">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Image Path / URL</label>
            <input type="text" name="image" value="{{ old('image') }}" required
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="e.g. newassets/2026-2027 DOMESTIC/2026 AMEGA BORACAY  NEW.jpg">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Description</label>
            <textarea name="description" rows="4" required
                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                      placeholder="Enter destination highlights and package description...">{{ old('description') }}</textarea>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" checked id="is_featured" class="w-4 h-4 rounded text-primary">
            <label for="is_featured" class="text-xs font-bold text-dark cursor-pointer">Show as Featured on Homepage</label>
        </div>

        <div class="pt-4 flex items-center justify-end gap-3">
            <a href="{{ route('admin.destinations.index') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">Cancel</a>
            <button type="submit" class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md">
                Save Destination
            </button>
        </div>
    </form>
</div>
@endsection
