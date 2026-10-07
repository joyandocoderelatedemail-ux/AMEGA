@props(['doc', 'title', 'label', 'requiredText'])

{{--
    Status panel for one scan on a passenger in the ticket wizard (`doc` is 'passport' or 'government_id').
    Amber when the document is required and missing (it can be added later but blocks issuing), green once one is held, neutral when it is optional.
    The button reads "Choose File" until a document exists, then "Re-upload". For a registered client the
    file is saved to their profile at once (see onScanChosen), so a re-upload replaces it for every booking.
--}}
@php
    $upload = "p.{$doc}_upload";
    $status = "scanStatus(p, '{$doc}')";
@endphp
<div class="space-y-2">
    <span class="text-xs font-bold text-dark/70">{{ $title }}</span>
    <input type="hidden" :name="'passengers[' + idx + '][use_profile_{{ $doc }}]'" :value="(p.client_id && p.use_profile_{{ $doc }} && !p.{{ $doc }}_file_name) ? 1 : 0">
    <div class="rounded-xl border-2 p-4 flex flex-wrap items-center gap-3 transition-colors" role="status"
         :class="{
             'border-primary/30 bg-primary/5': {{ $upload }} === 'uploading',
             'border-emerald-300 bg-emerald-50': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'uploaded',
             'border-amber-300 bg-amber-50': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'missing',
             'border-gray-200 bg-gray-50': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'optional',
         }">
        <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-white"
              :class="{
                  'bg-primary': {{ $upload }} === 'uploading',
                  'bg-emerald-600': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'uploaded',
                  'bg-amber-500': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'missing',
                  'bg-gray-400': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'optional',
              }">
            <svg x-show="{{ $upload }} === 'uploading'" class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <svg x-show="{{ $upload }} !== 'uploading' && {{ $status }}.state === 'uploaded'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            <svg x-show="{{ $upload }} !== 'uploading' && {{ $status }}.state !== 'uploaded'" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold"
               :class="{
                   'text-primary': {{ $upload }} === 'uploading',
                   'text-emerald-800': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'uploaded',
                   'text-amber-800': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'missing',
                   'text-dark': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'optional',
               }"
               x-text="{{ $upload }} === 'uploading' ? 'Uploading {{ strtolower($label) }}…' : ({{ $status }}.state === 'uploaded' ? '{{ $label }} Uploaded' : '{{ $label }} Not Uploaded')"></p>
            <p x-show="{{ $upload }} !== 'uploading' && {{ $status }}.state === 'uploaded'" class="text-xs font-semibold text-dark/70 truncate" x-text="{{ $status }}.file"></p>
            <p class="text-xs text-dark/60"
               x-text="{{ $upload }} === 'uploading'
                   ? ({{ $status }}.state === 'uploaded' ? 'The current {{ strtolower($label) }} stays in place until the new one is saved.' : 'Saving the {{ strtolower($label) }}…')
                   : ({ uploaded: 'Valid document', missing: '{{ $requiredText }}', optional: 'Optional for this passenger' }[{{ $status }}.state])"></p>
        </div>
        <label class="inline-flex items-center px-4 py-1.5 rounded-lg text-xs font-bold transition-colors focus-within:ring-2 focus-within:ring-primary/40"
               :class="{
                   'opacity-60 pointer-events-none bg-gray-100 text-dark/60': {{ $upload }} === 'uploading',
                   'cursor-pointer bg-white border border-gray-300 text-dark hover:bg-gray-50': {{ $upload }} !== 'uploading' && {{ $status }}.state === 'uploaded',
                   'cursor-pointer bg-primary text-white hover:bg-navy': {{ $upload }} !== 'uploading' && {{ $status }}.state !== 'uploaded',
               }">
            <span x-text="{{ $upload }} === 'uploading' ? 'Uploading…' : ({{ $status }}.state === 'uploaded' ? 'Re-upload' : 'Choose File')"></span>
            <input type="file" :name="'passengers[' + idx + '][{{ $doc }}_file]'" accept="image/jpeg,image/png,image/webp,image/jpg,application/pdf"
                   :disabled="{{ $upload }} === 'uploading'"
                   @change="onScanChosen($event, p, '{{ $doc }}')"
                   class="sr-only">
        </label>
    </div>
    <p x-show="p.{{ $doc }}_error" class="text-xs font-semibold text-rose-700" x-text="p.{{ $doc }}_error"></p>
</div>
