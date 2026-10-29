<?php

namespace App\Services;

use App\Models\Scopes\OwnFilesScope;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
use App\Models\TouristVisaApplication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What the office knows about a client, kept on their client record so it is typed once: every desk
 * form that is saved writes what it learnt here, and every new form for the client is filled from it.
 * The newest form wins: a renewed passport, a new address or an SRRV number replaces the old value.
 * The client's name is never changed this way, and an email is taken only when no one else has it.
 */
class ClientMemory
{
    /** The facts kept on the client record, and the kind of each. */
    public const FACTS = [
        'phone' => 'text',
        'email' => 'email',
        'nationality' => 'text',
        'address' => 'text',
        'date_of_birth' => 'date',
        'place_of_birth' => 'text',
        'gender' => 'gender',
        'civil_status' => 'civil_status',
        'height' => 'text',
        'weight' => 'text',
        'passport_number' => 'text',
        'passport_expiry' => 'date',
        'passport_country' => 'text',
        'passport_place' => 'text',
        'srrv_number' => 'text',
        'srrv_visa_class' => 'visa_class',
        'srrv_issued_on' => 'date',
        'acr_number' => 'text',
        'acr_valid_until' => 'date',
        'ssrn' => 'text',
    ];

    /** Lengths the columns allow, so a long value never breaks a save. */
    private const MAX = ['height' => 50, 'weight' => 50];

    /**
     * Keep what a desk form just recorded about the client: every non-empty value replaces the stored one.
     *
     * @param  array<string, mixed>  $facts
     */
    public static function remember(?User $client, array $facts): ?User
    {
        if (! $client || ! $client->isClient()) {
            return $client;
        }

        $updates = [];
        foreach (self::FACTS as $field => $kind) {
            if (! array_key_exists($field, $facts)) {
                continue;
            }
            $value = self::clean($kind, $facts[$field]);
            if ($value === null) {
                continue;
            }
            if (isset(self::MAX[$field])) {
                $value = mb_substr($value, 0, self::MAX[$field]);
            }
            if ($field === 'email' && (str_ends_with($value, '@clients.amegatravel.local')
                || User::where('email', $value)->whereKeyNot($client->id)->exists())) {
                continue;
            }

            $current = $client->getAttribute($field);
            $current = $current instanceof Carbon ? $current->toDateString() : $current;
            if ((string) $current !== (string) $value) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $client->forceFill($updates)->save();
        }

        return $client;
    }

    /**
     * What a new form for this client starts with, under the keys above. Only what is known.
     *
     * @return array<string, string>
     */
    public static function recall(?User $client): array
    {
        if (! $client) {
            return [];
        }

        $facts = [];
        foreach (array_keys(self::FACTS) as $field) {
            $value = $field === 'email' ? $client->real_email : $client->getAttribute($field);
            $value = $value instanceof Carbon ? $value->toDateString() : $value;
            if (filled($value)) {
                $facts[$field] = (string) $value;
            }
        }

        return $facts;
    }

    /**
     * Fill the new columns for existing clients from their files: the latest SRRV number, class and issue
     * date, and the latest ACR, SSRN and place of birth from the tourist visa forms. Run once, on install.
     */
    public static function backfill(): void
    {
        foreach (User::where('role', 'client')->get() as $client) {
            $files = self::filesOf($client);
            $latest = fn (iterable $rows, string $column) => collect($rows)->sortByDesc('updated_at')->pluck($column)->filter()->first();

            self::remember($client, [
                'srrv_number' => $latest([...$files['srrv'], ...$files['renewals'], ...$files['requests']], 'srrv_card_number'),
                'srrv_visa_class' => $latest([...$files['srrv'], ...$files['renewals'], ...$files['requests']], 'visa_class'),
                'srrv_issued_on' => collect($files['requests'])->sortByDesc('updated_at')->map(fn ($r) => $r->details['srrv_issued_on'] ?? null)->filter()->first(),
                'acr_number' => $latest($files['cgaf'], 'acr_number'),
                'acr_valid_until' => optional(collect($files['cgaf'])->sortByDesc('updated_at')->pluck('acr_validity')->filter()->first())->toDateString(),
                'ssrn' => $latest($files['cgaf'], 'ssrn'),
                'place_of_birth' => $latest($files['cgaf'], 'birthplace'),
            ]);
        }
    }

    /**
     * The client's SRRV and tourist visa files, matched by their email, phone, passport or name.
     *
     * @return array{srrv: iterable, renewals: iterable, requests: iterable, cgaf: iterable}
     */
    private static function filesOf(User $client): array
    {
        $email = $client->real_email;
        $phone = $client->phone;
        $name = trim((string) $client->name);
        $passport = $client->passport_number;

        $match = function ($query, array $columns) use ($email, $phone, $name, $passport) {
            $query->where(function ($q) use ($columns, $email, $phone, $name, $passport) {
                $any = false;
                foreach (['email' => $email, 'phone' => $phone, 'name' => $name, 'passport' => $passport] as $key => $value) {
                    if (filled($value) && isset($columns[$key])) {
                        $q->orWhere($columns[$key], $value);
                        $any = true;
                    }
                }
                // Nothing to match on: match nothing, never everything.
                if (! $any) {
                    $q->whereRaw('1 = 0');
                }
            });

            return $query;
        };

        return [
            'srrv' => $match(SrrvApplication::withoutGlobalScope(OwnFilesScope::class), ['email' => 'retiree_email', 'phone' => 'retiree_phone', 'name' => 'retiree_name'])->get(),
            'renewals' => $match(SrrvRenewal::withoutGlobalScope(OwnFilesScope::class), ['email' => 'retiree_email', 'name' => 'retiree_name'])->get(),
            'requests' => $match(SrrvServiceRequest::withoutGlobalScope(OwnFilesScope::class), ['email' => 'retiree_email', 'phone' => 'retiree_phone', 'name' => 'retiree_name'])->get(),
            'cgaf' => $match(TouristVisaApplication::withoutGlobalScope(OwnFilesScope::class), ['email' => 'email', 'phone' => 'mobile_number', 'passport' => 'passport_number'])->get(),
        ];
    }

    private static function clean(string $kind, mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($kind) {
            'email' => filter_var(Str::lower($value), FILTER_VALIDATE_EMAIL) ?: null,
            'date' => self::date($value),
            'gender' => array_key_exists(Str::lower($value), User::GENDERS) ? Str::lower($value)
                : match (Str::upper($value)) {
                    'M' => 'male', 'F' => 'female', default => null
                },
            'civil_status' => array_key_exists(Str::lower($value), User::CIVIL_STATUSES) ? Str::lower($value) : null,
            'visa_class' => in_array(Str::lower($value), SrrvApplication::VISA_CLASSES, true) ? Str::lower($value) : null,
            default => $value,
        };
    }

    private static function date(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
