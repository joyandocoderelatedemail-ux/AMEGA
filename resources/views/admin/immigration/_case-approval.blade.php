{{-- The admin's reason when a case was returned. Waiting for approval is shown in the status dropdown. Expects $case, a row from ImmigrationCases. --}}
@if (($case['approval'] ?? null) === 'returned' && $case['approval_note'])
    <span class="mt-1 block text-[11px] font-semibold text-rose-700">{{ $case['approval_note'] }}</span>
@endif
