{{--
    One checklist requirement: tick it as received, and upload the client's file for it.
    A file in hand ticks the box on its own. Needs $app, $key and $text; $number and
    $optional are for the numbered BI items.
--}}
@php
    $document = $app->documentFor($key);
    $ticked = in_array($key, old('checklist', $app->checklist ?? []), true);
@endphp
<div x-data="{ done: {{ Js::from($ticked) }}, chosen: '' }"
     :class="done ? 'border-emerald-600/40 bg-emerald-50' : 'border-slate-200'"
     class="rounded-lg border p-3 space-y-3 transition-colors">
    <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" name="checklist[]" value="{{ $key }}" x-model="done"
               class="mt-0.5 w-4 h-4 rounded text-navy-700 border-slate-300 focus:ring-navy-500">
        <span class="text-sm text-slate-800">
            @isset($number)
                <span class="font-semibold">{{ $number }}.</span>
            @endisset
            {{ $text }}
            @if ($optional ?? false)
                <span class="italic text-slate-500">(If applicable)</span>
            @endif
        </span>
    </label>

    <div class="pl-7 flex flex-wrap items-center gap-x-3 gap-y-2">
        <label class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 cursor-pointer hover:border-slate-400 hover:text-navy-700 transition-colors focus-within:ring-2 focus-within:ring-navy-500">
            <i data-lucide="upload" class="w-4 h-4"></i>
            <span>{{ $document ? 'Replace file' : 'Upload' }}</span>
            <input type="file" name="documents[{{ $key }}]" accept="application/pdf,image/jpeg,image/png,image/webp" class="sr-only"
                   @change="chosen = $event.target.files[0]?.name || ''; if (chosen) done = true">
        </label>

        <span x-show="chosen" x-cloak class="text-sm text-slate-600 truncate max-w-[16rem]" x-text="chosen"></span>

        @if ($document)
            <a href="{{ route('visa.tourist-visa.document', [$app, $key]) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-navy-700 hover:text-navy-900" x-show="!chosen">
                <i data-lucide="paperclip" class="w-4 h-4"></i>
                <span class="truncate max-w-[16rem]">{{ $document['name'] }}</span>
                <span class="font-normal text-xs text-slate-500">{{ \App\Models\TouristVisaApplication::fileSizeLabel((int) $document['size']) }}</span>
            </a>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer" x-show="!chosen">
                <input type="checkbox" name="remove_documents[{{ $key }}]" value="1" class="w-4 h-4 rounded text-navy-700 border-slate-300 focus:ring-navy-500">
                Remove file
            </label>
        @else
            <span class="text-xs text-slate-500" x-show="!chosen">PDF, JPG, PNG or WebP, up to 10 MB.</span>
        @endif
    </div>
</div>
