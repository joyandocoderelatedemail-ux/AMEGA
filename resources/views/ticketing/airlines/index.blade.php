@extends('layouts.ticketing')

@section('title', 'Airlines - AMEGA')

@section('content')
@php
    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $fieldLabel = 'block text-xs font-semibold text-slate-600 mb-1';
    // Which form the old input belongs to: 'new', or the id of the airline being edited.
    $editing = old('editing');
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Airlines</h1>
        <p class="text-sm text-slate-500 mt-1">The airlines listed in the ticket wizard's fare search. Turning one off hides it from the wizard; tickets already booked on it keep it.</p>
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

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if ($airlines->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-slate-500">No airlines yet. Add the first one below.</p>
        @else
            <ul class="divide-y divide-slate-200">
                @foreach ($airlines as $airline)
                    <li x-data="{ open: {{ Js::from((string) $editing === (string) $airline->id) }} }" class="px-5 py-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                                    <span class="truncate">{{ $airline->name }}</span>
                                    @if ($airline->code)
                                        <span class="font-mono text-xs text-slate-500">{{ $airline->code }}</span>
                                    @endif
                                    @unless ($airline->is_active)
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-500">Off</span>
                                    @endunless
                                </p>
                                <p class="text-xs text-slate-500 truncate mt-0.5">
                                    <a href="{{ $airline->booking_url }}" target="_blank" rel="noopener noreferrer" class="hover:text-navy-700 hover:underline">{{ $airline->booking_url }}</a>
                                    @if ($airline->agent_portal_url)
                                        <span class="text-slate-300">&middot;</span>
                                        <a href="{{ $airline->agent_portal_url }}" target="_blank" rel="noopener noreferrer" class="hover:text-navy-700 hover:underline">Agent portal</a>
                                    @endif
                                </p>
                            </div>
                            <button type="button" @click="open = !open" :aria-expanded="open"
                                    class="shrink-0 inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                <span x-text="open ? 'Close' : 'Edit'">Edit</span>
                            </button>
                        </div>

                        <form x-show="open" x-cloak method="POST" action="{{ route('ticketing.airlines.update', $airline) }}"
                              class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-6 gap-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="editing" value="{{ $airline->id }}">
                            @php $mine = (string) $editing === (string) $airline->id; @endphp

                            <div class="sm:col-span-3">
                                <label class="{{ $fieldLabel }}" for="name-{{ $airline->id }}">Name</label>
                                <input id="name-{{ $airline->id }}" name="name" required maxlength="100" value="{{ $mine ? old('name') : $airline->name }}" class="{{ $input }}">
                            </div>
                            <div class="sm:col-span-1">
                                <label class="{{ $fieldLabel }}" for="code-{{ $airline->id }}">Code</label>
                                <input id="code-{{ $airline->id }}" name="code" maxlength="2" value="{{ $mine ? old('code') : $airline->code }}" class="{{ $input }} font-mono uppercase placeholder:normal-case">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="{{ $fieldLabel }}" for="order-{{ $airline->id }}">List order</label>
                                <input id="order-{{ $airline->id }}" name="sort_order" type="number" min="0" max="999" value="{{ $mine ? old('sort_order') : $airline->sort_order }}" class="{{ $input }}">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="{{ $fieldLabel }}" for="booking-{{ $airline->id }}">Booking site</label>
                                <input id="booking-{{ $airline->id }}" name="booking_url" type="url" required value="{{ $mine ? old('booking_url') : $airline->booking_url }}" class="{{ $input }}">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="{{ $fieldLabel }}" for="portal-{{ $airline->id }}">Agent portal <span class="font-normal text-slate-400">(optional)</span></label>
                                <input id="portal-{{ $airline->id }}" name="agent_portal_url" type="url" value="{{ $mine ? old('agent_portal_url') : $airline->agent_portal_url }}" class="{{ $input }}">
                            </div>
                            <div class="sm:col-span-6 flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" @checked($mine ? old('is_active') : $airline->is_active)
                                           class="w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                                    Show in the ticket wizard
                                </label>
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

    <form method="POST" action="{{ route('ticketing.airlines.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <h2 class="text-base font-semibold text-slate-900">Add an airline</h2>
        @csrf
        <input type="hidden" name="editing" value="new">
        @php $isNew = $editing === 'new'; @endphp

        <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
            <div class="sm:col-span-3">
                <label class="{{ $fieldLabel }}" for="new-name">Name</label>
                <input id="new-name" name="name" required maxlength="100" value="{{ $isNew ? old('name') : '' }}" placeholder="e.g. Cebgo" class="{{ $input }}">
            </div>
            <div class="sm:col-span-1">
                <label class="{{ $fieldLabel }}" for="new-code">Code</label>
                <input id="new-code" name="code" maxlength="2" value="{{ $isNew ? old('code') : '' }}" placeholder="DG" class="{{ $input }} font-mono uppercase placeholder:normal-case">
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $fieldLabel }}" for="new-order">List order</label>
                <input id="new-order" name="sort_order" type="number" min="0" max="999" value="{{ $isNew ? old('sort_order') : $airlines->max('sort_order') + 1 }}" class="{{ $input }}">
            </div>
            <div class="sm:col-span-3">
                <label class="{{ $fieldLabel }}" for="new-booking">Booking site</label>
                <input id="new-booking" name="booking_url" type="url" required value="{{ $isNew ? old('booking_url') : '' }}" placeholder="https://" class="{{ $input }}">
            </div>
            <div class="sm:col-span-3">
                <label class="{{ $fieldLabel }}" for="new-portal">Agent portal <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="new-portal" name="agent_portal_url" type="url" value="{{ $isNew ? old('agent_portal_url') : '' }}" placeholder="https://" class="{{ $input }}">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg bg-navy-700 text-white text-sm font-semibold hover:bg-navy-800">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Add airline
            </button>
        </div>
    </form>
</div>
@endsection
