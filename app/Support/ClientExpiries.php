<?php

namespace App\Support;

use App\Models\ExpiryReminder;
use App\Models\ImmigrationClient;
use App\Models\Scopes\OwnFilesScope;
use App\Models\User;
use App\Services\ClientNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every client document with an expiry date on file, one row each: the visa on an Immigration folder,
 * and the passport and ACR I-Card on the client's account (the ACR date may also come from a 13(A) or
 * ACR renewal case, whichever is latest). Used by Reports & Analytics > Expiring Documents and the
 * daily one-month reminder.
 */
class ClientExpiries
{
    /** The documents listed, key => label. */
    public const DOCUMENTS = ['visa' => 'Visa', 'passport' => 'Passport', 'acr' => 'ACR I-Card'];

    /** The automatic reminder goes this many days before the date. */
    public const AUTO_REMIND_DAYS = 30;

    /**
     * @param  bool  $allFiles  every folder, whoever opened it (the daily job); otherwise the viewer's own-files rules apply
     * @return Collection<int, array{key: string, document: string, label: string, name: string, email: ?string, phone: ?string, number: ?string, date: Carbon, days: int, tone: string, when: string, url: string, user_id: ?int, folder_id: ?int}>
     */
    public static function all(bool $allFiles = false): Collection
    {
        $folders = ImmigrationClient::query()
            ->when($allFiles, fn ($query) => $query->withoutGlobalScope(OwnFilesScope::class))
            ->with(['user:id,name,email,phone,passport_number,acr_valid_until,acr_number', 'petitions' => fn ($case) => $case->whereIn('type', ['spouse', 'permanent', 'renewal'])])
            ->get();

        $rows = collect();
        $acrUsers = [];

        foreach ($folders as $folder) {
            $email = $folder->email ?: $folder->user?->real_email;
            $phone = $folder->mobile_number ?: $folder->user?->phone;
            $url = ImmigrationServices::folderUrl($folder);

            if ($folder->visa_expiry_date) {
                $rows->push(self::row('visa', "visa:folder:{$folder->id}", $folder->full_name, $email, $phone, $folder->passport_number ? 'Passport '.$folder->passport_number : null,
                    $folder->visa_expiry_date, ImmigrationServices::folderUrl($folder, 'visa_extension'), $folder->user_id, $folder->id));
            }

            [$acrDate, $acrNumber] = ImmigrationExpiries::latestAcr($folder);
            if ($acrDate) {
                $rows->push(self::row('acr', $folder->user_id ? "acr:user:{$folder->user_id}" : "acr:folder:{$folder->id}", $folder->full_name, $email, $phone,
                    $acrNumber, $acrDate, $url, $folder->user_id, $folder->id));
                if ($folder->user_id) {
                    $acrUsers[$folder->user_id] = true;
                }
            }
        }

        $clients = User::query()
            ->where('role', 'client')
            ->where(fn ($query) => $query->whereNotNull('passport_expiry')->orWhereNotNull('acr_valid_until'))
            ->get();

        foreach ($clients as $client) {
            $url = route('admin.users.show', $client);

            if ($client->passport_expiry) {
                $rows->push(self::row('passport', "passport:user:{$client->id}", $client->full_name, $client->real_email, $client->phone, $client->passport_number,
                    $client->passport_expiry, $url, $client->id, null));
            }

            // A card already listed from the client's folder (which reads the account too) is not repeated.
            if ($client->acr_valid_until && ! isset($acrUsers[$client->id])) {
                $rows->push(self::row('acr', "acr:user:{$client->id}", $client->full_name, $client->real_email, $client->phone, $client->acr_number,
                    $client->acr_valid_until, $url, $client->id, null));
            }
        }

        // Two folders for one client must not list the same card twice.
        $rows = $rows->unique('key')->sortBy(fn (array $row) => $row['date']->timestamp)->values();

        // When each was last reminded, for the page.
        $last = ExpiryReminder::whereIn('doc_key', $rows->pluck('key'))->with('sentBy:id,name')->latest()->get()
            ->groupBy('doc_key')->map->first();

        return $rows->map(fn (array $row) => $row + ['reminded' => ($r = $last->get($row['key'])) && $r->expires_on->isSameDay($row['date']) ? $r : null]);
    }

    /**
     * The rows the daily job reminds: due within AUTO_REMIND_DAYS, not past, and not yet reminded for
     * this date (by the job or by staff).
     */
    public static function dueForAutoReminder(): Collection
    {
        return self::all(allFiles: true)
            ->filter(fn (array $row) => $row['days'] >= 0 && $row['days'] <= self::AUTO_REMIND_DAYS && $row['reminded'] === null)
            ->values();
    }

    private static function row(string $document, string $key, string $name, ?string $email, ?string $phone, ?string $number, Carbon $date, string $url, ?int $userId, ?int $folderId): array
    {
        $days = (int) today()->diffInDays($date->copy()->startOfDay(), false);

        return [
            'key' => $key,
            'document' => $document,
            'label' => self::DOCUMENTS[$document],
            'name' => $name,
            'email' => ClientNotifier::canReceive($email) ? $email : null,
            'phone' => $phone,
            'number' => $number,
            'date' => $date->copy(),
            'days' => $days,
            'tone' => match (true) {
                $days < 0 => 'rose',
                $days <= self::AUTO_REMIND_DAYS => 'amber',
                default => 'slate',
            },
            'when' => match (true) {
                $days < 0 => 'Expired '.abs($days).' '.str('day')->plural(abs($days)).' ago',
                $days === 0 => 'Expires today',
                $days === 1 => 'Expires tomorrow',
                default => 'In '.$days.' days',
            },
            'url' => $url,
            'user_id' => $userId,
            'folder_id' => $folderId,
        ];
    }
}
