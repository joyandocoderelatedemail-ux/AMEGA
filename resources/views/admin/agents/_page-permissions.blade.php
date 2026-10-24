{{--
    The pages an agent may open, grouped (Services, Clients, Staff / Users, Reports & Analytics,
    Contents). Each group folds open like a dropdown, shows how many of its pages are ticked, and
    has a box that ticks or clears the whole group. Expects $allowedPages (the ticked page keys).
--}}
@php
    $pageOptions = \App\Models\User::PAGE_PERMISSIONS;
@endphp

<div class="space-y-2.5 pt-2">
    @foreach (\App\Models\User::PAGE_GROUPS as $groupLabel => [$groupIcon, $groupPages])
        @php $groupPages = array_values(array_intersect($groupPages, array_keys($pageOptions))); @endphp
        <div class="rounded-xl bg-white border border-emerald-200/80 overflow-hidden"
             x-data="{
                 open: {{ count(array_intersect($groupPages, $allowedPages)) ? 'true' : 'false' }},
                 checked: @js(array_values(array_intersect($groupPages, $allowedPages))),
                 pages: @js($groupPages),
                 get all() { return this.checked.length === this.pages.length },
                 get some() { return this.checked.length > 0 && ! this.all },
                 toggleAll() { this.checked = this.all ? [] : [...this.pages] },
             }">
            <div class="flex items-center gap-3 px-4 py-3">
                <input type="checkbox" :checked="all" :indeterminate="some" @change="toggleAll()"
                       aria-label="Allow every page under {{ $groupLabel }}"
                       class="w-4 h-4 rounded text-emerald-600 border-gray-300 focus:ring-emerald-500">
                <button type="button" @click="open = ! open" :aria-expanded="open"
                        class="flex-1 flex items-center justify-between gap-3 text-left">
                    <span class="flex items-center gap-2 text-xs font-bold text-dark">
                        <i data-lucide="{{ $groupIcon }}" class="w-4 h-4 text-emerald-600"></i>
                        {{ $groupLabel }}
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                              :class="checked.length ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/50'"
                              x-text="checked.length + ' of ' + pages.length">{{ count(array_intersect($groupPages, $allowedPages)) }} of {{ count($groupPages) }}</span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 transition-transform" :class="open && 'rotate-180'"></i>
                    </span>
                </button>
            </div>

            <div x-show="open" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 px-4 pb-4 pt-1 border-t border-emerald-100">
                @foreach ($groupPages as $pageKey)
                    @php [$label, $desc, $icon] = $pageOptions[$pageKey]; @endphp
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-emerald-50/40 border border-emerald-100 hover:border-emerald-400 cursor-pointer transition-all">
                        <input type="checkbox" name="allowed_pages[]" value="{{ $pageKey }}" x-model="checked"
                               {{ in_array($pageKey, $allowedPages) ? 'checked' : '' }}
                               class="mt-0.5 w-4 h-4 rounded text-emerald-600 border-gray-300 focus:ring-emerald-500">
                        <div>
                            <div class="text-xs font-bold text-dark flex items-center gap-1.5">
                                <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                <span>{{ $label }}</span>
                            </div>
                            <p class="text-[10px] text-dark/50 mt-0.5">{{ $desc }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
