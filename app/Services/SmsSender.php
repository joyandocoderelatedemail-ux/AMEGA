<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a text message to a client, whichever provider is set up.
 *
 * No provider is connected yet (config/sms.php, driver "none"), so every caller
 * asks enabled() first and falls back to a message staff copy and send by hand.
 * Connecting one later means adding a branch to deliver(); nothing else changes.
 * Like ClientNotifier, a failure is logged and reported as false, never thrown:
 * the action at the counter has already succeeded.
 */
class SmsSender
{
    public static function enabled(): bool
    {
        return in_array(config('sms.driver'), ['log'], true);
    }

    /**
     * Send the message; false when SMS is off, the number is unusable or sending failed.
     */
    public static function send(?string $number, string $message): bool
    {
        $to = self::normalise($number);

        if (! self::enabled() || $to === null) {
            return false;
        }

        try {
            return self::deliver($to, $message);
        } catch (Throwable $e) {
            Log::warning('SMS could not be sent', ['to' => $to, 'driver' => config('sms.driver'), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * The one place a provider plugs in.
     */
    private static function deliver(string $to, string $message): bool
    {
        if (config('sms.driver') === 'log') {
            Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);

            return true;
        }

        return false;
    }

    /**
     * A Philippine mobile number in international form ("+639171234567"), or null
     * when it cannot be one. Numbers already written with another country code
     * are kept as they are.
     */
    public static function normalise(?string $number): ?string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        $international = str_starts_with($number, '+');
        $digits = preg_replace('/\D/', '', $number);

        return match (true) {
            $international && strlen($digits) >= 8 && strlen($digits) <= 15 => '+'.$digits,
            str_starts_with($digits, '63') && strlen($digits) === 12 => '+'.$digits,
            str_starts_with($digits, '09') && strlen($digits) === 11 => '+63'.substr($digits, 1),
            str_starts_with($digits, '9') && strlen($digits) === 10 => '+63'.$digits,
            default => null,
        };
    }
}
