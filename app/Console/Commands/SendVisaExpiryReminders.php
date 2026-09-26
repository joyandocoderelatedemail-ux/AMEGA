<?php

namespace App\Console\Commands;

use App\Models\ImmigrationClient;
use App\Notifications\VisaExpiryReminderNotification;
use App\Services\ClientNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendVisaExpiryReminders extends Command
{
    /**
     * Visas expiring within this many days get a reminder.
     */
    public const WINDOW_DAYS = 7;

    /**
     * @var string
     */
    protected $signature = 'immigration:send-expiry-reminders';

    /**
     * @var string
     */
    protected $description = 'Email immigration clients whose visa expires within the next 7 days';

    /**
     * Each client is reminded once per expiry date: a sheet whose visa is
     * extended gets a fresh reminder when the new date comes around. A send
     * that fails is not marked, so tomorrow's run tries again.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        $clients = ImmigrationClient::query()
            ->whereNotNull('email')
            ->whereBetween('visa_expiry_date', [$today, $today->copy()->addDays(self::WINDOW_DAYS)])
            ->where(fn ($pending) => $pending
                ->whereNull('expiry_reminder_sent_for')
                ->orWhereRaw('DATE(expiry_reminder_sent_for) <> DATE(visa_expiry_date)'))
            ->get();

        $sent = 0;

        foreach ($clients as $client) {
            if (ClientNotifier::send($client->email, $client->full_name, new VisaExpiryReminderNotification($client))) {
                $client->forceFill(['expiry_reminder_sent_for' => $client->visa_expiry_date->toDateString()])->saveQuietly();
                $sent++;
            }
        }

        $this->info("Sent {$sent} visa expiry reminder(s).");

        return self::SUCCESS;
    }
}
