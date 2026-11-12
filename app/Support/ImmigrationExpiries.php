<?php

namespace App\Support;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The counter's call lists: visas running out (or lapsed) and ACR I-Cards running out (or expired),
 * so staff phone the client instead of waiting for them, and the next service starts in one click.
 * Folders follow the counter's own-files rules (OwnFilesScope on ImmigrationClient).
 */
class ImmigrationExpiries
{
    /** Visas expiring within this many days are listed. */
    public const VISA_AHEAD_DAYS = 30;

    /** Visas that lapsed up to this many days ago stay listed (an overstay to fix); older ones drop off. */
    public const VISA_BEHIND_DAYS = 90;

    /** ACR I-Cards expiring within this many days are listed. */
    public const ACR_AHEAD_DAYS = 60;

    /** Expired cards stay listed for this long. */
    public const ACR_BEHIND_DAYS = 180;

    /**
     * Folders whose visa runs out within VISA_AHEAD_DAYS or lapsed within VISA_BEHIND_DAYS, soonest
     * first (the lapsed ones lead).
     *
     * @return Collection<int, array{folder: ImmigrationClient, date: Carbon, days: int, tone: string, when: string, action: string, url: string}>
     */
    public static function visas(): Collection
    {
        $today = today();

        return ImmigrationClient::query()
            ->whereNotNull('visa_expiry_date')
            ->whereBetween('visa_expiry_date', [$today->copy()->subDays(self::VISA_BEHIND_DAYS), $today->copy()->addDays(self::VISA_AHEAD_DAYS)])
            ->orderBy('visa_expiry_date')
            ->with('user:id,phone')
            ->get()
            ->map(function (ImmigrationClient $folder) use ($today): array {
                $days = (int) $today->diffInDays($folder->visa_expiry_date->copy()->startOfDay(), false);
                $lapsed = $days < 0;

                return [
                    'folder' => $folder,
                    'date' => $folder->visa_expiry_date,
                    'days' => $days,
                    'tone' => self::tone($days),
                    'when' => self::when($days, 'Lapsed'),
                    // A lapsed visa is an overstay to update; one still valid is extended.
                    'action' => $lapsed ? 'Start overstay' : 'Start extension',
                    'url' => ImmigrationServices::folderUrl($folder, $lapsed ? 'visa_overstaying' : 'visa_extension'),
                ];
            });
    }

    /**
     * Folders whose ACR I-Card expires within ACR_AHEAD_DAYS or expired within ACR_BEHIND_DAYS. The
     * expiry is the latest one on file: the client's profile, or a 13(A) / ACR renewal case. A folder
     * with a renewal under way, or renewed since, is left out.
     *
     * @return Collection<int, array{folder: ImmigrationClient, date: Carbon, days: int, tone: string, when: string, acr: ?string, url: string}>
     */
    public static function acrCards(): Collection
    {
        $today = today();
        $from = $today->copy()->subDays(self::ACR_BEHIND_DAYS);
        $until = $today->copy()->addDays(self::ACR_AHEAD_DAYS);

        return ImmigrationClient::query()
            ->where(fn ($query) => $query
                ->whereHas('user', fn ($user) => $user->whereNotNull('acr_valid_until'))
                ->orWhereHas('petitions', fn ($case) => $case->whereIn('type', ['spouse', 'permanent', 'renewal'])))
            ->with(['user:id,phone,acr_valid_until,acr_number', 'petitions' => fn ($case) => $case->whereIn('type', ['spouse', 'permanent', 'renewal'])])
            ->get()
            ->map(function (ImmigrationClient $folder) use ($today): ?array {
                [$date, $acr] = self::latestAcr($folder);
                if ($date === null) {
                    return null;
                }

                // A renewal still open, or one released within the card's last 90 days, has this in hand.
                $renewing = $folder->petitions->contains(fn (ImmigrationPetition $case) => $case->type === 'renewal'
                    && ($case->released_on === null || $case->released_on->gte($date->copy()->subDays(90))));
                if ($renewing) {
                    return null;
                }

                $days = (int) $today->diffInDays($date->copy()->startOfDay(), false);

                return [
                    'folder' => $folder,
                    'date' => $date,
                    'days' => $days,
                    'tone' => self::tone($days),
                    'when' => self::when($days, 'Expired'),
                    'acr' => $acr,
                    'url' => ImmigrationServices::folderUrl($folder, 'permanent_13a_series'),
                ];
            })
            ->filter(fn (?array $row) => $row !== null && $row['date']->between($from, $until))
            ->sortBy(fn (array $row) => $row['date']->timestamp)
            ->values();
    }

    /**
     * The latest ACR I-Card expiry on file for the folder, and the card number that goes with it.
     *
     * @return array{0: ?Carbon, 1: ?string}
     */
    private static function latestAcr(ImmigrationClient $folder): array
    {
        $known = collect();

        if ($folder->user?->acr_valid_until) {
            $known->push([$folder->user->acr_valid_until->copy(), $folder->user->acr_number]);
        }

        foreach ($folder->petitions as $case) {
            $expires = $case->details['acr_expires_on'] ?? null;
            if (filled($expires)) {
                try {
                    $known->push([Carbon::parse($expires), $case->details['acr_number'] ?? null]);
                } catch (\Throwable) {
                    // A date typed badly on an old case is skipped, not fatal.
                }
            }
        }

        return $known->sortByDesc(fn (array $row) => $row[0]->timestamp)->first() ?? [null, null];
    }

    /** rose once lapsed, amber within 7 days, slate otherwise. */
    private static function tone(int $days): string
    {
        return match (true) {
            $days < 0 => 'rose',
            $days <= 7 => 'amber',
            default => 'slate',
        };
    }

    private static function when(int $days, string $past): string
    {
        return match (true) {
            $days < 0 => $past.' '.abs($days).' '.str('day')->plural(abs($days)).' ago',
            $days === 0 => 'Today',
            $days === 1 => 'Tomorrow',
            default => 'In '.$days.' days',
        };
    }
}
