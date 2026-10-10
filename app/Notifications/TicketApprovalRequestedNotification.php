<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins a ticket booking is waiting for their approval, with a link
 * to its Official Ticket Booking Confirmation on the approvals page.
 */
class TicketApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public TicketBooking $ticket, public bool $resubmitted = false) {}

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
        $agent = $ticket->createdBy?->name ?? 'An agent';

        return (new MailMessage)
            ->subject(($this->resubmitted ? 'Corrected booking to approve' : 'Booking to approve').' - '.$ticket->booking_reference)
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line($this->resubmitted
                ? "{$agent} corrected booking {$ticket->booking_reference} and sent it back for your approval."
                : "{$agent} submitted booking {$ticket->booking_reference} for your approval.")
            ->line('**Client:** '.($ticket->contact_name ?: '—'))
            ->line('**Trip:** '.trim($ticket->origin.' → '.$ticket->destination, ' →').($ticket->departure_date ? ' · '.$ticket->departure_date->format('M j, Y') : ''))
            ->line('**Total:** PHP '.number_format((float) $ticket->total_amount, 2))
            ->action('Review the booking', route('admin.ticket-approvals.show', $ticket))
            ->line('Once approved, it goes to the cashier for payment.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
