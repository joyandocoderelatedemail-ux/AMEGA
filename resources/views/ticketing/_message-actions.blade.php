@php
    /**
     * The on-demand client emails for one ticket: a flight reminder while the
     * flight is still ahead, and another copy of the booking paperwork.
     * Pass `compact` to show icons only, where a table has no room for labels.
     *
     * @var \App\Models\TicketBooking $ticket
     */
    $canEmail = \App\Services\ClientNotifier::canReceive($ticket->contact_email);
    $isOpen = ! $ticket->isCancelled();
    $flightAhead = $isOpen && $ticket->departure_date && ! $ticket->departure_date->isBefore(today());
    $labelClass = ($compact ?? false) ? 'sr-only' : '';
    $noEmailHint = 'No client email address on file';
    $actionButton = 'inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-navy-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed';
@endphp

@if ($flightAhead)
    <form method="POST" action="{{ route('ticketing.tickets.reminder', $ticket) }}" class="inline"
          onsubmit="return confirm(@js('Email a flight reminder to '.$ticket->contact_email.'?'))">
        @csrf
        <button type="submit" class="{{ $actionButton }}" @disabled(! $canEmail)
                title="{{ $canEmail ? 'Email the client that their flight is '.\App\Notifications\FlightReminderNotification::when($ticket) : $noEmailHint }}">
            <i data-lucide="bell" class="w-3.5 h-3.5"></i>
            <span class="{{ $labelClass }}">Remind</span>
        </button>
    </form>
@endif

@if ($isOpen)
    <form method="POST" action="{{ route('ticketing.tickets.documents', $ticket) }}" class="inline"
          onsubmit="return confirm(@js('Email the booking agreement and consent form to '.$ticket->contact_email.'?'))">
        @csrf
        <button type="submit" class="{{ $actionButton }}" @disabled(! $canEmail)
                title="{{ $canEmail ? 'Email the booking agreement and consent form again' : $noEmailHint }}">
            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
            <span class="{{ $labelClass }}">Resend docs</span>
        </button>
    </form>
@endif
