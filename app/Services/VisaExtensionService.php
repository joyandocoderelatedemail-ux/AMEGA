<?php

namespace App\Services;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use Illuminate\Support\Carbon;

/**
 * A finished extension moves the visa on. Each ledger row with a date and a length pushes the
 * expiry forward from the current one (or from the extension date when no expiry is on file),
 * the expired and penalty marks are cleared, and the expiry reminder starts over for the new date.
 */
class VisaExtensionService
{
    /**
     * Move the client's visa on for these rows, in ledger order. A row the visa was already moved
     * for is skipped, so approving a row later never moves it twice.
     *
     * @param  iterable<ImmigrationClientExtension>  $rows
     * @return Carbon|null the new expiry, or null when no row moved the visa
     */
    public static function extend(ImmigrationClient $client, iterable $rows, bool $allowed = true): ?Carbon
    {
        $expiry = $client->visa_expiry_date;
        $moved = [];

        foreach (collect($rows)->sortBy('sequence') as $extension) {
            if (! $allowed || $extension->extension_date === null || $extension->visa_extended_at !== null) {
                continue;
            }

            $until = ImmigrationClientExtension::runsUntil($expiry ?? $extension->extension_date, $extension->duration);

            if ($until !== null) {
                $expiry = $until;
                $moved[] = $extension;
            }
        }

        if ($moved === []) {
            return null;
        }

        $client->forceFill([
            'visa_expiry_date' => $expiry,
            'is_expired' => false,
            'has_penalty' => false,
            'expiry_reminder_sent_for' => null,
        ])->save();

        foreach ($moved as $extension) {
            $extension->forceFill(['visa_extended_at' => now()])->save();
        }

        ActivityLogger::log('Client Sheets', 'EXTEND', "Visa of '{$client->full_name}' extended to {$expiry->format('M d, Y')}");

        return $expiry;
    }
}
