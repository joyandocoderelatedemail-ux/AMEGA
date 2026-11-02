{{--
    Every permission (App\Support\Permissions), grouped like the menu (Services, Clients, Staff / Users,
    Reports & Analytics, Contents). Each group folds open, shows how many are ticked, and has a
    box that ticks or clears the whole group; each page lists its actions as ticks. A module in
    Permissions::ALONGSIDE (Prices & Catalogs) is one more tick on another row's line (Ticketing), with
    its own box: the group's box and its count leave it out, so it is only ever granted by ticking it.
    Expects $selected (permission keys) and optionally $name (default "permissions[]") and $open.
--}}
@php
    $name = $name ?? 'permissions[]';
    $selected = array_values($selected ?? []);
    $alongside = \App\Support\Permissions::ALONGSIDE;
    $allModules = collect(\App\Support\Permissions::MODULES)
        ->map(fn (array $module, string $key) => ['key' => $key, 'label' => $module[0], 'icon' => $module[1], 'group' => $module[2], 'actions' => $module[3]]);
    // The ticks that sit on another row: row => [permission keys].
    $extras = $allModules->filter(fn ($m, $key) => isset($alongside[$key]))
        ->groupBy(fn ($m, $key) => $alongside[$key])
        ->map(fn ($rows) => $rows->flatMap(fn ($m) => collect($m['actions'])->map(fn ($a) => $m['key'].'.'.$a))->all());
    $groups = $allModules->reject(fn ($m, $key) => isset($alongside[$key]))->groupBy('group');
    $actionLabels = \App\Support\Permissions::ACTIONS;
@endphp

<div class="space-y-2.5">
    @foreach ($groups as $groupLabel => $modules)
        @php
            $groupKeys = $modules->flatMap(fn ($m) => collect($m['actions'])->map(fn ($a) => $m['key'].'.'.$a))->values()->all();
        @endphp
        <div class="rounded-xl bg-white border border-slate-200 overflow-hidden"
             x-data="{
                 open: {{ ($open ?? false) || count(array_intersect([...$groupKeys, ...$extras->flatten()->all()], $selected)) ? 'true' : 'false' }},
                 checked: @js(array_values(array_intersect($groupKeys, $selected))),
                 extra: @js(array_values(array_intersect($extras->flatten()->all(), $selected))),
                 keys: @js($groupKeys),
                 get all() { return this.checked.length === this.keys.length },
                 get some() { return this.checked.length > 0 && ! this.all },
                 toggleAll() { this.checked = this.all ? [] : [...this.keys] },
             }">
            <div class="flex items-center gap-3 px-4 py-3">
                <input type="checkbox" :checked="all" :indeterminate="some" @change="toggleAll()"
                       aria-label="Allow everything under {{ $groupLabel }}"
                       class="w-4 h-4 rounded text-navy-700 border-slate-300 focus:ring-navy-600">
                <button type="button" @click="open = ! open" :aria-expanded="open" class="flex-1 flex items-center justify-between gap-3 text-left">
                    <span class="text-sm font-semibold text-slate-900">{{ $groupLabel }}</span>
                    <span class="flex items-center gap-2">
                        <span class="text-[11px] text-slate-500" x-text="checked.length + ' of ' + keys.length"></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform" :class="open && 'rotate-180'"></i>
                    </span>
                </button>
            </div>
            <div x-show="open" x-cloak class="border-t border-slate-100 divide-y divide-slate-100">
                @foreach ($modules as $module)
                    <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                        <span class="sm:w-52 shrink-0 flex items-center gap-2 text-sm text-slate-800">
                            <i data-lucide="{{ $module['icon'] }}" class="w-4 h-4 text-slate-500"></i>
                            {{ $module['label'] }}
                        </span>
                        <span class="flex flex-wrap gap-1.5">
                            @foreach ($module['actions'] as $action)
                                @php $key = $module['key'].'.'.$action; @endphp
                                <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer select-none transition-colors"
                                       :class="checked.includes('{{ $key }}') ? 'bg-navy-50 border-navy-300 text-navy-800' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300'">
                                    <input type="checkbox" name="{{ $name }}" value="{{ $key }}" x-model="checked"
                                           class="w-3.5 h-3.5 rounded text-navy-700 border-slate-300 focus:ring-navy-600">
                                    {{ $actionLabels[$action] ?? ucfirst($action) }}
                                </label>
                            @endforeach
                            @foreach ($extras->get($module['key'], []) as $extraKey)
                                <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer select-none transition-colors ml-1"
                                       title="Add and edit the ticketing packages, the airlines, the insurance plans and the BI prices. Granted on its own: the box above does not tick it."
                                       :class="extra.includes('{{ $extraKey }}') ? 'bg-navy-50 border-navy-300 text-navy-800' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300'">
                                    <input type="checkbox" name="{{ $name }}" value="{{ $extraKey }}" x-model="extra"
                                           class="w-3.5 h-3.5 rounded text-navy-700 border-slate-300 focus:ring-navy-600">
                                    Packages, airlines &amp; prices
                                </label>
                            @endforeach
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    <p class="text-[11px] text-slate-500">Deleting for good is never granted here: only admins delete. A desk's "Open, Create, Edit" covers the files a person opened themselves; "See everyone's files" and "Approve" are for supervisors.</p>
</div>
