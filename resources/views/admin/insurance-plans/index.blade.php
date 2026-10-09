@extends('layouts.admin')

@section('title', 'Travel Insurance - AMEGA Admin')
@section('page_title', 'Travel Insurance Plans')

@section('content')
@php
    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $fieldLabel = 'block text-xs font-semibold text-slate-600 mb-1';
    // Which form the old input belongs to: 'new', or the id of the plan being edited.
    $editing = old('editing');
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">Travel insurance plans</h2>
        <p class="text-sm text-slate-500 mt-1">The plans the ticketing desk can add under Visa, Contact &amp; Extras. The price is per passenger and fills in the booking's insurance fee. Turning a plan off hides it from new bookings; bookings already made on it keep its name. With every plan off, the insurance option is hidden.</p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <ul class="text-sm text-rose-900 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        @if ($plans->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-slate-500">No plans yet. Add the first one below.</p>
        @else
            <ul class="divide-y divide-slate-200">
                @foreach ($plans as $plan)
                    @php $mine = (string) $editing === (string) $plan->id; @endphp
                    <li x-data="{ open: {{ Js::from($mine) }} }" class="px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 flex flex-wrap items-center gap-2">
                                    {{ $plan->name }}
                                    <span class="font-mono text-xs text-slate-500">₱{{ number_format($plan->price_per_pax, 2) }} / pax</span>
                                    @if ($plan->is_popular)
                                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-xs font-semibold text-amber-800">Most Popular</span>
                                    @endif
                                    @unless ($plan->is_active)
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-500">Off</span>
                                    @endunless
                                </p>
                                <p class="text-xs text-slate-500 mt-0.5 truncate">{{ implode(' · ', $plan->coverage ?? []) ?: 'No coverage listed' }}</p>
                            </div>
                            <button type="button" @click="open = !open" :aria-expanded="open"
                                    class="shrink-0 inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                <span x-text="open ? 'Close' : 'Edit'">Edit</span>
                            </button>
                        </div>

                        <form x-show="open" x-cloak method="POST" action="{{ route('admin.insurance-plans.update', $plan) }}"
                              class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-6 gap-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="editing" value="{{ $plan->id }}">

                            <div class="sm:col-span-3">
                                <label class="{{ $fieldLabel }}" for="name-{{ $plan->id }}">Plan name</label>
                                <input id="name-{{ $plan->id }}" name="name" required maxlength="100" value="{{ $mine ? old('name') : $plan->name }}" class="{{ $input }}">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="{{ $fieldLabel }}" for="price-{{ $plan->id }}">Price per passenger (₱)</label>
                                <input id="price-{{ $plan->id }}" name="price_per_pax" type="number" step="0.01" min="0" required value="{{ $mine ? old('price_per_pax') : $plan->price_per_pax }}" class="{{ $input }} font-mono">
                            </div>
                            <div class="sm:col-span-1">
                                <label class="{{ $fieldLabel }}" for="order-{{ $plan->id }}">Order</label>
                                <input id="order-{{ $plan->id }}" name="sort_order" type="number" min="0" max="999" value="{{ $mine ? old('sort_order') : $plan->sort_order }}" class="{{ $input }}">
                            </div>
                            <div class="sm:col-span-6">
                                <label class="{{ $fieldLabel }}" for="coverage-{{ $plan->id }}">What it covers <span class="font-normal text-slate-400">(one benefit per line)</span></label>
                                <textarea id="coverage-{{ $plan->id }}" name="coverage" rows="4" maxlength="2000" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">{{ $mine ? old('coverage') : implode("\n", $plan->coverage ?? []) }}</textarea>
                            </div>
                            <div class="sm:col-span-6 flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" @checked($mine ? old('is_active') : $plan->is_active)
                                               class="w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                                        Offer in the ticket wizard
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="is_popular" value="1" @checked($mine ? old('is_popular') : $plan->is_popular)
                                               class="w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                                        Mark as Most Popular
                                    </label>
                                </div>
                                <button type="submit" class="inline-flex items-center justify-center h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                                    Save changes
                                </button>
                            </div>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.insurance-plans.store') }}" class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
        <h2 class="text-base font-semibold text-slate-900">Add a plan</h2>
        @csrf
        <input type="hidden" name="editing" value="new">
        @php $isNew = $editing === 'new'; @endphp

        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
            <div class="sm:col-span-3">
                <label class="{{ $fieldLabel }}" for="new-name">Plan name</label>
                <input id="new-name" name="name" required maxlength="100" value="{{ $isNew ? old('name') : '' }}" placeholder="e.g. Family Plan" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $fieldLabel }}" for="new-price">Price per passenger (₱)</label>
                <input id="new-price" name="price_per_pax" type="number" step="0.01" min="0" required value="{{ $isNew ? old('price_per_pax') : '' }}" class="{{ $input }} font-mono">
            </div>
            <div class="sm:col-span-1">
                <label class="{{ $fieldLabel }}" for="new-order">Order</label>
                <input id="new-order" name="sort_order" type="number" min="0" max="999" value="{{ $isNew ? old('sort_order') : $plans->max('sort_order') + 1 }}" class="{{ $input }}">
            </div>
            <div class="sm:col-span-6">
                <label class="{{ $fieldLabel }}" for="new-coverage">What it covers <span class="font-normal text-slate-400">(one benefit per line)</span></label>
                <textarea id="new-coverage" name="coverage" rows="4" maxlength="2000" placeholder="Up to $50,000 Medical&#10;Trip Cancellation Coverage" class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600">{{ $isNew ? old('coverage') : '' }}</textarea>
            </div>
            <div class="sm:col-span-6">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_popular" value="1" @checked($isNew && old('is_popular'))
                           class="w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                    Mark as Most Popular
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Add plan
            </button>
        </div>
    </form>
</div>
@endsection
