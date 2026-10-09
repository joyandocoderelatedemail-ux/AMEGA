<?php

namespace App\Notifications;

use App\Models\TicketFlightChange;
use App\Support\FlightChangeMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the client their flight was delayed, rescheduled or cancelled, with
 * what it was and what it is now.
 */
class FlightChangedNotification extends Notification
{
    use Queueable;

    public function __construct(public TicketFlightChange $change) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $change = $this->change;
        $ticket = $change->ticket;

        $message = (new MailMessage)
            ->subject("Flight update for {$ticket->booking_reference}: ".($change->isAirlineCancellation() ? 'cancelled' : 'new schedule'))
            ->greeting('Hello '.($ticket->contact_name ?: 'there').',')
            ->line('Your flight '.trim("{$ticket->origin} to {$ticket->destination}").' '.FlightChangeMessage::headline($change).'.')
            ->line("**Reference:** {$ticket->booking_reference}");

        if (! $change->isAirlineCancellation()) {
            foreach ($change->movements() as $move) {
                $message->line("**{$move['label']}:** {$move['to']} (was {$move['from']})");
            }
        }

        if (filled($change->reason)) {
            $message->line('**Reason:** '.trim($change->reason));
        }

        if ((float) $change->change_fee > 0) {
            $message->line('**Change fee:** PHP '.number_format((float) $change->change_fee, 2));
        }

        return $message
            ->line(FlightChangeMessage::nextStep($change))
            ->line('You can reach us at +63 992 922 5733 or +63 949 9900 663.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
