<?php

use App\Models\ImmigrationClient;
use App\Models\User;

function heightAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('height and weight typed on a client sheet are kept on the client record', function () {
    $this->actingAs(heightAdmin())->post(route('admin.client-sheets.store'), [
        'service' => 'ecc', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123', 'nationality' => 'Korean',
        'height' => '175 cm', 'weight' => '70 kg',
    ]);

    $sheet = ImmigrationClient::where('passport_number', 'M123')->firstOrFail();
    expect($sheet->height)->toBe('175 cm')->and($sheet->weight)->toBe('70 kg')
        ->and($sheet->user->height)->toBe('175 cm')->and($sheet->user->weight)->toBe('70 kg');

    // Changed later, the client record follows the latest sheet.
    $this->actingAs(heightAdmin())->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'ecc', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'height' => '176 cm', 'weight' => '72 kg',
    ]);
    expect($sheet->fresh()->user->height)->toBe('176 cm')->and($sheet->fresh()->user->weight)->toBe('72 kg');
});

test('a sheet saved without them does not wipe what the client record holds', function () {
    $client = User::factory()->create(['role' => 'client', 'height' => '160 cm', 'weight' => '55 kg']);
    $sheet = ImmigrationClient::create(['last_name' => 'Lee', 'given_name' => 'Ana', 'user_id' => $client->id]);

    $this->actingAs(heightAdmin())->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'ecc', 'last_name' => 'Lee', 'given_name' => 'Ana',
    ]);

    expect($client->fresh()->height)->toBe('160 cm')->and($client->fresh()->weight)->toBe('55 kg');
});

test('a new folder started from a client record opens with their height and weight', function () {
    $client = User::factory()->create(['role' => 'client', 'height' => '168 cm', 'weight' => '60 kg']);

    $this->actingAs(heightAdmin())->get(route('admin.client-sheets.create', ['client' => $client->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('value="168 cm"', false)->assertSee('value="60 kg"', false);
});

test('height and weight are editable on the client record and shown on its page', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs(heightAdmin())->get(route('admin.users.edit', $client))->assertOk()->assertSee('name="height"', false)->assertSee('name="weight"', false);

    $this->actingAs(heightAdmin())->put(route('admin.users.update', $client), [
        'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => $client->email, 'account_category' => 'Individual', 'role' => 'client', 'phone' => '09171234567', 'address' => '12 Rizal St', 'height' => '181 cm', 'weight' => '77 kg',
    ]);
    expect($client->fresh()->height)->toBe('181 cm')->and($client->fresh()->weight)->toBe('77 kg');

    $this->actingAs(heightAdmin())->get(route('admin.users.show', $client))->assertOk()->assertSee('181 cm / 77 kg');
});
