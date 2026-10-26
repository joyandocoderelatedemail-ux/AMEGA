@extends('layouts.admin')

@section('title', $template['name'].' - Message Templates - AMEGA Admin')
@section('page_title', 'Message Templates')

@php
    $input = 'w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-700';
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-6"
     x-data="{
         subject: @js(old('subject', $current['subject'])),
         body: @js(old('body', $current['body'])),
         samples: @js($samples),
         fill(text) {
             return text.replace(/\{([a-z_]+)\}/g, (match, name) => name in this.samples ? this.samples[name] : match);
         },
         insert(name) {
             const area = this.$refs.body;
             const at = area.selectionStart ?? this.body.length;
             this.body = this.body.slice(0, at) + '{' + name + '}' + this.body.slice(area.selectionEnd ?? at);
             this.$nextTick(() => { area.focus(); area.selectionStart = area.selectionEnd = at + name.length + 2; });
         },
     }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.message-templates.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Message Templates
            </a>
            <h2 class="mt-1 text-xl font-bold text-slate-900">{{ $template['name'] }}</h2>
            <p class="text-sm text-slate-500">{{ $template['description'] }}</p>
        </div>
        @if ($current['custom'])
            <form method="POST" action="{{ route('admin.message-templates.reset', $key) }}" onsubmit="return confirm('Go back to the default wording? Your changes to this message are discarded.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-1.5 h-10 px-4 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Reset to default
                </button>
            </form>
        @endif
    </div>

    @include('admin.partials._form-errors')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- The wording -->
        <form method="POST" action="{{ route('admin.message-templates.update', $key) }}" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="subject" class="block text-xs font-semibold text-slate-600 mb-1">Email subject</label>
                <input id="subject" name="subject" x-model="subject" maxlength="255" required class="{{ $input }}">
            </div>
            <div>
                <label for="body" class="block text-xs font-semibold text-slate-600 mb-1">Message</label>
                <textarea id="body" name="body" x-ref="body" x-model="body" rows="22" required
                          class="{{ $input }} font-mono text-xs leading-relaxed"></textarea>
                <p class="mt-1 text-[11px] text-slate-500">A blank line starts a new paragraph. Emojis are kept as typed.</p>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-600 mb-1.5">Placeholders <span class="font-normal text-slate-400">— click to insert where the cursor is</span></p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($template['placeholders'] as $name => [$meaning])
                        <button type="button" @click="insert(@js($name))" title="{{ $meaning }}"
                                class="px-2 py-1 rounded-md border border-slate-200 bg-slate-50 text-[11px] font-mono text-navy-700 hover:border-navy-700">{{ '{'.$name.'}' }}</button>
                    @endforeach
                </div>
                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-[11px]">
                    @foreach ($template['placeholders'] as $name => [$meaning])
                        <div class="flex gap-1.5"><dt class="font-mono text-slate-700 shrink-0">{{ '{'.$name.'}' }}</dt><dd class="text-slate-500">{{ $meaning }}</dd></div>
                    @endforeach
                </dl>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <a href="{{ route('admin.message-templates.index') }}" class="h-10 px-4 inline-flex items-center rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="h-10 px-5 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-semibold">Save template</button>
            </div>
        </form>

        <!-- What the client will see, with sample details -->
        <div class="space-y-2">
            <p class="text-xs font-semibold text-slate-600">Preview <span class="font-normal text-slate-400">— with sample details</span></p>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-[11px] text-slate-500">Subject</p>
                <p class="text-sm font-semibold text-slate-900 mb-3" x-text="fill(subject)"></p>
                <div class="rounded-xl bg-white border border-slate-200 p-5 text-sm text-slate-700 leading-relaxed whitespace-pre-line break-words" x-text="fill(body)"></div>
            </div>
        </div>
    </div>
</div>
@endsection
