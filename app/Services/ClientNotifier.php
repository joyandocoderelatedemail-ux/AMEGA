<?php

namespace App\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails a desk client about their file.
 *
 * Desk files hold the client's address as plain text rather than a user
 * account, so notifications go to an on-demand route. Sending happens in the
 * request (the host runs no queue worker), and a mail failure is logged, never
 * thrown: the counter's action has already succeeded and must not error out.
 */
class ClientNotifier
{
    /**
     * Domain of the placeholder addresses given to walk-in clients with no email.
     */
    private const PLACEHOLDER_DOMAIN = '@clients.amegatravel.local';

    /**
     * Send the notification if the address can receive mail.
     */
    public static function send(?string $email, ?string $name, Notification $notification): bool
    {
        $email = Str::lower(trim((string) $email));

        if (! self::canReceive($email)) {
            return false;
        }

        try {
            NotificationFacade::route('mail', [$email => trim((string) $name) ?: $email])
                ->notifyNow($notification);

            return true;
        } catch (Throwable $e) {
            Log::warning('Client email notification failed', [
                'notification' => $notification::class,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function canReceive(?string $email): bool
    {
        return filled($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && ! str_ends_with(Str::lower($email), self::PLACEHOLDER_DOMAIN);
    }
}
