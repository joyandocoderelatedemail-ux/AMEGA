@php
    /**
     * Staff accounts and what each did in the dashboard's period, with a
     * search to find one and a link through to their full activity.
     *
     * @var \Illuminate\Pagination\LengthAwarePaginator $staff
     * @var \App\Support\DateRange $range
     */
    $roles = \App\Http\Controllers\Admin\AdminAgentController::STAFF_ROLES;
    $th = 'text-xs font-semibold uppercase tracking-wide text-slate-500 px-3 py-3 whitespace-nowrap';
    $control = 'h-9 rounded-lg border border-slate-300 bg-white text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-500/30 focus:border-navy-600';
@endphp

<section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden" aria-labelledby="staff-activity-heading" id="staff-activity">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100">
        <div>
            <h2 id="staff-activity-heading" class="font-heading text-base font-bold text-slate-900">Staff Activity</h2>
            <p class="text-xs text-slate-500 mt-0.5">What each staff account did &middot; {{ $range->label() }}</p>
        </div>

        <form method="GET" action="{{ route('admin.dashboard') }}#staff-activity" class="flex flex-wrap items-center gap-2" role="search">
            @foreach ($range->query() as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label for="staff_role" class="sr-only">Role</label>
            <select id="staff_role" name="staff_role" onchange="this.form.submit()" class="{{ $control }} pl-3 pr-8 font-semibold">
                <option value="">All roles</option>
                @foreach ($roles as $roleKey => $roleLabel)
                    <option value="{{ $roleKey }}" @selected(request('staff_role') === $roleKey)>{{ $roleLabel }}</option>
                @endforeach
            </select>
            <div class="relative">
                <label for="staff_search" class="sr-only">Search staff</label>
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input id="staff_search" type="search" name="staff" value="{{ request('staff') }}" placeholder="Search name, email, phone"
                       class="{{ $control }} w-56 pl-8 pr-3">
            </div>
            <button type="submit" class="inline-flex items-center h-9 px-3 rounded-lg bg-navy-700 text-white text-xs font-semibold hover:bg-navy-800 transition-colors cursor-pointer">
                Search
            </button>
            @if (request()->filled('staff') || request()->filled('staff_role'))
                <a href="{{ route('admin.dashboard', $range->query()) }}#staff-activity" class="inline-flex items-center h-9 px-2 text-xs font-semibold text-slate-500 hover:text-slate-800">Clear</a>
            @endif
        </form>
    </div>

    @if ($staff->isNotEmpty())
        <div class="relative overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th scope="col" class="{{ $th }} pl-5 sm:pl-6">Staff member</th>
                        <th scope="col" class="{{ $th }}">Role</th>
                        <th scope="col" class="{{ $th }} text-right">Files opened</th>
                        <th scope="col" class="{{ $th }} text-right hidden md:table-cell">Tickets issued</th>
                        <th scope="col" class="{{ $th }} text-right hidden md:table-cell">Ticket sales</th>
                        <th scope="col" class="{{ $th }} text-right hidden lg:table-cell">Actions</th>
                        <th scope="col" class="{{ $th }} hidden lg:table-cell">Last active</th>
                        <th scope="col" class="{{ $th }} pr-5 sm:pr-6 text-right"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($staff as $row)
                        @php $member = $row['user']; @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="pl-5 sm:pl-6 pr-3 py-3.5">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-8 h-8 rounded-full bg-navy-50 text-navy-700 text-xs font-bold flex items-center justify-center shrink-0" aria-hidden="true">
                                        {{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-slate-900 truncate max-w-[180px]">{{ $member->name }}</div>
                                        <div class="text-xs text-slate-500 truncate max-w-[180px]">{{ $member->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3.5 text-xs font-semibold text-slate-600 whitespace-nowrap">{{ $roles[$member->role] ?? ucfirst($member->role) }}</td>
                            <td class="px-3 py-3.5 text-right">
                                <div class="text-sm font-bold text-slate-900 tabular-nums">{{ number_format($row['files']) }}</div>
                                @if ($row['files'] > 0)
                                    <div class="text-xs text-slate-500 whitespace-nowrap">
                                        {{ collect(['tickets' => 'ticket', 'visa' => 'visa', 'srrv' => 'SRRV'])->filter(fn ($label, $key) => $row[$key] > 0)->map(fn ($label, $key) => $row[$key].' '.($label === 'ticket' ? Str::plural('ticket', $row[$key]) : $label))->implode(' · ') }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-right text-sm text-slate-700 tabular-nums hidden md:table-cell">{{ number_format($row['issued']) }}</td>
                            <td class="px-3 py-3.5 text-right text-sm text-slate-700 tabular-nums hidden md:table-cell whitespace-nowrap">&#8369;{{ number_format($row['sales'], 0) }}</td>
                            <td class="px-3 py-3.5 text-right text-sm text-slate-700 tabular-nums hidden lg:table-cell">{{ number_format($row['actions']) }}</td>
                            <td class="px-3 py-3.5 text-xs text-slate-500 whitespace-nowrap hidden lg:table-cell">{{ $row['lastActiveAt']?->diffForHumans() ?? 'Never' }}</td>
                            <td class="pl-3 pr-5 sm:pr-6 py-3.5 text-right">
                                <a href="{{ route('admin.agents.show', ['agent' => $member] + $range->query()) }}"
                                   class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors whitespace-nowrap">
                                    View
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($staff->hasPages())
            <div class="px-5 sm:px-6 py-3 border-t border-slate-100">
                {{ $staff->fragment('staff-activity')->links() }}
            </div>
        @endif
    @else
        <div class="flex flex-col items-center justify-center text-center gap-2 py-12 px-6">
            <i data-lucide="user-search" class="w-7 h-7 text-slate-300"></i>
            <p class="text-sm font-semibold text-slate-500">No staff accounts match</p>
            <p class="text-xs text-slate-400">Try another name, email or role.</p>
        </div>
    @endif
</section>
