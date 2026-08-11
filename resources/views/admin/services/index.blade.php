@extends('layouts.admin')

@section('title', 'Services Management - AMEGA Admin')
@section('page_title', 'Services Management')

@section('content')
<div class="space-y-6">
    
    <!-- Action Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Active & Managed Core Services</h2>
            <p class="text-xs text-dark/50">Enable, disable, edit, or add services displayed on public pages</p>
        </div>
        <a href="{{ route('admin.services.create') }}" class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Add New Service</span>
        </a>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($services as $service)
            <div class="service-card bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col justify-between space-y-4 relative transition-opacity {{ !$service->is_active ? 'opacity-75 bg-gray-50/50' : '' }}">
                <div class="space-y-3">
                    @if($service->badge)
                        <div class="flex items-center justify-end">
                            <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold">{{ $service->badge }}</span>
                        </div>
                    @endif

                    <div class="flex items-center gap-3 pt-1">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
                            <i data-lucide="{{ $service->icon ?? 'globe' }}" class="w-5 h-5"></i>
                        </div>
                        <h3 class="font-heading font-bold text-base text-dark">{{ $service->title }}</h3>
                    </div>

                    <p class="text-xs text-dark/60 leading-relaxed line-clamp-3">
                        {{ $service->short_description }}
                    </p>
                </div>

                <!-- Footer Actions Bar -->
                <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="text-[10px] text-dark/40 font-mono">Order: #{{ $service->order }}</div>

                    <div class="flex items-center gap-1.5">
                        <!-- Status Toggle Button -->
                        <button type="button" 
                                onclick="toggleServiceStatus(this, '{{ route('admin.services.toggle-status', $service) }}')"
                                class="status-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer {{ $service->is_active ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-rose-100 text-rose-800 border-rose-300' }}" title="Click to Toggle Status">
                            {{ $service->is_active ? '● Enabled' : '○ Disabled' }}
                        </button>

                        <!-- Edit Icon Button -->
                        <a href="{{ route('admin.services.edit', $service) }}" class="p-1.5 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Edit Service">
                            <i data-lucide="edit" class="w-4 h-4"></i>
                        </a>

                        <!-- Delete Icon Button -->
                        <form method="POST" action="{{ route('admin.services.destroy', $service) }}" onsubmit="return confirm('Delete this service permanently?');" class="inline-flex m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Service">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-gray-100">
                <i data-lucide="briefcase" class="w-10 h-10 text-dark/30 mx-auto mb-2"></i>
                <h3 class="font-heading text-lg font-bold text-dark">No services found</h3>
                <p class="text-xs text-dark/50 mt-1">Click "Add New Service" to create your first service offer.</p>
            </div>
        @endforelse
    </div>
</div>

<script>
function toggleServiceStatus(btn, url) {
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
            const card = btn.closest('.service-card');
            if (data.is_active) {
                btn.className = 'status-toggle-btn px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer bg-emerald-100 text-emerald-800 border-emerald-300';
                btn.innerText = '● Enabled';
                if (card) {
                    card.classList.remove('opacity-75', 'bg-gray-50/50');
                }
            } else {
                btn.className = 'status-toggle-btn px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer bg-rose-100 text-rose-800 border-rose-300';
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
        console.error('Error toggling service status:', err);
    });
}
</script>
@endsection
