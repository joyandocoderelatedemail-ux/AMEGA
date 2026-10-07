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

test('registering a client saves the optional frequent flyer, foreigner stamps and ID remarks', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('name="frequent_flyer_membership"', false)
        ->assertSee('name="stamps_photo"', false)
        ->assertSee('name="arrival_stamp_photo"', false)
        ->assertSee('name="government_id_remarks"', false);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'nationality' => 'Japan',
        'frequent_flyer_membership' => 'Cebu Pacific GetGo 123456',
        'government_id_type' => 'Other',
        'government_id_remarks' => 'Alien Certificate of Registration',
    ]))->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();

    expect($client->frequent_flyer_membership)->toBe('Cebu Pacific GetGo 123456')
        ->and($client->stamps_photo)->toBeNull()
        ->and($client->arrival_stamp_photo)->toBeNull()
        ->and($client->government_id_remarks)->toBe('Alien Certificate of Registration');
});

test('staff pick the account category from Individual, Corporate or Group only', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $page = $this->actingAs($admin)->get(route('admin.users.create'))->assertOk();
    foreach (['Individual', 'Corporate', 'Group'] as $category) {
        $page->assertSee('value="'.$category.'"', false);
    }
    $page->assertDontSee('Visa Processing Assistance')->assertDontSee('Philippine Retirement Visa (SRRV)');

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['account_category' => 'Group']))
        ->assertSessionHasNoErrors();
    expect(User::where('email', 'juan.delacruz@example.com')->firstOrFail()->account_category)->toBe('Group');

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'account_category' => 'Agency', 'email' => 'other@example.com', 'phone' => '09170000009', 'last_name' => 'Other',
    ]))->assertSessionHasErrors('account_category');
});

test('a client saved under an older category can be edited without losing it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client', 'account_category' => 'Agency']);

    $this->actingAs($admin)->get(route('admin.users.edit', $client))->assertOk()->assertSee('value="Agency" selected', false);
});

test('a client can be registered with no email or no phone number', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('name="no_email"', false)
        ->assertSee('name="no_phone"', false);

    $payload = clientRegistrationPayload(['no_email' => '1', 'no_phone' => '1']);
    unset($payload['email'], $payload['phone']);

    $this->actingAs($admin)->post(route('admin.users.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    $client = User::where('first_name', $payload['first_name'])->where('last_name', $payload['last_name'])->firstOrFail();

    expect($client->phone)->toBeNull()
        ->and($client->hasPlaceholderEmail())->toBeTrue()
        ->and($client->real_email)->toBeNull();

    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()->assertSee('No email');
    $this->actingAs($admin)->get(route('admin.users.edit', $client))->assertOk();

    // Saving the edit form again keeps the same placeholder rather than minting a new one.
    $this->actingAs($admin)->put(route('admin.users.update', $client), [
        'first_name' => $client->first_name,
        'last_name' => $client->last_name,
        'no_email' => '1',
        'no_phone' => '1',
        'account_category' => 'Individual',
        'role' => 'client',
    ])->assertSessionHasNoErrors();

    expect($client->fresh()->email)->toBe($client->email);
});

test('email and phone are still required unless the matching box is ticked', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = clientRegistrationPayload();
    unset($payload['email'], $payload['phone']);

    $this->actingAs($admin)->post(route('admin.users.store'), $payload)
        ->assertSessionHasErrors(['email', 'phone']);
});

test('a staff account cannot be left without an email', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent']);

    $this->actingAs($admin)->put(route('admin.users.update', $agent), [
        'first_name' => 'Ana',
        'last_name' => 'Agent',
        'phone' => '09171234567',
        'no_email' => '1',
        'account_category' => 'Individual',
        'role' => 'agent',
    ])->assertSessionHasErrors('email');
});

test('a foreigner can have the multiple stamps and arrival stamp scans uploaded, privately', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'nationality' => 'Japan',
        'stamps_photo' => UploadedFile::fake()->image('stamps.jpg'),
        'arrival_stamp_photo' => UploadedFile::fake()->create('arrival.pdf', 100, 'application/pdf'),
    ]))->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();

    expect($client->stamps_photo)->toStartWith('stamps/')
        ->and($client->arrival_stamp_photo)->toStartWith('stamps/');
    Storage::disk(DocumentStorage::diskName())->assertExists([$client->stamps_photo, $client->arrival_stamp_photo]);

    $this->actingAs($admin)->get($client->stamps_photo_url)->assertOk();
    $this->actingAs($admin)->get($client->arrival_stamp_photo_url)->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'client']))->get($client->stamps_photo_url)->assertForbidden();

    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()->assertSee('Multiple Stamps');

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'email' => 'other@example.com',
        'phone' => '09170000001',
        'last_name' => 'Other',
        'stamps_photo' => UploadedFile::fake()->create('stamps.exe', 10),
    ]))->assertSessionHasErrors('stamps_photo');
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

test('the client form only registers travelers, whatever role is submitted', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['role' => 'admin']))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'juan.delacruz@example.com')->value('role'))->toBe('client');
});

test('the client form offers Client / Traveler as the only account role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('Client / Traveler')
        ->assertSee('<option value="client" selected>Client / Traveler</option>', false)
        ->assertDontSee('<option value="ticketing">', false)
        ->assertDontSee('<option value="admin">', false)
        ->assertDontSee('<option value="agent">', false);
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
