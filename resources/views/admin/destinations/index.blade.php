@extends('layouts.admin')

@section('title', 'Destinations Management - AMEGA Admin')
@section('page_title', 'Destinations Management')

@section('content')
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">All Destinations</h2>
            <p class="text-xs text-dark/50">Manage local island destinations & international spots</p>
        </div>
        <a href="{{ route('admin.destinations.create') }}" class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Add Destination</span>
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                    <th class="pb-3 px-3">Destination</th>
                    <th class="pb-3 px-3">Type</th>
                    <th class="pb-3 px-3">Starting Rate</th>
                    <th class="pb-3 px-3">Featured</th>
                    <th class="pb-3 px-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($destinations as $dest)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-3 font-bold text-dark flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl overflow-hidden border border-gray-100 shadow-sm shrink-0">
                                <img src="{{ asset($dest->image) }}" alt="{{ $dest->name }}" loading="lazy" decoding="async" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <div>{{ $dest->name }}</div>
                                <div class="text-[11px] text-dark/50 font-normal">{{ $dest->location }}</div>
                            </div>
                        </td>
                        <td class="py-4 px-3">
                            <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider {{ $dest->type === 'domestic' ? 'bg-accent/20 text-dark border border-accent/40' : 'bg-primary/10 text-primary border border-primary/20' }}">
                                {{ $dest->type }}
                            </span>
                        </td>
                        <td class="py-4 px-3 font-bold text-primary">
                            {{ $dest->starting_price }}
                        </td>
                        <td class="py-4 px-3">
                            <button type="button" 
                                    onclick="toggleDestinationFeatured(this, '{{ route('admin.destinations.toggle-featured', $dest) }}')"
                                    class="featured-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold transition-all border cursor-pointer {{ $dest->is_featured ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-gray-100 text-gray-400 border-gray-200' }}">
                                {{ $dest->is_featured ? '★ Featured' : '☆ Normal' }}
                            </button>
                        </td>
                        <td class="py-4 px-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.destinations.edit', $dest) }}" class="p-2 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Edit Destination">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.destinations.destroy', $dest) }}" onsubmit="return confirm('Delete this destination?');" class="inline-flex m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Destination">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-dark/40 font-medium">
                            No destinations found in database.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pt-4">
        {{ $destinations->links() }}
    </div>
</div>

<script>
function toggleDestinationFeatured(btn, url) {
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
        console.error('Error toggling featured destination:', err);
    });
}
</script>
@endsection
