@extends('layouts.admin')

@section('title', 'Expiring Documents - AMEGA Admin')
@section('page_title', 'Expiring Documents')

@php
    /**
     * Client visas, passports and ACR I-Cards with an expiry date on file, soonest first
     * (App\Support\ClientExpiries). Remind emails the client now (and texts them when SMS is on);
     * the daily job reminds each one a month ahead by itself. Ticketing tokens: rounded-xl cards,
     * rounded-lg controls; amber within a month, rose once expired.
     *
     * @var \Illuminate\Pagination\LengthAwarePaginator $rows
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $control = 'h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-500 focus:border-navy-500';
    $th = 'px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';
    $pill = [
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    $docIcon = ['visa' => 'stamp', 'passport' => 'book-open', 'acr' => 'id-card'];
@endphp

@section('content')
<div class="space-y-6">

    <div class="min-w-0">
        <p class="text-sm text-slate-500">Client visas, passports and ACR I-Cards, soonest first. Each client is reminded by email {{ \App\Support\ClientExpiries::AUTO_REMIND_DAYS }} days before by itself; Remind sends one now.</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([
            ['Expired', $summary['expired'], 'Already past their date', 'rose'],
            ['Within 30 days', $summary['month'], 'Reminded automatically', 'amber'],
            ['Within 90 days', $summary['quarter'], 'Coming up', 'slate'],
            ['Cannot be reached', $summary['unreachable'], 'Due soon, no email or phone', 'rose'],
        ] as [$label, $count, $meta, $tone])
            <div class="{{ $card }} p-5">
                <span class="text-sm font-semibold text-slate-600">{{ $label }}</span>
                <div class="mt-2 text-2xl font-bold tabular-nums {{ $count > 0 && $tone === 'rose' ? 'text-rose-700' : ($count > 0 && $tone === 'amber' ? 'text-amber-700' : 'text-slate-900') }}">{{ number_format($count) }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ $meta }}</div>
            </div>
        @endforeach
    </div>

    <section class="{{ $card }} overflow-hidden">
        <form method="GET" action="{{ route('admin.expiring-documents.index') }}" class="flex flex-col gap-3 px-4 sm:px-5 py-4 border-b border-slate-200 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <select name="document" onchange="this.form.submit()" class="{{ $control }} pr-9" aria-label="Document">
                    <option value="">All documents</option>
                    @foreach (\App\Support\ClientExpiries::DOCUMENTS as $key => $label)
                        <option value="{{ $key }}" @selected($filters['document'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="within" onchange="this.form.submit()" class="{{ $control }} pr-9" aria-label="Expiring within">
                    @foreach (\App\Http\Controllers\Admin\AdminExpiringDocumentsController::WINDOWS as $key => $label)
                        <option value="{{ $key }}" @selected($filters['window'] === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="expired" value="0">
                <label class="inline-flex items-center gap-2 h-10 px-3 rounded-lg border border-slate-300 bg-white text-sm text-slate-700">
                    <input type="checkbox" name="expired" value="1" @checked($filters['expired']) onchange="this.form.submit()" class="rounded border-slate-300 text-navy-700 focus:ring-navy-500">
                    Include expired
                </label>
            </div>
            <div class="flex items-center gap-2">
                <label for="expiry-search" class="sr-only">Search clients</label>
                <input id="expiry-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Name, email, phone or number" class="{{ $control }} w-full lg:w-72">
                <button type="submit" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Search</button>
            </div>
        </form>

        @if ($rows->isEmpty())
            <div class="px-6 py-14 text-center">
                <i data-lucide="calendar-check" class="w-8 h-8 text-slate-300 mx-auto" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-semibold text-slate-900">Nothing expiring here</p>
                <p class="mt-1 text-sm text-slate-500">No client document matches these filters. Try a longer period or all documents.</p>
            </div>
        @else
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="{{ $th }} pl-4 sm:pl-5">Client</th>
                            <th scope="col" class="{{ $th }}">Document</th>
                            <th scope="col" class="{{ $th }}">Expires</th>
                            <th scope="col" class="{{ $th }}">Last reminded</th>
                            <th scope="col" class="{{ $th }} pr-4 sm:pr-5"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="pl-4 sm:pl-5 pr-4 py-3.5">
                                    <a href="{{ $row['url'] }}" class="font-semibold text-slate-900 hover:underline">{{ $row['name'] }}</a>
                                    <div class="mt-0.5 text-xs text-slate-500">
                                        @if ($row['phone'])
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $row['phone']) }}" class="font-semibold text-navy-700 hover:underline">{{ $row['phone'] }}</a>
                                        @endif
                                        @if ($row['phone'] && $row['email']) &middot; @endif
                                        {{ $row['email'] }}
                                        @if (! $row['phone'] && ! $row['email'])
                                            <span class="text-rose-700">No email or phone on file</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 font-semibold text-slate-900">
                                        <i data-lucide="{{ $docIcon[$row['document']] }}" class="w-4 h-4 text-slate-400" aria-hidden="true"></i>
                                        {{ $row['label'] }}
                                    </span>
                                    @if ($row['number'])
                                        <div class="mt-0.5 text-xs text-slate-500 font-mono">{{ $row['number'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $pill[$row['tone']] }}">{{ $row['when'] }}</span>
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $row['date']->format('M j, Y') }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-600 whitespace-nowrap">
                                    @if ($row['reminded'])
                                        {{ $row['reminded']->created_at->format('M j, Y') }}
                                        <div class="text-slate-500">{{ $row['reminded']->isAutomatic() ? 'Automatic' : 'By '.($row['reminded']->sentBy?->name ?? 'staff') }} &middot; {{ str_replace('+', ' + ', $row['reminded']->channel) }}</div>
                                    @else
                                        <span class="text-slate-400">Not yet</span>
                                    @endif
                                </td>
                                <td class="pl-4 pr-4 sm:pr-5 py-3.5 text-right whitespace-nowrap">
                                    @if ($row['email'] || ($row['phone'] && \App\Services\SmsSender::enabled()))
                                        <form method="POST" action="{{ route('admin.expiring-documents.remind') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="key" value="{{ $row['key'] }}">
                                            <button type="submit" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-navy-700 text-white text-xs font-semibold hover:bg-navy-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                                                <i data-lucide="bell-ring" class="w-3.5 h-3.5" aria-hidden="true"></i>
                                                Remind
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400" title="No email address on file">Call instead</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">{{ $rows->links() }}</div>
            @endif
        @endif
    </section>
</div>
@endsection
