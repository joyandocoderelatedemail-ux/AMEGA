{{-- Mark a case released (with the date, today unless changed) or reopen one. Expects $case, a row from ImmigrationCases. --}}
@if ($case['released_on'])
    <form method="POST" action="{{ route('admin.immigration.cases.reopen', [$case['kind'], $case['id']]) }}" class="inline"
          onsubmit="return confirm('Reopen {{ $case['reference'] }}?');">
        @csrf
        <button type="submit" class="text-[11px] font-bold text-dark/60 hover:text-primary hover:underline whitespace-nowrap">Reopen</button>
    </form>
@else
    <div x-data="{ open: false }" class="inline-flex items-center gap-1.5" @keydown.escape="open = false">
        <button type="button" x-show="! open" @click="open = true"
                class="px-2.5 py-1 rounded-lg border border-gray-200 text-[11px] font-bold text-dark/70 hover:border-primary hover:text-primary transition-colors whitespace-nowrap">Mark released</button>
        <form x-show="open" style="display: none" method="POST" action="{{ route('admin.immigration.cases.release', [$case['kind'], $case['id']]) }}" class="inline-flex items-center gap-1.5">
            @csrf
            <input type="date" name="released_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required aria-label="Date released"
                   class="px-2 py-1 rounded-lg bg-white border border-gray-200 text-[11px] text-dark focus:outline-none focus:ring-2 focus:ring-primary">
            <button type="submit" class="px-2.5 py-1 rounded-lg bg-primary text-white text-[11px] font-bold hover:bg-primary-dark transition-colors">Save</button>
            <button type="button" @click="open = false" class="px-1.5 text-dark/40 hover:text-dark text-sm leading-none" aria-label="Cancel">&times;</button>
        </form>
    </div>
@endif
