@extends('layouts.cashier')

@section('title', $desk.' - Cashier - AMEGA')

{{-- A desk the cashier does not take payments for yet. --}}
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ $desk }}</h1>
    <div class="bg-white rounded-xl border border-slate-200">
        <p class="px-5 py-12 text-center text-sm text-slate-500">Nothing to collect yet. {{ $desk }} files are not sent to the cashier.</p>
    </div>
</div>
@endsection
