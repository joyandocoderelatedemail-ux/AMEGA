<?php

use App\Models\TicketPassengerDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Register a client through the real endpoint, optionally with documents.
 *
 * The register routes sit behind the "guest" middleware, so any session left
 * over from an earlier registration in the same test is cleared first.
 *
 * @param  array<string, mixed>  $overrides
 */
function registerClient(TestCase $test, array $overrides = []): User
{
    Auth::logout();

    $email = 'client'.Str::random(10).'@example.com';

    $test->post('/register', array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Tan',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides))->assertRedirect();

    return User::where('email', $email)->firstOrFail();
}

// ---------------------------------------------------------------------------
// Uploads must never reach the public disk
// ---------------------------------------------------------------------------

test('registration writes the government ID and profile photo to the private disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    expect($client->government_id_photo)->toStartWith('ids/');
    expect($client->profile_photo)->toStartWith('profiles/');

    Storage::disk('local')->assertExists($client->government_id_photo);
    Storage::disk('local')->assertExists($client->profile_photo);

    // The whole point: nothing lands where the web server can hand it out.
    Storage::disk('public')->assertMissing($client->government_id_photo);
    Storage::disk('public')->assertMissing($client->profile_photo);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('ticket passenger documents are written to the private disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post('/ticketing/tickets', [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila',
        'destination' => 'Cebu',
        'trip_type' => 'one_way',
        'departure_date' => now()->addMonth()->format('Y-m-d'),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Jane Tan',
        'contact_email' => 'jane@example.com',
        'contact_phone' => '+63 900 000 0000',
        'passengers' => [
            [
                'first_name' => 'Jane',
                'last_name' => 'Tan',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                'passport_expiry_date' => now()->addYear()->format('Y-m-d'),
                'passport_file' => UploadedFile::fake()->image('passport.jpg'),
                'government_id_file' => UploadedFile::fake()->image('govid.jpg'),
            ],
        ],
    ]);

    $document = TicketPassengerDocument::firstOrFail();

    expect($document->file_path)->toStartWith('tickets/');
    Storage::disk('local')->assertExists($document->file_path);
    Storage::disk('public')->assertMissing($document->file_path);
});

// ---------------------------------------------------------------------------
// Access control on the served documents
// ---------------------------------------------------------------------------

test('a client can view their own government ID', function () {
    Storage::fake('local');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);

    $this->actingAs($client)
        ->get(route('users.government-id', $client))
        ->assertOk();
});

test('an admin can view a client government ID', function () {
    Storage::fake('local');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('users.government-id', $client))
        ->assertOk();
});

test('staff holding the users permission can view a client government ID', function () {
    Storage::fake('local');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['dashboard', 'users'],
    ]);

    $this->actingAs($agent)
        ->get(route('users.government-id', $client))
        ->assertOk();
});

test('an unrelated client cannot view someone else government ID', function () {
    Storage::fake('local');

    $owner = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);
    $stranger = User::factory()->create(['role' => 'client']);

    $this->actingAs($stranger)
        ->get(route('users.government-id', $owner))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->get(route('users.profile-photo', $owner))
        ->assertForbidden();
});

test('a dedicated desk officer cannot view client identity documents', function () {
    Storage::fake('local');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);

    foreach (['srrv', 'visa_assistance', 'ticketing'] as $role) {
        $officer = User::factory()->create(['role' => $role]);

        $this->actingAs($officer)
            ->get(route('users.government-id', $client))
            ->assertForbidden();
    }
});

test('an agent without the users permission cannot view client identity documents', function () {
    Storage::fake('local');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['dashboard', 'bookings'],
    ]);

    $this->actingAs($agent)
        ->get(route('users.government-id', $client))
        ->assertForbidden();
});

test('guests cannot reach client identity documents', function () {
    Storage::fake('local');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);

    Auth::logout();

    $this->get(route('users.government-id', $client))->assertRedirect('/login');
});

test('a missing document returns 404 rather than an error', function () {
    Storage::fake('local');

    $client = registerClient($this);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('users.government-id', $client))
        ->assertNotFound();
});

test('the photo accessor points at the authorised route not a public asset', function () {
    Storage::fake('local');

    $client = registerClient($this, [
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ]);

    expect($client->government_id_photo_url)->toBe(route('users.government-id', $client));
    expect($client->government_id_photo_url)->not->toContain('/storage/');
});

// ---------------------------------------------------------------------------
// The backfill command
// ---------------------------------------------------------------------------

test('the privatise command moves documents off the public disk', function () {
    Storage::fake('public');
    Storage::fake('local');

    Storage::disk('public')->put('ids/id1.jpg', 'id-file');
    Storage::disk('public')->put('profiles/avatar.jpg', 'photo-file');
    Storage::disk('public')->put('tickets/TKT-1/p1/passport.jpg', 'passport-file');
    Storage::disk('public')->put('chat_attachments/keep-me.jpg', 'chat-file');

    $this->artisan('documents:privatise')->assertSuccessful();

    Storage::disk('local')->assertExists('ids/id1.jpg');
    Storage::disk('local')->assertExists('profiles/avatar.jpg');
    Storage::disk('local')->assertExists('tickets/TKT-1/p1/passport.jpg');

    Storage::disk('public')->assertMissing('ids/id1.jpg');
    Storage::disk('public')->assertMissing('profiles/avatar.jpg');
    Storage::disk('public')->assertMissing('tickets/TKT-1/p1/passport.jpg');

    // Chat attachments are deliberately left alone.
    Storage::disk('public')->assertExists('chat_attachments/keep-me.jpg');
});

test('the privatise command preserves file contents', function () {
    Storage::fake('public');
    Storage::fake('local');

    Storage::disk('public')->put('ids/id1.jpg', 'original-bytes');

    $this->artisan('documents:privatise')->assertSuccessful();

    expect(Storage::disk('local')->get('ids/id1.jpg'))->toBe('original-bytes');
});

test('the privatise command is safe to run twice', function () {
    Storage::fake('public');
    Storage::fake('local');

    Storage::disk('public')->put('ids/id1.jpg', 'id-file');

    $this->artisan('documents:privatise')->assertSuccessful();
    $this->artisan('documents:privatise')->assertSuccessful();

    Storage::disk('local')->assertExists('ids/id1.jpg');
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('the privatise command refuses to run when the document disk is public', function () {
    Storage::fake('public');

    config(['filesystems.documents_disk' => 'public']);

    Storage::disk('public')->put('ids/id1.jpg', 'id-file');

    $this->artisan('documents:privatise')->assertFailed();

    // Nothing was deleted.
    Storage::disk('public')->assertExists('ids/id1.jpg');
});
