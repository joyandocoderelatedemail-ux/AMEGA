<?php

use App\Models\TicketBooking;
use App\Models\TicketPassenger;
use App\Models\User;
use App\Support\DocumentStorage;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function registeredClient(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'client',
        'name' => 'Juan Reyes Dela Cruz',
        'first_name' => 'Juan',
        'middle_name' => 'Reyes',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.delacruz@example.com',
        'phone' => '09171234567',
        'nationality' => 'Filipino',
        'gender' => 'male',
        'date_of_birth' => '1990-05-14',
        'passport_number' => 'P1234567A',
        'passport_expiry' => Carbon::today()->addYears(5)->toDateString(),
        'emergency_contact_name' => 'Maria Dela Cruz',
        'emergency_contact_relationship' => 'Spouse',
        'emergency_contact_phone' => '09179998888',
    ], $overrides));
}

/**
 * A domestic booking for one Filipino adult, the selected client.
 *
 * @param  array<string, mixed>  $passenger
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientBookingPayload(User $client, array $passenger = [], array $overrides = []): array
{
    return array_merge([
        'client_user_id' => $client->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu',
        'trip_type' => 'one_way',
        'departure_date' => Carbon::today()->addMonths(2)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => $client->name,
        'contact_email' => $client->email,
        'contact_phone' => $client->phone,
        'passengers' => [array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'passenger_type' => 'adult',
            'nationality_type' => 'filipino',
        ], $passenger)],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

test('a ticketing officer can find a client by name, email, phone or passport', function (string $term) {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient();
    registeredClient(['name' => 'Ana Lopez', 'first_name' => 'Ana', 'last_name' => 'Lopez', 'email' => 'ana@example.com', 'phone' => '09000000000', 'passport_number' => 'X0000000']);

    $response = $this->actingAs($officer)->getJson(route('ticketing.clients.search', ['q' => $term]))->assertOk();

    expect($response->json('clients'))->toHaveCount(1)
        ->and($response->json('clients.0.id'))->toBe($client->id);
})->with(['juan cruz', 'delacruz@example', '0917123', 'P1234567']);

test('search results carry what the booking needs', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    registeredClient();

    $client = $this->actingAs($officer)->getJson(route('ticketing.clients.search', ['q' => 'Juan']))->json('clients.0');

    expect($client)->toMatchArray([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'gender' => 'male',
        'date_of_birth' => '1990-05-14',
        'passport_number' => 'P1234567A',
        'is_filipino' => true,
        'emergency_contact_name' => 'Maria Dela Cruz',
        'has_passport_scan' => false,
    ]);
});

test('staff accounts never appear in client search', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    User::factory()->create(['role' => 'admin', 'name' => 'Juan Admin']);

    $this->actingAs($officer)->getJson(route('ticketing.clients.search', ['q' => 'Juan']))
        ->assertOk()
        ->assertJsonCount(0, 'clients');
});

test('a one-letter search returns nothing', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    registeredClient();

    $this->actingAs($officer)->getJson(route('ticketing.clients.search', ['q' => 'J']))
        ->assertJsonCount(0, 'clients');
});

test('clients cannot use the ticketing client search', function () {
    $client = registeredClient();

    $this->actingAs($client)->getJson(route('ticketing.clients.search', ['q' => 'Juan']))
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Registration from the ticketing desk
// ---------------------------------------------------------------------------

test('a ticketing officer can open the registration form, started from the search', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.clients.create', ['name' => 'Pedro Penduko']))
        ->assertOk()
        ->assertSee('value="Pedro"', false)
        ->assertSee('value="Penduko"', false)
        ->assertSee('name="passport_photo"', false);
});

test('registering a client returns to a new booking with them selected', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($officer)->post(route('ticketing.clients.store'), [
        'account_category' => 'Individual',
        'first_name' => 'Pedro',
        'last_name' => 'Penduko',
        'email' => 'pedro@example.com',
        'phone' => '09181234567',
        'nationality' => 'Filipino',
        'address' => 'Quezon City',
        'gender' => 'male',
        'date_of_birth' => '1992-03-08',
        'passport_photo' => UploadedFile::fake()->image('passport.jpg'),
    ]);

    $client = User::where('email', 'pedro@example.com')->firstOrFail();

    expect($client->role)->toBe('client')
        ->and($client->name)->toBe('Pedro Penduko')
        ->and($client->passport_photo)->toStartWith('passports/');

    $response->assertRedirect(route('ticketing.tickets.create', ['client' => $client->id]));

    $this->actingAs($officer)->get(route('ticketing.tickets.create', ['client' => $client->id]))
        ->assertOk()
        ->assertViewHas('preselectedClient', fn (array $selected) => $selected['id'] === $client->id
            && $selected['email'] === 'pedro@example.com'
            && $selected['has_passport_scan'] === true);
});

test('the booking wizard puts the client search before the travel type', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect(strpos($html, 'Search registered clients'))->toBeLessThan(strpos($html, 'Category Selection Cards'))
        ->and($html)->toContain('name="client_user_id"');
});

// ---------------------------------------------------------------------------
// Booking with a selected client
// ---------------------------------------------------------------------------

test('a booking for a selected client is linked to that client', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient();

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, [
        'government_id_file' => UploadedFile::fake()->image('umid.jpg'),
    ]))->assertSessionHasNoErrors();

    $booking = TicketBooking::firstOrFail();

    expect($booking->user_id)->toBe($client->id)
        ->and(User::where('role', 'client')->count())->toBe(1);
});

test('the client\'s ID scan on file satisfies the government ID requirement', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient(['government_id_photo' => 'ids/juan.jpg']);
    Storage::disk(DocumentStorage::diskName())->put('ids/juan.jpg', 'scan');

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, [
        'use_profile_government_id' => 1,
    ]))->assertSessionHasNoErrors();

    $document = TicketBooking::firstOrFail()->passengers->first()->documents->first();

    expect($document->document_type)->toBe('government_id')
        ->and($document->file_path)->not->toBe('ids/juan.jpg');

    // A copy, so the booking keeps its record if the profile scan is replaced.
    Storage::disk(DocumentStorage::diskName())->assertExists([$document->file_path, 'ids/juan.jpg']);
});

test('the client\'s passport scan on file satisfies an international passport requirement', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient(['passport_photo' => 'passports/juan.pdf']);
    Storage::disk(DocumentStorage::diskName())->put('passports/juan.pdf', 'scan');

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, [
        'passport_number' => 'P1234567A',
        'passport_expiry_date' => Carbon::today()->addYears(5)->toDateString(),
        'use_profile_passport' => 1,
    ], [
        'travel_type' => 'international',
        'destination_country' => 'Japan',
        'destination_city' => 'Tokyo',
        'arrival_airport' => 'NRT',
        'emergency_contact_name' => 'Maria Dela Cruz',
        'emergency_contact_relationship' => 'Spouse',
        'emergency_contact_phone' => '09179998888',
    ]))->assertSessionHasNoErrors();

    $documents = TicketBooking::firstOrFail()->passengers->first()->documents;

    expect($documents->pluck('document_type')->all())->toBe(['passport_scan']);
});

test('a profile scan cannot stand in when the client has none on file', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient();

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, [
        'use_profile_government_id' => 1,
    ]))->assertSessionHasErrors('passengers.0.government_id_file');

    expect(TicketBooking::count())->toBe(0);
});

test('a fresh upload wins over the profile scan', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient(['government_id_photo' => 'ids/juan.jpg']);
    Storage::disk(DocumentStorage::diskName())->put('ids/juan.jpg', 'scan');

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, [
        'use_profile_government_id' => 1,
        'government_id_file' => UploadedFile::fake()->image('new-umid.jpg'),
    ]))->assertSessionHasNoErrors();

    $documents = TicketBooking::firstOrFail()->passengers->first()->documents;

    expect($documents)->toHaveCount(1)
        ->and($documents->first()->original_name)->toBe('new-umid.jpg');
});

test('only a registered client can be attached to a booking', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $staff = User::factory()->create(['role' => 'admin']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($staff))
        ->assertSessionHasErrors('client_user_id');
});

// ---------------------------------------------------------------------------
// Several clients on one booking, each in their age category
// ---------------------------------------------------------------------------

test('a traveller\'s category is worked out from their age on the travel date', function (string $dateOfBirth, string $expected) {
    $travelDate = Carbon::parse('2026-12-01');

    expect(TicketPassenger::typeForAge(Carbon::parse($dateOfBirth), $travelDate))->toBe($expected);
})->with([
    'turns 12 on the travel date' => ['2014-12-01', 'adult'],
    'a day short of 12' => ['2014-12-02', 'child'],
    'turns 2 on the travel date' => ['2024-12-01', 'child'],
    'a day short of 2' => ['2024-12-02', 'infant'],
    'newborn' => ['2026-11-01', 'infant'],
]);

test('a family of registered clients books together, each reusing their own scans', function () {
    Storage::fake(DocumentStorage::diskName());
    $disk = Storage::disk(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $departure = Carbon::today()->addMonths(2);

    $parent = registeredClient(['government_id_photo' => 'ids/juan.jpg']);
    $spouse = registeredClient([
        'name' => 'Maria Dela Cruz', 'first_name' => 'Maria', 'email' => 'maria@example.com',
        'gender' => 'female', 'government_id_photo' => 'ids/maria.jpg',
    ]);
    $child = registeredClient([
        'name' => 'Pia Dela Cruz', 'first_name' => 'Pia', 'email' => 'pia@example.com',
        'gender' => 'female', 'date_of_birth' => $departure->copy()->subYears(7)->toDateString(),
    ]);
    $disk->put('ids/juan.jpg', 'scan');
    $disk->put('ids/maria.jpg', 'scan');

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($parent, [], [
        'departure_date' => $departure->toDateString(),
        'total_passengers' => 3,
        'adults_count' => 2,
        'children_count' => 1,
        'passengers' => [
            ['client_user_id' => $parent->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'date_of_birth' => '1990-05-14', 'use_profile_government_id' => 1],
            ['client_user_id' => $spouse->id, 'first_name' => 'Maria', 'last_name' => 'Dela Cruz', 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'date_of_birth' => '1990-05-14', 'use_profile_government_id' => 1],
            ['client_user_id' => $child->id, 'first_name' => 'Pia', 'last_name' => 'Dela Cruz', 'passenger_type' => 'child', 'nationality_type' => 'filipino', 'date_of_birth' => $child->date_of_birth->toDateString(), 'birth_cert_file' => UploadedFile::fake()->image('psa.jpg')],
        ],
    ]))->assertSessionHasNoErrors();

    $booking = TicketBooking::firstOrFail();
    $passengers = $booking->passengers()->with('documents')->orderBy('passenger_number')->get();

    expect($booking->user_id)->toBe($parent->id)
        ->and($passengers->pluck('passenger_type')->all())->toBe(['adult', 'adult', 'child'])
        ->and($passengers[1]->documents->pluck('document_type')->all())->toBe(['government_id'])
        ->and($passengers[2]->documents->pluck('document_type')->all())->toBe(['birth_certificate']);
});

test('a passenger booked in the wrong age category is rejected', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient();
    $departure = Carbon::today()->addMonths(2);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, [], [
        'departure_date' => $departure->toDateString(),
        'total_passengers' => 2,
        'adults_count' => 2,
        'passengers' => [
            ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'government_id_file' => UploadedFile::fake()->image('umid.jpg')],
            // A one-year-old sent as an adult.
            ['first_name' => 'Baby', 'last_name' => 'Dela Cruz', 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'date_of_birth' => $departure->copy()->subYear()->toDateString(), 'government_id_file' => UploadedFile::fake()->image('id.jpg')],
        ],
    ]))->assertSessionHasErrors('passengers.1.passenger_type');

    expect(TicketBooking::count())->toBe(0);
});

test('a passenger can only be linked to a registered client', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient();
    $staff = User::factory()->create(['role' => 'admin']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, ['client_user_id' => $staff->id]))
        ->assertSessionHasErrors('passengers.0.client_user_id');
});

test('a birth date entered for a client without one is saved to their profile', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = registeredClient(['date_of_birth' => null]);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), clientBookingPayload($client, [
        'client_user_id' => $client->id,
        'date_of_birth' => '1988-02-20',
        'government_id_file' => UploadedFile::fake()->image('umid.jpg'),
    ]))->assertSessionHasNoErrors();

    expect($client->fresh()->date_of_birth->toDateString())->toBe('1988-02-20');
});

test('the ticketing desk cannot register a client without a birth date', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.clients.store'), [
        'account_category' => 'Individual',
        'first_name' => 'Pedro',
        'last_name' => 'Penduko',
        'email' => 'pedro@example.com',
        'phone' => '09181234567',
        'nationality' => 'Filipino',
        'address' => 'Quezon City',
    ])->assertSessionHasErrors('date_of_birth');

    expect(User::where('email', 'pedro@example.com')->exists())->toBeFalse();
});

test('the registration form asks for the birth date up front', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.clients.create'))
        ->assertOk()
        ->assertSee('Date of Birth *')
        ->assertSee('Sets Adult, Child or Infant on ticket bookings.');
});
