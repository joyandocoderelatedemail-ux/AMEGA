{{-- Shown on a new case that was started from the client's previous one. Expects $copiedFrom and $service. --}}
<div class="rounded-2xl bg-navy-50 border border-navy-200 px-5 py-3 text-xs text-navy-900 flex flex-wrap items-center justify-between gap-3" role="status">
    <p class="font-semibold">
        Filled in from {{ $client->full_name }}'s previous {{ $copiedFrom['label'] }}
        ({{ $copiedFrom['reference'] }}{{ $copiedFrom['released_on'] ? ', closed '.$copiedFrom['released_on'] : '' }}).
        <span class="font-normal">Check every step, change what is different, then save to start a new transaction. Dates such as the trip or arrival are copied too, so update them.</span>
    </p>
    <a href="{{ $copiedFrom['blank_url'] }}" class="shrink-0 px-4 py-2 rounded-lg bg-white border border-navy-200 text-navy-700 text-xs font-bold hover:bg-navy-50">Start blank</a>
</div>
