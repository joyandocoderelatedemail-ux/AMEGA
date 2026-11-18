<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Tells the agent who made a ticket booking that it moved on without them: an admin approved it
 * (it is with the cashier now), returned it with a reason, or the cashier took it to paid in full
 * (it is ready to issue). Returned and paid are on the agent's bell too; this is the email.
 */
class TicketAgentUpdateNotification extends Notification
{
    use Queueable;

    public const APPROVED = 'approved';

    public const RETURNED = 'returned';

    public const PAID = 'paid';

    public function __construct(public TicketBooking $ticket, public string $kind) {}

    /**
     * Email the booking's agent once the current page has been sent, unless they did it themselves.
     * A failed email never stops the change: the booking shows it either way.
     */
    public static function sendToAgent(TicketBooking $ticket, string $kind, ?User $actor = null): void
    {
        $agent = $ticket->createdBy;

        if (! $agent || ($actor && $agent->is($actor)) || blank($agent->email)) {
            return;
        }

        defer(function () use ($agent, $ticket, $kind): void {
            try {
                $agent->notify(new self($ticket, $kind));
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

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
        $reference = $ticket->booking_reference;

        $mail = (new MailMessage)
            ->subject(match ($this->kind) {
                self::APPROVED => "Booking approved - {$reference}",
                self::RETURNED => "Booking returned to you - {$reference}",
                default => "Paid in full, ready to issue - {$reference}",
            })
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line(match ($this->kind) {
                self::APPROVED => "Booking {$reference} was approved. It is now with the cashier for payment.",
                self::RETURNED => "Booking {$reference} was returned to you to correct.",
                default => "Booking {$reference} is paid in full. You can issue the ticket now.",
            });

        if ($this->kind === self::RETURNED && filled($ticket->review_note)) {
            $mail->line('**What to correct:** '.$ticket->review_note);
        }

        return $mail
            ->line('**Client:** '.($ticket->contact_name ?: '—'))
            ->line('**Trip:** '.$ticket->routeSummary().($ticket->departure_date ? ' · '.$ticket->departure_date->format('M j, Y') : ''))
            ->action('Open the booking', route('ticketing.tickets.show', $ticket))
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
