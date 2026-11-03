<?php

namespace App\Notifications;

use App\Support\MessageTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails an Immigration client the message an agent sent from the Notice box: the template for the file's
 * status, pasted and edited, or the agent's own words. Branded like the e-ticket emails, with the file it is
 * about (reference, service, status) and who sent it.
 */
class ClientCaseNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{reference: string, service: string, status: string}|null  $file  the case it is about; null for a folder message
     */
    public function __construct(public string $subject, public string $body, public ?array $file = null, public ?string $agent = null) {}

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
            ->subject($this->subject)
            ->view(['html' => 'mail.client-notice', 'text' => 'mail.client-notice-text'], [
                'subject' => $this->subject,
                'paragraphs' => MessageTemplates::paragraphs($this->body),
                'body' => $this->body,
                'file' => $this->file,
                'agent' => $this->agent,
            ]);
    }
}
