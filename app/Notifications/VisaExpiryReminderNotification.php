<?php

namespace App\Notifications;

use App\Models\ImmigrationClient;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds an immigration client that their Philippine visa is about to expire.
 */
class VisaExpiryReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public ImmigrationClient $client) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiry = $this->client->visa_expiry_date;
        $days = (int) now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false);
        $when = match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => "in {$days} days",
        };

        return (new MailMessage)
            ->subject('Your visa expires '.$when)
            ->greeting('Hello '.($this->client->given_name ?: $this->client->full_name).',')
            ->line("This is a reminder that your visa expires {$when}, on **{$expiry->format('F j, Y')}**.")
            ->line('To avoid overstay penalties, please visit our office to arrange your visa extension before it expires.')
            ->line('Bring your passport and any immigration documents you have on file.')
            ->salutation("Regards,\nAmega Travel and Tours Services\nUnit 1&2, Astrofield Building, Balibago, Angeles City");
    }
}
