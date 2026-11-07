@extends('layouts.admin')

@section('title', 'Add Service - AMEGA Admin')
@section('page_title', 'Create Service')

@section('content')
<div class="max-w-2xl bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Create New Service</h2>
            <p class="text-xs text-dark/50">Add a new agency service offered to clients</p>
        </div>
        <a href="{{ route('admin.services.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Back to List</a>
    </div>

    <form method="POST" action="{{ route('admin.services.store') }}" class="space-y-5" enctype="multipart/form-data">
        @csrf

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Service Title</label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="e.g. Visa Assistance & Processing">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Lucide Icon Name</label>
                <input type="text" name="icon" value="{{ old('icon', 'globe') }}" required
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                       placeholder="e.g. plane, file-text, globe, shield">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Badge Label</label>
                <input type="text" name="badge" value="{{ old('badge') }}"
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                       placeholder="e.g. Most Popular, 99.8% Approval">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Inquiry Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                       placeholder="e.g. visas@amegatravelandtour.com">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Partner Logo Strip</label>
                @if (!empty($service?->logo_strip))
                    <img src="{{ asset($service->logo_strip) }}" alt="" class="mb-2 h-10 w-auto max-w-full rounded border border-gray-200 bg-white p-1">
                @endif
                <input type="file" name="logo_strip_file" accept="image/png,image/jpeg,image/webp"
                       class="w-full text-xs text-dark/70 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold">
                <details class="mt-1">
                    <summary class="text-[11px] font-bold text-dark/50 cursor-pointer">Or type an image path</summary>
                    <input type="text" name="logo_strip" value="{{ old('logo_strip', $service->logo_strip ?? '') }}"
                           class="mt-1 w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="e.g. images/services/cruise-lines.png">
                </details>
                @error('logo_strip_file')<p class="text-[11px] font-bold text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Short Summary Description</label>
            <textarea name="short_description" rows="3" required
                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                      placeholder="Brief overview displayed on service cards...">{{ old('short_description') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Bullet Points (one per line)</label>
            <textarea name="full_description" rows="5"
                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                      placeholder="Comprehensive service details, process steps, and requirements...">{{ old('full_description') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Display Order</label>
            <input type="number" name="order" value="{{ old('order', 1) }}"
                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" checked id="is_active" class="w-4 h-4 rounded text-primary">
            <label for="is_active" class="text-xs font-bold text-dark cursor-pointer">Enable & Publish Service Immediately</label>
        </div>

        <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-100">
            <a href="{{ route('admin.services.index') }}" class="px-6 py-3 rounded-full bg-gray-100 text-dark font-bold text-xs hover:bg-gray-200">Cancel</a>
            <button type="submit" class="px-6 py-3 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-dark shadow-md">
                Save Service
            </button>
        </div>
    </form>
</div>
@endsection
