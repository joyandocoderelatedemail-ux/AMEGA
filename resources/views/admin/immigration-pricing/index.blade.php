@extends('layouts.immigration')

@section('title', 'Immigration Pricing - AMEGA Admin')
@section('page_title', 'Immigration Pricing Rows')

@section('content')
<div class="space-y-6">

    <!-- Action Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Bureau of Immigration Price Rows</h2>
            <p class="text-xs text-dark/50">Every price, condition, and processing time is editable here - no code deploy needed when BI adjusts a fee</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.immigration-categories.index') }}" class="px-5 py-2.5 bg-navy text-white font-bold text-xs rounded-full hover:bg-primary transition-all shadow-md flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4"></i>
                <span>Categories</span>
            </a>
            <a href="{{ route('admin.immigration-pricing.create') }}" class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add Price Row</span>
            </a>
        </div>
    </div>

    @if ($needsReviewCount > 0)
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
            <div class="flex-1">
                <p class="text-xs font-bold text-amber-900">{{ $needsReviewCount }} price {{ Str::plural('row', $needsReviewCount) }} still {{ $needsReviewCount === 1 ? 'needs' : 'need' }} staff confirmation</p>
                <p class="text-[11px] text-amber-800/80 mt-0.5">These figures were transcribed from illegible or ambiguous parts of the source sheet. They are hidden from the public price list until confirmed.</p>
            </div>
            <a href="{{ route('admin.immigration-pricing.index', ['needs_review' => 1]) }}" class="px-3.5 py-2 bg-amber-500 text-white text-[11px] font-bold rounded-full hover:bg-amber-600 transition-all shrink-0">
                Review Now
            </a>
        </div>
    @endif

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.immigration-pricing.index') }}" class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex flex-col sm:flex-row gap-3">
        <select name="category" class="flex-1 px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>

        <select name="payment_method" class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="">All Payment Methods</option>
            <option value="cash" {{ request('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
            <option value="card" {{ request('payment_method') === 'card' ? 'selected' : '' }}>Card</option>
        </select>

        <label class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-xs font-semibold text-dark cursor-pointer">
            <input type="checkbox" name="needs_review" value="1" {{ request()->boolean('needs_review') ? 'checked' : '' }} class="w-3.5 h-3.5 rounded text-amber-500">
            <span>Needs review only</span>
        </label>

        <button type="submit" class="px-5 py-2.5 bg-navy text-white text-xs font-bold rounded-xl hover:bg-primary transition-all">
            Filter
        </button>

        <a href="{{ route('admin.immigration-pricing.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark text-xs font-bold rounded-xl hover:bg-gray-200 transition-all text-center">
            Reset
        </a>
    </form>

    <!-- Table Section -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Category</th>
                        <th class="pb-3 px-3">Tier</th>
                        <th class="pb-3 px-3">Condition</th>
                        <th class="pb-3 px-3">Process</th>
                        <th class="pb-3 px-3 text-right">Price</th>
                        <th class="pb-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($tiers as $tier)
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $tier->needs_review ? 'bg-amber-50/40' : '' }}">
                            <td class="py-4 px-3">
                                <div class="font-bold text-dark">{{ $tier->category->name ?? 'Uncategorised' }}</div>
                                <div class="text-[11px] text-dark/40 font-mono">#{{ $tier->sort_order }}</div>
                            </td>
                            <td class="py-4 px-3">
                                <div class="font-bold text-dark whitespace-nowrap">{{ $tier->extension_label ?? '—' }}</div>
                                <div class="text-[11px] text-dark/50 whitespace-nowrap">{{ $tier->duration_label ?? '—' }}</div>
                            </td>
                            <td class="py-4 px-3 text-dark/70 max-w-xs">
                                {{ $tier->condition_notes ?: '—' }}
                                @if ($tier->needs_review)
                                    <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">
                                        <i data-lucide="alert-triangle" class="w-3 h-3"></i> Needs review
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $tier->process_type === 'express' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800' }}">
                                    {{ $tier->process_type }}
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $tier->payment_method === 'card' ? 'bg-violet-100 text-violet-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $tier->payment_method }}
                                </span>
                                <div class="text-[11px] text-dark/50 mt-1 whitespace-nowrap">{{ $tier->processing_time }}</div>
                            </td>
                            <td class="py-4 px-3 text-right font-heading font-black text-dark whitespace-nowrap">
                                ₱{{ number_format((float) $tier->price, 2) }}
                            </td>
                            <td class="py-4 px-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($tier->needs_review)
                                        <form method="POST" action="{{ route('admin.immigration-pricing.confirm-review', $tier) }}" onsubmit="return confirm('Confirm this figure matches the source sheet and publish it to the public price list?');" class="inline-flex m-0">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500 text-white hover:bg-amber-600 transition-all" title="Confirm figure and publish">
                                                Confirm
                                            </button>
                                        </form>
                                    @else
                                        <button type="button"
                                                onclick="togglePricingStatus(this, '{{ route('admin.immigration-pricing.toggle-status', $tier) }}')"
                                                class="status-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer {{ $tier->is_active ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-rose-100 text-rose-800 border-rose-300' }}" title="Click to Toggle Status">
                                            {{ $tier->is_active ? '● Enabled' : '○ Disabled' }}
                                        </button>
                                    @endif

                                    <a href="{{ route('admin.immigration-pricing.edit', $tier) }}" class="p-1.5 text-primary hover:bg-primary/5 rounded-lg flex items-center justify-center transition-colors" title="Edit Price Row">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </a>

                                    <form method="POST" action="{{ route('admin.immigration-pricing.destroy', $tier) }}" onsubmit="return confirm('Delete this price row permanently?');" class="inline-flex m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Price Row">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <i data-lucide="table" class="w-10 h-10 text-dark/30 mx-auto mb-2"></i>
                                <h3 class="font-heading text-lg font-bold text-dark">No price rows found</h3>
                                <p class="text-xs text-dark/50 mt-1">Adjust the filters above, or click "Add Price Row" to create one.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function togglePricingStatus(btn, url) {
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
            if (data.is_active) {
                btn.className = 'status-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer bg-emerald-100 text-emerald-800 border-emerald-300';
                btn.innerText = '● Enabled';
            } else {
                btn.className = 'status-toggle-btn px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider transition-all border cursor-pointer bg-rose-100 text-rose-800 border-rose-300';
                btn.innerText = '○ Disabled';
            }
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        console.error('Error toggling price row status:', err);
    });
}
</script>
@endsection
