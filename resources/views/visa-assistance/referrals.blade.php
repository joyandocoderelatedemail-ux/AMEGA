@extends('layouts.visa-assistance')

@section('title', 'Ticketing Referrals - Visa Assistance')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('visa.dashboard') }}" class="text-xs font-semibold text-navy-700 hover:underline">&larr; Visa Assistance Counter</a>
        <h1 class="mt-2 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Ticketing referrals</h1>
        <p class="mt-1 text-sm text-slate-500">Clients booked at the Ticketing desk who need help with a visa or a passport. A booking leaves this list once its ticket is issued or it is cancelled.</p>
    </div>

    @forelse ($referrals as $ticket)
        @php $isNew = in_array($ticket->id, $newIds, true); @endphp
        <div class="{{ $card }} p-5 space-y-4 {{ $isNew ? 'ring-2 ring-rose-200' : '' }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-bold text-slate-900">{{ $ticket->booking_reference }}</span>
                        @if ($isNew)
                            <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-xs font-semibold ring-1 ring-rose-600/20">New</span>
                        @endif
                        @foreach (\App\Support\VisaReferrals::needs($ticket) as $need)
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold ring-1 {{ $need === 'visa' ? 'bg-navy-50 text-navy-700 ring-navy-700/20' : 'bg-amber-50 text-amber-800 ring-amber-600/20' }}">
                                This client needs {{ $need }} assistance
                            </span>
                        @endforeach
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $ticket->destination ?: 'No destination' }}
                        @if ($ticket->departure_date) &middot; departs {{ $ticket->departure_date->format('M j, Y') }} @endif
                    </p>
                </div>
                <div class="text-xs text-slate-500 text-right">
                    <div>Booked by {{ $ticket->createdBy?->name ?? 'Ticketing' }}</div>
                    <div>{{ $ticket->created_at?->diffForHumans() }}</div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-xs font-semibold text-slate-500 mb-1">Contact</div>
                    <div class="text-sm font-semibold text-slate-900">{{ $ticket->contact_name ?: '—' }}</div>
                    <div class="text-sm text-slate-600">{{ collect([$ticket->contact_phone, $ticket->contact_email])->filter()->implode(' · ') ?: '—' }}</div>
                </div>
                <div class="space-y-2">
                    @foreach (['visa' => 'Visa', 'passport' => 'Passport'] as $need => $name)
                        @php $people = \App\Support\VisaReferrals::passengers($ticket, $need); @endphp
                        @if ($people)
                            <div>
                                <div class="text-xs font-semibold text-slate-500 mb-1">{{ $name }} assistance for</div>
                                <ul class="text-sm text-slate-800 space-y-0.5">
                                    @foreach ($people as $person)
                                        <li>{{ $person->full_name }}@if ($need === 'visa' && $person->purpose_of_travel) <span class="text-slate-500"> · {{ $person->purpose_of_travel }}</span>@endif
                                            @if ($need === 'passport' && $person->passport_expiry_date) <span class="text-slate-500"> · passport expires {{ $person->passport_expiry_date->format('M j, Y') }}</span>@endif</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="{{ $card }} p-10 text-center">
            <p class="text-sm font-semibold text-slate-900">No referrals right now.</p>
            <p class="mt-1 text-sm text-slate-500">When Ticketing books a client who needs visa or passport assistance, they appear here and in the bell.</p>
        </div>
    @endforelse
</div>
@endsection
