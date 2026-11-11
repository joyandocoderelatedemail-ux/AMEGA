@extends('layouts.local-tours')

@section('title', 'Suppliers & Procedures - Local Tours - AMEGA')

{{-- The Local Tours Booking Procedures Workflow as the desk's reference: each tour's supplier, what
     they need to know and the steps to book it, the AMEGA drivers, and the reminders. --}}
@use('App\Models\LocalTourBooking')
@use('App\Support\LocalTours')
@php
    $card = 'bg-white rounded-xl border border-slate-200 p-5 sm:p-6';
    $term = 'text-xs font-semibold text-slate-500';
    $canCreate = auth()->user()->hasPermission('local_tours.create');
    $workStages = array_diff(array_keys(LocalTourBooking::STAGES), ['completed']);
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Suppliers and booking procedures</h1>
        <p class="text-sm text-slate-500">Who to call for each tour, what they need to know about the guests, and the steps a booking goes through.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        @foreach (LocalTours::TOURS as $key => $tour)
            @php $sample = new LocalTourBooking(['tour' => $key, 'transport' => 'amega']); @endphp
            <section class="{{ $card }} space-y-4" aria-labelledby="tour-{{ $key }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0"><i data-lucide="{{ $tour['icon'] }}" class="w-5 h-5"></i></span>
                        <div class="min-w-0">
                            <h2 id="tour-{{ $key }}" class="text-base font-semibold text-slate-900">{{ $tour['label'] }}</h2>
                            <p class="text-sm text-slate-700"><span class="{{ $term }}">{{ $tour['contact_label'] }}:</span> {{ $tour['supplier'] }}</p>
                            @if ($tour['phone'])
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $tour['phone']) }}" class="text-sm font-semibold text-navy-700 hover:underline">{{ $tour['phone'] }}</a>
                            @endif
                            @if ($tour['accreditation'])
                                <p class="text-xs text-slate-500">{{ $tour['accreditation'] }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($canCreate)
                        <a href="{{ route('local-tours.bookings.create', ['tour' => $key]) }}" class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i data-lucide="plus" class="w-4 h-4"></i> Book
                        </a>
                    @endif
                </div>

                <div>
                    <h3 class="{{ $term }} mb-1">Required guest information</h3>
                    <ul class="text-sm text-slate-700 space-y-1 list-disc pl-4">
                        @foreach ($tour['guest_info'] as $item)<li>{{ $item }}</li>@endforeach
                    </ul>
                </div>

                <div>
                    <h3 class="{{ $term }} mb-1">Booking process</h3>
                    <ol class="space-y-2">
                        @foreach ($workStages as $index => $stage)
                            <li class="text-sm">
                                <p class="font-semibold text-slate-900">{{ $index + 1 }}. {{ LocalTourBooking::STAGES[$stage] }}</p>
                                <ul class="mt-0.5 text-slate-700 space-y-0.5 list-disc pl-8">
                                    @foreach ($sample->tasks($stage) as $task)<li>{{ $task }}</li>@endforeach
                                    @if ($stage === 'guest_details')<li>Upload the guests' valid IDs. An approver then checks the booking.</li>@endif
                                    @if ($stage === 'voucher')<li>The cashier records the client's payment in full first.</li>@endif
                                </ul>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if ($tour['important'])
                    <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-900"><strong>Important:</strong> {{ $tour['important'] }}</p>
                @endif
            </section>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <section class="{{ $card }} space-y-4" aria-labelledby="drivers-heading">
            <h2 id="drivers-heading" class="text-base font-semibold text-slate-900">Driver coordination and assignment</h2>
            <div class="relative overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr><th class="px-4 py-2.5">Driver</th><th class="px-4 py-2.5">Contact number</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach (LocalTours::DRIVERS as [$name, $phone])
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-slate-900">{{ $name }}</td>
                                <td class="px-4 py-2.5"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-navy-700 hover:underline">{{ $phone }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ol class="text-sm text-slate-700 space-y-1 list-decimal pl-5">
                @foreach (LocalTours::DRIVER_PROCEDURE as $step)<li>{{ $step }}</li>@endforeach
            </ol>
            <p class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-900"><strong>Important:</strong> {{ LocalTours::DRIVER_IMPORTANT }}</p>
        </section>

        <section class="{{ $card }} space-y-3" aria-labelledby="reminders-heading">
            <h2 id="reminders-heading" class="text-base font-semibold text-slate-900">Important reminders</h2>
            <ul class="text-sm text-slate-700 space-y-1.5 list-disc pl-5">
                @foreach (LocalTours::REMINDERS as $reminder)<li>{{ $reminder }}</li>@endforeach
            </ul>
        </section>
    </div>
</div>
@endsection
