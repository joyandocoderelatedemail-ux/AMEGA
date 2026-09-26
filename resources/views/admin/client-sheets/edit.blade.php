@extends('layouts.immigration')

@section('title', 'Client Sheet - AMEGA Admin')
@section('page_title', 'Client Information Sheet')

@section('content')
<div class="max-w-4xl space-y-6">

    <!-- Header with the print action, which is the point of this screen -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-heading text-xl font-bold text-dark">{{ $client->full_name }}</h2>
                @include('admin.client-sheets.marks', ['client' => $client])
            </div>
            <p class="text-xs text-dark/50 mt-0.5">
                <span class="font-mono font-bold text-primary">{{ $client->passport_number ?: 'No passport on file' }}</span>
                @if ($client->nationality)
                    &nbsp;·&nbsp; {{ $client->nationality }}
                @endif
                &nbsp;·&nbsp; next extension would be the {{ $client->next_extension_number }}{{ \App\Models\ImmigrationClientExtension::ordinalSuffix($client->next_extension_number) }}
            </p>
            @if ($band = $client->validity_band)
                <p class="text-xs font-bold mt-1.5 {{ $band['key'] === 'expired' ? 'text-rose-600' : ($band['key'] === 'express' ? 'text-amber-600' : 'text-emerald-600') }}">
                    {{ $band['label'] }} — {{ $band['detail'] }}
                </p>
            @endif
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('admin.client-sheets.index') }}" class="px-5 py-2.5 bg-gray-100 text-dark font-bold text-xs rounded-full hover:bg-gray-200 transition-all">
                Back to counter
            </a>
            <a href="{{ route('admin.client-sheets.print', $client) }}" target="_blank"
               class="px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-full hover:bg-primary/5 transition-all flex items-center gap-2"
               title="Print the last saved version without reviewing">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Print last saved
            </a>
        </div>
    </div>

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
        <form method="POST" action="{{ route('admin.client-sheets.update', $client) }}" novalidate>
            @csrf
            @method('PUT')

            @include('admin.client-sheets.form', ['client' => $client])
        </form>
    </div>
</div>
@endsection
