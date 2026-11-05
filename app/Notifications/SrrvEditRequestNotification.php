<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A Request edit on a released or approved SRRV retiree file: to the approvers when the agent asks (with the reason), and back
 * to the agent when the admin approves (the file is open again) or declines it (with the admin's note).
 */
class SrrvEditRequestNotification extends Notification
{
    use Queueable;

    /** @param  string  $state  'requested', 'approved' or 'declined' */
    public function __construct(public string $state, public string $reference, public string $clientName, public string $by, public ?string $note = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Hello '.($notifiable->name ?? 'there').',');

        $mail = match ($this->state) {
            'requested' => $mail->subject("Edit requested on an SRRV file - {$this->clientName}")
                ->line("{$this->by} asked to edit the file {$this->reference} of {$this->clientName}.")
                ->line("**Reason:** {$this->note}")
                ->action('Review the request', route('admin.srrv-approvals.index'))
                ->line('The file stays locked until you approve the request.'),
            'approved' => $mail->subject("Edit approved - {$this->reference}")
                ->line("{$this->by} approved your request: {$this->reference} ({$this->clientName}) is open again, so you can edit it.")
                ->line($this->note ? "**Note:** {$this->note}" : 'Your changes go to the admin for approval as usual.')
                ->action('Open the file', route('srrv.services', 'classic')),
            default => $mail->subject("Edit declined - {$this->reference}")
                ->line("{$this->by} declined your request to edit {$this->reference} ({$this->clientName}). The file stays locked.")
                ->line("**Reply:** {$this->note}"),
        };

        return $mail->salutation("Regards,\nAmega Travel and Tours Services");
    }
}
