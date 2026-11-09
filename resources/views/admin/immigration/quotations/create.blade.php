@extends('layouts.immigration')

@section('title', 'New Quotation - AMEGA Admin')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">New quotation</h1>
        <a href="{{ route('admin.immigration.quotations.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Back to quotations</a>
    </div>

    @include('admin.partials._uppercase-inputs')
    <form method="POST" action="{{ route('admin.immigration.quotations.store') }}" class="space-y-6" novalidate data-uppercase>
        @csrf
        @include('admin.immigration.quotations._form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.immigration.quotations.index') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-white border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex items-center gap-2 h-10 px-5 rounded-lg bg-navy-700 text-sm font-semibold text-white shadow-sm hover:bg-navy-800">
                <i data-lucide="save" class="w-4 h-4"></i><span>Save quotation</span>
            </button>
        </div>
    </form>
</div>
@endsection
