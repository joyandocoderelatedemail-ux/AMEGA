@extends('layouts.visa-assistance')

@section('title', $label.' - AMEGA Travel and Tours')

@php
    /**
     * A service's open files, before anything is started. Open shows one as a read-only preview on a
     * single page; New goes on to start a new one.
     */
    $card = 'rounded-2xl bg-white border border-gray-200 shadow-card p-6 sm:p-8';
    $icons = ['visit_visa' => 'globe', 'e_visa' => 'zap', 'passporting' => 'book-user'];
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="{{ $card }} space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider bg-primary/10 text-primary">
                    <i data-lucide="{{ $icons[$service] ?? 'folder' }}" class="w-3.5 h-3.5"></i>
                    <span>{{ $label }}</span>
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-dark tracking-tight mt-1.5">Active files</h1>
                <p class="text-xs text-dark/50 mt-1">Open one to see the whole file, or start a new one.</p>
            </div>
            <a href="{{ $newUrl }}"
               class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md">
                <i data-lucide="plus" class="w-4 h-4"></i>
                New {{ $label }}
            </a>
        </div>

        @if ($files->isEmpty())
            <p class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-8 text-center text-sm text-dark/60">
                No active {{ $label }} files yet.
            </p>
        @else
            <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                @foreach ($files as $file)
                    @php $hold = \App\Support\CaseProgress::approvalHold($file->approval_status); @endphp
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                        <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0" aria-hidden="true">
                            <i data-lucide="folder" class="w-4 h-4"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-dark truncate">{{ $file->client_name }}</span>
                            <span class="block text-xs text-dark/50 truncate">
                                <span class="font-mono font-bold text-primary">{{ $file->reference }}</span>
                                @if ($file->destination_country) &middot; {{ $file->destination_country }} @endif
                            </span>
                        </span>
                        <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($hold ?? 'preparing') }}">
                            {{ $hold ? \App\Support\CaseProgress::holdLabel($hold) : $file->status_label }}
                        </span>
                        <a href="{{ route('visa.applications.show', [$file, 'preview' => 1]) }}" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-navy transition-colors">
                            <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                            Open
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
