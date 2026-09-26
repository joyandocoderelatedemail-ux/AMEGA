<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the client their ticket has been issued, with the itinerary and passengers.
 */
class TicketIssuedNotification extends Notification
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
        $ticket = $this->ticket->loadMissing('passengers');

        $message = (new MailMessage)
            ->subject("Your ticket has been issued - {$ticket->booking_reference}")
            ->greeting('Hello '.($ticket->contact_name ?: 'there').',')
            ->line('Good news: your ticket has been issued. Please keep this email for your records.')
            ->line("**Reference:** {$ticket->booking_reference}")
            ->line('**Route:** '.trim("{$ticket->origin} to {$ticket->destination}"))
            ->line('**Departure:** '.($ticket->departure_date?->format('D, M j, Y') ?? 'To be confirmed'));

        if ($ticket->return_date) {
            $message->line('**Return:** '.$ticket->return_date->format('D, M j, Y'));
        }

        if ($ticket->preferred_airline) {
            $message->line("**Airline:** {$ticket->preferred_airline}");
        }

        $names = $ticket->passengers->sortBy('passenger_number')->map(fn ($p): string => strtoupper($p->full_name))->filter();
        if ($names->isNotEmpty()) {
            $message->line('**Passengers:** '.$names->implode(', '));
        }

        return $message
            ->line('Please bring a valid ID or passport matching the names above when you travel.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
