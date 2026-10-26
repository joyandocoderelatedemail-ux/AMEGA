@extends('layouts.admin')

@section('title', 'Message Templates - AMEGA Admin')
@section('page_title', 'Message Templates')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">Message Templates</h2>
        <p class="text-sm text-slate-500 mt-0.5">The wording of the messages sent to clients. Placeholders such as <code class="text-xs">{flight_date}</code> are filled in from the booking or folder.</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100">
        @foreach ($templates as $template)
            <a href="{{ route('admin.message-templates.edit', $template['key']) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50 transition-colors">
                <span class="flex items-start gap-3 min-w-0">
                    <span class="w-10 h-10 rounded-lg bg-slate-100 text-navy-700 flex items-center justify-center shrink-0">
                        <i data-lucide="{{ $template['key'] === 'flight_reminder' ? 'plane' : 'stamp' }}" class="w-5 h-5"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900">{{ $template['name'] }}</span>
                        <span class="block text-xs text-slate-500">{{ $template['description'] }}</span>
                    </span>
                </span>
                <span class="flex items-center gap-3 shrink-0">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold">{{ $template['channel'] }}</span>
                    @if ($template['custom'])
                        <span class="text-[11px] text-slate-500">Edited {{ $template['custom']->updated_at->format('M j, Y') }}@if ($template['custom']->editor) by {{ $template['custom']->editor->name }}@endif</span>
                    @else
                        <span class="text-[11px] text-slate-400">Default wording</span>
                    @endif
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
                </span>
            </a>
        @endforeach
    </div>
</div>
@endsection
