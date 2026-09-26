<?php

use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        'address' => '12 Rizal St, Makati City',
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

test('the registration form asks for the details the ticket form needs', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('name="gender"', false)
        ->assertSee('name="date_of_birth"', false)
        ->assertSee('name="passport_expiry"', false)
        ->assertSee('name="passport_country"', false)
        ->assertSee('name="government_id_type"', false)
        ->assertSee('name="emergency_contact_name"', false)
        ->assertSee('name="emergency_contact_email"', false);
});

test('registering a client saves their travel profile', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload())
        ->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();

    expect($client->gender)->toBe('male')
        ->and($client->date_of_birth->format('Y-m-d'))->toBe('1990-05-14')
        ->and($client->passport_number)->toBe('P1234567A')
        ->and($client->passport_expiry->format('Y-m-d'))->toBe('2031-01-31')
        ->and($client->passport_country)->toBe('Philippines')
        ->and($client->government_id_type)->toBe('UMID')
        ->and($client->government_id_number)->toBe('0028-1234567-8')
        ->and($client->emergency_contact_name)->toBe('Maria Dela Cruz')
        ->and($client->emergency_contact_relationship)->toBe('Spouse')
        ->and($client->emergency_contact_phone)->toBe('+63 917 123 4567')
        ->and($client->emergency_contact_email)->toBe('maria@example.com');
});

test('apart from the birth date the travel profile is optional, so a quick walk-in can still be registered', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $payload = array_intersect_key(clientRegistrationPayload(), array_flip([
        'role', 'account_category', 'first_name', 'last_name', 'email', 'phone', 'nationality', 'address', 'date_of_birth',
    ]));

    $this->actingAs($admin)->post(route('admin.users.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'juan.delacruz@example.com')->exists())->toBeTrue();
});

test('a client cannot be registered without a birth date', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['date_of_birth' => '']))
        ->assertSessionHasErrors('date_of_birth');
});

test('a staff account can be created without a birth date', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['role' => 'ticketing', 'date_of_birth' => '']))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'juan.delacruz@example.com')->value('role'))->toBe('ticketing');
});

test('invalid travel details are rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'gender' => 'unknown',
        'date_of_birth' => now()->addDay()->format('Y-m-d'),
        'emergency_contact_email' => 'not-an-email',
    ]))->assertSessionHasErrors(['gender', 'date_of_birth', 'emergency_contact_email']);
});

test('the travel profile can be corrected from the edit page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client', 'passport_number' => 'OLD123']);

    $this->actingAs($admin)->get(route('admin.users.edit', $client))
        ->assertOk()
        ->assertSee('value="OLD123"', false);

    $this->actingAs($admin)->put(route('admin.users.update', $client), clientRegistrationPayload([
        'email' => $client->email,
        'passport_number' => 'NEW456',
        'gender' => 'female',
    ]))->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->passport_number)->toBe('NEW456')
        ->and($client->gender)->toBe('female')
        ->and($client->emergency_contact_name)->toBe('Maria Dela Cruz');
});

test('passport and government ID scans are stored privately on registration', function () {
    Storage::fake(DocumentStorage::diskName());
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'passport_photo' => UploadedFile::fake()->image('passport.jpg'),
        'government_id_photo' => UploadedFile::fake()->create('umid.pdf', 200, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();

    expect($client->passport_photo)->toStartWith('passports/')
        ->and($client->government_id_photo)->toStartWith('ids/');

    Storage::disk(DocumentStorage::diskName())->assertExists([$client->passport_photo, $client->government_id_photo]);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();

    $this->actingAs($admin)->get($client->passport_photo_url)->assertOk();
});

test('uploading a new scan replaces the old file', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);

    $upload = fn () => $this->actingAs($admin)->put(route('admin.users.update', $client), clientRegistrationPayload([
        'email' => $client->email,
        'passport_photo' => UploadedFile::fake()->image('passport.png'),
    ]))->assertSessionHasNoErrors();

    $upload();
    $first = $client->refresh()->passport_photo;

    $upload();
    $second = $client->refresh()->passport_photo;

    expect($second)->not->toBe($first);
    Storage::disk(DocumentStorage::diskName())->assertMissing($first);
    Storage::disk(DocumentStorage::diskName())->assertExists($second);
});

test('saving without a new file keeps the scan on record', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client', 'passport_photo' => 'passports/existing.jpg']);
    Storage::disk(DocumentStorage::diskName())->put('passports/existing.jpg', 'scan');

    $this->actingAs($admin)->put(route('admin.users.update', $client), clientRegistrationPayload([
        'email' => $client->email,
    ]))->assertSessionHasNoErrors();

    expect($client->refresh()->passport_photo)->toBe('passports/existing.jpg');
});

test('scans must be an image or PDF', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'passport_photo' => UploadedFile::fake()->create('passport.exe', 10),
    ]))->assertSessionHasErrors('passport_photo');
});

test('another client cannot open someone else\'s passport scan', function () {
    Storage::fake(DocumentStorage::diskName());
    $client = User::factory()->create(['role' => 'client', 'passport_photo' => 'passports/private.jpg']);
    Storage::disk(DocumentStorage::diskName())->put('passports/private.jpg', 'scan');
    $stranger = User::factory()->create(['role' => 'client']);

    $this->actingAs($stranger)->get(route('users.passport', $client))->assertForbidden();
    $this->actingAs($client)->get(route('users.passport', $client))->assertOk();
});
