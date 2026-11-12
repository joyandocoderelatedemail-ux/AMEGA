<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Reminds a client that their visa, passport or ACR I-Card runs out (or has), and what to do. */
class DocumentExpiryReminderNotification extends Notification
{
    use Queueable;

    /** What the client should do about each document. */
    public const ADVICE = [
        'visa' => 'Please visit our office to arrange your visa extension before it expires, to avoid overstay penalties. Bring your passport.',
        'passport' => 'Please renew your passport before you travel: most countries and airlines need it valid for at least six months. We can assist with the renewal.',
        'acr' => 'Please visit our office to renew your ACR I-Card. Bring your passport and your current ACR I-Card.',
    ];

    public function __construct(
        public string $name,
        public string $document,
        public string $label,
        public Carbon $date,
        public ?string $number = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function sentence(string $label, Carbon $date): string
    {
        $days = (int) today()->diffInDays($date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => "your {$label} expired on {$date->format('F j, Y')}",
            $days === 0 => "your {$label} expires today, {$date->format('F j, Y')}",
            default => "your {$label} expires on {$date->format('F j, Y')}, in {$days} ".str('day')->plural($days),
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Reminder: your '.$this->label.($this->date->isPast() && ! $this->date->isToday() ? ' has expired' : ' expires on '.$this->date->format('M j, Y')))
            ->greeting('Hello '.$this->name.',')
            ->line('This is a reminder that '.self::sentence($this->label, $this->date).'.');

        if ($this->number) {
            $mail->line("**{$this->label}:** {$this->number}");
        }

        return $mail
            ->line(self::ADVICE[$this->document] ?? 'Please visit our office so we can help you renew it.')
            ->line('Reply to this email or call us to set an appointment.')
            ->salutation("Regards,\nAmega Travel and Tours Services\nUnit 1&2, Astrofield Building, Balibago, Angeles City");
    }
}
