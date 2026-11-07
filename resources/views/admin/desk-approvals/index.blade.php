@extends('layouts.admin')

{{-- A desk's approval queue (SRRV, visa counter). Expects $title, $routePrefix (e.g. admin.srrv-approvals), $placeholder, $items, $tab, $counts, $search.
     An item with 'edit' => true is an agent's Request edit on a released file: approved (the file reopens) or declined
     through $routePrefix.edit-approve / .edit-decline. --}}
@section('title', $title.' - AMEGA Admin')
@section('page_title', $title)

@php
    $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned'];
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
@endphp

@section('content')
<div class="space-y-6">
    @if (session('error'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-900" role="alert">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-semibold text-emerald-900" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-900" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <nav class="inline-flex flex-wrap gap-1 p-1 rounded-lg bg-slate-100" aria-label="Approval queues">
            @foreach ($tabs as $key => $label)
                <a href="{{ route($routePrefix.'.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-md text-sm font-semibold {{ $tab === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">
                    <span>{{ $label }}</span>
                    <span class="px-1.5 rounded-full text-xs {{ $key === 'pending' && $counts[$key] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route($routePrefix.'.index') }}" class="flex flex-wrap gap-2" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="approval-search" class="sr-only">Search</label>
            <input id="approval-search" type="search" name="search" value="{{ $search }}" placeholder="{{ $placeholder }}" class="{{ $control }} w-64">
            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
            <x-date-range :label="$tab === 'pending' ? 'Sent' : 'Decided'" />
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($items->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">
                {{ $tab === 'pending' ? 'Nothing waiting for approval.' : 'Nothing here yet.' }}
            </p>
        @else
            <ul class="divide-y divide-slate-200">
                @foreach ($items as $item)
                    <li class="px-5 py-4" x-data="{ mode: null }">
                        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                            <div class="min-w-0 flex-1 space-y-1">
                                {{-- Transaction number, client, service, agent: the same order on every approval queue. --}}
                                <dl class="grid grid-cols-2 lg:grid-cols-[11rem_minmax(0,1fr)_10rem_12rem] gap-x-6 gap-y-2">
                                    <div>
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Transaction no.</dt>
                                        <dd class="font-mono text-sm font-semibold text-slate-900">{{ $item['reference'] }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Client</dt>
                                        <dd class="text-sm font-bold text-slate-900 flex flex-wrap items-center gap-x-2">
                                            <span class="truncate">{{ $item['client'] }}</span>
                                            @if ($item['edit'] ?? false)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-amber-300 bg-amber-50 text-[11px] font-bold text-amber-800"><i data-lucide="pencil-line" class="w-3 h-3"></i> {{ $tab === 'pending' ? 'Edit request' : 'Edit declined' }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Service</dt>
                                        <dd class="text-sm font-semibold text-slate-800">{{ $item['label'] }}@if ($item['detail']) <span class="font-mono text-xs font-normal text-slate-500">&middot; {{ $item['detail'] }}</span>@endif</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Agent</dt>
                                        <dd class="text-sm text-slate-800">{{ $item['agent'] ?: '—' }}</dd>
                                    </div>
                                </dl>
                                @if ($item['summary'])
                                    <div class="text-xs text-slate-600">{{ $item['summary'] }}</div>
                                @endif
                                @if ($item['note'] && ($item['edit'] ?? false))
                                    <p class="text-xs font-semibold text-amber-800">{{ $item['note'] }}</p>
                                @elseif ($item['note'])
                                    <p class="text-xs font-semibold text-rose-700">Returned: {{ $item['note'] }}</p>
                                @endif
                                <div class="text-[11px] text-slate-500">
                                    {{ $tab === 'pending' ? 'Submitted' : ($tab === 'approved' ? 'Approved' : 'Returned') }}
                                    {{ $item['at']?->format('M j, Y g:i A') }}
                                    @if ($tab !== 'pending' && $item['reviewer']) by {{ $item['reviewer'] }} @endif
                                </div>
                                    @if (\App\Support\Commissions::visibleTo(auth()->user()) && $tab === 'approved' && ($commissionAmount = \App\Support\Commissions::amount($commissionDesk ?? '', $item['kind'], $item['id'])) !== null)
                                        <div class="text-[11px] font-semibold text-slate-600">Agent commission ₱{{ number_format($commissionAmount, 2) }} <span class="font-normal text-slate-400">(only admins see this)</span></div>
                                    @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview</a>
                                @endif
                                @if ($tab === 'pending' && ($item['edit'] ?? false))
                                    <button type="button" @click="mode = mode === 'edit-approve' ? null : 'edit-approve'" class="inline-flex items-center h-9 px-3 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Approve reopening</button>
                                    <button type="button" @click="mode = mode === 'edit-decline' ? null : 'edit-decline'" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Decline</button>
                                @elseif ($tab === 'pending')
                                    <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="inline-flex items-center h-9 px-3 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Approve</button>
                                    <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Return</button>
                                @endif
                            </div>
                        </div>

                        @if ($tab === 'pending' && ($item['edit'] ?? false))
                            <form x-show="mode === 'edit-approve'" style="display: none" method="POST" action="{{ route($routePrefix.'.edit-approve', [$item['kind'], $item['id']]) }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                                @csrf
                                <input type="text" name="note" maxlength="1000" placeholder="A note for the agent (optional)" aria-label="Note for the agent" class="{{ $control }} flex-1 min-w-[16rem]">
                                <button type="submit" class="h-9 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Approve reopening</button>
                            </form>
                            <form x-show="mode === 'edit-decline'" style="display: none" method="POST" action="{{ route($routePrefix.'.edit-decline', [$item['kind'], $item['id']]) }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                                @csrf
                                <input type="text" name="note" required maxlength="1000" placeholder="Why does the file stay locked?" aria-label="Why the file stays locked" class="{{ $control }} flex-1 min-w-[16rem]">
                                <button type="submit" class="h-9 px-4 rounded-lg border border-rose-300 bg-white text-rose-700 text-sm font-semibold hover:bg-rose-50">Decline</button>
                            </form>
                        @elseif ($tab === 'pending')
                            <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route($routePrefix.'.approve', [$item['kind'], $item['id']]) }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                                @csrf
                                @if (\App\Support\Commissions::visibleTo(auth()->user()))
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <span>Agent commission (₱)</span>
                                    <input type="number" name="commission" min="0" step="0.01" placeholder="0.00" aria-label="Agent commission in pesos (only admins see this)"
                                           class="w-32 h-9 rounded-lg border border-slate-300 px-3 text-sm tabular-nums focus:outline-none focus:ring-2 focus:ring-navy-600">
                                </label>
                                @endif
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded border-slate-300">
                                    I reviewed the dates and details and they are correct.
                                </label>
                                <button type="submit" class="h-9 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Approve</button>
                            </form>
                            <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route($routePrefix.'.reject', [$item['kind'], $item['id']]) }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                                @csrf
                                <label for="note-{{ $item['kind'] }}-{{ $item['id'] }}" class="sr-only">What to correct</label>
                                <input id="note-{{ $item['kind'] }}-{{ $item['id'] }}" type="text" name="review_note" required maxlength="2000" placeholder="What does the agent need to correct?" class="{{ $control }} flex-1 min-w-[16rem]">
                                <button type="submit" class="h-9 px-4 rounded-lg border border-rose-300 bg-white text-rose-700 text-sm font-semibold hover:bg-rose-50">Return to the agent</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
