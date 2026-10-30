@extends('layouts.ticketing')

@section('title', 'Quotations - AMEGA')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-heading font-extrabold uppercase tracking-wider">
                <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                Priced, not booked
            </span>
            <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight mt-1">Quotations</h1>
            <p class="text-xs text-dark/50 mt-1">Trips saved with Save as Quotation. When the client accepts, convert one into a booking: it goes to the admin for approval like any booking.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('ticketing.quotations.index') }}" class="bg-white rounded-xl p-4 border border-gray-200 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3.5 top-3"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search reference, client, email or destination..."
                   class="w-full pl-10 pr-4 py-2.5 rounded-lg bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-primary text-white font-bold text-xs hover:bg-navy">Search</button>
        @if (request()->filled('search'))
            <a href="{{ route('ticketing.quotations.index') }}" class="px-4 py-2.5 rounded-lg bg-gray-100 text-dark/60 text-xs font-bold text-center">Reset</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if ($quotations->isEmpty())
            <div class="p-10 text-center">
                <i data-lucide="file-text" class="w-8 h-8 text-dark/20 mx-auto"></i>
                <p class="mt-2 text-sm font-semibold text-dark/70">No open quotations{{ request()->filled('search') ? ' match that search' : '' }}.</p>
                <p class="text-xs text-dark/50">Use Save as Quotation on the last step of a new ticket booking.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-dark/60 uppercase text-[10px] font-bold">
                        <tr>
                            <th class="px-4 py-3">Quotation</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Trip</th>
                            <th class="px-4 py-3">Passengers</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quotations as $quotation)
                            @php $named = $quotation->passengers->count(); @endphp
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3">
                                    <a href="{{ route('ticketing.tickets.show', $quotation) }}" class="font-mono font-bold text-primary hover:underline">{{ $quotation->booking_reference }}</a>
                                    <span class="block text-[11px] text-dark/50">{{ $quotation->created_at->format('M d, Y') }}{{ $quotation->createdBy ? ' · '.$quotation->createdBy->name : '' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-dark">{{ $quotation->contact_name }}</span>
                                    <span class="block text-[11px] text-dark/50">{{ $quotation->contact_email ?: 'No email yet' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-dark">{{ $quotation->origin }} → {{ $quotation->destination }}</span>
                                    <span class="block text-[11px] {{ $quotation->departure_date?->isPast() && ! $quotation->departure_date?->isToday() ? 'text-rose-600 font-semibold' : 'text-dark/50' }}">
                                        {{ $quotation->departure_date?->format('M d, Y') }}{{ $quotation->departure_date?->isPast() && ! $quotation->departure_date?->isToday() ? ' · date passed' : '' }}
                                        @if ($quotation->package_name) · {{ $quotation->package_name }} @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-dark">{{ $quotation->total_passengers }} pax</span>
                                    <span class="block text-[11px] {{ $named < $quotation->total_passengers ? 'text-amber-700' : 'text-emerald-700' }}">{{ $named }} of {{ $quotation->total_passengers }} named</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-dark">₱{{ number_format((float) $quotation->total_amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('ticketing.tickets.show', $quotation) }}" class="px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-dark/70 hover:bg-gray-50">View</a>
                                        @can('ticketing.create')
                                            <a href="{{ route('ticketing.quotations.convert', $quotation) }}" class="px-3 py-1.5 rounded-lg bg-primary text-white text-[11px] font-bold hover:bg-navy whitespace-nowrap">Convert to booking</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($quotations->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $quotations->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
