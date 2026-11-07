<?php

namespace App\Support;

use App\Models\Scopes\OwnFilesScope;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\VisaApplication;
use App\Services\ClientNotifier;
use Illuminate\Support\Collection;

/**
 * The cashier's account lookup: type a name, email, phone, reference or passport and get the client's
 * whole payment history across the desks the cashier takes money for (ticketing, visa assistance and
 * PRA-SRRV). A client is one account: their email (or phone, or name) ties their files together, so
 * finding one file pulls up all of them.
 */
class CashierAccounts
{
    /** The most accounts one search shows. */
    public const MAX_ACCOUNTS = 12;

    /** The most files one search starts from. */
    private const SEEDS = 60;

    /**
     * @return Collection<int, array<string, mixed>> One entry per account: name, email, phone,
     *                                               tickets, visa, srrv (the files) and totals per currency.
     */
    public static function search(string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $tickets = TicketBooking::withoutGlobalScope(OwnFilesScope::class)
            ->where(fn ($q) => $q->where('booking_reference', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('contact_email', 'like', $like)
                ->orWhere('contact_phone', 'like', $like)
                ->orWhereHas('passengers', fn ($p) => $p->where('passport_number', 'like', $like)))
            ->latest('id')->limit(self::SEEDS)->get();

        $visa = VisaApplication::withoutGlobalScope(OwnFilesScope::class)
            ->where(fn ($q) => $q->where('reference', 'like', $like)
                ->orWhere('client_name', 'like', $like)
                ->orWhere('client_email', 'like', $like)
                ->orWhere('client_phone', 'like', $like))
            ->latest('id')->limit(self::SEEDS)->get();

        $srrv = SrrvApplication::withoutGlobalScope(OwnFilesScope::class)
            ->where(fn ($q) => $q->where('reference', 'like', $like)
                ->orWhere('retiree_name', 'like', $like)
                ->orWhere('retiree_email', 'like', $like)
                ->orWhere('retiree_phone', 'like', $like))
            ->latest('id')->limit(self::SEEDS)->get();

        // Finding one file pulls up the client's other files: the same email or phone.
        $emails = $tickets->pluck('contact_email')->merge($visa->pluck('client_email'))->merge($srrv->pluck('retiree_email'))
            ->map(fn ($e) => self::email($e))->filter()->unique()->values()->all();
        $rawPhones = $tickets->pluck('contact_phone')->merge($visa->pluck('client_phone'))->merge($srrv->pluck('retiree_phone'))
            ->map(fn ($p) => trim((string) $p))->filter()->unique()->values()->all();
        $phones = collect($rawPhones)->map(fn ($p) => self::phone($p))->filter()->unique()->values()->all();

        $tickets = self::everything(TicketBooking::class, 'contact_email', 'contact_phone', $tickets, $emails, $rawPhones, $phones, ['payments.receivedBy', 'createdBy:id,name']);
        $visa = self::everything(VisaApplication::class, 'client_email', 'client_phone', $visa, $emails, $rawPhones, $phones, ['payments.receivedBy']);
        $srrv = self::everything(SrrvApplication::class, 'retiree_email', 'retiree_phone', $srrv, $emails, $rawPhones, $phones, ['payments.receivedBy']);

        $accounts = [];

        foreach ($tickets as $file) {
            $key = self::key($file->contact_email, $file->contact_phone, $file->contact_name);
            self::touch($accounts, $key, $file->contact_name, $file->contact_email, $file->contact_phone, $file->id);
            $accounts[$key]['tickets'][] = $file;
        }
        foreach ($visa as $file) {
            $key = self::key($file->client_email, $file->client_phone, $file->client_name);
            self::touch($accounts, $key, $file->client_name, $file->client_email, $file->client_phone, $file->id);
            $accounts[$key]['visa'][] = $file;
        }
        foreach ($srrv as $file) {
            $key = self::key($file->retiree_email, $file->retiree_phone, $file->retiree_name);
            self::touch($accounts, $key, $file->retiree_name, $file->retiree_email, $file->retiree_phone, $file->id);
            $accounts[$key]['srrv'][] = $file;
        }

        return collect($accounts)
            ->map(fn (array $account): array => $account + ['totals' => self::totals($account)])
            ->sortByDesc(fn (array $account): int => $account['latest'])
            ->take(self::MAX_ACCOUNTS)
            ->values();
    }

    /**
     * What counts toward an account's money: a quotation is only a price, a cancelled booking owes nothing.
     */
    public static function ticketCounts(TicketBooking $ticket): bool
    {
        return ! $ticket->isQuotation() && ! $ticket->isCancelled();
    }

    /** The total, paid and balance per currency, over the files that count. */
    private static function totals(array $account): array
    {
        $totals = [];
        $add = function (string $currency, float $total, float $paid) use (&$totals): void {
            $row = $totals[$currency] ?? ['total' => 0.0, 'paid' => 0.0];
            $totals[$currency] = ['total' => $row['total'] + $total, 'paid' => $row['paid'] + $paid];
        };

        foreach ($account['tickets'] as $ticket) {
            if (self::ticketCounts($ticket)) {
                $add('PHP', (float) $ticket->total_amount, (float) $ticket->amount_paid);
            }
        }
        foreach ($account['visa'] as $file) {
            $add($file->currency ?: 'PHP', (float) $file->total_amount, (float) $file->amount_paid);
        }
        foreach ($account['srrv'] as $file) {
            $add($file->currency ?: 'PHP', (float) $file->service_fee, (float) $file->amount_paid);
        }

        return collect($totals)->map(fn (array $row): array => $row + ['balance' => max(0, round($row['total'] - $row['paid'], 2))])->all();
    }

    /** The files that matched, plus every other file of the same client (the same email or phone). */
    private static function everything(string $model, string $emailColumn, string $phoneColumn, Collection $seeds, array $emails, array $rawPhones, array $phones, array $with): Collection
    {
        $others = $model::withoutGlobalScope(OwnFilesScope::class)
            ->where(function ($q) use ($emailColumn, $phoneColumn, $emails, $rawPhones, $seeds): void {
                $q->whereIn('id', $seeds->pluck('id'));
                if ($emails !== []) {
                    $q->orWhereIn($emailColumn, $emails);
                }
                if ($rawPhones !== []) {
                    $q->orWhereIn($phoneColumn, $rawPhones);
                }
            })
            ->with($with)->latest('id')->limit(self::SEEDS * 2)->get();

        // Keep only what truly belongs to the account: the same email, or the same phone number.
        return $others->filter(function ($file) use ($seeds, $emailColumn, $phoneColumn, $emails, $phones): bool {
            return $seeds->contains('id', $file->id)
                || in_array(self::email($file->{$emailColumn}), $emails, true)
                || in_array(self::phone($file->{$phoneColumn}), $phones, true);
        })->values();
    }

    /** Start the account on first sight, and fill in any detail it was missing. */
    private static function touch(array &$accounts, string $key, ?string $name, ?string $email, ?string $phone, int $fileId): void
    {
        $accounts[$key] ??= [
            'key' => $key, 'name' => $name, 'email' => self::email($email), 'phone' => $phone,
            'tickets' => [], 'visa' => [], 'srrv' => [], 'latest' => 0,
        ];

        $accounts[$key]['name'] = $accounts[$key]['name'] ?: $name;
        $accounts[$key]['email'] = $accounts[$key]['email'] ?: self::email($email);
        $accounts[$key]['phone'] = $accounts[$key]['phone'] ?: $phone;
        $accounts[$key]['latest'] = max($accounts[$key]['latest'], $fileId);
    }

    /** One account per client: their email, else their phone number, else their name. */
    private static function key(?string $email, ?string $phone, ?string $name): string
    {
        return self::email($email) ?: (self::phone($phone) ?: 'name:'.mb_strtolower(trim((string) $name)));
    }

    /** The email in lower case, or null when it is blank or one of the system's placeholder addresses. */
    private static function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return ClientNotifier::canReceive($email) ? $email : null;
    }

    /** The phone's last ten digits, so 0917 123 4567, +63 917 123 4567 and 9171234567 are the same number. */
    private static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) >= 7 ? substr($digits, -10) : null;
    }
}
