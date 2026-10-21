{{-- Update where a case stands. Choosing a status saves it. Expects $case, a row from ImmigrationCases. --}}
<form method="POST" action="{{ route('admin.immigration.cases.status', [$case['kind'], $case['id']]) }}" class="inline-flex items-center gap-1.5">
    @csrf
    <select name="status" onchange="this.form.requestSubmit()" aria-label="Update status of {{ $case['reference'] }}"
            class="pl-2.5 pr-7 py-1.5 rounded-lg border text-[11px] font-bold focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer {{ \App\Support\CaseProgress::tone($case['progress']) }}">
        @foreach (\App\Support\CaseProgress::OPTIONS as $statusKey => $statusLabel)
            <option value="{{ $statusKey }}" @selected($case['progress'] === $statusKey) class="bg-white text-dark font-semibold">{{ $statusLabel }}</option>
        @endforeach
    </select>
    <noscript><button type="submit" class="px-2 py-1 rounded-lg bg-primary text-white text-[11px] font-bold">Update</button></noscript>
</form>
