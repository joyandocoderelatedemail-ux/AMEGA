@extends('layouts.admin')

@section('title', 'Immigration Approvals - AMEGA Admin')
@section('page_title', 'Immigration Approvals')

@php
    $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned'];
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);
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
                <a href="{{ route('admin.immigration-approvals.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-md text-sm font-semibold {{ $tab === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">
                    <span>{{ $label }}</span>
                    <span class="px-1.5 rounded-full text-xs {{ $key === 'pending' && $counts[$key] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.immigration-approvals.index') }}" class="flex gap-2" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="approval-search" class="sr-only">Search</label>
            <input id="approval-search" type="search" name="search" value="{{ $search }}" placeholder="Client, passport, reference or agent" class="{{ $control }} w-64">
            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
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
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="text-sm font-bold text-slate-900">{{ $item['client'] }}</span>
                                    @if ($item['passport'])<span class="font-mono text-xs text-slate-500">{{ $item['passport'] }}</span>@endif
                                </div>
                                <div class="text-xs text-slate-600">
                                    <span class="font-semibold text-slate-800">{{ $item['label'] }}</span>
                                    &middot; <span class="font-mono">{{ $item['reference'] }}</span>
                                    @if ($item['agent']) &middot; by {{ $item['agent'] }} @endif
                                </div>
                                <div class="text-xs text-slate-600">
                                    @if ($item['payment'])
                                        <span class="inline-flex px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold">{{ $item['payment'] }}@if ($item['terms']) &middot; {{ $item['terms'] }} terms @endif</span>
                                    @else
                                        <span class="text-slate-400">No payment option recorded</span>
                                    @endif
                                    @if ($item['fee'] !== null)
                                        &middot; fee {{ $peso($item['fee']) }}
                                        @if ($item['payment']) &middot; paid {{ $peso($item['paid']) }} &middot; balance {{ $peso($item['balance']) }} @endif
                                    @endif
                                </div>
                                @if ($item['note'])
                                    <p class="text-xs font-semibold text-rose-700">Returned: {{ $item['note'] }}</p>
                                @endif
                                <div class="text-[11px] text-slate-500">
                                    {{ $tab === 'pending' ? 'Submitted' : ($tab === 'approved' ? 'Approved' : 'Returned') }}
                                    {{ $item['at']?->format('M j, Y g:i A') }}
                                    @if ($tab !== 'pending' && $item['reviewer']) by {{ $item['reviewer'] }} @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview</a>
                                @endif
                                @if ($tab === 'pending')
                                    <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="inline-flex items-center h-9 px-3 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Approve</button>
                                    <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Return</button>
                                @endif
                            </div>
                        </div>

                        @if ($tab === 'pending')
                            <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route('admin.immigration-approvals.approve', [$item['kind'], $item['id']]) }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                                @csrf
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded border-slate-300">
                                    I reviewed the dates and details and they are correct.
                                </label>
                                <button type="submit" class="h-9 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">Approve</button>
                            </form>
                            <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route('admin.immigration-approvals.reject', [$item['kind'], $item['id']]) }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
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
