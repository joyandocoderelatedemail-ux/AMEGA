<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('new clients can register with 3-part extended details including split name, address, and ID photo upload', function () {
    Storage::fake('public');

    $avatar = UploadedFile::fake()->image('profile.jpg');
    $idPhoto = UploadedFile::fake()->image('driver_license.jpg');

    $response = $this->post('/register', [
        // Part 1: Personal & Split Name & Address & Emergency Contact
        'first_name' => 'Maria',
        'middle_name' => 'Clara',
        'last_name' => 'Santos',
        'suffix' => 'Jr.',
        'email' => 'mariaclara@example.com',
        'phone' => '+63 917 888 9999',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'nationality' => 'Filipino',

        // Address
        'address_line' => '123 Rizal Street',
        'city' => 'Makati',
        'province' => 'Metro Manila',
        'postal_code' => '1200',
        'country' => 'Philippines',

        // Emergency Contact
        'emergency_contact_name' => 'Juan Santos',
        'emergency_contact_phone' => '+63 918 777 6666',
        'emergency_contact_relationship' => 'Father',

        // Part 2: Passport & IDs & ID Photo & Category
        'account_category' => 'Family',
        'passport_number' => 'P9876543A',
        'passport_expiry' => '2030-12-31',
        'passport_country' => 'Philippines',
        'government_id_type' => "Driver's License",
        'government_id_number' => 'N01-12-345678',
        'government_id_photo' => $idPhoto,

        // Part 3: Uploads & E-Signature
        'profile_photo' => $avatar,
        'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();

    $user = User::where('email', 'mariaclara@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('Maria');
    expect($user->last_name)->toBe('Santos');
    expect($user->suffix)->toBe('Jr.');
    expect($user->full_name)->toBe('Maria Clara Santos Jr.');
    expect($user->city)->toBe('Makati');
    expect($user->government_id_photo)->not->toBeNull();

    $this->assertDatabaseHas('users', [
        'email' => 'mariaclara@example.com',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'suffix' => 'Jr.',
        'city' => 'Makati',
        'account_category' => 'Family',
        'passport_number' => 'P9876543A',
        'government_id_type' => "Driver's License",
    ]);
});

test('admin can view registered client accounts directory and inspect profile with split name and address', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create([
        'first_name' => 'Inspectable',
        'last_name' => 'Client',
        'name' => 'Inspectable Client',
        'email' => 'inspectable@example.com',
        'city' => 'Cebu City',
        'account_category' => 'Corporate',
        'passport_number' => 'P11223344',
    ]);

    $indexResponse = $this->actingAs($admin)->get('/admin/users');
    $indexResponse->assertStatus(200);
    $indexResponse->assertSee('Inspectable Client');

    $showResponse = $this->actingAs($admin)->get("/admin/users/{$client->id}");
    $showResponse->assertStatus(200);
    $showResponse->assertSee('Inspectable');
    $showResponse->assertSee('Cebu City');
    $showResponse->assertSee('Corporate Category');
});
