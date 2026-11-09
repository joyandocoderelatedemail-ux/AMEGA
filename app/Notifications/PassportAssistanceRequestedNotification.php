<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use App\Models\TicketPassenger;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Tells the Visa Assistance desk that an international booking has passengers with no valid passport (none, expired or under six months left),
 * so the desk can help them apply while the ticket goes through approval and payment.
 */
class PassportAssistanceRequestedNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, TicketPassenger>  $passengers  Those who ticked "Need Passport Assistance".
     */
    public function __construct(public TicketBooking $ticket, public Collection $passengers) {}

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
        $agent = $ticket->createdBy?->name ?? 'A ticketing agent';
        $count = $this->passengers->count();

        $mail = (new MailMessage)
            ->subject('Passport assistance needed - '.$ticket->booking_reference)
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line("{$agent} booked {$ticket->booking_reference} with {$count} ".($count === 1 ? 'passenger' : 'passengers').' who need passport assistance (no passport, expired, or under six months left):');

        foreach ($this->passengers as $passenger) {
            $mail->line('• **'.$passenger->full_name.'**');
        }

        return $mail
            ->line('**Destination:** '.($ticket->destination ?: '—'))
            ->line('**Departure:** '.($ticket->departure_date ? $ticket->departure_date->format('M j, Y') : '—'))
            ->line('**Contact:** '.($ticket->contact_name ?: '—').($ticket->contact_phone ? ' · '.$ticket->contact_phone : '').($ticket->contact_email ? ' · '.$ticket->contact_email : ''))
            ->action('Open Visa Assistance', route('visa.dashboard'))
            ->line('Please contact the client about their passport application. The ticket cannot be issued until a valid passport is on file.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
