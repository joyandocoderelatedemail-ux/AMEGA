<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds the client their flight is coming up so they don't miss it.
 */
class FlightReminderNotification extends Notification
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
        $when = self::when($ticket);

        $message = (new MailMessage)
            ->subject("Reminder from Amega: your flight is {$when}")
            ->greeting('Hello '.($ticket->contact_name ?: 'there').',')
            ->line("Your flight is {$when}. Don't miss it!")
            ->line("**Reference:** {$ticket->booking_reference}")
            ->line('**Route:** '.trim("{$ticket->origin} to {$ticket->destination}"))
            ->line('**Departure:** '.$ticket->departure_date->format('D, M j, Y'));

        if ($ticket->preferred_airline) {
            $message->line("**Airline:** {$ticket->preferred_airline}");
        }

        return $message
            ->line('Please arrive at the airport early and bring a valid ID or passport matching your ticket.')
            ->salutation("Safe travels,\nAmega Travel and Tours Services");
    }

    /**
     * How far off the departure is, in words: "today", "tomorrow", "in 2 days".
     */
    public static function when(TicketBooking $ticket): string
    {
        $days = (int) now()->startOfDay()->diffInDays($ticket->departure_date->copy()->startOfDay(), false);

        return match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => "in {$days} days",
        };
    }
}
