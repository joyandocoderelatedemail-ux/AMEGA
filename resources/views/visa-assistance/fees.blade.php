@extends('layouts.visa-assistance')

@section('title', 'Fees - Visa Assistance Counter')

@php
    /**
     * The counter's fee schedule, one table per service, read as plain figures. With "Manage prices"
     * each row has Edit, which opens its form under the row, and each table has Add a fee.
     * A fee is on the counter's price list when it is both Shown and Confirmed.
     */
    $card = 'bg-white rounded-xl border border-slate-200 shadow-sm';
    $th = 'px-4 py-3 text-xs font-semibold text-slate-500 whitespace-nowrap';
    $money = fn ($value) => $value === null ? '—' : '₱'.number_format((float) $value, 0);
    $isAdmin = auth()->user()?->isAdmin();
    // The form that failed validation reopens with what was typed.
    $failed = $errors->any() ? old('_fee') : null;
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <a href="{{ route('visa.dashboard') }}" class="text-xs font-semibold text-navy-700 hover:underline">&larr; Visa Assistance Counter</a>
            <h1 class="mt-1 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Fees</h1>
            <p class="mt-1 text-sm text-slate-500">Per person, the same for adults, children and infants.</p>
        </div>
        @unless ($canManage)
            <p class="inline-flex items-center gap-2 text-xs text-slate-500">
                <i data-lucide="lock" class="w-4 h-4" aria-hidden="true"></i>
                Read only. Changing a fee takes the "Manage prices" permission.
            </p>
        @endunless
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <p class="text-sm font-semibold text-rose-900">The fee was not saved.</p>
            <ul class="mt-1 text-sm text-rose-800 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach ($groups as $group)
        @php
            $newKey = 'new-'.$group['key'];
            $open = $failed === $newKey ? 'new' : (str_starts_with((string) $failed, 'new-') ? null : $failed);
            $open = $group['fees']->contains('id', (int) $open) || $open === 'new' ? $open : null;
        @endphp
        <section class="{{ $card }} overflow-hidden" aria-labelledby="fees-{{ $group['key'] }}"
                 x-data="{ editing: {{ Js::from($open === 'new' ? 'new' : ($open ? (int) $open : null)) }} }">
            <div class="flex items-center justify-between gap-4 px-4 sm:px-5 py-4 border-b border-slate-200">
                <div class="min-w-0">
                    <h2 id="fees-{{ $group['key'] }}" class="text-base font-bold text-slate-900">{{ $group['label'] }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $group['fees']->count() }} {{ Str::plural('fee', $group['fees']->count()) }}</p>
                </div>
                @if ($canManage)
                    <button type="button" @click="editing = editing === 'new' ? null : 'new'"
                            class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-800 hover:bg-slate-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">
                        <i data-lucide="plus" class="w-3.5 h-3.5" aria-hidden="true"></i> Add a fee
                    </button>
                @endif
            </div>

            @if ($canManage)
                <div x-show="editing === 'new'" @if ($open !== 'new') style="display: none" @endif class="px-4 sm:px-5 py-5 bg-slate-50 border-b border-slate-200">
                    <p class="mb-4 text-sm font-semibold text-slate-900">New {{ $group['label'] }} fee</p>
                    @include('visa-assistance._fee-form', [
                        'fee' => null,
                        'formKey' => $newKey,
                        'action' => route('visa.fees.store'),
                        'service' => $group['key'],
                        'sortOrder' => ($group['fees']->max('sort_order') ?? 0) + 1,
                        'useOld' => $failed === $newKey,
                    ])
                </div>
            @endif

            <div class="relative overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="{{ $th }} pl-4 sm:pl-5">Fee</th>
                            <th scope="col" class="{{ $th }}">Country</th>
                            <th scope="col" class="{{ $th }} text-right">Service fee</th>
                            <th scope="col" class="{{ $th }} text-right">Visa fee</th>
                            <th scope="col" class="{{ $th }} text-right">Insurance</th>
                            <th scope="col" class="{{ $th }} text-right">Price</th>
                            <th scope="col" class="{{ $th }}">Processing</th>
                            <th scope="col" class="{{ $th }}">Status</th>
                            @if ($canManage)
                                <th scope="col" class="{{ $th }} pr-4 sm:pr-5"><span class="sr-only">Actions</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($group['fees'] as $fee)
                            <tr class="align-top hover:bg-slate-50 transition-colors" :class="editing === {{ $fee->id }} ? 'bg-slate-50' : ''">
                                <td class="pl-4 sm:pl-5 pr-4 py-3.5 min-w-[14rem] max-w-sm">
                                    <div class="font-semibold text-slate-900">{{ $fee->label }}</div>
                                    @if ($fee->inclusions)
                                        <div class="mt-0.5 text-xs text-slate-600">Includes {{ $fee->inclusions }}</div>
                                    @endif
                                    @if ($fee->condition_notes)
                                        <div class="mt-0.5 text-xs text-slate-500 line-clamp-2" title="{{ $fee->condition_notes }}">{{ $fee->condition_notes }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-slate-700 whitespace-nowrap">{{ $fee->country ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-700 whitespace-nowrap">{{ $money($fee->service_fee) }}</td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-700 whitespace-nowrap">{{ $money($fee->visa_fee) }}</td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-700 whitespace-nowrap">{{ $money($fee->insurance_fee) }}</td>
                                <td class="px-4 py-3.5 text-right tabular-nums font-bold text-slate-900 whitespace-nowrap">{{ $money($fee->amount) }}</td>
                                <td class="px-4 py-3.5 text-slate-700 whitespace-nowrap">{{ $fee->processing_time ?: '—' }}</td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    @if ($fee->is_active && ! $fee->needs_review)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>On the price list</span>
                                    @elseif ($fee->needs_review)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20"><span class="w-1.5 h-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>Not confirmed</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600"><span class="w-1.5 h-1.5 rounded-full bg-slate-400" aria-hidden="true"></span>Hidden</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="pl-4 pr-4 sm:pr-5 py-3.5 whitespace-nowrap text-right">
                                        <button type="button" @click="editing = editing === {{ $fee->id }} ? null : {{ $fee->id }}"
                                                :aria-expanded="editing === {{ $fee->id }}"
                                                class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5" aria-hidden="true"></i>
                                            <span x-text="editing === {{ $fee->id }} ? 'Close' : 'Edit'">Edit</span>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                            @if ($canManage)
                                <tr x-show="editing === {{ $fee->id }}" @if ((string) $open !== (string) $fee->id) style="display: none" @endif class="bg-slate-50">
                                    <td colspan="9" class="px-4 sm:px-5 pt-2 pb-5">
                                        @include('visa-assistance._fee-form', [
                                            'fee' => $fee,
                                            'formKey' => (string) $fee->id,
                                            'action' => route('visa.fees.update', $fee),
                                            'service' => $group['key'],
                                            'sortOrder' => $fee->sort_order,
                                            'useOld' => (string) $failed === (string) $fee->id,
                                        ])
                                        @if ($isAdmin)
                                            <form method="POST" action="{{ route('visa.fees.destroy', $fee) }}" class="mt-3 flex justify-end" onsubmit="return confirm('Delete this fee? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700 hover:underline">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5" aria-hidden="true"></i> Delete this fee
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-8 text-center text-sm text-slate-500">No fees for {{ $group['label'] }} yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <p class="text-xs text-slate-500">A fee reaches the counter's forms only when it is both <strong class="font-semibold text-slate-700">Shown</strong> and <strong class="font-semibold text-slate-700">Confirmed</strong>. When a service fee or visa fee is filled in, the price is their sum.</p>
</div>
@endsection
