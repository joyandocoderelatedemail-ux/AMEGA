<?php

namespace App\Console\Commands;

use App\Models\Scopes\OwnFilesScope;
use App\Models\TicketBooking;
use App\Notifications\TicketQuotationExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendQuotationExpiryReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ticketing:send-quotation-reminders';

    /**
     * @var string
     */
    protected $description = 'Email each agent whose ticketing quotation ends tomorrow, ends today or has ended';

    /**
     * Each quotation is emailed once per end date (a new date starts over). A send that fails is not marked,
     * so tomorrow's run tries again.
     */
    public function handle(): int
    {
        $quotations = TicketBooking::withoutGlobalScope(OwnFilesScope::class)
            ->quoteEnding()
            ->whereNull('quote_expiry_notified_at')
            ->whereNotNull('created_by')
            ->with('createdBy')
            ->get();

        $sent = 0;

        foreach ($quotations as $ticket) {
            $agent = $ticket->createdBy;

            if ($agent === null || blank($agent->email)) {
                continue;
            }

            try {
                $agent->notifyNow(new TicketQuotationExpiringNotification($ticket));
                $ticket->forceFill(['quote_expiry_notified_at' => now()])->saveQuietly();
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Quotation expiry reminder failed', ['ticket' => $ticket->booking_reference, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Sent {$sent} quotation expiry reminder(s).");

        return self::SUCCESS;
    }
}
