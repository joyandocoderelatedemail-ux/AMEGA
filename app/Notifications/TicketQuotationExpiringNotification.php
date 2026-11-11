<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the agent who made a ticketing quotation that its price is about to stop holding (or has). */
class TicketQuotationExpiringNotification extends Notification
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

        return (new MailMessage)
            ->subject("Quotation {$ticket->booking_reference} {$ticket->quoteEndingLabel()}")
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line("Your quotation **{$ticket->booking_reference}** for **".($ticket->contact_name ?: 'the client')."** {$ticket->quoteEndingLabel()}.")
            ->line('**Trip:** '.collect([$ticket->origin, $ticket->destination])->filter()->implode(' → ').($ticket->departure_date ? ' · departs '.$ticket->departure_date->format('M j, Y') : ''))
            ->line('**Quoted total:** ₱'.number_format((float) $ticket->total_amount, 2))
            ->line('Follow up with the client: convert it to a booking if they agree, or give it a new date if the price still holds.')
            ->action('Open the quotation', route('ticketing.tickets.show', $ticket))
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
