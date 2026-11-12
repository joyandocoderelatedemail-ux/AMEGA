<?php

namespace App\Console\Commands;

use App\Models\Scopes\OwnFilesScope;
use App\Models\TicketBooking;
use App\Notifications\TicketDeadlineDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTicketDeadlineReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ticketing:send-deadline-reminders';

    /**
     * @var string
     */
    protected $description = 'Email each agent whose booking must be ticketed with the airline within 24 hours';

    /**
     * Each booking is emailed once per deadline (a new deadline starts over). A send that fails is not
     * marked, so the next run tries again.
     */
    public function handle(): int
    {
        $tickets = TicketBooking::withoutGlobalScope(OwnFilesScope::class)
            ->deadlineDue()
            ->whereNull('deadline_reminded_at')
            ->whereNotNull('created_by')
            ->with('createdBy')
            ->get();

        $sent = 0;

        foreach ($tickets as $ticket) {
            $agent = $ticket->createdBy;

            if ($agent === null || blank($agent->email)) {
                continue;
            }

            try {
                $agent->notifyNow(new TicketDeadlineDueNotification($ticket));
                $ticket->forceFill(['deadline_reminded_at' => now()])->saveQuietly();
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Ticketing deadline reminder failed', ['ticket' => $ticket->booking_reference, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Sent {$sent} ticketing deadline reminder(s).");

        return self::SUCCESS;
    }
}
