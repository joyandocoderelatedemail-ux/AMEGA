<?php

use App\Models\Booking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function packageForBooking(): TravelPackage
{
    return TravelPackage::create([
        'title' => 'Test Package',
        'duration' => '5 Days',
        'price' => '$1,000',
        'rating' => 5,
        'image' => 'images/test.jpg',
        'description' => 'Test package',
        'category' => 'short_haul',
        'status' => 'active',
    ]);
}

// ---------------------------------------------------------------------------
// Login throttling
// ---------------------------------------------------------------------------

test('repeated failed logins against one account are throttled', function () {
    $user = User::factory()->create([
        'role' => 'client',
        'password' => bcrypt('correct-password'),
    ]);

    // The limiter allows five attempts per minute per email + IP.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(302);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(429);

    // Even the right password is refused once the budget is spent.
    $this->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
        ->assertStatus(429);

    $this->assertGuest();
});

test('a correct password within the limit still signs in', function () {
    $user = User::factory()->create([
        'role' => 'client',
        'password' => bcrypt('correct-password'),
    ]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
        ->assertRedirect();

    $this->assertAuthenticated();
});

test('one account being attacked does not lock out another', function () {
    $target = User::factory()->create(['role' => 'client']);
    $other = User::factory()->create([
        'role' => 'client',
        'password' => bcrypt('correct-password'),
    ]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['email' => $target->email, 'password' => 'wrong']);
    }

    // A different account from the same IP is unaffected by the first account's failures.
    $this->post('/login', ['email' => $other->email, 'password' => 'correct-password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($other);
});

test('the admin and agent login forms are throttled too', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong']);
    }

    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
        ->assertStatus(429);
});

test('registration is throttled', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/register', [
            'email' => 'flood'.$attempt.'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    $this->post('/register', [
        'email' => 'flood-final@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(429);
});

// ---------------------------------------------------------------------------
// Public form throttling
// ---------------------------------------------------------------------------

test('the contact form is throttled', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/contact', []);
    }

    $this->post('/contact', [])->assertStatus(429);
});

test('the public booking form is throttled', function () {
    $package = packageForBooking();

    $payload = [
        'travel_package_id' => $package->id,
        'customer_name' => 'Juan Dela Cruz',
        'customer_email' => 'juan@example.com',
        'customer_phone' => '09171234567',
        'travel_date' => now()->addDays(10)->format('Y-m-d'),
        'number_of_passengers' => 2,
    ];

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->post('/bookings', $payload);
    }

    $this->post('/bookings', $payload)->assertStatus(429);

    // Only the permitted ten got through.
    expect(Booking::count())->toBe(10);
});

// ---------------------------------------------------------------------------
// Guest chat throttling
// ---------------------------------------------------------------------------

test('guest chat sends are throttled per conversation', function () {
    $token = 'gst_'.Str::random(24);

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $this->post('/chat/send', [
            'guest_token' => $token,
            'message' => 'message '.$attempt,
        ])->assertOk();
    }

    $this->post('/chat/send', [
        'guest_token' => $token,
        'message' => 'one too many',
    ])->assertStatus(429);
});

test('chat polling is not throttled at the rate the widget actually polls', function () {
    $token = 'gst_'.Str::random(24);

    // The widget polls every few seconds; half a minute of polling must pass.
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->get('/chat/poll?guest_token='.$token.'&last_id=0')->assertOk();
    }
});

// ---------------------------------------------------------------------------
// Booking references
// ---------------------------------------------------------------------------

test('booking references are unpredictable and avoid ambiguous characters', function () {
    $references = [];

    for ($i = 0; $i < 200; $i++) {
        $references[] = Booking::generateReference();
    }

    expect(array_unique($references))->toHaveCount(200);

    foreach ($references as $reference) {
        // I, O, 0 and 1 are excluded so the code survives being read aloud.
        expect($reference)->toMatch('/^AMG-\d{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/');
    }
});

test('the reference no longer leaks the clock', function () {
    $reference = Booking::generateReference();

    // The old format ended in the tail of uniqid(); the new one must not.
    expect($reference)->not->toMatch('/^AMG-\d{4}-[0-9A-F]{5}$/');
    expect(strlen($reference))->toBe(17);
});

test('generated references never collide with ones already stored', function () {
    $package = packageForBooking();
    $stored = [];

    for ($i = 0; $i < 25; $i++) {
        $reference = Booking::generateReference();

        Booking::create([
            'booking_reference' => $reference,
            'travel_package_id' => $package->id,
            'customer_name' => 'Client '.$i,
            'customer_email' => 'client'.$i.'@example.com',
            'customer_phone' => '09171234567',
            'travel_date' => now()->addDays(10)->format('Y-m-d'),
            'number_of_passengers' => 1,
        ]);

        $stored[] = $reference;
    }

    expect(array_unique($stored))->toHaveCount(25);
    expect(Booking::count())->toBe(25);
});

// ---------------------------------------------------------------------------
// Deleted accounts
// ---------------------------------------------------------------------------

test('deleting a user removes their stored documents', function () {
    Storage::fake('local');

    $this->post('/register', [
        'first_name' => 'Jane',
        'last_name' => 'Tan',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        'government_id_photo' => UploadedFile::fake()->image('id.jpg'),
    ])->assertRedirect();

    $client = User::where('email', 'jane@example.com')->firstOrFail();
    $photo = $client->profile_photo;
    $idPhoto = $client->government_id_photo;

    Storage::disk('local')->assertExists($photo);
    Storage::disk('local')->assertExists($idPhoto);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $client))
        ->assertRedirect();

    expect(User::find($client->id))->toBeNull();
    Storage::disk('local')->assertMissing($photo);
    Storage::disk('local')->assertMissing($idPhoto);
});
