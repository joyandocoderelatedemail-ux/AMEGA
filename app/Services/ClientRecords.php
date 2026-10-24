<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CrmLead;
use App\Models\CustomPackageInquiry;
use App\Models\ImmigrationClient;
use App\Models\Inquiry;
use App\Models\Scopes\OwnFilesScope;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\TouristVisaApplication;
use App\Models\User;
use App\Models\VisaApplication;
use App\Support\DocumentStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Every service recorded for a client, across the desks: online bookings, ticket bookings, visa
 * files, the immigration folder, tourist-visa extensions, SRRV files and renewals, custom package
 * requests, website inquiries and CRM leads. Deleting a client deletes these too.
 *
 * Matching is strict, so another person's file is never caught: the linked account, the exact
 * email, the exact passport number, or the exact full name. Every staff member's files count
 * (OwnFilesScope is lifted), because they are all this client's.
 */
class ClientRecords
{
    /**
     * What is on file for the client: label => query.
     *
     * @return array<string, Builder>
     */
    public static function for(User $client): array
    {
        $email = mb_strtolower(trim((string) $client->real_email));
        $name = mb_strtolower(trim((string) $client->name));
        $passport = trim((string) $client->passport_number);
        $phone = trim((string) $client->phone);

        $exact = fn (Builder $query, string $column, string $value) => $query->whereRaw("LOWER(TRIM({$column})) = ?", [$value]);
        $srrv = SrrvApplication::withoutGlobalScope(OwnFilesScope::class)
            ->where(fn ($q) => $q->when($email !== '', fn ($q) => $exact($q, 'retiree_email', $email))
                ->when($name !== '', fn ($q) => $q->orWhere(fn ($q) => $exact($q, 'retiree_name', $name))));

        return [
            'Online bookings' => Booking::where('user_id', $client->id),
            'Ticket bookings' => TicketBooking::withoutGlobalScope(OwnFilesScope::class)
                ->where(fn ($q) => $q->where('user_id', $client->id)
                    ->when($email !== '', fn ($q) => $q->orWhere(fn ($q) => $exact($q, 'contact_email', $email)))),
            'Visa applications' => VisaApplication::withoutGlobalScope(OwnFilesScope::class)
                ->where(fn ($q) => $q->when($email !== '', fn ($q) => $exact($q, 'client_email', $email))
                    ->when($name !== '', fn ($q) => $q->orWhere(fn ($q) => $exact($q, 'client_name', $name)))),
            'Immigration folders' => ImmigrationClient::where(fn ($q) => $q->where('user_id', $client->id)
                ->when($email !== '', fn ($q) => $q->orWhere(fn ($q) => $exact($q, 'email', $email)))
                ->when($passport !== '', fn ($q) => $q->orWhere('passport_number', $passport))),
            'Tourist visa extensions' => $passport === ''
                ? TouristVisaApplication::withoutGlobalScope(OwnFilesScope::class)->whereRaw('1 = 0')
                : TouristVisaApplication::withoutGlobalScope(OwnFilesScope::class)->forPassport($passport),
            'SRRV renewals' => SrrvRenewal::withoutGlobalScope(OwnFilesScope::class)
                ->where(fn ($q) => $q->whereIn('srrv_application_id', (clone $srrv)->select('id'))
                    ->when($email !== '', fn ($q) => $q->orWhere(fn ($q) => $exact($q, 'retiree_email', $email)))
                    ->when($name !== '', fn ($q) => $q->orWhere(fn ($q) => $exact($q, 'retiree_name', $name)))),
            'SRRV applications' => $srrv,
            'Custom package requests' => CustomPackageInquiry::where(fn ($q) => $q->where('user_id', $client->id)
                ->when($email !== '', fn ($q) => $q->orWhere(fn ($q) => $exact($q, 'client_email', $email)))),
            'Website inquiries' => $email === '' ? Inquiry::whereRaw('1 = 0') : $exact(Inquiry::query(), 'email', $email),
            'CRM leads' => CrmLead::where(fn ($q) => $q->when($email !== '', fn ($q) => $exact($q, 'client_email', $email))
                ->when($phone !== '', fn ($q) => $q->orWhere('client_phone', $phone))
                ->when($email === '' && $phone === '', fn ($q) => $q->whereRaw('1 = 0'))),
        ];
    }

    /**
     * How many of each, leaving out what there is none of.
     *
     * @return array<string, int>
     */
    public static function summary(User $client): array
    {
        return array_filter(array_map(fn (Builder $query) => (clone $query)->count(), self::for($client)));
    }

    /**
     * Delete every record and its uploaded files. Rows go in one transaction (child rows such as
     * passengers, payments and documents go with their parent); the files are removed once it
     * has committed, so a failure leaves nothing half-deleted.
     *
     * @return array<string, int> what was deleted, label => count
     */
    public static function deleteAll(User $client, callable $alsoDelete): array
    {
        $files = [];
        $deleted = [];

        DB::transaction(function () use ($client, $alsoDelete, &$files, &$deleted) {
            foreach (self::for($client) as $label => $query) {
                $records = $query->get();

                foreach ($records as $record) {
                    array_push($files, ...self::filesOf($record));
                    // Flight changes have no foreign key to cascade from, so they go by hand.
                    if ($record instanceof TicketBooking) {
                        $record->flightChanges()->delete();
                    }
                    $record->delete();
                }

                if ($records->isNotEmpty()) {
                    $deleted[$label] = $records->count();
                }
            }

            $alsoDelete();
        });

        foreach (array_unique(array_filter($files)) as $path) {
            if (DocumentStorage::disk()->exists($path)) {
                DocumentStorage::disk()->delete($path);
            }
        }

        return $deleted;
    }

    /**
     * Uploaded files a record keeps on the private disk.
     *
     * @return list<string>
     */
    private static function filesOf(Model $record): array
    {
        return match (true) {
            $record instanceof TicketBooking => $record->documents()->pluck('file_path')->all(),
            $record instanceof VisaApplication, $record instanceof SrrvApplication, $record instanceof ImmigrationClient => $record->documents()->pluck('file_path')->all(),
            $record instanceof TouristVisaApplication => [(string) $record->photo_path],
            default => [],
        };
    }
}
