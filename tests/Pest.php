<?php

use App\Models\Airline;
use App\Models\Concerns\NeedsAdminApproval;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Desk files (SRRV, visa counter) go to an admin when staff save them. Tests of what happens after
 * approval (stages, forms, payments) call this so the files they open are already approved; the
 * approval tests themselves do not.
 */
function autoApproveDeskFiles(): void
{
    foreach ([SrrvApplication::class, SrrvRenewal::class, VisaApplication::class] as $model) {
        if (! class_exists($model) || ! in_array(NeedsAdminApproval::class, class_uses_recursive($model), true)) {
            continue;
        }

        $model::saved(function ($file) {
            if ($file->approval_status === SrrvApplication::APPROVAL_PENDING) {
                $file->forceFill(['approval_status' => SrrvApplication::APPROVAL_APPROVED])->saveQuietly();
            }
        });
    }
}

/**
 * What every completed ticket booking needs (a quotation does not): the flight
 * booked on the airline's site, and the agent's acknowledgement that the
 * information is correct. Spread it first so a test's own values win.
 *
 * @return array<string, mixed>
 */
function bookingFlight(): array
{
    return [
        'airline_id' => Airline::factory()->create()->id,
        'flight_number' => '5J 5054',
        'departure_time' => '08:00',
        'arrival_time' => '09:30',
        'return_flight_number' => '5J 5055',
        'return_departure_time' => '15:00',
        'return_arrival_time' => '16:30',
        'agent_acknowledged' => 1,
    ];
}

/**
 * What the agent posts to issue a ticket: the data privacy consent and the actual flight the
 * airline ticketed. Spread a test's own values after it to change them.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function issueFlight(array $overrides = []): array
{
    return array_merge([
        'data_privacy_consent' => '1',
        'airline_id' => Airline::factory()->create()->id,
        'airline_pnr' => 'QWE4RT',
        'flight_number' => '5J 5054',
        'departure_time' => '08:00',
        'arrival_time' => '09:30',
        'return_flight_number' => '5J 5055',
        'return_departure_time' => '15:00',
        'return_arrival_time' => '16:30',
    ], $overrides);
}

/**
 * A cashier: records payment on the ticket bookings an admin approved.
 */
function cashier(): User
{
    return User::factory()->create(['role' => 'cashier']);
}

/**
 * A complete client registration, as the admin client form sends it. Shared by the client form tests
 * (here rather than in one of them, so each file also runs on its own and in parallel).
 */
function clientRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'role' => 'client',
        'account_category' => 'Individual',
        'first_name' => 'Juan',
        'middle_name' => 'Reyes',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.delacruz@example.com',
        'phone' => '+63 912 345 6789',
        'nationality' => 'Filipino',
        'address' => '12 Rizal St',
        'province' => 'Metro Manila',
        'city' => 'Makati City',
        'gender' => 'male',
        'date_of_birth' => '1990-05-14',
        'passport_number' => 'P1234567A',
        'passport_expiry' => '2031-01-31',
        'passport_country' => 'Philippines',
        'government_id_type' => 'UMID',
        'government_id_number' => '0028-1234567-8',
        'emergency_contact_name' => 'Maria Dela Cruz',
        'emergency_contact_relationship' => 'Spouse',
        'emergency_contact_phone' => '+63 917 123 4567',
        'emergency_contact_email' => 'maria@example.com',
    ], $overrides);
}
