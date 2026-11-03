<?php

namespace App\Notifications;

use App\Support\MessageTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails an Immigration client the notice for where their file stands, in the wording already filled in
 * from the Message Templates (see CaseNotice). The same text is offered to copy for a text or Messenger.
 */
class ClientCaseNotification extends Notification
{
    use Queueable;

    public function __construct(public string $subject, public string $body) {}

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
            ->markdown('mail.template-message', ['paragraphs' => MessageTemplates::paragraphs($this->body)]);
    }
}
