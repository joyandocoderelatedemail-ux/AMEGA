@extends('layouts.immigration')

@section('title', 'Immigration Categories - AMEGA Admin')
@section('page_title', 'Immigration Process Categories')

@section('content')
<div class="space-y-6">

    <!-- Action Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Immigration Process Categories</h2>
            <p class="text-xs text-dark/50">Manage the process groups, requirement checklists, and process notes shown on the public price list</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.immigration-pricing.index') }}" class="px-5 py-2.5 bg-navy text-white font-bold text-xs rounded-full hover:bg-primary transition-all shadow-md flex items-center gap-2">
                <i data-lucide="table" class="w-4 h-4"></i>
                <span>Price Rows</span>
            </a>
            <a href="{{ route('admin.immigration-categories.create') }}" class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add Category</span>
            </a>
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($categories as $category)
            <div class="category-card bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col justify-between space-y-4 relative transition-opacity {{ !$category->is_active ? 'opacity-75 bg-gray-50/50' : '' }}">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
                            <i data-lucide="{{ $category->icon ?? 'stamp' }}" class="w-5 h-5"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-heading font-bold text-base text-dark leading-tight">{{ $category->name }}</h3>
                            <p class="text-[10px] text-dark/40 font-mono truncate">{{ $category->slug }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-dark/60 leading-relaxed line-clamp-3">
                        {{ $category->description }}
                    </p>

                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold">
                            {{ $category->pricing_tiers_count }} price {{ Str::plural('row', $category->pricing_tiers_count) }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-gray-100 text-dark/60 text-[10px] font-bold">
                            {{ $category->requirements_count }} {{ Str::plural('entry', $category->requirements_count) }}
                        </span>
                        @if($category->processing_time)
                            <span class="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-bold">{{ $category->processing_time }}</span>
                        @endif
                    </div>
                </div>

                <!-- Footer Actions Bar -->
                <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="text-[10px] text-dark/40 font-mono">Order: #{{ $category->sort_order }}</div>

                    <div class="flex items-center gap-1.5">
                        <button type="button"
                                onclick="toggleCategoryStatus(this, '{{ route('admin.immigration-categories.toggle-status', $category) }}')"
                                class="status-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer {{ $category->is_active ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-rose-100 text-rose-800 border-rose-300' }}" title="Click to Toggle Status">
                            {{ $category->is_active ? '● Enabled' : '○ Disabled' }}
                        </button>

                        <a href="{{ route('admin.immigration-categories.edit', $category) }}" class="p-1.5 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Edit Category">
                            <i data-lucide="edit" class="w-4 h-4"></i>
                        </a>

                        <form method="POST" action="{{ route('admin.immigration-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category and every price row inside it?');" class="inline-flex m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Category">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-gray-100">
                <i data-lucide="stamp" class="w-10 h-10 text-dark/30 mx-auto mb-2"></i>
                <h3 class="font-heading text-lg font-bold text-dark">No immigration categories found</h3>
                <p class="text-xs text-dark/50 mt-1">Click "Add Category" to create your first immigration process group.</p>
            </div>
        @endforelse
    </div>
</div>

<script>
function toggleCategoryStatus(btn, url) {
    btn.disabled = true;
    btn.classList.add('opacity-50');

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        if (data.success) {
            const card = btn.closest('.category-card');
            if (data.is_active) {
                btn.className = 'status-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer bg-emerald-100 text-emerald-800 border-emerald-300';
                btn.innerText = '● Enabled';
                if (card) {
                    card.classList.remove('opacity-75', 'bg-gray-50/50');
                }
            } else {
                btn.className = 'status-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer bg-rose-100 text-rose-800 border-rose-300';
                btn.innerText = '○ Disabled';
                if (card) {
                    card.classList.add('opacity-75', 'bg-gray-50/50');
                }
            }
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        console.error('Error toggling category status:', err);
    });
}
</script>
@endsection
