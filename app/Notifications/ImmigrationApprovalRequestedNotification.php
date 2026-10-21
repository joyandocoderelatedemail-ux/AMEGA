<?php

namespace App\Notifications;

use App\Models\ImmigrationClient;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins an agent saved immigration work that is waiting for their approval, with a
 * link to the approvals page.
 */
class ImmigrationApprovalRequestedNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $items  what was saved, one line each (a case reference, or the extensions added)
     */
    public function __construct(public ImmigrationClient $client, public array $items, public string $agent, public bool $resubmitted = false) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(($this->resubmitted ? 'Corrected immigration file to approve' : 'Immigration file to approve').' - '.$this->client->full_name)
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line($this->resubmitted
                ? "{$this->agent} corrected the file of {$this->client->full_name} and sent it back for your approval."
                : "{$this->agent} saved work for {$this->client->full_name} that needs your approval.");

        foreach ($this->items as $item) {
            $mail->line('• '.$item);
        }

        return $mail
            ->action('Review it', route('admin.immigration-approvals.index'))
            ->line('Until you approve it, the file cannot be printed or marked submitted or released.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
