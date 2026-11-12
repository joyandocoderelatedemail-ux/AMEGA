<?php

namespace App\Services;

use App\Models\ExpiryReminder;
use App\Models\User;
use App\Notifications\DocumentExpiryReminderNotification;

/**
 * Sends a client the reminder for one row of App\Support\ClientExpiries, by email (and SMS when it is
 * switched on), and records it. Null when the client could not be reached at all.
 */
class ExpiryReminderSender
{
    /**
     * @param  array<string, mixed>  $row
     * @param  ?User  $by  the staff member, or null for the daily job
     */
    public static function send(array $row, ?User $by): ?ExpiryReminder
    {
        $channels = [];

        if ($row['email'] && ClientNotifier::send($row['email'], $row['name'], new DocumentExpiryReminderNotification(
            $row['name'], $row['document'], $row['label'], $row['date'], $row['number'],
        ))) {
            $channels[] = 'email';
        }

        if ($row['phone'] && SmsSender::send($row['phone'], 'AMEGA Travel: '.ucfirst(DocumentExpiryReminderNotification::sentence($row['label'], $row['date']))
            .'. Please contact us to arrange the renewal.')) {
            $channels[] = 'sms';
        }

        if ($channels === []) {
            return null;
        }

        return ExpiryReminder::create([
            'doc_key' => $row['key'],
            'document' => $row['document'],
            'expires_on' => $row['date']->toDateString(),
            'user_id' => $row['user_id'],
            'immigration_client_id' => $row['folder_id'],
            'channel' => implode('+', $channels),
            'sent_by' => $by?->id,
        ]);
    }
}
