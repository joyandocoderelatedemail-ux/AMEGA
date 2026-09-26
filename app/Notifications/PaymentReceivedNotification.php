<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A payment acknowledgement for a file on any desk.
 */
class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $service,
        public string $reference,
        public string $clientName,
        public string $currency,
        public float $amountReceived,
        public float $totalPaid,
        public float $balance,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $money = fn (float $amount): string => $this->currency.' '.number_format($amount, 2);

        return (new MailMessage)
            ->subject("Payment received - {$this->reference}")
            ->greeting('Hello '.($this->clientName ?: 'there').',')
            ->line("We have received your payment for your {$this->service} ({$this->reference}). Thank you.")
            ->line('**Amount received:** '.$money($this->amountReceived))
            ->line('**Total paid to date:** '.$money($this->totalPaid))
            ->line('**Balance:** '.($this->balance > 0 ? $money($this->balance) : 'Fully paid'))
            ->line('This email is an acknowledgement, not an official receipt.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
