<?php

namespace App\Services;

use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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
    ];

    /**
     * Rules for registering a new client.
     *
     * @return array<string, string>
     */
    public static function registrationRules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'nationality' => 'required|string|max:255',
            'account_category' => 'required|string|max:255',
            // Sets Adult, Child or Infant when the client is picked for a ticket.
            'date_of_birth' => 'required|date|before:today',
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
            'date_of_birth' => 'nullable|date|before:today',
            'passport_number' => 'nullable|string|max:50',
            'passport_expiry' => 'nullable|date',
            'passport_country' => 'nullable|string|max:100',
            'passport_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'government_id_type' => 'nullable|string|max:100',
            'government_id_number' => 'nullable|string|max:100',
            'government_id_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_email' => 'nullable|email|max:255',
        ];
    }

    /**
     * Create a client account from validated registration data.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function register(array $validated, Request $request, string $role = 'client'): User
    {
        $attributes = self::withoutUploads($validated);
        $attributes['role'] = $role;
        $attributes['name'] = self::fullName($attributes);
        $attributes['password'] = bcrypt(Str::random(16));

        $user = User::create($attributes);

        self::storeUploads($request, $user);

        return $user;
    }

    /**
     * Drop the upload fields, which are stored as files rather than columns.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function withoutUploads(array $validated): array
    {
        return Arr::except($validated, array_keys(self::UPLOAD_FOLDERS));
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
     */
    public static function storeUploads(Request $request, User $user): void
    {
        $superseded = [];

        foreach (self::UPLOAD_FOLDERS as $field => $folder) {
            if ($request->hasFile($field)) {
                $superseded[] = $user->{$field};
                $user->{$field} = $request->file($field)->store($folder, DocumentStorage::diskName());
            }
        }

        if ($superseded === []) {
            return;
        }

        $user->save();

        $disk = DocumentStorage::disk();

        foreach (array_filter($superseded) as $path) {
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
            'email' => $user->email,
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
