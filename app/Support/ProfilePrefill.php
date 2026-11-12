<?php

namespace App\Support;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * What the client's profile already knows, filled into an Immigration folder's empty fields when the
 * folder is opened, so nothing on file is typed twice. Only empty fields are filled, and only on the
 * page: it is saved when the agent saves the folder. A field the folder already holds is never changed.
 */
class ProfilePrefill
{
    /** Folder field => client profile field. */
    private const FOLDER = [
        'email' => 'real_email',
        'mobile_number' => 'phone',
        'address' => 'address',
        'nationality' => 'nationality',
        'date_of_birth' => 'date_of_birth',
        'passport_number' => 'passport_number',
        'height' => 'height',
        'weight' => 'weight',
        'ssrn' => 'ssrn',
    ];

    /** 13(A), ACR renewal, R.A. 9225, ECC and downgrading case detail => client profile field. */
    private const CASE_DETAILS = [
        'middle_name' => 'middle_name',
        'name_suffix' => 'suffix',
        'place_of_birth' => 'place_of_birth',
        'birth_place' => 'place_of_birth',
        'passport_expires_on' => 'passport_expiry',
        'passport_place' => 'passport_place',
        'passport_country' => 'passport_country',
        'acr_number' => 'acr_number',
        'acr_expires_on' => 'acr_valid_until',
        'spouse_name' => 'spouse_name',
    ];

    /** Fill the folder's empty fields from the client's profile. */
    public static function folder(ImmigrationClient $folder, ?User $profile): void
    {
        if (! $profile) {
            return;
        }

        foreach (self::FOLDER as $field => $source) {
            $value = self::value($profile, $source);
            if (blank($folder->getAttribute($field)) && filled($value)) {
                $folder->setAttribute($field, $value);
            }
        }

        // The sheet keeps the BI's wording ("Married").
        if (blank($folder->civil_status) && filled($status = User::CIVIL_STATUSES[$profile->civil_status] ?? null)) {
            $folder->civil_status = $status;
        }
    }

    /**
     * Fill a case's empty details from the client's profile. Only a case still being worked on: a new
     * one, or one the admin returned. An approved or sent case is left as it was approved.
     */
    public static function petition(ImmigrationPetition $case, ?User $profile): void
    {
        if (! $profile || ($case->exists && ! $case->isReturned())) {
            return;
        }

        $details = $case->details ?? [];
        foreach (self::CASE_DETAILS as $key => $source) {
            $value = self::value($profile, $source);
            if (blank($details[$key] ?? null) && filled($value)) {
                $details[$key] = $value;
            }
        }

        $sex = ['male' => 'M', 'female' => 'F'][strtolower((string) $profile->gender)] ?? null;
        if (blank($details['sex'] ?? null) && $sex) {
            $details['sex'] = $sex;
        }

        $case->details = $details;
    }

    private static function value(User $profile, string $field): ?string
    {
        $value = $profile->getAttribute($field);

        return $value instanceof Carbon ? $value->toDateString() : (filled($value) ? (string) $value : null);
    }
}
