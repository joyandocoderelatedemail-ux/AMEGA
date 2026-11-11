@extends('layouts.local-tours')

@section('title', 'New Local Tour Booking - AMEGA')

@section('content')
<form method="POST" action="{{ route('local-tours.bookings.store') }}" class="space-y-6">
    @csrf
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">New local tour booking</h1>
            <p class="text-sm text-slate-500">Take down what the client asked for. The booking then starts at the supplier's availability and rates.</p>
        </div>
    </div>

    @include('local-tours._form', ['booking' => $booking])

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="h-10 px-5 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-sm font-semibold inline-flex items-center gap-2">
            <i data-lucide="check" class="w-4 h-4"></i> Open booking
        </button>
        <a href="{{ route('local-tours.dashboard') }}" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center">Cancel</a>
    </div>
</form>
@endsection
