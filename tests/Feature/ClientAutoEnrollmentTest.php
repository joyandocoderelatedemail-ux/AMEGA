<?php

use App\Models\ImmigrationClient;
use App\Models\TicketBooking;
use App\Models\User;
use App\Services\ClientAccountService;

test('processing an immigration client automatically enrolls a client user account', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['immigration'],
    ]);

    $response = $this->actingAs($agent)->post(route('admin.client-sheets.store'), [
        'given_name' => 'Jean-Luc',
        'last_name' => 'Picard',
        'email' => 'picard@enterprise.org',
        'mobile_number' => '+63 919 888 7766',
        'passport_number' => 'FR99887766',
        'nationality' => 'French',
        'address' => 'Makati City, Metro Manila',
        'date_of_birth' => '1985-07-13',
    ]);

    $response->assertRedirect();

    // Check user was created in client accounts
    $user = User::where('email', 'picard@enterprise.org')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('client');
    expect($user->name)->toBe('Jean-Luc Picard');
    expect($user->passport_number)->toBe('FR99887766');

    // Check immigration client is linked to user
    $sheet = ImmigrationClient::where('passport_number', 'FR99887766')->first();
    expect($sheet->user_id)->toBe($user->id);
});

test('processing a ticket booking automatically enrolls a client user account', function () {
    $staff = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['ticketing'],
    ]);

    $response = $this->actingAs($staff)->post(route('ticketing.tickets.store'), [
        'save_as_quotation' => true,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'MNL',
        'destination' => 'CEB',
        'trip_type' => 'one_way',
        'travel_class' => 'economy',
        'departure_date' => now()->addDays(14)->format('Y-m-d'),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Arthur Dent',
        'contact_email' => 'arthur.dent@galaxy.com',
        'contact_phone' => '+63 920 444 5555',
        'passengers' => [
            [
                'passenger_type' => 'adult',
                'first_name' => 'Arthur',
                'last_name' => 'Dent',
                'date_of_birth' => '1990-05-11',
                'nationality' => 'British',
                'gender' => 'male',
                'passport_number' => 'UK5432109',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $user = User::where('email', 'arthur.dent@galaxy.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('client');
    expect($user->name)->toBe('Arthur Dent');

    $ticket = TicketBooking::where('contact_email', 'arthur.dent@galaxy.com')->first();
    expect($ticket->user_id)->toBe($user->id);
});

test('processing a visa assistance file automatically enrolls a client user account', function () {
    $staff = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['visa_assistance'],
    ]);

    $response = $this->actingAs($staff)->post(route('visa.applications.store'), [
        'service_type' => 'visit_visa',
        'client_name' => 'Leia Organa',
        'client_email' => 'leia@alderaan.gov',
        'client_phone' => '+63 915 222 3333',
        'destination_country' => 'United States',
        'purpose' => 'tourist',
        'processing_speed' => 'regular',
        'applicant_type' => 'individual',
        'service_fee' => 5000,
    ]);

    $response->assertRedirect();

    $user = User::where('email', 'leia@alderaan.gov')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('client');
    expect($user->name)->toBe('Leia Organa');
});

test('processing an SRRV application automatically enrolls a client user account', function () {
    $staff = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['srrv'],
    ]);

    $response = $this->actingAs($staff)->post(route('srrv.applications.store'), [
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'retiree_name' => 'Klaus Schmidt',
        'retiree_email' => 'klaus.schmidt@berlin.de',
        'retiree_phone' => '+63 917 999 1111',
        'date_of_birth' => '1958-03-22',
        'nationality' => 'German',
        'srrv_card_number' => 'SRRV-DE-8899',
        'investment_amount' => 20000,
        'copies_submitted' => 4,
    ]);

    $response->assertRedirect();

    $user = User::where('email', 'klaus.schmidt@berlin.de')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('client');
    expect($user->name)->toBe('Klaus Schmidt');
});

test('syncAllDeskClients scans all desk records and populates client accounts', function () {
    // 1. Unlinked immigration client
    ImmigrationClient::create([
        'last_name' => 'Wayne',
        'given_name' => 'Bruce',
        'email' => 'bruce@wayne-enterprises.com',
        'passport_number' => 'US-GOTHAM-001',
    ]);

    // 2. Unlinked ticket booking
    TicketBooking::create([
        'booking_reference' => 'TKT-SYNC-999',
        'contact_name' => 'Clark Kent',
        'contact_email' => 'clark@dailyplanet.com',
        'destination' => 'Smallville',
        'departure_date' => now()->addDays(10),
        'total_amount' => 12000,
        'travel_type' => 'domestic',
    ]);

    ClientAccountService::syncAllDeskClients();

    expect(User::where('email', 'bruce@wayne-enterprises.com')->exists())->toBeTrue();
    expect(User::where('email', 'clark@dailyplanet.com')->exists())->toBeTrue();
});

/**
 * A domestic quotation posted through the ticket form, with one passenger.
 *
 * @param  array<string, mixed>  $passenger
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quotationWithLeadPassenger(array $passenger, array $overrides = []): array
{
    return array_merge([
        'save_as_quotation' => true,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'MNL',
        'destination' => 'CEB',
        'trip_type' => 'one_way',
        'travel_class' => 'economy',
        'departure_date' => now()->addDays(14)->format('Y-m-d'),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria.santos@example.com',
        'contact_phone' => '+63 917 555 0101',
        'passengers' => [array_merge([
            'passenger_type' => 'adult',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'date_of_birth' => '1988-02-20',
            'gender' => 'female',
        ], $passenger)],
    ], $overrides);
}

test('a walk-in booker who is the lead passenger gets their gender on the client record', function () {
    $staff = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($staff)->post(route('ticketing.tickets.store'), quotationWithLeadPassenger([]))
        ->assertSessionHasNoErrors();

    $client = User::where('email', 'maria.santos@example.com')->firstOrFail();

    expect($client->gender)->toBe('female')
        ->and($client->date_of_birth->format('Y-m-d'))->toBe('1988-02-20');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.users.edit', $client))
        ->assertOk()
        ->assertSee('<option value="female" selected>', false);
});

test('a companion travelling for the booker does not give the booker their gender', function () {
    $staff = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($staff)->post(route('ticketing.tickets.store'), quotationWithLeadPassenger([
        'first_name' => 'Jose',
        'last_name' => 'Rizal',
        'gender' => 'male',
    ]))->assertSessionHasNoErrors();

    expect(User::where('email', 'maria.santos@example.com')->firstOrFail()->gender)->toBeNull();
});

test('a picked client keeps the gender already on their record', function () {
    $staff = User::factory()->create(['role' => 'ticketing']);
    $client = User::factory()->create([
        'role' => 'client',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'name' => 'Maria Santos',
        'gender' => 'other',
    ]);

    $this->actingAs($staff)->post(route('ticketing.tickets.store'), quotationWithLeadPassenger(
        ['gender' => 'female'],
        ['client_user_id' => $client->id, 'contact_email' => $client->email],
    ))->assertSessionHasNoErrors();

    expect($client->fresh()->gender)->toBe('other');
});

test('syncing desk clients fills gender from past bookings where the booker travelled', function () {
    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-SYNC-GENDER',
        'contact_name' => 'Lea Salonga',
        'contact_email' => 'lea@example.com',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(10),
        'travel_type' => 'domestic',
    ]);
    $ticket->passengers()->create([
        'passenger_number' => 1,
        'passenger_type' => 'adult',
        'first_name' => 'Lea',
        'last_name' => 'Salonga',
        'gender' => 'female',
        'date_of_birth' => '1971-02-22',
    ]);

    ClientAccountService::syncAllDeskClients();

    $client = User::where('email', 'lea@example.com')->firstOrFail();
    expect($client->gender)->toBe('female')
        ->and($client->date_of_birth->format('Y-m-d'))->toBe('1971-02-22');
});
