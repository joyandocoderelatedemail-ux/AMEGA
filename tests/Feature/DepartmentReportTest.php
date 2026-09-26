<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function reportTicket(User $owner, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(30),
        'total_passengers' => 1,
        'contact_name' => 'Test Contact',
        'contact_email' => 'contact@example.com',
        'contact_phone' => '+63 900 000 0000',
        'total_amount' => 5000,
        'status' => 'pending',
        'created_by' => $owner->id,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function reportVisa(User $owner, array $overrides = []): VisaApplication
{
    return VisaApplication::create(array_merge([
        'reference' => 'VISA-'.fake()->unique()->numerify('######'),
        'client_name' => 'Ana Santos',
        'service_type' => 'visit_visa',
        'processing_speed' => 'regular',
        'status' => 'pending',
        'total_amount' => 8000,
        'amount_paid' => 0,
        'currency' => 'PHP',
        'created_by' => $owner->id,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function reportRetiree(User $owner, array $overrides = []): SrrvApplication
{
    return SrrvApplication::create(array_merge([
        'reference' => 'SRRV-'.fake()->unique()->numerify('######'),
        'retiree_name' => 'Ramon Dela Cruz',
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'status' => 'pending',
        'service_fee' => 1000,
        'amount_paid' => 0,
        'currency' => 'USD',
        'created_by' => $owner->id,
    ], $overrides));
}

test('the admin dashboard reports on all four departments', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertSee('Department Reports');

    $keys = array_column($response->viewData('departments'), 'key');
    expect($keys)->toBe(['ticketing', 'immigration', 'visa', 'srrv']);
});

test('ticketing figures split the pipeline and leave cancelled tickets and quotations out of the money', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    reportTicket($admin, ['status' => 'pending', 'amount_paid' => 1000]);
    reportTicket($admin, ['status' => 'issued', 'amount_paid' => 5000, 'departure_date' => now()->addDays(2)]);
    reportTicket($admin, ['status' => 'cancelled', 'amount_paid' => 0, 'departure_date' => now()->addDays(3)]);
    reportTicket($admin, ['is_quotation' => true, 'total_amount' => 99000]);

    $ticketing = collect($this->actingAs($admin)->get('/admin/dashboard')->viewData('departments'))
        ->firstWhere('key', 'ticketing');

    expect($ticketing['total'])->toBe(4)
        ->and($ticketing['pipeline'])->toBe(['open' => 2, 'done' => 1, 'cancelled' => 1])
        ->and($ticketing['money'][0]['amounts'])->toBe(['PHP' => 6000.0])
        ->and($ticketing['money'][1]['amounts'])->toBe(['PHP' => 4000.0])
        ->and($ticketing['attention']['count'])->toBe(1);
});

test('visa and srrv money is reported per currency', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    reportVisa($admin, ['status' => 'released', 'amount_paid' => 8000]);
    reportVisa($admin, ['processing_speed' => 'rush', 'amount_paid' => 3000]);
    reportVisa($admin, ['currency' => 'USD', 'total_amount' => 200, 'amount_paid' => 50]);
    reportVisa($admin, ['status' => 'cancelled', 'amount_paid' => 500]);

    reportRetiree($admin, ['amount_paid' => 400]);
    reportRetiree($admin, ['status' => 'released', 'amount_paid' => 1000, 'payment_in_full_at' => now(), 'oath_at' => now()]);
    SrrvRenewal::create([
        'reference' => 'REN-000001',
        'retiree_name' => 'Ramon Dela Cruz',
        'visa_class' => 'classic',
        'status' => 'pending',
        'fee_amount' => 360,
        'amount_paid' => 0,
        'currency' => 'USD',
        'created_by' => $admin->id,
    ]);

    $departments = collect($this->actingAs($admin)->get('/admin/dashboard')->viewData('departments'));

    $visa = $departments->firstWhere('key', 'visa');
    expect($visa['pipeline'])->toBe(['open' => 2, 'done' => 1, 'cancelled' => 1])
        ->and($visa['money'][0]['amounts'])->toBe(['PHP' => 11000.0, 'USD' => 50.0])
        ->and($visa['money'][1]['amounts'])->toBe(['PHP' => 5000.0, 'USD' => 150.0])
        ->and($visa['attention']['count'])->toBe(1);

    $srrv = $departments->firstWhere('key', 'srrv');
    expect($srrv['pipeline'])->toBe(['open' => 1, 'done' => 1, 'cancelled' => 0])
        ->and($srrv['money'][0]['amounts'])->toBe(['USD' => 1400.0])
        ->and($srrv['money'][1]['amounts'])->toBe(['USD' => 960.0]);
});

test('immigration reports sheets, extensions and what needs attention', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $client = ImmigrationClient::factory()->create(['visa_expiry_date' => now()->addDays(3)]);
    ImmigrationClient::factory()->create(['visa_expiry_date' => now()->addMonths(6)]);
    ImmigrationClientExtension::factory()->create([
        'immigration_client_id' => $client->id,
        'extension_date' => now()->toDateString(),
        'amount_paid' => 4500,
    ]);

    $immigration = collect($this->actingAs($admin)->get('/admin/dashboard')->viewData('departments'))
        ->firstWhere('key', 'immigration');

    expect($immigration['total'])->toBe(2)
        ->and($immigration['pipeline'])->toBeNull()
        ->and($immigration['money'][0]['amounts'])->toBe(['PHP' => 4500.0])
        ->and($immigration['attention']['count'])->toBe(1);
});

test('agents only see departments they can open, counting their own files', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['dashboard', 'bookings', 'ticketing']]);

    reportTicket($admin);
    reportTicket($agent);
    reportTicket($agent);

    $departments = $this->actingAs($agent)->get('/admin/dashboard')->viewData('departments');

    expect(array_column($departments, 'key'))->toBe(['ticketing'])
        ->and($departments[0]['total'])->toBe(2);
});

test('empty departments render honest empty states', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertSee('No tickets yet', false);
    $response->assertSee('No applications yet', false);
    $response->assertSee('Nothing departing in the next 7 days', false);
});
