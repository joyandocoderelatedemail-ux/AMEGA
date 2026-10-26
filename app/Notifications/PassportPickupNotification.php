<?php

namespace App\Notifications;

use App\Models\ImmigrationClient;
use App\Support\MessageTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an Immigration client their passport and visa are ready to collect, in the wording an
 * admin set under Message Templates (the desk's default otherwise). The same text is offered to
 * copy for a text or Messenger blast.
 */
class PassportPickupNotification extends Notification
{
    use Queueable;

    public function __construct(public ImmigrationClient $client, public ?string $sender = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = self::message($this->client, $this->sender);

        return (new MailMessage)
            ->subject($message['subject'])
            ->markdown('mail.template-message', ['paragraphs' => MessageTemplates::paragraphs($message['body'])]);
    }

    /**
     * The filled-in notice, for the email and for staff to copy.
     *
     * @return array{subject: string, body: string}
     */
    public static function message(ImmigrationClient $client, ?string $sender): array
    {
        return MessageTemplates::render('passport_pickup', [
            'client_name' => $client->full_name,
            'valid_until' => $client->visa_expiry_date?->format('F j, Y'),
            'passport_number' => $client->passport_number,
            'agent_name' => $sender,
        ]);
    }
}
