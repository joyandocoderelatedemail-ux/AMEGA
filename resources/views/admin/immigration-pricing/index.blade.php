@extends('layouts.immigration')

@section('title', 'Extension Rates - AMEGA Admin')
@section('page_title', 'Extension Rates')

@section('content')
<div class="space-y-6">

    <!-- Action Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-heading text-xl font-bold text-dark">Bureau of Immigration Rates</h2>
            <p class="text-xs text-dark/50">The Visa Extension ledger on each client sheet prices a new row from these rates. Edit them here when the BI changes a fee.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('prices.manage')
            <a href="{{ route('admin.immigration-pricing.create') }}" class="px-5 py-2.5 bg-accent text-dark font-bold text-xs rounded-full hover:bg-accent-dark transition-all shadow-md flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add Price Row</span>
            </a>
            @endcan
        </div>
    </div>

    @if ($needsReviewCount > 0)
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
            <div class="flex-1">
                <p class="text-xs font-bold text-amber-900">{{ $needsReviewCount }} price {{ Str::plural('row', $needsReviewCount) }} still {{ $needsReviewCount === 1 ? 'needs' : 'need' }} staff confirmation</p>
                <p class="text-[11px] text-amber-800/80 mt-0.5">These figures were transcribed from illegible or ambiguous parts of the source sheet. They are not used to price the ledger until confirmed.</p>
            </div>
            <a href="{{ route('admin.immigration-pricing.index', ['needs_review' => 1]) }}" class="px-3.5 py-2 bg-amber-500 text-white text-[11px] font-bold rounded-full hover:bg-amber-600 transition-all shrink-0">
                Review Now
            </a>
        </div>
    @endif

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.immigration-pricing.index') }}" class="bg-white rounded-2xl p-4 border border-gray-200 shadow-card flex flex-col sm:flex-row gap-3">
        @if (request()->filled('category'))
            <input type="hidden" name="category" value="{{ request('category') }}">
        @endif
        <p class="flex-1 self-center text-xs text-dark/50">Rows are grouped by category below. Narrow them by payment method, or show only the ones to confirm.</p>

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

    @php
        // One tab per category (only those with rows after the filters), each grouped by tier: 1st, 2nd, 3rd extension...
        // A row whose category is missing still shows, under Uncategorised.
        $byCategory = $tiers->groupBy(fn ($tier) => $tier->category ? $tier->immigration_category_id : 0);
        $tabs = $categories->filter(fn ($category) => $byCategory->has($category->id))->values();
        if ($byCategory->has(0)) {
            $tabs->push((object) ['id' => 0, 'name' => 'Uncategorised']);
        }
        $activeTab = $tabs->firstWhere('id', (int) request('category'))?->id ?? $tabs->first()?->id;
    @endphp

    <!-- Categories -->
    <div x-data="{ tab: {{ Js::from($activeTab) }} }" class="space-y-4">
        @if ($tabs->isEmpty())
            <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card py-16 text-center">
                <i data-lucide="table" class="w-10 h-10 text-dark/30 mx-auto mb-2"></i>
                <h3 class="font-heading text-lg font-bold text-dark">No price rows found</h3>
                <p class="text-xs text-dark/50 mt-1">Adjust the filters above, or click "Add Price Row" to create one.</p>
            </div>
        @else
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Pricing categories">
                @foreach ($tabs as $category)
                    <button type="button" role="tab" @click="tab = {{ $category->id }}" :aria-selected="tab === {{ $category->id }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-xs font-bold transition-colors"
                            :class="tab === {{ $category->id }} ? 'bg-primary text-white border-primary' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                        <span>{{ $category->name }}</span>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-extrabold"
                              :class="tab === {{ $category->id }} ? 'bg-white/20 text-white' : 'bg-gray-100 text-dark/60'">{{ $byCategory->get($category->id)->count() }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($tabs as $category)
                @php $groups = $byCategory->get($category->id)->groupBy(fn ($tier) => $tier->extension_label ?: 'Other rates'); @endphp
                @php $groupKeys = $groups->keys()->values(); @endphp
                <div x-show="tab === {{ $category->id }}" @if ($category->id !== $activeTab) style="display: none" @endif role="tabpanel" class="space-y-4"
                     x-data="{ group: {{ Js::from($groupKeys->first()) }}, open: false }">

                    @if ($groupKeys->count() > 1)
                        {{-- One tier at a time: pick 1st Extension, 2nd Extension... from the dropdown. --}}
                        <div class="relative inline-block" @click.outside="open = false" @keydown.escape.window="open = false">
                            <button type="button" @click="open = !open" aria-haspopup="listbox" :aria-expanded="open"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-gray-200 text-xs font-bold text-dark hover:border-primary transition-colors shadow-sm">
                                <i data-lucide="list-filter" class="w-4 h-4 text-primary"></i>
                                <span x-text="group"></span>
                                <span class="inline-flex transition-transform" :class="open && 'rotate-180'"><i data-lucide="chevron-down" class="w-4 h-4 text-dark/50"></i></span>
                            </button>
                            <ul x-show="open" x-cloak role="listbox"
                                class="absolute z-20 left-0 mt-1.5 w-56 bg-white rounded-xl border border-gray-200 shadow-lg p-1.5">
                                @foreach ($groups as $groupLabel => $rows)
                                    <li>
                                        <button type="button" role="option" :aria-selected="group === {{ Js::from($groupLabel) }}"
                                                @click="group = {{ Js::from($groupLabel) }}; open = false"
                                                class="w-full flex items-center justify-between gap-4 px-3 py-2 rounded-lg text-left text-xs font-semibold transition-colors"
                                                :class="group === {{ Js::from($groupLabel) }} ? 'bg-primary/10 text-primary' : 'text-dark hover:bg-gray-50'">
                                            <span>{{ $groupLabel }}</span>
                                            <span class="px-1.5 py-0.5 rounded-md bg-gray-100 text-dark/60 text-[10px] font-extrabold">{{ $rows->count() }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @foreach ($groups as $groupLabel => $rows)
                        <section x-show="group === {{ Js::from($groupLabel) }}" @unless ($loop->first) style="display: none" @endunless
                                 class="bg-white rounded-3xl border border-gray-200 shadow-card overflow-hidden">
                            <div class="px-6 py-3.5 bg-gray-50 border-b border-gray-100 flex flex-wrap items-baseline justify-between gap-2">
                                <h3 class="font-heading text-sm font-bold text-dark">{{ $groupLabel }}</h3>
                                <p class="text-[11px] text-dark/50">{{ $rows->count() }} {{ Str::plural('rate', $rows->count()) }}</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-100 text-[10px] text-dark/50 font-extrabold uppercase tracking-wider">
                                            <th class="py-2.5 px-6">Duration</th>
                                            <th class="py-2.5 px-3">Condition</th>
                                            <th class="py-2.5 px-3">Process</th>
                                            <th class="py-2.5 px-3 text-right">Price</th>
                                            <th class="py-2.5 px-6 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @foreach ($rows as $tier)
                                            <tr class="hover:bg-gray-50/50 transition-colors {{ $tier->needs_review ? 'bg-amber-50/40' : '' }}">
                                                <td class="py-3.5 px-6">
                                                    <div class="font-bold text-dark whitespace-nowrap">{{ $tier->duration_label ?? '—' }}</div>
                                                    <div class="text-[11px] text-dark/40 font-mono">#{{ $tier->sort_order }}</div>
                                                </td>
                                                <td class="py-3.5 px-3 text-dark/70 max-w-xs">
                                                    {{ $tier->condition_notes ?: '—' }}
                                                    @if ($tier->needs_review)
                                                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">
                                                            <i data-lucide="alert-triangle" class="w-3 h-3"></i> Needs review
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="py-3.5 px-3">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $tier->process_type === 'express' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800' }}">
                                                        {{ $tier->process_type }}
                                                    </span>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $tier->payment_method === 'card' ? 'bg-violet-100 text-violet-800' : 'bg-emerald-100 text-emerald-800' }}">
                                                        {{ $tier->payment_method }}
                                                    </span>
                                                    <div class="text-[11px] text-dark/50 mt-1 whitespace-nowrap">{{ $tier->processing_time }}</div>
                                                </td>
                                                <td class="py-3.5 px-3 text-right font-heading font-black text-dark whitespace-nowrap">
                                                    ₱{{ number_format((float) $tier->price, 2) }}
                                                </td>
                                                <td class="py-3.5 px-6">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                    @can('prices.manage')
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
                                                    @endcan

                                                        @if (auth()->user()?->isAdmin())
                                                        <form method="POST" action="{{ route('admin.immigration-pricing.destroy', $tier) }}" onsubmit="return confirm('Delete this price row permanently?');" class="inline-flex m-0">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg flex items-center justify-center transition-colors" title="Delete Price Row">
                                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                            </button>
                                                        </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endforeach
                </div>
            @endforeach
        @endif
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
