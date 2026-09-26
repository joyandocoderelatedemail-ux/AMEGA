@extends('layouts.admin')

@section('title', $agent->name.' - Staff Activity - AMEGA Admin')
@section('page_title', 'Staff Activity')

@php
    /**
     * One staff account's period: headline figures, the desk files they
     * opened, and their activity log. Tokens follow the admin dashboard.
     */
    $card = 'bg-white rounded-2xl border border-slate-200/80 shadow-sm';
    $label = 'text-xs font-semibold uppercase tracking-wide text-slate-500';
    $figure = 'font-heading text-3xl font-extrabold text-slate-900 tracking-tight tabular-nums';
    $th = 'text-xs font-semibold uppercase tracking-wide text-slate-500 px-3 py-3 whitespace-nowrap';
    $money = fn (?string $currency, float $amount): string => (($currency ?? 'PHP') === 'PHP' ? '₱' : $currency.' ').number_format($amount, 2);

    // Every desk file they opened, newest first, in one list.
    $files = collect()
        ->concat($tickets->map(fn ($t) => [
            'at' => $t->created_at, 'type' => 'Ticket', 'reference' => $t->booking_reference, 'client' => $t->contact_name,
            'status' => $t->is_quotation ? 'Quotation' : ucfirst((string) $t->status), 'amount' => $money('PHP', (float) $t->total_amount),
            'url' => route('ticketing.tickets.show', $t),
        ]))
        ->concat($visaFiles->map(fn ($v) => [
            'at' => $v->created_at, 'type' => 'Visa', 'reference' => $v->reference, 'client' => $v->client_name,
            'status' => $v->status_label, 'amount' => $money($v->currency, (float) $v->total_amount),
            'url' => route('visa.applications.show', $v),
        ]))
        ->concat($srrvApplications->map(fn ($a) => [
            'at' => $a->created_at, 'type' => 'SRRV', 'reference' => $a->reference, 'client' => $a->retiree_name,
            'status' => ucfirst(str_replace('_', ' ', (string) $a->status)), 'amount' => $money($a->currency, (float) $a->service_fee),
            'url' => route('srrv.applications.show', $a),
        ]))
        ->concat($srrvRenewals->map(fn ($r) => [
            'at' => $r->created_at, 'type' => 'SRRV renewal', 'reference' => $r->reference, 'client' => $r->retiree_name,
            'status' => ucfirst(str_replace('_', ' ', (string) $r->status)), 'amount' => $money($r->currency, (float) $r->fee_amount),
            'url' => route('srrv.renewals.show', $r),
        ]))
        ->sortByDesc('at')
        ->values();

    $editUrl = $agent->isAgent() ? route('admin.agents.edit', $agent) : route('admin.users.edit', $agent);
@endphp

@section('content')
<div class="space-y-4 sm:space-y-6">

    <div class="{{ $card }} p-5 sm:p-6">
        <a href="{{ route('admin.agents.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-navy-700 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            Staff Accounts
        </a>

        <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-4">
            <span class="w-14 h-14 rounded-full bg-navy-700 text-white text-xl font-bold flex items-center justify-center shrink-0" aria-hidden="true">
                {{ mb_strtoupper(mb_substr($agent->name, 0, 1)) }}
            </span>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight truncate">{{ $agent->name }}</h1>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                    <span class="inline-flex px-2 py-0.5 rounded-lg bg-navy-50 text-navy-700 text-xs font-semibold">{{ $staffRoles[$agent->role] ?? ucfirst($agent->role) }}</span>
                    <span class="truncate">{{ $agent->email }}</span>
                    @if ($agent->phone)
                        <span class="text-slate-300" aria-hidden="true">&bull;</span>
                        <span>{{ $agent->phone }}</span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Account created {{ $agent->created_at?->format('M j, Y') ?? '—' }}
                    &middot; Last active {{ $summary['lastActiveAt']?->diffForHumans() ?? 'never' }}
                </p>
            </div>
            <a href="{{ $editUrl }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors self-start sm:self-auto">
                <i data-lucide="{{ $agent->isAgent() ? 'shield-check' : 'pencil' }}" class="w-4 h-4"></i>
                {{ $agent->isAgent() ? 'Permissions' : 'Edit account' }}
            </a>
        </div>
    </div>

    @include('admin.partials._date-range', ['range' => $range, 'action' => route('admin.agents.show', $agent), 'keep' => []])

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="{{ $card }} p-5">
            <span class="{{ $label }}">Files opened</span>
            <div class="{{ $figure }} mt-3">{{ number_format($summary['files']) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600">
                <span class="font-bold text-slate-900 tabular-nums">{{ $summary['tickets'] }}</span> tickets
                <span class="text-slate-300" aria-hidden="true">&bull;</span>
                <span class="font-bold text-slate-900 tabular-nums">{{ $summary['visa'] }}</span> visa
                <span class="text-slate-300" aria-hidden="true">&bull;</span>
                <span class="font-bold text-slate-900 tabular-nums">{{ $summary['srrv'] }}</span> SRRV
            </div>
        </div>
        <div class="{{ $card }} p-5">
            <span class="{{ $label }}">Tickets issued</span>
            <div class="{{ $figure }} mt-3">{{ number_format($summary['issued']) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600">Issued by {{ $agent->name }}</div>
        </div>
        <div class="{{ $card }} p-5">
            <span class="{{ $label }}">Ticket sales</span>
            <div class="{{ $figure }} mt-3">&#8369;{{ number_format($summary['sales'], 0) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600">
                <span class="font-bold text-emerald-700 tabular-nums">&#8369;{{ number_format($collected, 0) }}</span> collected
            </div>
        </div>
        <div class="{{ $card }} p-5">
            <span class="{{ $label }}">Actions logged</span>
            <div class="{{ $figure }} mt-3">{{ number_format($summary['actions']) }}</div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600">From the activity log</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">

        <section class="xl:col-span-7 {{ $card }} overflow-hidden" aria-labelledby="files-heading">
            <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                <h2 id="files-heading" class="font-heading text-base font-bold text-slate-900">Files opened</h2>
                <p class="text-xs text-slate-500 mt-0.5">Tickets, visa and SRRV files &middot; {{ $range->label() }}</p>
            </div>

            @if ($files->isNotEmpty())
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th scope="col" class="{{ $th }} pl-5 sm:pl-6">Opened</th>
                                <th scope="col" class="{{ $th }}">File</th>
                                <th scope="col" class="{{ $th }} hidden sm:table-cell">Status</th>
                                <th scope="col" class="{{ $th }} pr-5 sm:pr-6 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($files as $file)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="pl-5 sm:pl-6 pr-3 py-3 text-xs text-slate-500 whitespace-nowrap tabular-nums">{{ $file['at']?->format('M j, Y') }}</td>
                                    <td class="px-3 py-3">
                                        <a href="{{ $file['url'] }}" class="font-mono text-xs font-semibold text-navy-700 hover:underline whitespace-nowrap">{{ $file['reference'] }}</a>
                                        <div class="text-xs text-slate-500 truncate max-w-[220px]">{{ $file['type'] }} &middot; {{ $file['client'] }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-xs font-semibold text-slate-600 hidden sm:table-cell whitespace-nowrap">{{ $file['status'] }}</td>
                                    <td class="pl-3 pr-5 sm:pr-6 py-3 text-right text-sm text-slate-900 tabular-nums whitespace-nowrap">{{ $file['amount'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center gap-2 py-12 px-6">
                    <i data-lucide="folder-open" class="w-7 h-7 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No files opened in this period</p>
                </div>
            @endif
        </section>

        <section class="xl:col-span-5 {{ $card }} overflow-hidden" aria-labelledby="activity-heading">
            <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-100">
                <div>
                    <h2 id="activity-heading" class="font-heading text-base font-bold text-slate-900">Activity log</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Latest {{ $activity->count() }} &middot; {{ $range->label() }}</p>
                </div>
                <a href="{{ route('admin.activity-logs.index', ['search' => $agent->name]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-navy-700 bg-navy-50 hover:bg-navy-100 transition-colors whitespace-nowrap">
                    Full log
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            @if ($activity->isNotEmpty())
                <ul class="divide-y divide-slate-100 max-h-[560px] overflow-y-auto">
                    @foreach ($activity as $entry)
                        <li class="px-5 sm:px-6 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex px-2 py-0.5 rounded-lg bg-slate-100 text-xs font-semibold text-slate-600 whitespace-nowrap">{{ $entry->module }} &middot; {{ $entry->action }}</span>
                                <time class="text-xs text-slate-400 whitespace-nowrap" datetime="{{ $entry->created_at?->toIso8601String() }}" title="{{ $entry->created_at?->format('M j, Y g:i A') }}">
                                    {{ $entry->created_at?->diffForHumans() }}
                                </time>
                            </div>
                            <p class="mt-1 text-sm text-slate-700">{{ $entry->description }}</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex flex-col items-center justify-center text-center gap-2 py-12 px-6">
                    <i data-lucide="history" class="w-7 h-7 text-slate-300"></i>
                    <p class="text-sm font-semibold text-slate-500">No activity in this period</p>
                </div>
            @endif
        </section>
    </div>

</div>
@endsection
