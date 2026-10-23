<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins a staff member saved a desk file (SRRV, visa counter) that is waiting for their
 * approval, with a link to that desk's approvals page.
 */
class DeskApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $desk,
        public string $fileName,
        public string $reference,
        public string $agent,
        public string $url,
        public bool $resubmitted = false,
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
        return (new MailMessage)
            ->subject(($this->resubmitted ? "Corrected {$this->desk} file to approve" : "{$this->desk} file to approve")." - {$this->fileName}")
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line($this->resubmitted
                ? "{$this->agent} corrected {$this->reference} ({$this->fileName}) and sent it back for your approval."
                : "{$this->agent} saved {$this->reference} ({$this->fileName}) and it needs your approval.")
            ->action('Review it', $this->url)
            ->line('Until you approve it, the file cannot be advanced, paid, printed or sent to the client.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
