<?php

use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function clientFormAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('the phone fields on the client form take numbers only', function () {
    $html = $this->actingAs(clientFormAdmin())->get(route('admin.users.create'))->assertOk()->getContent();

    expect($html)->toContain('id="phone" type="tel" inputmode="numeric" pattern="[0-9]*"')
        ->and($html)->toContain('id="emergency_contact_phone" type="tel" inputmode="numeric" pattern="[0-9]*"')
        ->and($html)->toContain('placeholder="09123456789"');
});

test('a pasted number is cleaned to digits, and letters are refused', function () {
    $admin = clientFormAdmin();

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'phone' => '+63 (912) 345-6789', 'emergency_contact_phone' => '0917.123.4567',
    ]))->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();
    expect($client->phone)->toBe('639123456789')->and($client->emergency_contact_phone)->toBe('09171234567');

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['email' => 'two@example.com', 'last_name' => 'Santos', 'phone' => '0917 call me']))
        ->assertSessionHasErrors(['phone' => 'The phone must be numbers only.']);
    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['email' => 'three@example.com', 'last_name' => 'Reyes', 'phone' => '09171110000', 'emergency_contact_phone' => 'ask Maria']))
        ->assertSessionHasErrors('emergency_contact_phone');
});

test('editing a client shows an older formatted number as digits, and saves it that way', function () {
    $admin = clientFormAdmin();
    $client = User::factory()->create(['role' => 'client', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'phone' => '+63 917 111 2233', 'emergency_contact_phone' => '0918-222-3344']);

    $this->actingAs($admin)->get(route('admin.users.edit', $client))->assertOk()
        ->assertSee('value="639171112233"', false)->assertSee('value="09182223344"', false);

    $this->actingAs($admin)->put(route('admin.users.update', $client), [
        'first_name' => $client->first_name, 'last_name' => $client->last_name, 'email' => $client->email, 'account_category' => $client->account_category ?? 'Individual',
        'role' => 'client', 'phone' => '639171112233', 'emergency_contact_phone' => '09182223344', 'address' => 'Somewhere',
    ])->assertRedirect();

    expect($client->fresh()->phone)->toBe('639171112233');
});

test('a profile picture is added with the client, shown, and replaced from the edit page', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = clientFormAdmin();

    $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()
        ->assertSee('Profile Picture')->assertSee('name="profile_photo"', false)->assertSee('Add picture');

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'profile_photo' => UploadedFile::fake()->image('juan.jpg', 400, 400),
    ]))->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();
    $first = $client->profile_photo;
    expect($first)->toStartWith('profiles/');
    Storage::disk(DocumentStorage::diskName())->assertExists($first);
    $this->actingAs($admin)->get(route('users.profile-photo', $client))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.edit', $client))->assertSee('Change picture')->assertSee('profile-photo', false);

    $this->actingAs($admin)->put(route('admin.users.update', $client), [
        'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => $client->email, 'account_category' => 'Individual', 'role' => 'client',
        'phone' => $client->phone, 'address' => '12 Rizal St', 'profile_photo' => UploadedFile::fake()->image('new.png', 300, 300),
    ])->assertRedirect();

    $replaced = $client->fresh()->profile_photo;
    expect($replaced)->not->toBe($first);
    Storage::disk(DocumentStorage::diskName())->assertMissing($first);
    Storage::disk(DocumentStorage::diskName())->assertExists($replaced);
});

test('a profile picture must be an image', function () {
    Storage::fake(DocumentStorage::diskName());

    $this->actingAs(clientFormAdmin())->post(route('admin.users.store'), clientRegistrationPayload([
        'profile_photo' => UploadedFile::fake()->create('scan.pdf', 40, 'application/pdf'),
    ]))->assertSessionHasErrors('profile_photo');
});

test('the client list no longer has the ID photo and signature column', function () {
    $admin = clientFormAdmin();
    User::factory()->create(['role' => 'client', 'signature' => 'data:image/png;base64,AAAA']);

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
        ->assertDontSee('ID Photo & Signature')->assertDontSee('E-Signature Preview')->assertDontSee('No ID photo')
        ->assertSee('User / Account')->assertSee('Category')->assertSee('Actions');
});

test('the client page no longer has the profile photo and digital signature card', function () {
    $admin = clientFormAdmin();
    $client = User::factory()->create(['role' => 'client', 'signature' => 'data:image/png;base64,AAAA']);

    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()
        ->assertDontSee('Profile Photo &amp; Digital Signature', false)
        ->assertDontSee('Stored account photo and recorded authorization signature')
        ->assertDontSee('Recorded Digital E-Signature')
        ->assertSee('ID Documents');
});

test('the client list shows which documents are uploaded for each client', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = clientFormAdmin();
    $disk = DocumentStorage::diskName();

    $both = User::factory()->create(['role' => 'client', 'first_name' => 'Ana', 'last_name' => 'Reyes',
        'passport_photo' => UploadedFile::fake()->image('p.jpg')->store('passports', $disk),
        'government_id_photo' => UploadedFile::fake()->create('id.pdf', 20, 'application/pdf')->store('ids', $disk)]);
    $foreigner = User::factory()->create(['role' => 'client', 'first_name' => 'John', 'last_name' => 'Smith',
        'stamps_photo' => UploadedFile::fake()->image('s.jpg')->store('stamps', $disk),
        'arrival_stamp_photo' => UploadedFile::fake()->image('a.jpg')->store('stamps', $disk)]);
    User::factory()->create(['role' => 'client', 'first_name' => 'Lia', 'last_name' => 'Cruz']);

    $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
        ->assertSeeInOrder(['User / Account', 'Category', 'Documents', 'Actions'])
        ->assertSee(route('users.passport', $both), false)->assertSee(route('users.government-id', $both), false)
        ->assertSee(route('users.stamps', $foreigner), false)->assertSee(route('users.arrival-stamp', $foreigner), false)
        ->assertSee('Passport stamps')->assertSee('Arrival stamp')->assertSee('None uploaded')
        ->getContent();

    // Only what exists is listed: the client with no uploads shows the empty note once, and the others do not.
    expect(substr_count($html, 'None uploaded'))->toBe(1);
});
