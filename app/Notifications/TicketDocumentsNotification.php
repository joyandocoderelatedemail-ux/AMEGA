<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use App\Services\TicketDocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends the client another copy of their Data Privacy Consent Form and
 * Booking Agreement, when staff resend them from the ticketing desk.
 */
class TicketDocumentsNotification extends Notification
{
    use Queueable;

    public function __construct(public TicketBooking $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;

        $message = (new MailMessage)
            ->subject("Your booking documents - {$ticket->booking_reference}")
            ->greeting('Hello '.($ticket->contact_name ?: 'there').',')
            ->line('As requested, here is another copy of the documents for your booking.')
            ->line("**Reference:** {$ticket->booking_reference}")
            ->line('**Route:** '.trim("{$ticket->origin} to {$ticket->destination}"));

        $attached = TicketDocumentPdf::attachTo($message, $ticket);

        if ($attached !== []) {
            $message->line('Attached: '.implode(' and ', $attached).'.');
        }

        return $message->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
