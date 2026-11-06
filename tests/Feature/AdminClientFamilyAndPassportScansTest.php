<?php

use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('the new client form shows spouse and dependents once Married is picked, and takes several passport scans', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()
        // The family block is tied to the civil status select and shows only for Married.
        ->assertSee('x-model="civil"', false)->assertSee("civil === 'married'", false)
        ->assertSee('name="spouse_name"', false)->assertSee('Add dependent')
        ->assertSee('name="passport_photos[]"', false)->assertSee('multiple', false);
});

test('a married client is saved with the spouse and dependents; blank dependent rows are dropped', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'civil_status' => 'married',
        'spouse_name' => 'Maria Santos Dela Cruz',
        'dependents' => [
            ['name' => 'Ana Dela Cruz', 'date_of_birth' => '2015-03-02'],
            ['name' => '', 'date_of_birth' => ''],
            ['name' => 'Ben Dela Cruz', 'date_of_birth' => ''],
        ],
    ]))->assertSessionHasNoErrors();

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();

    expect($client->spouse_name)->toBe('Maria Santos Dela Cruz')
        ->and($client->dependents)->toBe([
            ['name' => 'Ana Dela Cruz', 'date_of_birth' => '2015-03-02'],
            ['name' => 'Ben Dela Cruz', 'date_of_birth' => null],
        ]);

    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()
        ->assertSee('Maria Santos Dela Cruz')->assertSee('Ana Dela Cruz')->assertSee('born Mar 02, 2015');
});

test('a client who is not married keeps no spouse or dependents, even if they were sent', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'civil_status' => 'single',
        'spouse_name' => 'Should Not Stay',
        'dependents' => [['name' => 'Nor Should This', 'date_of_birth' => '']],
    ]))->assertSessionHasNoErrors();

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();
    expect($client->spouse_name)->toBeNull()->and($client->dependents)->toBeNull();

    // Changing a married client to another status clears them.
    $married = User::factory()->create(['role' => 'client', 'civil_status' => 'married', 'spouse_name' => 'Maria', 'dependents' => [['name' => 'Ana', 'date_of_birth' => null]]]);
    $this->actingAs($admin)->put(route('admin.users.update', $married), clientRegistrationPayload(['email' => $married->email, 'civil_status' => 'widowed']))->assertSessionHasNoErrors();
    expect($married->refresh()->spouse_name)->toBeNull()->and($married->dependents)->toBeNull();

    // An update that does not send a civil status leaves the family as it is.
    $kept = User::factory()->create(['role' => 'client', 'civil_status' => 'married', 'spouse_name' => 'Maria', 'dependents' => [['name' => 'Ana', 'date_of_birth' => null]]]);
    $payload = clientRegistrationPayload(['email' => $kept->email]);
    $this->actingAs($admin)->put(route('admin.users.update', $kept), $payload)->assertSessionHasNoErrors();
    expect($kept->refresh()->civil_status)->toBe('married')->and($kept->spouse_name)->toBe('Maria')->and($kept->dependents)->toHaveCount(1);
});

test('several passport scans are stored together; the first stays the main scan', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'passport_photos' => [
            UploadedFile::fake()->image('page1.jpg'),
            UploadedFile::fake()->image('page2.png'),
            UploadedFile::fake()->create('old-passport.pdf', 200, 'application/pdf'),
        ],
    ]))->assertSessionHasNoErrors();

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();
    $paths = $client->passportScanPaths();

    expect($paths)->toHaveCount(3)->and($client->passport_photo)->toBe($paths[0])->and($client->passport_scans)->toHaveCount(2);
    Storage::disk(DocumentStorage::diskName())->assertExists($paths);

    // Each one opens through its own authorised address; a place that does not exist is a 404.
    $scans = $client->passport_scan_list;
    expect($scans)->toHaveCount(3)->and($scans[0]['url'])->toBe(route('users.passport', $client))->and($scans[2]['pdf'])->toBeTrue();
    foreach ($scans as $scan) {
        $this->actingAs($admin)->get($scan['url'])->assertOk();
    }
    $this->actingAs($admin)->get(route('users.passport', [$client, 7]))->assertNotFound();

    // The profile page shows every scan.
    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()
        ->assertSee('alt="Passport"', false)->assertSee('alt="Passport 2"', false)->assertSee('Passport 3');

    // Another client cannot open any of them.
    $stranger = User::factory()->create(['role' => 'client']);
    $this->actingAs($stranger)->get($scans[1]['url'])->assertForbidden();
});

test('saving again adds scans to the ones on record, and ticked scans are removed', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);
    $save = fn (array $extra) => $this->actingAs($admin)->put(route('admin.users.update', $client), clientRegistrationPayload(['email' => $client->email] + $extra))->assertSessionHasNoErrors();

    $save(['passport_photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]);
    $first = $client->refresh()->passportScanPaths();
    expect($first)->toHaveCount(2);

    // A later save with no files keeps both; one with a file adds it.
    $save([]);
    expect($client->refresh()->passportScanPaths())->toBe($first);
    $save(['passport_photos' => [UploadedFile::fake()->image('c.jpg')]]);
    $three = $client->refresh()->passportScanPaths();
    expect($three)->toHaveCount(3)->and(array_slice($three, 0, 2))->toBe($first);

    // Removing the main scan promotes the next one, and the file is deleted.
    $save(['remove_passport_scans' => [0]]);
    $client->refresh();
    expect($client->passportScanPaths())->toBe([$three[1], $three[2]])->and($client->passport_photo)->toBe($three[1]);
    Storage::disk(DocumentStorage::diskName())->assertMissing($three[0]);
    Storage::disk(DocumentStorage::diskName())->assertExists([$three[1], $three[2]]);

    // Removing the rest leaves none.
    $save(['remove_passport_scans' => [0, 1]]);
    expect($client->refresh()->passportScanPaths())->toBe([])->and($client->passport_photo)->toBeNull()->and($client->passport_scans)->toBeNull();

    // Deleting the client clears every scan they had.
    $save(['passport_photos' => [UploadedFile::fake()->image('d.jpg'), UploadedFile::fake()->image('e.jpg')]]);
    $paths = $client->refresh()->passportScanPaths();
    $this->actingAs($admin)->delete(route('admin.users.destroy', $client))->assertRedirect();
    Storage::disk(DocumentStorage::diskName())->assertMissing($paths);
});

test('passport scans must be images or PDFs, and no more than ten at a time', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'passport_photos' => [UploadedFile::fake()->image('ok.jpg'), UploadedFile::fake()->create('virus.exe', 10)],
    ]))->assertSessionHasErrors('passport_photos.1');

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'passport_photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 11)),
    ]))->assertSessionHasErrors('passport_photos');

    expect(User::where('email', 'juan.delacruz@example.com')->exists())->toBeFalse();
});
