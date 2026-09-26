@extends('layouts.srrv')

@section('title', 'Annual Renewals - AMEGA Travel and Tours')

@section('content')

    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
                <i data-lucide="calendar-check" class="w-4 h-4"></i>
                <span>Annual Renewals</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">Renewal Ledger</h1>
            <p class="text-sm text-dark/50 mt-1">Due once a year, for as long as the retiree stays.</p>
        </div>
        <a href="{{ route('srrv.renewals.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>Record Renewal</span>
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        @foreach([
            ['label' => 'Total', 'value' => $stats['total'], 'icon' => 'calendar-check'],
            ['label' => 'Classic', 'value' => $stats['classic'], 'icon' => 'badge-check'],
            ['label' => 'Courtesy', 'value' => $stats['courtesy'], 'icon' => 'shield-check'],
            ['label' => 'Open', 'value' => $stats['open'], 'icon' => 'loader-circle'],
            ['label' => 'Ready at PRA', 'value' => $stats['ready'], 'icon' => 'package-check'],
        ] as $card)
            <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">{{ $card['label'] }}</span>
                    <i data-lucide="{{ $card['icon'] }}" class="w-4 h-4 text-primary"></i>
                </div>
                <div class="font-heading text-xl font-extrabold text-dark">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('srrv.renewals.index') }}"
          class="rounded-2xl bg-white border border-gray-100 shadow-sm p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="lg:col-span-2">
                <label for="search" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Search</label>
                <input id="search" type="text" name="search" value="{{ request('search') }}"
                       placeholder="Reference, retiree or SRRV card"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="visa_class" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Class</label>
                <select id="visa_class" name="visa_class"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Both classes</option>
                    <option value="classic" {{ request('visa_class') === 'classic' ? 'selected' : '' }}>Classic</option>
                    <option value="courtesy" {{ request('visa_class') === 'courtesy' ? 'selected' : '' }}>Courtesy</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Status</label>
                <select id="status" name="status"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Any status</option>
                    @foreach(\App\Models\SrrvRenewal::STATUSES as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ \App\Models\SrrvRenewal::STAGE_LABELS[$status] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 mt-3 pt-3 border-t border-gray-100">
            @if(request()->hasAny(['search', 'visa_class', 'status']))
                <a href="{{ route('srrv.renewals.index') }}"
                   class="px-4 py-2 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Clear</a>
            @endif
            <button type="submit"
                    class="px-5 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">
                Apply Filters
            </button>
        </div>
    </form>

    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
        @if($renewals->isEmpty())
            <div class="px-6 py-16 text-center">
                <i data-lucide="calendar-check" class="w-9 h-9 text-dark/20 mx-auto mb-3"></i>
                <p class="text-sm font-bold text-dark/50">No renewals recorded.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-dark/50">
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Retiree</th>
                            <th class="px-5 py-3">Class</th>
                            <th class="px-5 py-3">Years</th>
                            <th class="px-5 py-3 text-right">Fee</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($renewals as $renewal)
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-5 py-3">
                                    <a href="{{ route('srrv.renewals.show', $renewal) }}"
                                       class="text-xs font-bold text-primary hover:underline">{{ $renewal->reference }}</a>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="text-xs font-semibold text-dark">{{ $renewal->retiree_name }}</div>
                                    @if($renewal->srrv_card_number)
                                        <div class="text-[10px] text-dark/45">Card {{ $renewal->srrv_card_number }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $renewal->visa_class === 'courtesy' ? 'bg-amber-100 text-amber-800' : 'bg-primary/10 text-primary' }}">
                                        {{ $renewal->visa_class }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-dark/60">{{ $renewal->years_paid }}</td>
                                <td class="px-5 py-3 text-right text-xs font-heading font-extrabold text-dark whitespace-nowrap">
                                    {{ $renewal->currency }} {{ number_format((float) $renewal->fee_amount, 2) }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider whitespace-nowrap {{ $renewal->status === 'collected' ? 'bg-emerald-100 text-emerald-800' : ($renewal->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $renewal->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('srrv.renewals.show', $renewal) }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 text-dark/70 font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors">
                                        <span>Open</span>
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($renewals->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">{{ $renewals->links() }}</div>
            @endif
        @endif
    </div>

@endsection
