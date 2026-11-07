@extends('layouts.admin')

@section('title', 'Agent Commissions - AMEGA Admin')
@section('page_title', 'Agent Commissions')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $commissions */
    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
    $control = 'px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs font-bold text-dark focus:outline-none focus:ring-2 focus:ring-primary';
    $filtered = request()->filled('agent') || request()->filled('desk') || request()->filled('search') || request()->filled('range') || request()->filled('from') || request()->filled('to');
@endphp

@section('content')
<div class="space-y-6">

    {{-- Admin eyes only. --}}
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="font-heading text-xl font-bold text-dark">What each agent earned</h2>
                <p class="text-xs text-dark/50">The commissions you set when approving a service, added up. Only admins can see this page.</p>
            </div>
            <form method="GET" action="{{ route('admin.commissions.index') }}" class="flex flex-wrap items-center gap-2.5">
                <select name="agent" onchange="this.form.submit()" class="{{ $control }}" aria-label="Agent">
                    <option value="">All agents</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((string) request('agent') === (string) $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                    <option value="none" @selected(request('agent') === 'none')>No agent recorded</option>
                </select>
                <select name="desk" onchange="this.form.submit()" class="{{ $control }}" aria-label="Service">
                    <option value="">All services</option>
                    @foreach ($desks as $key => $label)
                        <option value="{{ $key }}" @selected(request('desk') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Reference or client"
                           class="w-48 pl-9 pr-4 py-2 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3 top-2.5"></i>
                </div>
                <x-date-range label="Approved" inputClass="py-2 px-3 rounded-xl bg-gray-50 border border-gray-200 text-xs text-dark focus:outline-none focus:ring-2 focus:ring-primary" buttonClass="py-2 px-3 rounded-xl bg-navy text-white text-xs font-bold hover:bg-primary" />
                @if ($filtered)
                    <a href="{{ route('admin.commissions.index') }}" class="text-xs font-bold text-dark/60 hover:text-dark">Clear</a>
                @endif
            </form>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-2xl bg-slate-50 border border-gray-200 p-4">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-dark/50">Total commissions</dt>
                <dd class="mt-1 text-2xl font-bold text-dark tabular-nums">{{ $peso($grandTotal) }}</dd>
            </div>
            <div class="rounded-2xl bg-slate-50 border border-gray-200 p-4">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-dark/50">Services with a commission</dt>
                <dd class="mt-1 text-2xl font-bold text-dark tabular-nums">{{ number_format($fileCount) }}</dd>
            </div>
            <div class="rounded-2xl bg-slate-50 border border-gray-200 p-4">
                <dt class="text-[11px] font-bold uppercase tracking-wider text-dark/50">Agents paid</dt>
                <dd class="mt-1 text-2xl font-bold text-dark tabular-nums">{{ number_format($perAgent->where('agent_id', '!=', 0)->count()) }}</dd>
            </div>
        </dl>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Per agent --}}
        <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card">
            <h3 class="font-heading text-base font-bold text-dark mb-3">By agent</h3>
            @if ($perAgent->isEmpty())
                <p class="text-sm text-dark/50">No commissions yet.</p>
            @else
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                            <th class="pb-2 pr-3">Agent</th><th class="pb-2 pr-3 text-right">Services</th><th class="pb-2 text-right">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($perAgent as $row)
                            <tr>
                                <td class="py-2.5 pr-3 font-bold text-dark">
                                    <a href="{{ route('admin.commissions.index', array_merge(request()->except('page', 'agent'), ['agent' => $row['agent_id'] ?: 'none'])) }}" class="hover:text-primary hover:underline">{{ $row['name'] }}</a>
                                </td>
                                <td class="py-2.5 pr-3 text-right tabular-nums">{{ $row['files'] }}</td>
                                <td class="py-2.5 text-right font-bold tabular-nums">{{ $peso($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 font-extrabold">
                            <td class="pt-2.5 pr-3">Total</td>
                            <td class="pt-2.5 pr-3 text-right tabular-nums">{{ $fileCount }}</td>
                            <td class="pt-2.5 text-right tabular-nums">{{ $peso($grandTotal) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>

        {{-- Per month --}}
        <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card">
            <h3 class="font-heading text-base font-bold text-dark mb-3">By month</h3>
            @if ($perMonth->isEmpty())
                <p class="text-sm text-dark/50">No commissions yet.</p>
            @else
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                            <th class="pb-2 pr-3">Month</th><th class="pb-2 pr-3 text-right">Services</th><th class="pb-2 text-right">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($perMonth as $row)
                            <tr>
                                <td class="py-2.5 pr-3 font-bold text-dark">{{ $row['label'] }}</td>
                                <td class="py-2.5 pr-3 text-right tabular-nums">{{ $row['files'] }}</td>
                                <td class="py-2.5 text-right font-bold tabular-nums">{{ $peso($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- File by file --}}
    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card">
        <h3 class="font-heading text-base font-bold text-dark mb-3">Commission by service</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                        <th class="pb-3 px-3">Approved</th>
                        <th class="pb-3 px-3">Agent</th>
                        <th class="pb-3 px-3">Service</th>
                        <th class="pb-3 px-3">Client</th>
                        <th class="pb-3 px-3 text-right">Commission</th>
                        <th class="pb-3 px-3">Set by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($commissions as $commission)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3 px-3 whitespace-nowrap text-dark/60">{{ $commission->created_at->format('M j, Y') }}</td>
                            <td class="py-3 px-3 font-bold text-dark">{{ $commission->agent?->name ?? 'No agent recorded' }}</td>
                            <td class="py-3 px-3">
                                <span class="font-mono font-semibold text-dark">{{ $commission->reference ?: '—' }}</span>
                                <span class="block text-[11px] text-dark/50">{{ $desks[$commission->desk] ?? $commission->desk }}</span>
                            </td>
                            <td class="py-3 px-3 text-dark/80">{{ $commission->client_name ?: '—' }}</td>
                            <td class="py-3 px-3 text-right font-bold tabular-nums whitespace-nowrap">{{ $peso((float) $commission->amount) }}</td>
                            <td class="py-3 px-3 text-dark/60">{{ $commission->setBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-dark/40 font-medium">
                                {{ $filtered ? 'No commissions match these filters.' : 'No commissions yet. Set one in the Agent commission box when you approve a service.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-4">{{ $commissions->links() }}</div>
    </div>
</div>
@endsection
