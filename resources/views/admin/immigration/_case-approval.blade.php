{{-- Where a case stands with the admin: waiting, or returned with a reason. Expects $case, a row from ImmigrationCases. --}}
@if (($case['approval'] ?? null) === 'pending')
    <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-300 text-amber-800 text-[10px] font-bold">Awaiting admin approval</span>
@elseif (($case['approval'] ?? null) === 'returned')
    <span class="mt-1 block text-[11px] font-semibold text-rose-700">Returned by the admin{{ $case['approval_note'] ? ': '.$case['approval_note'] : '' }}</span>
@endif
