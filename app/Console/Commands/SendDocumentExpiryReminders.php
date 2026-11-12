<?php

namespace App\Console\Commands;

use App\Services\ExpiryReminderSender;
use App\Support\ClientExpiries;
use Illuminate\Console\Command;

class SendDocumentExpiryReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'clients:send-expiry-reminders';

    /**
     * @var string
     */
    protected $description = 'Remind clients a month before their visa, passport or ACR I-Card expires';

    /**
     * Each document is reminded once per expiry date; one a staff member already reminded is skipped.
     * A client who cannot be reached is tried again on the next run.
     */
    public function handle(): int
    {
        $sent = 0;

        foreach (ClientExpiries::dueForAutoReminder() as $row) {
            if (ExpiryReminderSender::send($row, null)) {
                $sent++;
            }
        }

        $this->info("Sent {$sent} document expiry reminder(s).");

        return self::SUCCESS;
    }
}
