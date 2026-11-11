@extends('layouts.local-tours')

@section('title', 'Edit '.$booking->reference.' - Local Tours - AMEGA')

@section('content')
<form method="POST" action="{{ route('local-tours.bookings.update', $booking) }}" class="space-y-6">
    @csrf
    @method('PUT')
    <div>
        <a href="{{ route('local-tours.bookings.show', $booking) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>{{ $booking->reference }}</span>
        </a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Edit booking details</h1>
        @if ($booking->approval_status !== null)
            <p class="mt-1 text-sm text-amber-800">This booking has gone for approval. Changing the tour date, the guests, the transport or the amount quoted sends it back to the approver.</p>
        @endif
    </div>

    @include('local-tours._form', ['booking' => $booking])

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="h-10 px-5 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold inline-flex items-center gap-2">
            <i data-lucide="check" class="w-4 h-4"></i> Save details
        </button>
        <a href="{{ route('local-tours.bookings.show', $booking) }}" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center">Cancel</a>
    </div>
</form>
@endsection
