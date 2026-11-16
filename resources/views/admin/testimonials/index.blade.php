@extends('layouts.admin')

@section('title', 'Testimonials Management - AMEGA Admin')
@section('page_title', 'Client Reviews')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">Client Testimonials</h2>
        <p class="text-sm text-slate-500 mt-0.5">The client reviews published on the public website.</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-4">Add a testimonial</h3>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.testimonials.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-xs font-semibold text-slate-700 mb-1">Client name</span>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="block text-xs font-semibold text-slate-700 mb-1">Location <span class="font-normal text-slate-400">(optional)</span></span>
                    <input type="text" name="location" value="{{ old('location') }}" maxlength="255" placeholder="e.g. Makati City" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
            </div>
            <div x-data="{ preview: null }" class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                    <img x-show="preview" :src="preview" alt="" class="w-full h-full object-cover" style="display: none;">
                    <i x-show="!preview" data-lucide="user" class="w-6 h-6 text-slate-400"></i>
                </div>
                <label class="block flex-1">
                    <span class="block text-xs font-semibold text-slate-700 mb-1">Client photo <span class="font-normal text-slate-400">(optional)</span></span>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                    <span class="block text-xs text-slate-400 mt-1">JPG, PNG or WebP, up to 5 MB. A square photo of the client's face works best.</span>
                </label>
            </div>
            <label class="block">
                <span class="block text-xs font-semibold text-slate-700 mb-1">Review <span class="font-normal text-slate-400">(optional)</span></span>
                <textarea name="comment" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('comment') }}</textarea>
            </label>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="block text-xs font-semibold text-slate-700 mb-1">Review date</span>
                    <input type="date" name="reviewed_on" value="{{ old('reviewed_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="w-44 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="block text-xs font-semibold text-slate-700 mb-1">Rating <span class="font-normal text-slate-400">(optional)</span></span>
                    <select name="rating" class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="" @selected(old('rating') === '')>No rating</option>
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" @selected((int) old('rating', 5) === $i)>{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}</option>
                        @endfor
                    </select>
                </label>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add testimonial
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse ($testimonials as $t)
            <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($t->avatar)
                            <img src="{{ \App\Support\WebImage::url($t->avatar, \App\Support\WebImage::LOGO) }}" alt="{{ $t->name }}" class="w-10 h-10 rounded-full object-cover shrink-0">
                        @else
                            <span class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 text-sm font-semibold flex items-center justify-center shrink-0">{{ mb_substr($t->name, 0, 1) }}</span>
                        @endif
                        <div class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900">{{ $t->name }}</span>
                            <span class="block text-xs text-slate-500">{{ collect([$t->location, $t->reviewed_on?->format('M j, Y')])->filter()->implode(' · ') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($t->rating)
                        <span class="text-xs font-semibold text-slate-600" title="{{ $t->rating }} out of 5">
                            {{ str_repeat('★', (int) $t->rating) }}<span class="text-slate-300">{{ str_repeat('★', 5 - (int) $t->rating) }}</span>
                        </span>
                        @endif
                        @if (auth()->user()?->isAdmin())
                        <form method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" onsubmit="return confirm('Delete this review?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-rose-500 hover:text-rose-700 p-1" title="Delete">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                @if (filled($t->comment))
                    <p class="text-sm text-slate-600 italic leading-relaxed">"{{ $t->comment }}"</p>
                @endif
            </div>
        @empty
            <p class="md:col-span-2 px-5 py-10 text-center text-sm text-slate-500 bg-white rounded-2xl border border-slate-200">No testimonials yet. Add the first one above.</p>
        @endforelse
    </div>
</div>
@endsection
