@extends('layouts.immigration')

@section('title', $service['label'].' - AMEGA Admin')
@section('page_title', $service['label'])

@php
    /**
     * A service's active applications, before anything is started. Open shows one as a read-only
     * preview on a single page; "New" goes on to name the client and then Step 0.
     */
    $card = 'bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm';
    $isExtension = $service['key'] === 'visa_extension';
    $rows = $isExtension ? $extensions : $cases;
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <p class="text-xs text-dark/50">{{ $service['blurb'] }}</p>

    <div class="{{ $card }} space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider bg-primary/10 text-primary">
                    <i data-lucide="{{ $service['icon'] }}" class="w-3.5 h-3.5"></i>
                    <span>{{ $service['label'] }}</span>
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-dark tracking-tight mt-1.5">Active applications</h1>
                <p class="text-xs text-dark/50 mt-1">Open one to see the whole file, or start a new one.</p>
            </div>
            <a href="{{ route('admin.client-sheets.start', ['service' => $service['key']]) }}"
               class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md">
                <i data-lucide="plus" class="w-4 h-4"></i>
                New {{ $service['label'] }}
            </a>
        </div>
        <form method="GET">
            <input type="hidden" name="service" value="{{ $service['key'] }}">
            <x-date-range label="Opened" inputClass="px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary" buttonClass="px-4 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-navy" />
        </form>

        @if ($rows->isEmpty())
            <p class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-8 text-center text-sm text-dark/60">
                No active {{ $service['label'] }} applications yet.
            </p>
        @else
            <ul class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                @if ($isExtension)
                    @foreach ($extensions as $row)
                        @php
                            $hold = \App\Support\CaseProgress::approvalHold($row['extension']?->approval_status);
                        @endphp
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0" aria-hidden="true">
                                <i data-lucide="folder" class="w-4 h-4"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-dark truncate">{{ $row['client']->full_name }}</span>
                                <span class="block text-xs text-dark/50 truncate">{{ collect([$row['client']->passport_number ? 'Passport '.$row['client']->passport_number : null, $row['extension'] ? 'Extension #'.$row['extension']->sequence : null])->filter()->implode(' · ') }}</span>
                            </span>
                            <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($hold ?? 'approved') }}">
                                {{ $hold ? \App\Support\CaseProgress::holdLabel($hold) : 'Approved' }}
                            </span>
                            <a href="{{ $row['url'] }}" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-navy transition-colors">
                                <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                Open
                            </a>
                        </li>
                    @endforeach
                @else
                    @foreach ($cases as $case)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0" aria-hidden="true">
                                <i data-lucide="folder" class="w-4 h-4"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-dark truncate">{{ $case['client']?->full_name ?? 'Unknown client' }}</span>
                                <span class="block text-xs text-dark/50 truncate">
                                    <span class="font-mono font-bold text-primary">{{ $case['reference'] }}</span> &middot; {{ $case['summary'] }}
                                </span>
                            </span>
                            <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone(\App\Support\CaseProgress::approvalHold($case['approval']) ?? $case['progress']) }}">
                                {{ ($hold = \App\Support\CaseProgress::approvalHold($case['approval'])) ? \App\Support\CaseProgress::holdLabel($hold) : $case['progress_label'] }}
                            </span>
                            @if ($case['preview_url'])
                                <a href="{{ $case['preview_url'] }}" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-navy transition-colors">
                                    <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                    Open
                                </a>
                            @endif
                        </li>
                    @endforeach
                @endif
            </ul>
        @endif
    </div>
</div>
@endsection
