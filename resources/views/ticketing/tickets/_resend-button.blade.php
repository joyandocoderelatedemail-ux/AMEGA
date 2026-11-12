{{-- Sends a returned booking back for approval as it is (what was asked was done outside the form). Expects $ticket. --}}
<form method="POST" action="{{ route('ticketing.tickets.resubmit', $ticket) }}" class="inline"
      onsubmit="return confirm('Send {{ $ticket->booking_reference }} back for approval without changing it? Do this when what the admin asked for is already done.')">
    @csrf
    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-300 bg-white text-rose-700 text-xs font-semibold hover:bg-rose-50">
        <i data-lucide="send" class="w-3.5 h-3.5" aria-hidden="true"></i>
        <span>Resend as is</span>
    </button>
</form>
