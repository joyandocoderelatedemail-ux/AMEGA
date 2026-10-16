<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the client their booking has been received by the ticketing desk.
 */
class TicketBookedNotification extends Notification
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
            ->subject("Booking received - {$ticket->booking_reference}")
            ->greeting('Hello '.($ticket->contact_name ?: 'there').',')
            ->line('Thank you for booking with Amega Travel and Tours Services. We have received your booking.')
            ->line("**Reference:** {$ticket->booking_reference}");

        // Every multi-city leg in order, or the route with its dates.
        if ($ticket->multiCityLegs() !== []) {
            $message->line('**Itinerary:**');
        }
        foreach ($ticket->itineraryLines() as $line) {
            $message->line($line);
        }

        $message->line("**Passengers:** {$ticket->total_passengers}");

        if ((float) $ticket->total_amount > 0) {
            $message->line('**Total:** PHP '.number_format((float) $ticket->total_amount, 2));
        }

        return $message
            ->line('Your ticket will be issued once payment is completed. We will email you again when it is issued.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
