@extends('layouts.admin')

@section('title', 'Approve ' . $ticket->booking_reference . ' - AMEGA Admin')
@section('page_title', 'Ticket Approval')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <a href="{{ route('admin.ticket-approvals.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>Ticket Approvals</span>
            </a>
            <h2 class="mt-2 text-xl font-bold text-slate-900 font-mono">{{ $ticket->booking_reference }}</h2>
            <p class="text-sm text-slate-500">
                Submitted by {{ $ticket->createdBy?->name ?? 'an agent' }}{{ $ticket->approval_requested_at ? ' on '.$ticket->approval_requested_at->format('M j, Y g:i A') : '' }}
                @if ($ticket->agent_acknowledged_at)
                    · confirmed correct by {{ $ticket->acknowledgedBy?->name ?? 'the agent' }}
                @endif
            </p>
        </div>
        @if ($ticket->isApproved())
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-sm font-semibold text-emerald-700">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                Approved by {{ $ticket->reviewedBy?->name ?? 'an admin' }} · {{ $ticket->reviewed_at?->format('M j, Y g:i A') }}
            </span>
        @elseif ($ticket->isRejected())
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-sm font-semibold text-rose-700">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                Returned by {{ $ticket->reviewedBy?->name ?? 'an admin' }} · {{ $ticket->reviewed_at?->format('M j, Y g:i A') }}
            </span>
        @endif
    </div>

    @if (session('error'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-900" role="alert">{{ session('error') }}</div>
    @endif

    @if ($ticket->isRejected() && $ticket->review_note)
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3">
            <p class="text-xs font-semibold text-rose-800">Reason returned</p>
            <p class="text-sm text-rose-900 whitespace-pre-line">{{ $ticket->review_note }}</p>
        </div>
    @endif

    {{-- The booking as the agent submitted it --}}
    @include('ticketing.tickets._confirmation', ['readonly' => true, 'documentLinks' => true])

    {{-- Approve, or return to the agent --}}
    @if ($ticket->isAwaitingApproval())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <form method="POST" action="{{ route('admin.ticket-approvals.approve', $ticket) }}" x-data="{ acknowledged: false }"
                  class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-4">
                @csrf
                <h3 class="text-base font-semibold text-slate-900">Approve for payment</h3>
                <label class="flex items-start gap-3 p-4 rounded-lg border cursor-pointer"
                       :class="acknowledged ? 'border-navy-700 bg-navy-50' : '{{ $errors->has('admin_acknowledged') ? 'border-rose-400 bg-rose-50' : 'border-slate-200 bg-slate-50' }}'">
                    <input type="checkbox" name="admin_acknowledged" value="1" x-model="acknowledged" class="mt-0.5 w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                    <span class="text-sm text-slate-700 leading-relaxed">
                        I acknowledge that I have reviewed all the information in this booking and that it is correct.
                        I, <strong>{{ auth()->user()->name }}</strong>, will be held accountable for any incorrect information.
                    </span>
                </label>
                @error('admin_acknowledged')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror
                <button type="submit" :disabled="!acknowledged"
                        class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    <span>Approve and send to cashier</span>
                </button>
            </form>

            <form method="POST" action="{{ route('admin.ticket-approvals.reject', $ticket) }}"
                  class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 space-y-4">
                @csrf
                <h3 class="text-base font-semibold text-slate-900">Return to the agent</h3>
                <div>
                    <label for="review_note" class="block text-xs font-semibold text-slate-600 mb-1">What needs correcting *</label>
                    <textarea id="review_note" name="review_note" rows="4" maxlength="2000" required
                              class="w-full px-3 py-2 rounded-lg bg-white border {{ $errors->has('review_note') ? 'border-rose-400' : 'border-slate-300' }} text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600"
                              placeholder="e.g. Passenger 2's passport number does not match the scan">{{ old('review_note') }}</textarea>
                    @error('review_note')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-lg border border-rose-300 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    <span>Return to agent</span>
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
