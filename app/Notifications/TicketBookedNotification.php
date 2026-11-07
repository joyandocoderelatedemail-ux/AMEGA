<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use App\Services\TicketDocumentPdf;
use App\Support\VisaWaiver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        // The visa is not approved yet: the client signs a waiver before the ticket can be issued, so it goes out
        // with the very first email. A waiver that fails to render is logged; the email itself still sends.
        if (! $ticket->hasSignedVisaWaiver() && $waiver = VisaWaiver::forTicket($ticket)) {
            try {
                $message->attachData(TicketDocumentPdf::visaWaiver($waiver), $waiver->filename(), ['mime' => 'application/pdf']);
                $message->line('**Visa Waiver:** your visa is not yet approved, so please print the attached Visa Waiver, sign it and send it back to us. Your ticket cannot be issued until we have the signed copy.');
            } catch (Throwable $e) {
                Log::warning('Could not attach the Visa Waiver to the booking email', ['ticket' => $ticket->booking_reference, 'error' => $e->getMessage()]);
            }
        }

        return $message
            ->line('Your ticket will be issued once payment is completed. We will email you again when it is issued.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
