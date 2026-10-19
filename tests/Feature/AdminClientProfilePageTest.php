<?php

use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a new client must have a gender; an older client without one still saves', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('Gender *');

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['gender' => '']))
        ->assertSessionHasErrors('gender');
    expect(User::where('email', 'juan.delacruz@example.com')->exists())->toBeFalse();

    $older = User::factory()->create(['role' => 'client', 'gender' => null]);
    $this->actingAs($admin)->get(route('admin.users.edit', $older))->assertOk()->assertDontSee('Gender *');
});

test('the client page opens on the client information, with the services behind a button', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

    $html = $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()
        ->assertSee('Client Information')->assertSee('Services')
        ->assertDontSee('Full Profile Details')
        ->getContent();

    // The information view shows straight away; the services view starts hidden.
    expect($html)->toContain('x-data="{ view: \'info\', activeTab: \'overview\' }"')
        ->and($html)->toContain('<div x-show="view === \'info\'" class="grid grid-cols-1 md:grid-cols-2 gap-6">')
        ->and($html)->toContain('<div x-show="view === \'services\'" style="display: none;" class="space-y-6">');
});

test('the uploaded passport and government ID show side by side in the ID documents card', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create([
        'role' => 'client', 'passport_number' => 'P1234567A', 'passport_country' => 'Philippines',
        'government_id_type' => 'UMID', 'government_id_number' => '0028-1234567-8',
        'passport_photo' => UploadedFile::fake()->image('passport.jpg')->store('ids', DocumentStorage::diskName()),
        'government_id_photo' => UploadedFile::fake()->create('umid.pdf', 30, 'application/pdf')->store('ids', DocumentStorage::diskName()),
    ]);

    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()
        ->assertSee('ID Documents')
        ->assertSee('<img src="'.route('users.passport', $client).'" alt="Passport"', false)
        ->assertSee('No. P1234567A')
        ->assertSee('PDF document')
        ->assertSee(route('users.government-id', $client), false)
        ->assertDontSee('Open Original Passport Scan');

    $bare = User::factory()->create(['role' => 'client']);
    $this->actingAs($admin)->get(route('admin.users.show', $bare))->assertOk()
        ->assertSee('No passport scan uploaded')->assertSee('No government ID uploaded');
});

test('an older client shows one residential address line; a picked address shows its parts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $older = User::factory()->create(['role' => 'client', 'address' => 'Purok 3, Angeles City, Pampanga', 'province' => null, 'city' => null]);
    $picked = User::factory()->create(['role' => 'client', 'address_line' => '12 Rizal St', 'address' => '12 Rizal St, Makati City, Metro Manila 1210',
        'city' => 'Makati City', 'province' => 'Metro Manila', 'postal_code' => '1210']);

    $this->actingAs($admin)->get(route('admin.users.show', $older))->assertOk()
        ->assertSee('Residential Address:')->assertSee('Purok 3, Angeles City, Pampanga')
        ->assertDontSee('City / Municipality:');

    $this->actingAs($admin)->get(route('admin.users.show', $picked))->assertOk()
        ->assertSee('House No., Street, Barangay:')->assertSee('12 Rizal St')
        ->assertSee('Makati City')->assertSee('Metro Manila')->assertSee('1210');
});
