@props(['key'])

{{-- The ticket wizard's message for one field. `key` is a JavaScript expression naming the entry in `errors`. --}}
<p x-show="errors[{{ $key }}]" x-cloak x-text="errors[{{ $key }}]" class="mt-1 text-[11px] font-semibold text-rose-600"></p>
