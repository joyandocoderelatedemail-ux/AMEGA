<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the client their visa or SRRV file has moved to a new stage.
 */
class FileStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $service,
        public string $reference,
        public string $clientName,
        public string $stageLabel,
        public ?string $note = null,
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
        $message = (new MailMessage)
            ->subject("Update on your {$this->service} - {$this->reference}")
            ->greeting('Hello '.($this->clientName ?: 'there').',')
            ->line("There is an update on your {$this->service} ({$this->reference}).")
            ->line("**Current status:** {$this->stageLabel}");

        if ($this->note) {
            $message->line($this->note);
        }

        return $message
            ->line('If you have any questions, reply to this email or contact our office.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
