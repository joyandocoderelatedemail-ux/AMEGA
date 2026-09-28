<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use App\Services\TicketDocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the client their ticket has been issued, with an e-ticket card for
 * each passenger on each flight, and attaches their copy of the Data Privacy
 * Consent Form and, when one has been drawn up, the Booking Agreement.
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
        $ticket = $this->ticket->loadMissing(['passengers', 'airline']);

        $message = (new MailMessage)
            ->subject("Your ticket has been issued - {$ticket->booking_reference}")
            ->greeting('Hello '.($ticket->contact_name ?: 'there').',')
            ->line('Good news: your ticket has been issued. Please keep this email for your records.');

        $attached = TicketDocumentPdf::attachTo($message, $ticket);

        if ($attached !== []) {
            $message->line('Attached for your records: '.implode(' and ', $attached).'.');
        }

        return $message
            ->salutation("Regards,\nAmega Travel and Tours Services")
            ->markdown('mail.ticket-issued', [
                'ticket' => $ticket,
                'passes' => $this->passes($ticket),
                'checkInWith' => $ticket->airline?->name ?? $ticket->preferred_airline ?: 'your airline',
            ]);
    }

    /**
     * One card per passenger per flight, the way airlines issue boarding
     * passes. A booking with no manifest yet gets one card per flight in the
     * booker's name.
     *
     * @return list<array{passenger: string, leg: array<string, mixed>}>
     */
    private function passes(TicketBooking $ticket): array
    {
        $names = $ticket->passengers->sortBy('passenger_number')
            ->map(fn ($passenger): string => (string) $passenger->full_name)
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            $names = collect([(string) $ticket->contact_name]);
        }

        $passes = [];

        foreach ($ticket->itineraryLegs() as $leg) {
            foreach ($names as $name) {
                $passes[] = ['passenger' => $name, 'leg' => $leg];
            }
        }

        return $passes;
    }
}
