@extends('layouts.admin')

@section('title', 'Travel Package Management - AMEGA Admin')
@section('page_title', 'Travel Packages')

@section('content')
<div class="space-y-6">
    
    <!-- Top Action Bar & Filter Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Travel Packages Directory</h2>
        </div>
        <a href="{{ route('admin.packages.create') }}" class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2 shrink-0">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Create New Package</span>
        </a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.packages.index') }}" class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-dark/40">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Search packages by title or keyword..." 
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <select name="category" class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="">All Categories</option>
            <option value="short_haul" {{ request('category') === 'short_haul' ? 'selected' : '' }}>Short Haul (Asia)</option>
            <option value="long_haul" {{ request('category') === 'long_haul' ? 'selected' : '' }}>Long Haul (Europe/USA)</option>
            <option value="domestic" {{ request('category') === 'domestic' ? 'selected' : '' }}>Domestic</option>
        </select>

        <select name="status" class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="sold_out" {{ request('status') === 'sold_out' ? 'selected' : '' }}>Sold Out</option>
        </select>

        <button type="submit" class="px-5 py-2.5 bg-navy text-white text-xs font-bold rounded-xl hover:bg-primary transition-all">
            Filter
        </button>
    </form>

    <!-- Table Section -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Package</th>
                        <th class="pb-3 px-3">Category</th>
                        <th class="pb-3 px-3">Duration</th>
                        <th class="pb-3 px-3">Price</th>
                        <th class="pb-3 px-3">Status</th>
                        <th class="pb-3 px-3">Featured</th>
                        <th class="pb-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($packages as $pkg)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-3 font-bold text-dark flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl overflow-hidden border border-gray-100 shadow-sm shrink-0">
                                    <img src="{{ asset($pkg->image) }}" alt="{{ $pkg->title }}" loading="lazy" decoding="async" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <div class="font-bold text-dark text-sm">{{ $pkg->title }}</div>
                                </div>
                            </td>
                            <td class="py-4 px-3 uppercase text-[10px] font-extrabold text-dark/60">
                                {{ str_replace('_', ' ', $pkg->category) }}
                            </td>
                            <td class="py-4 px-3">
                                <span class="px-2.5 py-1 rounded-full bg-accent/20 text-dark font-bold text-[11px]">
                                    {{ $pkg->duration }}
                                </span>
                            </td>
                            <td class="py-4 px-3 font-heading font-bold text-sm text-primary">
                                {{ $pkg->price }}
                            </td>
                            <td class="py-4 px-3">
                                @if($pkg->status === 'active')
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold text-[10px] uppercase">Active</span>
                                @elseif($pkg->status === 'sold_out')
                                    <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold text-[10px] uppercase">Sold Out</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 font-bold text-[10px] uppercase">Draft</span>
                                @endif
                            </td>
                            <td class="py-4 px-3">
                                <button type="button" 
                                        onclick="togglePackageFeatured(this, '{{ route('admin.packages.toggle-featured', $pkg) }}')"
                                        class="featured-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold transition-all border cursor-pointer {{ $pkg->is_featured ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-gray-100 text-gray-400 border-gray-200' }}">
                                    {{ $pkg->is_featured ? '★ Featured' : '☆ Normal' }}
                                </button>
                            </td>
                            <td class="py-4 px-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.packages.edit', $pkg) }}" class="p-2 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Edit Package">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.packages.destroy', $pkg) }}" onsubmit="return confirm('Delete this package?');" class="inline-flex m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Package">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-dark/40 font-medium">
                                No travel packages found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-4">
            {{ $packages->links() }}
        </div>
    </div>
</div>

<script>
function togglePackageFeatured(btn, url) {
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
            if (data.is_featured) {
                btn.className = 'featured-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold transition-all border cursor-pointer bg-amber-100 text-amber-800 border-amber-300';
                btn.innerText = '★ Featured';
            } else {
                btn.className = 'featured-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold transition-all border cursor-pointer bg-gray-100 text-gray-400 border-gray-200';
                btn.innerText = '☆ Normal';
            }
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        console.error('Error toggling featured status:', err);
    });
}
</script>
@endsection
