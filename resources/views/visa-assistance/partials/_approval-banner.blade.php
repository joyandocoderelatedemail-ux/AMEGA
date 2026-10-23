{{-- Where a visa counter file stands with the admin: waiting, returned with a reason, or approved. Expects $file. --}}
@if ($file->isAwaitingApproval())
    <div class="mb-6 rounded-2xl bg-amber-50 border border-amber-200 px-5 py-3 text-xs font-semibold text-amber-900" role="status">
        Waiting for the admin to approve this file. It can be advanced, paid, printed and sent to the client once it is approved.
    </div>
@elseif ($file->isReturned())
    <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 px-5 py-3 text-xs font-semibold text-rose-900" role="alert">
        Returned by the admin{{ $file->review_note ? ': '.$file->review_note : '.' }} Correct it under Edit and save to send it again.
    </div>
@elseif ($file->approval_status === 'approved')
    <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-3 text-xs font-semibold text-emerald-900">
        Approved{{ $file->reviewedBy ? ' by '.$file->reviewedBy->name : '' }}{{ $file->reviewed_at ? ' on '.$file->reviewed_at->format('M d, Y') : '' }}.
    </div>
@endif
