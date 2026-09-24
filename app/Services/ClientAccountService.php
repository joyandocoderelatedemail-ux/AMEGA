<?php

namespace App\Services;

use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
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

        // 1. Try finding by email
        if ($email) {
            $user = User::where('email', $email)->first();
        }

        // 2. Try finding by passport number if not found
        if (! $user && $passport) {
            $user = User::where('passport_number', $passport)->first();
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
            $updates = [];

            if (empty($user->phone) && $phone) {
                $updates['phone'] = $phone;
            }
            if (empty($user->passport_number) && $passport) {
                $updates['passport_number'] = $passport;
            }
            if (empty($user->nationality) && ! empty($data['nationality'])) {
                $updates['nationality'] = $data['nationality'];
            }
            if (empty($user->address) && ! empty($data['address'])) {
                $updates['address'] = $data['address'];
            }

            if (! empty($updates)) {
                $user->update($updates);
            }

            return $user;
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

        // If no email was supplied, generate an amega client account email
        if (! $email) {
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
            'password' => Hash::make(Str::random(32)),
        ]);

        ActivityLogger::log('Users', 'AUTO_ENROLL', "Auto-enrolled client profile for '{$user->name}' ({$user->email}) from desk transaction");

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
            $user = self::findOrCreateClient([
                'name' => $imm->full_name,
                'first_name' => $imm->given_name,
                'last_name' => $imm->last_name,
                'email' => $imm->email,
                'phone' => $imm->mobile_number,
                'passport_number' => $imm->passport_number,
                'address' => $imm->address,
                'nationality' => $imm->nationality,
            ]);

            if ($imm->user_id !== $user->id) {
                $imm->update(['user_id' => $user->id]);
                $enrolledCount++;
            }
        }

        // 2. Sync Ticket Bookings
        $ticketBookings = TicketBooking::all();
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
        }

        // 3. Sync Visa Applications
        $visaApplications = VisaApplication::all();
        foreach ($visaApplications as $visa) {
            self::findOrCreateClient([
                'name' => $visa->client_name,
                'email' => $visa->client_email,
                'phone' => $visa->client_phone,
            ]);
        }

        // 4. Sync SRRV Applications
        $srrvApplications = SrrvApplication::all();
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
