<?php

namespace App\Services;

use App\Models\User;
use App\Rules\DigitsOnly;
use App\Rules\NotAlreadyRegistered;
use App\Support\DocumentStorage;
use App\Support\PhilippineLocations;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Registering a client and keeping the travel profile the ticket form draws on.
 *
 * Shared by the admin client directory and the ticketing desk, so a client
 * registered at either counter carries the same details into a booking.
 */
class ClientProfileService
{
    /**
     * Upload field => folder on the private document disk.
     *
     * @var array<string, string>
     */
    public const UPLOAD_FOLDERS = [
        'passport_photo' => 'passports',
        'government_id_photo' => 'ids',
        'stamps_photo' => 'stamps',
        'arrival_stamp_photo' => 'stamps',
        'profile_photo' => 'profiles',
    ];

    /** What a passport or ID scan must be, wherever it is uploaded. */
    public const SCAN_RULE = 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120';

    /** A client's profile picture: an image, never a PDF. */
    public const PHOTO_RULE = 'image|mimes:jpg,jpeg,png,webp|max:5120';

    /** Most passport scans one client keeps. */
    public const MAX_PASSPORT_SCANS = 10;

    /** Most dependents kept on a married client's record. */
    public const MAX_DEPENDENTS = 15;

    /** Phone fields kept as digits only on the admin client form. */
    public const NUMBER_FIELDS = ['phone', 'emergency_contact_phone'];

    /**
     * Rules for registering a new client.
     *
     * A client whose email, contact number or name is already on file is
     * turned away, so the same person is never registered twice.
     *
     * @return array<string, mixed>
     */
    public static function registrationRules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => ['required', 'string', 'max:255', new NotAlreadyRegistered(NotAlreadyRegistered::NAME)],
            'suffix' => 'nullable|string|max:20',
            // "No email" / "No phone" ticks stand in for a value the client does not have.
            'no_email' => 'nullable|boolean',
            'no_phone' => 'nullable|boolean',
            'email' => ['required_unless:no_email,1', 'nullable', 'email', 'max:255', new NotAlreadyRegistered(NotAlreadyRegistered::EMAIL)],
            'phone' => ['required_unless:no_phone,1', 'nullable', 'string', 'max:255', new NotAlreadyRegistered(NotAlreadyRegistered::PHONE)],
            'address' => 'required|string|max:500',
            ...PhilippineLocations::rules(required: true),
            'nationality' => 'required|string|max:255',
            'account_category' => ['required', Rule::in(User::ACCOUNT_CATEGORIES)],
            // Sets Adult, Child or Infant when the client is picked for a ticket.
            'date_of_birth' => 'required|date|before:today',
            // A new client's gender goes on every ticket and BI form; older records may lack it.
            'gender' => ['required', Rule::in(array_keys(User::GENDERS))],
        ] + self::travelProfileRules();
    }

    /**
     * The passenger details the ticket form needs, kept on the client's profile
     * so a booking can be filled from it instead of re-typed.
     *
     * @return array<string, string>
     */
    public static function travelProfileRules(): array
    {
        return [
            'gender' => 'nullable|in:'.implode(',', array_keys(User::GENDERS)),
            'civil_status' => 'nullable|in:'.implode(',', array_keys(User::CIVIL_STATUSES)),
            // Asked only of a married client (see normalizeFamily).
            'spouse_name' => 'nullable|string|max:255',
            'dependents' => 'nullable|array|max:'.self::MAX_DEPENDENTS,
            'dependents.*.name' => 'nullable|string|max:255',
            'dependents.*.date_of_birth' => 'nullable|date|before:tomorrow',
            'height' => 'nullable|string|max:50',
            'weight' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
            'passport_number' => 'nullable|string|max:50',
            'passport_expiry' => 'nullable|date',
            'passport_country' => 'nullable|string|max:100',
            'profile_photo' => 'nullable|'.self::PHOTO_RULE,
            'passport_photo' => 'nullable|'.self::SCAN_RULE,
            // The client form takes several passport scans; the ones ticked to remove are named by their place in the list.
            'passport_photos' => 'nullable|array|max:'.self::MAX_PASSPORT_SCANS,
            'passport_photos.*' => self::SCAN_RULE,
            'remove_passport_scans' => 'nullable|array',
            'remove_passport_scans.*' => 'integer|min:0',
            'government_id_type' => 'nullable|string|max:100',
            'government_id_number' => 'nullable|string|max:100',
            'government_id_remarks' => 'nullable|string|max:1000',
            'government_id_photo' => 'nullable|'.self::SCAN_RULE,
            'frequent_flyer_membership' => 'nullable|string|max:255',
            'stamps_photo' => 'nullable|'.self::SCAN_RULE,
            'arrival_stamp_photo' => 'nullable|'.self::SCAN_RULE,
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_email' => 'nullable|email|max:255',
        ];
    }

    /**
     * Phone numbers on the admin client form are numbers only. Anything pasted with spaces,
     * dashes, brackets, dots or a "+" is cleaned to its digits before it is checked.
     */
    public static function normalizeNumbers(Request $request): void
    {
        foreach (self::NUMBER_FIELDS as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => preg_replace('/[\s().+\-]/', '', $request->input($field))]);
            }
        }
    }

    /**
     * The rules with the numbers-only check added to the phone fields.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function withNumbersOnly(array $rules): array
    {
        foreach (self::NUMBER_FIELDS as $field) {
            if (isset($rules[$field])) {
                $existing = is_string($rules[$field]) ? explode('|', $rules[$field]) : $rules[$field];
                $rules[$field] = [...$existing, new DigitsOnly];
            }
        }

        return $rules;
    }

    /**
     * Create a client account from validated registration data.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function register(array $validated, Request $request, string $role = 'client'): User
    {
        $attributes = PhilippineLocations::composeAddress(self::withoutUploads(self::normalizeFamily(self::applyContactChoices($validated))));
        $attributes['role'] = $role;
        $attributes['name'] = self::fullName($attributes);
        $attributes['password'] = bcrypt(Str::random(16));

        $user = User::create($attributes);

        self::storeUploads($request, $user);

        return $user;
    }

    /**
     * Turn the "no email" / "no phone" ticks into what is stored: a made-up
     * address on the placeholder domain (the existing one is kept when the
     * client already has it) and an empty phone number.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function applyContactChoices(array $validated, ?User $existing = null): array
    {
        $noEmail = ! empty($validated['no_email']);
        $noPhone = ! empty($validated['no_phone']);

        unset($validated['no_email'], $validated['no_phone']);

        if ($noEmail) {
            $validated['email'] = $existing?->hasPlaceholderEmail() ? $existing->email : self::placeholderEmail($validated);
        }

        if ($noPhone) {
            $validated['phone'] = null;
        }

        return $validated;
    }

    /**
     * A unique address for a client with no email, named after them so staff can
     * tell the records apart.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function placeholderEmail(array $attributes): string
    {
        $name = Str::slug(trim(($attributes['first_name'] ?? '').' '.($attributes['last_name'] ?? '')), '.') ?: 'walk-in';

        do {
            $email = 'client.'.$name.'.'.Str::lower(Str::random(6)).User::PLACEHOLDER_EMAIL_DOMAIN;
        } while (User::where('email', $email)->exists());

        return $email;
    }

    /**
     * Drop the upload fields, which are stored as files rather than columns.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function withoutUploads(array $validated): array
    {
        return Arr::except($validated, [...array_keys(self::UPLOAD_FOLDERS), 'passport_photos', 'remove_passport_scans']);
    }

    /**
     * A spouse and dependents belong to a married client: for anyone else they are cleared, and a married
     * client's blank dependent rows are dropped. Left alone when the form did not send a civil status.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function normalizeFamily(array $validated): array
    {
        if (! array_key_exists('civil_status', $validated)) {
            return $validated;
        }

        if ($validated['civil_status'] !== 'married') {
            $validated['spouse_name'] = null;
            $validated['dependents'] = null;

            return $validated;
        }

        $dependents = collect($validated['dependents'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->map(fn (array $row) => ['name' => trim((string) $row['name']), 'date_of_birth' => $row['date_of_birth'] ?? null])
            ->values()
            ->all();

        $validated['spouse_name'] = filled($validated['spouse_name'] ?? null) ? trim((string) $validated['spouse_name']) : null;
        $validated['dependents'] = $dependents === [] ? null : $dependents;

        return $validated;
    }

    /**
     * The display name built from the name parts.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fullName(array $attributes): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [
            $attributes['first_name'] ?? '',
            $attributes['middle_name'] ?? '',
            $attributes['last_name'] ?? '',
            $attributes['suffix'] ?? '',
        ])));
    }

    /**
     * Save any newly uploaded passport or government ID scan to the private
     * document disk, replacing (and deleting) the file it supersedes.
     *
     * The new file is stored and the reference saved first; the old file goes
     * only once that has succeeded, so a failure never leaves the client with
     * no scan. If the save fails, the new file is removed and the old stays.
     *
     * @param  list<string>|null  $only  Upload fields to handle; all when null.
     */
    public static function storeUploads(Request $request, User $user, ?array $only = null): void
    {
        // The client form's passport scans: the ones removed, then the ones added (the rest of the uploads follow).
        if ($only === null) {
            self::updatePassportScans($request, $user);
        }

        $folders = $only === null ? self::UPLOAD_FOLDERS : Arr::only(self::UPLOAD_FOLDERS, $only);
        $superseded = [];
        $stored = [];

        foreach ($folders as $field => $folder) {
            if ($request->hasFile($field)) {
                $superseded[] = $user->{$field};
                $user->{$field} = $stored[] = $request->file($field)->store($folder, DocumentStorage::diskName());
            }
        }

        if ($superseded === []) {
            return;
        }

        $disk = DocumentStorage::disk();

        try {
            $user->save();
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $disk->delete($path);
            }

            throw $e;
        }

        foreach (array_filter($superseded) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Remove the passport scans ticked on the form, then add the newly uploaded ones after the rest. The first
     * scan stays in passport_photo (so the ticket desk and the profile page read it as before) and the others
     * in passport_scans. Files are stored before the save and the removed ones deleted after it, so a failed
     * save never costs the client a scan.
     */
    private static function updatePassportScans(Request $request, User $user): void
    {
        $uploads = array_values(array_filter((array) $request->file('passport_photos', []), fn ($file) => $file !== null && $file->isValid()));
        $remove = array_map('intval', (array) $request->input('remove_passport_scans', []));

        if ($uploads === [] && $remove === []) {
            return;
        }

        $current = $user->passportScanPaths();
        $removed = array_values(array_intersect_key($current, array_flip($remove)));
        $kept = array_values(array_diff_key($current, array_flip($remove)));
        $room = max(0, self::MAX_PASSPORT_SCANS - count($kept));
        $disk = DocumentStorage::disk();
        $stored = [];

        foreach (array_slice($uploads, 0, $room) as $file) {
            $stored[] = $file->store(self::UPLOAD_FOLDERS['passport_photo'], DocumentStorage::diskName());
        }

        $all = [...$kept, ...$stored];
        $user->passport_photo = $all[0] ?? null;
        $user->passport_scans = count($all) > 1 ? array_slice($all, 1) : null;

        try {
            $user->save();
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $disk->delete($path);
            }

            throw $e;
        }

        foreach ($removed as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * What the ticket form needs to fill the booker and the first passenger.
     *
     * @return array<string, mixed>
     */
    public static function ticketProfile(User $user): array
    {
        $disk = DocumentStorage::disk();

        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
            'email' => $user->real_email,
            'phone' => $user->phone,
            'nationality' => $user->nationality,
            'is_filipino' => self::isFilipino($user->nationality),
            'gender' => $user->gender,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'passport_number' => $user->passport_number,
            'passport_expiry' => $user->passport_expiry?->format('Y-m-d'),
            'passport_country' => $user->passport_country,
            'government_id_type' => $user->government_id_type,
            'government_id_number' => $user->government_id_number,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_relationship' => $user->emergency_contact_relationship,
            'emergency_contact_phone' => $user->emergency_contact_phone,
            'emergency_contact_email' => $user->emergency_contact_email,
            'has_passport_scan' => filled($user->passport_photo) && $disk->exists($user->passport_photo),
            'has_government_id_scan' => filled($user->government_id_photo) && $disk->exists($user->government_id_photo),
        ];
    }

    /**
     * Treat a blank nationality as Filipino, the desk's default traveller.
     */
    public static function isFilipino(?string $nationality): bool
    {
        return blank($nationality) || (bool) preg_match('/filipin|philippin|pinoy/i', $nationality);
    }
}
