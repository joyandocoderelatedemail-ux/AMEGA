<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the approvers an agent asked to unlock a released Immigration file, with the reason and a link
 * to the approvals page where it is approved or declined.
 */
class ImmigrationEditRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $reference, public string $clientName, public string $agent, public string $reason, public string $type = 'details') {}

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
            ->subject(($this->type === 'status' ? 'Status change requested on an immigration file' : 'Edit requested on a locked immigration file')." - {$this->clientName}")
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line($this->type === 'status'
                ? "{$this->agent} asked to change the status of the locked file {$this->reference} of {$this->clientName}. Its details stay locked."
                : "{$this->agent} asked to edit the locked (approved or released) file {$this->reference} of {$this->clientName}.")
            ->line("**Reason:** {$this->reason}")
            ->action('Review the request', route('admin.immigration-approvals.index'))
            ->line('The file stays locked until you approve the request.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
