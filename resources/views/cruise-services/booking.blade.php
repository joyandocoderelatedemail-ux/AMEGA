@extends('layouts.cruise-services')

@section('title', ($booking->exists ? $booking->reference : 'New Cruise Booking').' - Cruise Services - AMEGA')

{{--
    A cruise booking, step by step, in the ticketing wizard's stepper. Opens a new booking (no
    reference yet) or shows one at $step: the step being looked at, the booking's current one or one
    it has already reached (the stepper's bars link to those). Each step's form is in steps/.
--}}
@use('App\Models\CruiseBooking')
@php
    $stages = CruiseBooking::STAGES;
    $total = count($stages);
    $step ??= (string) $booking->status;
    $viewing = array_search($step, array_keys($stages), true);
    $reached = $booking->stageIndex();
    $canSave = auth()->user()->hasPermission($booking->exists ? 'cruise_services.edit' : 'cruise_services.create');

    $control = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600 disabled:bg-slate-100 disabled:text-slate-500';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
@endphp

@section('content')
<div class="space-y-6">
    <a href="{{ route('cruise-services.dashboard') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span>Cruise bookings</span>
    </a>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3" role="alert">
            <p class="text-sm font-semibold text-rose-900">Please correct the following:</p>
            <ul class="mt-1 text-sm text-rose-900 space-y-0.5">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($booking->exists)
        <x-file-owner :file="$booking" type="cruise" />
    @endif

    {{-- The ticketing wizard's stepper: one bar per step, olive for the current one. --}}
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold">
                    <i data-lucide="ship" class="w-3.5 h-3.5"></i>
                    {{ $booking->exists ? 'Cruise booking '.$booking->reference : 'New cruise booking' }}
                </span>
                <h1 class="mt-1.5 text-xl sm:text-2xl font-bold text-slate-900">Step {{ $viewing + 1 }} - {{ $stages[$step] }}</h1>
            </div>
            <div class="flex items-center gap-2 whitespace-nowrap">
                <span class="text-xs font-semibold text-slate-500">Step</span>
                <span class="w-8 h-8 rounded-full bg-primary text-white font-bold text-sm flex items-center justify-center shadow-md shadow-primary/30">{{ $viewing + 1 }}</span>
                <span class="text-xs font-semibold text-slate-500">of {{ $total }}</span>
            </div>
        </div>
        <div class="grid gap-1.5" style="grid-template-columns: repeat({{ $total }}, minmax(0, 1fr))">
            @foreach (array_keys($stages) as $index => $key)
                {{-- A step the booking has reached opens when its bar is clicked. --}}
                @php $open = $booking->exists && $index <= $reached && $index !== $viewing; @endphp
                <{{ $open ? 'a' : 'div' }} @if ($open) href="{{ route('cruise-services.bookings.show', ['booking' => $booking, 'step' => $key]) }}" title="Open {{ $stages[$key] }}" @endif
                    class="flex flex-col gap-1.5 {{ $open ? 'group' : '' }}" @if ($index === $viewing) aria-current="step" @endif>
                    <div class="h-2 rounded-full {{ $index === $viewing ? 'bg-accent' : ($index <= $reached ? 'bg-primary group-hover:bg-navy' : 'bg-gray-200') }}"></div>
                    <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block {{ $index === $viewing ? 'text-slate-900' : ($index <= $reached ? 'text-primary' : 'text-slate-400') }}">{{ $stages[$key] }}</span>
                </{{ $open ? 'a' : 'div' }}>
            @endforeach
        </div>
    </div>


    @include('cruise-services.steps.'.$step)
</div>
@endsection
