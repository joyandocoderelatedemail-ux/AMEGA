@extends('layouts.admin')

@section('title', 'Real-Time Staff Action Logs - AMEGA Admin')
@section('page_title', 'Real-Time Staff Audit Trail & Action Logs')

@section('content')
<div class="space-y-6">
    
    <!-- Action Header & Filters -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 bg-emerald-100 border border-emerald-300 px-2.5 py-0.5 rounded-full">
                    Real-Time Stream Active
                </span>
            </div>
            <h2 class="font-heading text-xl font-bold text-dark">Staff Action Audit Trail</h2>
            <p class="text-xs text-dark/50">Live timestamped record of every action performed by Agents & Admins across the platform</p>
        </div>

        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <select name="module" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs font-bold text-dark focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="">All Modules</option>
                <option value="Auth" {{ request('module') === 'Auth' ? 'selected' : '' }}>Auth & Logins</option>
                <option value="Bookings" {{ request('module') === 'Bookings' ? 'selected' : '' }}>Bookings</option>
                <option value="Inquiries" {{ request('module') === 'Inquiries' ? 'selected' : '' }}>Inquiries</option>
                <option value="Packages" {{ request('module') === 'Packages' ? 'selected' : '' }}>Packages</option>
                <option value="Destinations" {{ request('module') === 'Destinations' ? 'selected' : '' }}>Destinations</option>
                <option value="Services" {{ request('module') === 'Services' ? 'selected' : '' }}>Services</option>
                <option value="Users" {{ request('module') === 'Users' ? 'selected' : '' }}>Client Directory</option>
                <option value="Agents" {{ request('module') === 'Agents' ? 'selected' : '' }}>Agent Staff</option>
            </select>

            <select name="role" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs font-bold text-dark focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="">All Roles</option>
                <option value="agent" {{ request('role') === 'agent' ? 'selected' : '' }}>Travel Agents</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Administrators</option>
            </select>

            <div class="relative flex-1 md:w-56">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search action, staff, description..."
                       class="w-full pl-9 pr-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3 top-2.5"></i>
            </div>
        </form>
    </div>

    <!-- Live Activity Logs Table -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm overflow-hidden space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Timestamp</th>
                        <th class="pb-3 px-3">Staff User</th>
                        <th class="pb-3 px-3">Module</th>
                        <th class="pb-3 px-3">Action Type</th>
                        <th class="pb-3 px-3">Action Details & Summary</th>
                        <th class="pb-3 px-3 text-right">IP Address</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody" class="divide-y divide-gray-50">
                    @forelse ($logs as $log)
                        <tr id="log-row-{{ $log->id }}" class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3.5 px-3 whitespace-nowrap">
                                <div class="font-bold text-dark text-xs">{{ $log->created_at->format('M j, Y • g:i:s A') }}</div>
                                <div class="text-[10px] text-dark/40 font-mono">{{ $log->created_at->diffForHumans() }}</div>
                            </td>

                            <td class="py-3.5 px-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full {{ $log->user_role === 'admin' ? 'bg-amber-500 text-white' : ($log->user_role === 'agent' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-dark') }} font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ substr($log->user_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-dark text-xs">{{ $log->user_name }}</div>
                                        <span class="text-[9px] font-bold uppercase tracking-wider {{ $log->user_role === 'admin' ? 'text-amber-600' : ($log->user_role === 'agent' ? 'text-emerald-600' : 'text-gray-500') }}">
                                            {{ $log->user_role }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3.5 px-3 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-dark/80 border border-gray-200">
                                    {{ $log->module }}
                                </span>
                            </td>

                            <td class="py-3.5 px-3 whitespace-nowrap">
                                @php
                                    $actionColor = match($log->action) {
                                        'LOGIN' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                        'LOGOUT' => 'bg-gray-100 text-gray-700 border-gray-300',
                                        'UPDATE_STATUS', 'TOGGLE_FEATURED' => 'bg-amber-100 text-amber-900 border-amber-300',
                                        'CREATE' => 'bg-blue-100 text-blue-800 border-blue-300',
                                        'DELETE' => 'bg-rose-100 text-rose-800 border-rose-300',
                                        default => 'bg-primary/10 text-primary border-primary/20',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $actionColor }}">
                                    {{ $log->action }}
                                </span>
                            </td>

                            <td class="py-3.5 px-3">
                                <div class="font-semibold text-dark text-xs leading-relaxed max-w-xl">{{ $log->description }}</div>
                            </td>

                            <td class="py-3.5 px-3 text-right font-mono text-[11px] text-dark/40 whitespace-nowrap">
                                {{ $log->ip_address ?? '127.0.0.1' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-dark/40 font-medium">
                                No activity log records found matching the filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $logs->links() }}
        </div>
    </div>
</div>

<script>
let lastLogId = {{ $logs->first()->id ?? 0 }};

function pollRealtimeLogs() {
    fetch(`{{ route('admin.activity-logs.stream') }}?last_id=${lastLogId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.logs && data.logs.length > 0) {
                const tbody = document.getElementById('logs-tbody');
                
                data.logs.forEach(log => {
                    if (document.getElementById(`log-row-${log.id}`)) return;

                    lastLogId = Math.max(lastLogId, log.id);

                    const tr = document.createElement('tr');
                    tr.id = `log-row-${log.id}`;
                    tr.className = 'hover:bg-emerald-50/60 bg-emerald-50/30 transition-all duration-500 animate-pulse';

                    let roleClass = log.user_role === 'admin' ? 'bg-amber-500 text-white' : (log.user_role === 'agent' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-dark');
                    let roleBadgeClass = log.user_role === 'admin' ? 'text-amber-600' : (log.user_role === 'agent' ? 'text-emerald-600' : 'text-gray-500');

                    tr.innerHTML = `
                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <div class="font-bold text-dark text-xs">${log.created_at_formatted}</div>
                            <div class="text-[10px] text-dark/40 font-mono">${log.time_ago}</div>
                        </td>
                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full ${roleClass} font-bold text-xs flex items-center justify-center shrink-0">
                                    ${log.user_name.charAt(0)}
                                </div>
                                <div>
                                    <div class="font-bold text-dark text-xs">${log.user_name}</div>
                                    <span class="text-[9px] font-bold uppercase tracking-wider ${roleBadgeClass}">${log.user_role}</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-dark/80 border border-gray-200">${log.module}</span>
                        </td>
                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-emerald-100 text-emerald-800 border-emerald-300">${log.action}</span>
                        </td>
                        <td class="py-3.5 px-3">
                            <div class="font-semibold text-dark text-xs leading-relaxed max-w-xl">${log.description}</div>
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono text-[11px] text-dark/40 whitespace-nowrap">${log.ip_address || '127.0.0.1'}</td>
                    `;

                    tbody.insertBefore(tr, tbody.firstChild);

                    setTimeout(() => {
                        tr.classList.remove('bg-emerald-50/30', 'animate-pulse');
                    }, 3000);
                });
            }
        })
        .catch(err => console.error('Real-time log polling error:', err));
}

// Poll real-time action logs every 4 seconds
setInterval(pollRealtimeLogs, 4000);
</script>
@endsection
