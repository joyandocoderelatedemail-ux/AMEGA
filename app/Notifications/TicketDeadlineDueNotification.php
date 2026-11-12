<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the agent that a booking's airline hold must be ticketed within a day (or has lapsed). */
class TicketDeadlineDueNotification extends Notification
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
        $passed = $ticket->deadlineState() === 'passed';
        $standing = match (true) {
            $ticket->isAwaitingApproval() => 'It is still waiting for admin approval.',
            $ticket->isRejected() => 'It was returned by the admin and needs your correction first.',
            $ticket->isFullyPaid() => 'It is paid in full: issue the ticket.',
            default => 'It is approved and waiting for the cashier to record the payment.',
        };

        return (new MailMessage)
            ->subject(($passed ? 'Airline hold lapsed: ' : 'Ticket within 24 hours: ').$ticket->booking_reference)
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line("Booking **{$ticket->booking_reference}** for **".($ticket->contact_name ?: 'the client').'**: '.$ticket->deadlineLabel().'.')
            ->line('**Booking code (PNR):** '.($ticket->airline_pnr ?: 'not recorded'))
            ->line('**Trip:** '.collect([$ticket->origin, $ticket->destination])->filter()->implode(' → ').($ticket->departure_date ? ' · departs '.$ticket->departure_date->format('M j, Y') : ''))
            ->line($standing)
            ->line($passed
                ? 'Check with the airline whether the seats are still held, and record the new deadline if they extended it.'
                : 'If it is not paid and issued by then, the airline releases the seats.')
            ->action('Open the booking', route('ticketing.tickets.show', $ticket).'#airline-hold')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
