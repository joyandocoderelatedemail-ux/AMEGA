@extends('layouts.immigration')

@section('title', $quotation->quote_number.' - AMEGA Admin')

@php
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $tones = [
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    $button = 'inline-flex items-center gap-2 h-10 px-4 rounded-lg text-sm font-semibold transition-colors';
    $declined = $quotation->status === \App\Models\ImmigrationQuotation::STATUS_DECLINED;
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <a href="{{ route('admin.immigration.quotations.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">&larr; Quotations</a>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight font-mono">{{ $quotation->quote_number }}</h1>
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 {{ $tones[$quotation->statusTone()] }}">{{ $quotation->statusLabel() }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Prepared {{ $quotation->created_at->format('M j, Y') }} by {{ $quotation->createdBy?->name ?? '—' }}
                &middot; valid until {{ $quotation->valid_until->format('M j, Y') }}
                @if ($quotation->emailed_at) &middot; emailed {{ $quotation->emailed_at->format('M j, Y g:i A') }} @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.immigration.quotations.pdf', $quotation) }}" target="_blank" rel="noopener" class="{{ $button }} bg-navy-700 text-white hover:bg-navy-800">
                <i data-lucide="printer" class="w-4 h-4"></i><span>Print / PDF</span>
            </a>
            <form method="POST" action="{{ route('admin.immigration.quotations.email', $quotation) }}">
                @csrf
                <button type="submit" class="{{ $button }} bg-white border border-slate-300 text-slate-800 hover:bg-slate-50" @disabled(! \App\Services\ClientNotifier::canReceive($quotation->client_email))
                        title="{{ \App\Services\ClientNotifier::canReceive($quotation->client_email) ? 'Email the PDF to '.$quotation->client_email : 'No email address on this quotation' }}">
                    <i data-lucide="mail" class="w-4 h-4"></i><span>Email to client</span>
                </button>
            </form>
            @if ($quotation->isOpen())
                <a href="{{ route('admin.immigration.quotations.edit', $quotation) }}" class="{{ $button }} bg-white border border-slate-300 text-slate-800 hover:bg-slate-50">
                    <i data-lucide="pencil" class="w-4 h-4"></i><span>Edit</span>
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="{{ $card }} p-5 space-y-1 lg:col-span-1">
            <div class="text-xs font-semibold text-slate-500">Client</div>
            <div class="text-base font-semibold text-slate-900">{{ $quotation->client_name }}</div>
            @foreach ([$quotation->nationality, $quotation->passport_number ? 'Passport '.$quotation->passport_number : null, $quotation->client_email, $quotation->client_phone] as $line)
                @if ($line) <div class="text-sm text-slate-600">{{ $line }}</div> @endif
            @endforeach
            @if ($quotation->immigrationClient)
                <a href="{{ route('admin.client-sheets.edit', $quotation->immigrationClient) }}" class="inline-block pt-2 text-sm font-semibold text-navy-700 hover:underline">Open client folder</a>
            @endif
        </div>

        <div class="{{ $card }} p-5 lg:col-span-2 space-y-3">
            <div class="text-xs font-semibold text-slate-500">Where it stands</div>
            @if ($quotation->isOpen())
                <p class="text-sm text-slate-700">{{ $quotation->isExpired() ? 'This quotation has expired. Edit it to give a new date, or mark what the client decided.' : 'Waiting for the client to decide.' }}</p>
            @elseif ($quotation->status === \App\Models\ImmigrationQuotation::STATUS_ACCEPTED)
                <p class="text-sm text-slate-700">Accepted {{ $quotation->accepted_at?->format('M j, Y') }}. Open each service's file from the lines below.</p>
            @else
                <p class="text-sm text-slate-700">Declined {{ $quotation->declined_at?->format('M j, Y') }}.</p>
            @endif
            <div class="flex flex-wrap gap-2">
                @foreach (['accepted' => ['Mark accepted', 'check-circle', 'bg-emerald-600 text-white hover:bg-emerald-700'], 'declined' => ['Mark declined', 'x-circle', 'bg-white border border-slate-300 text-slate-800 hover:bg-slate-50'], 'open' => ['Reopen', 'rotate-ccw', 'bg-white border border-slate-300 text-slate-800 hover:bg-slate-50']] as $to => [$text, $icon, $style])
                    @continue($quotation->status === $to)
                    <form method="POST" action="{{ route('admin.immigration.quotations.status', $quotation) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $to }}">
                        <button type="submit" class="{{ $button }} {{ $style }}"><i data-lucide="{{ $icon }}" class="w-4 h-4"></i><span>{{ $text }}</span></button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    <div class="{{ $card }} overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500">#</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500">Service / description</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Qty</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Price</th>
                    <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Amount</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($quotation->items as $index => $item)
                    <tr>
                        <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $item->description }}</div>
                            @if ($item->serviceLabel()) <div class="text-xs text-slate-500">{{ $item->serviceLabel() }}</div> @endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right text-slate-700 whitespace-nowrap">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">₱{{ number_format((float) $item->amount, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($item->opensFile() && ! $declined)
                                <form method="POST" action="{{ route('admin.immigration.quotations.start', [$quotation, $item]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-navy-50 text-navy-700 text-xs font-semibold hover:bg-navy-100 whitespace-nowrap">
                                        <i data-lucide="folder-open" class="w-3.5 h-3.5"></i><span>Open file</span>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-slate-200">
                <tr><td colspan="4" class="px-4 py-2 text-right text-slate-500">Subtotal</td><td class="px-4 py-2 text-right font-semibold text-slate-900 whitespace-nowrap">₱{{ number_format((float) $quotation->subtotal, 2) }}</td><td></td></tr>
                @if ((float) $quotation->discount > 0)
                    <tr><td colspan="4" class="px-4 py-2 text-right text-slate-500">Discount</td><td class="px-4 py-2 text-right font-semibold text-slate-900 whitespace-nowrap">-₱{{ number_format((float) $quotation->discount, 2) }}</td><td></td></tr>
                @endif
                <tr><td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-700">Total</td><td class="px-4 py-3 text-right text-base font-bold text-slate-900 whitespace-nowrap">₱{{ number_format((float) $quotation->total, 2) }}</td><td></td></tr>
            </tfoot>
        </table>
    </div>

    @if ($quotation->notes)
        <div class="{{ $card }} p-5">
            <div class="text-xs font-semibold text-slate-500 mb-1">Notes for the client</div>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $quotation->notes }}</p>
        </div>
    @endif

    @if (auth()->user()->isAdmin())
        <form method="POST" action="{{ route('admin.immigration.quotations.destroy', $quotation) }}" onsubmit="return confirm('Delete quotation {{ $quotation->quote_number }}? This cannot be undone.')" class="text-right">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-semibold text-rose-600 hover:text-rose-700">Delete quotation</button>
        </form>
    @endif
</div>
@endsection
