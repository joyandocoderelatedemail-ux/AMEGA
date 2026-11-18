@extends('layouts.ticketing')

@section('title', 'Quotations - AMEGA')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $field = 'h-10 w-full px-3 rounded-lg bg-white border border-slate-300 text-sm font-normal text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $fieldLabel = 'flex flex-col gap-1 text-xs font-semibold text-slate-600';
    $th = 'px-3 xl:px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';
    $tabCount = ['all' => 'bg-slate-100 text-slate-600', 'ending' => 'bg-amber-50 text-amber-800', 'expired' => 'bg-rose-50 text-rose-700', 'unpriced' => 'bg-amber-50 text-amber-800'];
    $keep = request()->except(['view', 'page']);
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0 max-w-3xl">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Quotations</h1>
            <p class="mt-1 text-sm text-slate-500">Trips priced for a client but not booked yet. When the client agrees, convert the quotation into a booking: it then goes to the admin for approval like any other booking.</p>
        </div>
        @can('ticketing.create')
            <a href="{{ route('ticketing.tickets.create') }}"
               class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800 transition-colors self-start sm:self-auto shrink-0">
                <i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i>
                <span>New quotation</span>
            </a>
        @endcan
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="{{ $card }} px-5 py-4">
            <div class="text-sm font-semibold text-slate-600">Open quotations</div>
            <div class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ number_format($summary['open']) }}</div>
        </div>
        <div class="{{ $card }} px-5 py-4">
            <div class="text-sm font-semibold text-slate-600">Quoted value</div>
            <div class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">&#8369;{{ number_format($summary['value'], 0) }}</div>
        </div>
        <div class="{{ $card }} px-5 py-4 {{ $summary['expired'] > 0 ? 'ring-1 ring-rose-200' : '' }}">
            <div class="text-sm font-semibold {{ $summary['expired'] > 0 ? 'text-rose-700' : 'text-slate-600' }}">Expired</div>
            <div class="mt-1 text-2xl font-bold tabular-nums {{ $summary['expired'] > 0 ? 'text-rose-900' : 'text-slate-900' }}">{{ number_format($summary['expired']) }}</div>
        </div>
        <div class="{{ $card }} px-5 py-4 {{ $summary['unpriced'] > 0 ? 'ring-1 ring-amber-200' : '' }}">
            <div class="text-sm font-semibold {{ $summary['unpriced'] > 0 ? 'text-amber-700' : 'text-slate-600' }}">Not priced yet</div>
            <div class="mt-1 text-2xl font-bold tabular-nums {{ $summary['unpriced'] > 0 ? 'text-amber-900' : 'text-slate-900' }}">{{ number_format($summary['unpriced']) }}</div>
        </div>
    </div>

    <section class="{{ $card }} overflow-hidden" aria-label="Quotations">
        <div class="px-4 sm:px-5 py-4 border-b border-slate-200 space-y-4">
            <nav class="flex flex-wrap gap-2" aria-label="Filter quotations">
                @foreach ($views as $key => $tab)
                    @php $on = $view === $key; @endphp
                    <a href="{{ route('ticketing.quotations.index', $key === 'all' ? $keep : $keep + ['view' => $key]) }}"
                       @if ($on) aria-current="page" @endif
                       class="inline-flex items-center gap-2 h-9 pl-3 pr-2 rounded-lg text-sm font-semibold transition-colors {{ $on ? 'bg-navy-700 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:border-slate-300' }}">
                        {{ $tab['label'] }}
                        <span class="rounded-full px-1.5 text-xs font-semibold tabular-nums {{ $on ? 'bg-white/20 text-white' : $tabCount[$key] }}">{{ number_format($tab['count']) }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('ticketing.quotations.index') }}" class="flex flex-wrap items-end gap-3">
                @if ($view !== 'all')
                    <input type="hidden" name="view" value="{{ $view }}">
                @endif
                <label class="{{ $fieldLabel }} flex-[3_1_18rem]">Search
                    <span class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-slate-500 absolute left-3 top-3" aria-hidden="true"></i>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Reference, client, email or destination" class="{{ $field }} pl-9">
                    </span>
                </label>
                @if ($agents->count() > 1)
                    <label class="{{ $fieldLabel }} flex-[1_1_12rem]">Agent
                        <select name="agent" class="{{ $field }}">
                            <option value="">All agents</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((string) request('agent') === (string) $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="flex items-center gap-2">
                    <button type="submit" class="h-10 px-4 rounded-lg bg-navy-700 text-sm font-semibold text-white hover:bg-navy-800 transition-colors">Search</button>
                    @if (request()->hasAny(['search', 'agent', 'from', 'to', 'range']))
                        <a href="{{ route('ticketing.quotations.index', $view !== 'all' ? ['view' => $view] : []) }}" class="h-10 inline-flex items-center px-3 rounded-lg border border-slate-200 text-sm font-semibold text-slate-600 hover:text-slate-900">Reset</a>
                    @endif
                </div>
                <x-date-range label="Quoted" class="w-full" inputClass="h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600" buttonClass="h-10 px-4 rounded-lg bg-white border border-slate-300 text-sm font-semibold text-slate-700 hover:border-slate-400" />
            </form>
        </div>

        @if ($quotations->isEmpty())
            <div class="flex flex-col items-center text-center px-6 py-14">
                <i data-lucide="file-text" class="w-8 h-8 text-slate-300" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-semibold text-slate-900">No open quotations{{ request()->filled('search') || $view !== 'all' ? ' match' : '' }}.</p>
                <p class="mt-1 max-w-sm text-sm text-slate-500">Use Save as Quotation on the last step of a new ticket booking.</p>
            </div>
        @else
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="{{ $th }} pl-4 sm:pl-5">Quotation</th>
                            <th scope="col" class="{{ $th }}">Client</th>
                            <th scope="col" class="{{ $th }}">Trip</th>
                            <th scope="col" class="{{ $th }}">Passengers</th>
                            <th scope="col" class="{{ $th }}">Valid until</th>
                            <th scope="col" class="{{ $th }} text-right">Total</th>
                            <th scope="col" class="{{ $th }} pr-4 sm:pr-5 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($quotations as $quotation)
                            @php
                                $named = $quotation->passengers->count();
                                $datePassed = $quotation->departure_date?->isPast() && ! $quotation->departure_date?->isToday();
                                $priced = (float) $quotation->total_amount > 0;
                                $expired = $quotation->quoteExpired();
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors {{ $expired ? 'bg-rose-50/40' : '' }}">
                                <td class="pl-4 sm:pl-5 pr-3 xl:pr-4 py-4">
                                    <a href="{{ route('ticketing.tickets.show', $quotation) }}" class="font-mono text-xs font-semibold text-slate-900 hover:underline whitespace-nowrap">{{ $quotation->booking_reference }}</a>
                                    <div class="mt-0.5 text-xs text-slate-500 truncate max-w-[12rem]">{{ $quotation->created_at->format('M j, Y') }}{{ $quotation->createdBy ? ' · '.$quotation->createdBy->name : '' }}</div>
                                </td>
                                <td class="px-3 xl:px-4 py-4">
                                    <a href="{{ route('ticketing.tickets.show', $quotation) }}" class="block font-semibold text-slate-900 truncate max-w-[10rem] hover:underline">{{ $quotation->contact_name }}</a>
                                    <div class="mt-0.5 text-xs text-slate-500 truncate max-w-[10rem]">{{ $quotation->contact_email ?: 'No email yet' }}</div>
                                </td>
                                <td class="px-3 xl:px-4 py-4">
                                    <div class="font-semibold text-slate-900 truncate max-w-[13rem]">{{ $quotation->origin }} &rarr; {{ $quotation->destination }}</div>
                                    <div class="mt-0.5 text-xs truncate max-w-[13rem] {{ $datePassed ? 'font-semibold text-rose-700' : 'text-slate-500' }}">
                                        {{ $quotation->departure_date?->format('M j, Y') }}{{ $datePassed ? ' · date passed' : '' }}@if ($quotation->package_name) · {{ $quotation->package_name }}@endif
                                    </div>
                                </td>
                                <td class="px-3 xl:px-4 py-4 whitespace-nowrap">
                                    <div class="font-semibold text-slate-900">{{ $quotation->total_passengers }} pax</div>
                                    <div class="mt-0.5 text-xs {{ $named < $quotation->total_passengers ? 'text-amber-700' : 'text-emerald-700' }}">{{ $named }} of {{ $quotation->total_passengers }} named</div>
                                </td>
                                <td class="px-3 xl:px-4 py-4 whitespace-nowrap">
                                    @if ($quotation->quote_valid_until)
                                        <div class="font-semibold text-slate-900 tabular-nums">{{ $quotation->quote_valid_until->format('M j, Y') }}</div>
                                        @if ($expired)
                                            <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset bg-rose-50 text-rose-700 ring-rose-600/20"><span class="w-1.5 h-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>Expired</span>
                                        @elseif ($quotation->quoteEnding())
                                            <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset bg-amber-50 text-amber-800 ring-amber-600/20"><span class="w-1.5 h-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>{{ $quotation->quote_valid_until->isToday() ? 'Ends today' : 'Ends tomorrow' }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-500">No end date</span>
                                    @endif
                                </td>
                                <td class="px-3 xl:px-4 py-4 text-right whitespace-nowrap">
                                    @if ($priced)
                                        <span class="font-semibold text-slate-900 tabular-nums">&#8369;{{ number_format((float) $quotation->total_amount, 2) }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset bg-amber-50 text-amber-800 ring-amber-600/20">Not priced</span>
                                    @endif
                                </td>
                                <td class="pl-3 xl:pl-4 pr-4 sm:pr-5 py-4">
                                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        @can('ticketing.create')
                                            <a href="{{ route('ticketing.quotations.convert', $quotation) }}"
                                               class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold transition-colors {{ $priced && ! $expired ? 'bg-navy-700 text-white hover:bg-navy-800' : 'border border-slate-300 bg-white text-slate-700 hover:border-slate-400' }}">
                                                Convert to booking
                                            </a>
                                        @endcan
                                        <a href="{{ route('ticketing.quotations.print', $quotation) }}" target="_blank" rel="noopener" title="Print Quotation" aria-label="Print quotation {{ $quotation->booking_reference }}"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 bg-white text-slate-600 hover:text-navy-700 hover:border-slate-400 transition-colors">
                                            <i data-lucide="printer" class="w-4 h-4" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 sm:px-5 py-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <span class="text-sm text-slate-500">Showing {{ number_format($quotations->firstItem()) }}&ndash;{{ number_format($quotations->lastItem()) }} of {{ number_format($quotations->total()) }}</span>
                @if ($quotations->hasPages())
                    <div>{{ $quotations->links() }}</div>
                @endif
            </div>
        @endif
    </section>

</div>
@endsection
