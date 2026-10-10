<?php

use App\Models\Airline;
use App\Models\User;
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
 * A cashier: records payment on the ticket bookings an admin approved.
 */
function cashier(): User
{
    return User::factory()->create(['role' => 'cashier']);
}
