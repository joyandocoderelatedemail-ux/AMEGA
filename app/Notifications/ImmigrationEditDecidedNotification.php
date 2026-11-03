<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the agent whether the admin unlocked the released file they asked to edit, with the admin's note.
 */
class ImmigrationEditDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $reference, public string $clientName, public bool $approved, public string $admin, public ?string $note = null, public string $type = 'details') {}

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
            ->subject(($this->approved ? 'Edit approved' : 'Edit declined')." - {$this->reference} ({$this->clientName})")
            ->greeting('Hello '.($notifiable->name ?? 'there').',');

        if ($this->approved) {
            $mail->line($this->type === 'status'
                ? "{$this->admin} approved your request: {$this->reference} of {$this->clientName} is unlocked, so you can change its status. The file's details stay locked."
                : "{$this->admin} approved your request: {$this->reference} of {$this->clientName} is open again. Make your changes and save; they go to the admin for approval as usual.");
        } else {
            $mail->line("{$this->admin} declined your request to edit {$this->reference} of {$this->clientName}. The file stays locked.");
        }

        if (filled($this->note)) {
            $mail->line("**Note from the admin:** {$this->note}");
        }

        return $mail->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
