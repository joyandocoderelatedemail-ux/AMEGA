@extends('layouts.admin')

@section('title', 'Ticket Approvals - AMEGA Admin')
@section('page_title', 'Ticket Approvals')

@php
    $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned'];
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
@endphp

@section('content')
<div class="space-y-6">
    @if (session('error'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-900" role="alert">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <nav class="inline-flex flex-wrap gap-1 p-1 rounded-lg bg-slate-100" aria-label="Approval queues">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.ticket-approvals.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-md text-sm font-semibold {{ $tab === $key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">
                    <span>{{ $label }}</span>
                    <span class="px-1.5 rounded-full text-xs {{ $key === 'pending' && $counts[$key] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700' }}">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.ticket-approvals.index') }}" class="flex gap-2" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="approval-search" class="sr-only">Search bookings</label>
            <input id="approval-search" type="search" name="search" value="{{ request('search') }}" placeholder="Reference, client or destination" class="{{ $control }} w-64">
            <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($tickets->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">
                {{ $tab === 'pending' ? 'No bookings waiting for approval.' : 'Nothing here yet.' }}
            </p>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Trip</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3">Agent</th>
                            <th class="px-5 py-3">{{ $tab === 'pending' ? 'Submitted' : 'Reviewed' }}</th>
                            <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($tickets as $ticket)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-mono text-xs font-semibold text-slate-900">{{ $ticket->booking_reference }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $ticket->contact_name }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-slate-900">{{ $ticket->origin }} → {{ $ticket->destination }}</div>
                                    <div class="text-xs text-slate-500">{{ $ticket->departure_date?->format('M j, Y') }} · {{ ucfirst((string) $ticket->travel_type) }}</div>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">₱{{ number_format((float) $ticket->total_amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ $ticket->createdBy?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap">
                                    @if ($tab === 'pending')
                                        {{ $ticket->approval_requested_at?->format('M j, Y g:i A') }}
                                    @else
                                        {{ $ticket->reviewed_at?->format('M j, Y g:i A') }}
                                        @if ($ticket->reviewedBy)
                                            <div>by {{ $ticket->reviewedBy->name }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('admin.ticket-approvals.show', $ticket) }}"
                                       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg {{ $tab === 'pending' ? 'bg-navy-700 text-white hover:bg-navy-800' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }} text-sm font-semibold">
                                        <i class="fa-regular {{ $tab === 'pending' ? 'fa-square-check' : 'fa-eye' }}" aria-hidden="true"></i>
                                        <span>{{ $tab === 'pending' ? 'Review' : 'View' }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($tickets->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">{{ $tickets->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
