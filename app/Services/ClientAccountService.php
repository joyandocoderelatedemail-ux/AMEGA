<?php

namespace App\Services;

use App\Models\ImmigrationClient;
use App\Models\Scopes\OwnFilesScope;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\TicketPassenger;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientAccountService
{
    /**
     * Find an existing client account or create a new one from any desk interaction.
     */
    public static function findOrCreateClient(array $data): User
    {
        $email = ! empty($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $passport = ! empty($data['passport_number']) ? trim((string) $data['passport_number']) : null;
        $phone = ! empty($data['phone']) ? trim((string) $data['phone']) : null;

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' && (! empty($data['first_name']) || ! empty($data['last_name']))) {
            $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
        }
        if ($name === '') {
            $name = 'Client Traveler';
        }

        $user = null;

        // 1. Try finding by email. Only client accounts are matched: a desk
        //    record must never attach to a staff account, or the client would
        //    be missing from the client list.
        if ($email) {
            $user = User::where('email', $email)->where('role', 'client')->first();
        }

        // 2. Try finding by passport number if not found
        if (! $user && $passport) {
            $user = User::where('passport_number', $passport)->where('role', 'client')->first();
        }

        // 3. Try finding by phone number among clients
        if (! $user && $phone) {
            $user = User::where('phone', $phone)->where('role', 'client')->first();
        }

        // 4. Try finding by exact name match among clients
        if (! $user && $name && $name !== 'Client Traveler') {
            $user = User::where('name', $name)->where('role', 'client')->first();
        }

        // If user already exists, update any missing profile info from this new desk entry
        if ($user) {
            return self::fillMissingDetails($user, $data);
        }

        // Otherwise, split names for structured record
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;
        if (! $firstName && ! $lastName) {
            $parts = explode(' ', $name);
            if (count($parts) > 1) {
                $lastName = array_pop($parts);
                $firstName = implode(' ', $parts);
            } else {
                $firstName = $name;
                $lastName = '';
            }
        }

        // If no email was supplied, or it belongs to a staff account, generate
        // an amega client account email
        if (! $email || User::where('email', $email)->exists()) {
            $slug = Str::slug($name);
            $uniqueSuffix = Str::lower(Str::random(6));
            $email = "client.{$slug}.{$uniqueSuffix}@clients.amegatravel.local";
        }

        // Create the new client user account
        $user = User::create([
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'role' => 'client',
            'account_category' => $data['account_category'] ?? 'Individual',
            'passport_number' => $passport,
            'nationality' => $data['nationality'] ?? 'Filipino',
            'address' => $data['address'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => self::knownGender($data['gender'] ?? null),
            'password' => Hash::make(Str::random(32)),
        ]);

        ActivityLogger::log('Users', 'AUTO_ENROLL', "Auto-enrolled client profile for '{$user->name}' ({$user->email}) from desk transaction");

        return $user;
    }

    /**
     * The client account behind an immigration client sheet, created when the
     * client is new, so every sheet's client is in the admin client list.
     */
    public static function clientForSheet(ImmigrationClient $sheet): User
    {
        $details = [
            'name' => $sheet->full_name,
            'first_name' => $sheet->given_name,
            'last_name' => $sheet->last_name,
            'email' => $sheet->email,
            'phone' => $sheet->mobile_number,
            'passport_number' => $sheet->passport_number,
            'address' => $sheet->address,
            'nationality' => $sheet->nationality,
            'date_of_birth' => $sheet->date_of_birth?->toDateString(),
        ];

        // Keep an existing link, so editing the sheet's email or name later
        // cannot split the client into a second account.
        $linked = $sheet->user_id ? User::where('role', 'client')->find($sheet->user_id) : null;

        return $linked
            ? self::fillMissingDetails($linked, $details)
            : self::findOrCreateClient($details);
    }

    /**
     * Fill a client's missing gender and birth date from a ticket passenger
     * who is that client, so the admin client record is populated from the
     * booking instead of re-typed.
     */
    public static function fillFromPassenger(User $client, TicketPassenger $passenger): User
    {
        return self::fillMissingDetails($client, [
            'gender' => $passenger->gender,
            'date_of_birth' => $passenger->date_of_birth?->toDateString(),
        ]);
    }

    /**
     * Whether a traveller's first and last name are both in the account name,
     * so details are only copied onto the client's record when the traveller
     * is the client and not a companion on the same booking.
     */
    public static function isSamePerson(?string $firstName, ?string $lastName, ?string $accountName): bool
    {
        $words = fn (?string $value): array => preg_split('/\s+/', Str::lower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY);

        $traveller = [...$words($firstName), ...$words($lastName)];
        $account = $words($accountName);

        return $words($firstName) !== []
            && $words($lastName) !== []
            && array_diff($traveller, $account) === [];
    }

    /**
     * A gender the client profile accepts, or null for anything else.
     */
    private static function knownGender(?string $gender): ?string
    {
        $gender = Str::lower(trim((string) $gender));

        return array_key_exists($gender, User::GENDERS) ? $gender : null;
    }

    /**
     * Copy desk details onto an existing account only where it has none, so a
     * desk entry never overwrites what the client or another desk recorded.
     *
     * @param  array<string, mixed>  $data
     */
    private static function fillMissingDetails(User $user, array $data): User
    {
        $fields = [
            'phone' => $data['phone'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'address' => $data['address'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => self::knownGender($data['gender'] ?? null),
        ];

        $updates = [];
        foreach ($fields as $field => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if (empty($user->{$field}) && ! empty($value)) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        return $user;
    }

    /**
     * Scan existing desk records (immigration, ticketing, visa, srrv) and ensure
     * client accounts exist and are linked.
     */
    public static function syncAllDeskClients(): int
    {
        $enrolledCount = 0;

        // 1. Sync Immigration Clients
        $immClients = ImmigrationClient::all();
        foreach ($immClients as $imm) {
            $user = self::clientForSheet($imm);

            if ($imm->user_id !== $user->id) {
                $imm->update(['user_id' => $user->id]);
                $enrolledCount++;
            }
        }

        // 2. Sync Ticket Bookings
        $ticketBookings = TicketBooking::withoutGlobalScope(OwnFilesScope::class)->get();
        foreach ($ticketBookings as $ticket) {
            $user = self::findOrCreateClient([
                'name' => $ticket->contact_name,
                'email' => $ticket->contact_email,
                'phone' => $ticket->contact_phone,
            ]);

            if ($ticket->user_id !== $user->id) {
                $ticket->update(['user_id' => $user->id]);
                $enrolledCount++;
            }

            // The lead passenger is usually the booker; when they are, their
            // gender and birth date fill any gaps on the client record.
            $leadPassenger = $ticket->passengers()->orderBy('passenger_number')->first();

            if ($leadPassenger && self::isSamePerson($leadPassenger->first_name, $leadPassenger->last_name, $user->full_name)) {
                self::fillFromPassenger($user, $leadPassenger);
            }
        }

        // 3. Sync Visa Applications
        $visaApplications = VisaApplication::withoutGlobalScope(OwnFilesScope::class)->get();
        foreach ($visaApplications as $visa) {
            self::findOrCreateClient([
                'name' => $visa->client_name,
                'email' => $visa->client_email,
                'phone' => $visa->client_phone,
            ]);
        }

        // 4. Sync SRRV Applications
        $srrvApplications = SrrvApplication::withoutGlobalScope(OwnFilesScope::class)->get();
        foreach ($srrvApplications as $srrv) {
            self::findOrCreateClient([
                'name' => $srrv->retiree_name,
                'email' => $srrv->retiree_email,
                'phone' => $srrv->retiree_phone,
                'nationality' => $srrv->nationality,
            ]);
        }

        return $enrolledCount;
    }
}
