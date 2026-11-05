{{--
    Where a visa counter file stands with the admin: waiting, or returned with a reason. Expects $file.
    Nothing is shown once it is approved: the status badge in the header already says so, and there is nothing
    left to act on, so a separate "Approved" banner here would only repeat it.
--}}
@if ($file->isAwaitingApproval())
    <div class="mb-6 rounded-2xl bg-amber-50 border border-amber-200 px-5 py-3 text-xs font-semibold text-amber-900" role="status">
        Waiting for the admin to approve this file. It can be advanced, paid, printed and sent to the client once it is approved.
    </div>
@elseif ($file->isReturned())
    <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 px-5 py-3 text-xs font-semibold text-rose-900" role="alert">
        Returned by the admin{{ $file->review_note ? ': '.$file->review_note : '.' }} Correct it under Edit and save to send it again.
    </div>
@endif
