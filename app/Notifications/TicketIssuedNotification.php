<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use App\Services\TicketDocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the client their ticket has been issued, with the itinerary and
 * passengers, and attaches their copy of the Data Privacy Consent Form and,
 * when one has been drawn up, the Booking Agreement.
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

        $attached = $this->attachDocuments($message, $ticket);

        if ($attached !== []) {
            $message->line('Attached for your records: '.implode(' and ', $attached).'.');
        }

        return $message
            ->line('Please bring a valid ID or passport matching the names above when you travel.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }

    /**
     * Attach the ticket's paperwork as PDFs. A document that fails to render is
     * logged and left off, so the client still gets their issue email.
     *
     * @return list<string> What was attached, for the email body.
     */
    private function attachDocuments(MailMessage $message, TicketBooking $ticket): array
    {
        $documents = [
            'your Data Privacy Consent Form' => [
                "Data-Privacy-Consent-{$ticket->booking_reference}.pdf",
                fn (): string => TicketDocumentPdf::dataPrivacyConsent($ticket),
            ],
        ];

        if ($agreement = $ticket->bookingAgreement) {
            $documents['your Booking Agreement'] = [
                "Booking-Agreement-{$agreement->agreement_number}.pdf",
                fn (): string => TicketDocumentPdf::bookingAgreement($agreement),
            ];
        }

        $attached = [];

        foreach ($documents as $description => [$filename, $render]) {
            try {
                $message->attachData($render(), $filename, ['mime' => 'application/pdf']);
                $attached[] = $description;
            } catch (Throwable $e) {
                Log::warning('Could not attach a ticket document to the issue email', [
                    'ticket' => $ticket->booking_reference,
                    'document' => $filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $attached;
    }
}
