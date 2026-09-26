<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use App\Notifications\FlightReminderNotification;
use App\Notifications\TicketDocumentsNotification;
use App\Services\ActivityLogger;
use App\Services\BookingAgreementDrafter;
use App\Services\ClientNotifier;
use Illuminate\Http\RedirectResponse;

/**
 * Emails staff send to a ticket's client on demand from the ticketing desk.
 */
class TicketMessageController extends Controller
{
    /**
     * Remind the client their flight is coming up.
     */
    public function reminder(TicketBooking $ticket): RedirectResponse
    {
        if ($ticket->isCancelled()) {
            return back()->with('error', 'A cancelled booking has no flight to remind the client about.');
        }

        if (! $ticket->departure_date || $ticket->departure_date->isBefore(today())) {
            return back()->with('error', "{$ticket->booking_reference} has already departed.");
        }

        return $this->deliver($ticket, new FlightReminderNotification($ticket), 'REMIND', 'flight reminder');
    }

    /**
     * Send the client another copy of their consent form and booking agreement.
     */
    public function documents(TicketBooking $ticket, BookingAgreementDrafter $drafter): RedirectResponse
    {
        if ($ticket->isCancelled()) {
            return back()->with('error', 'Documents are not sent for a cancelled booking.');
        }

        if (ClientNotifier::canReceive($ticket->contact_email)) {
            $drafter->ensureFor($ticket, request()->user());
        }

        return $this->deliver($ticket, new TicketDocumentsNotification($ticket), 'RESEND', 'booking agreement and consent form');
    }

    private function deliver(TicketBooking $ticket, FlightReminderNotification|TicketDocumentsNotification $notification, string $action, string $what): RedirectResponse
    {
        if (! ClientNotifier::canReceive($ticket->contact_email)) {
            return back()->with('error', "{$ticket->booking_reference} has no client email address on file.");
        }

        if (! ClientNotifier::send($ticket->contact_email, $ticket->contact_name, $notification)) {
            return back()->with('error', "The {$what} could not be sent to {$ticket->contact_email}. Please try again.");
        }

        ActivityLogger::log('Ticketing', $action, "Sent the {$what} for {$ticket->booking_reference} to {$ticket->contact_email}");

        return back()->with('success', "Sent the {$what} to {$ticket->contact_email}.");
    }
}
