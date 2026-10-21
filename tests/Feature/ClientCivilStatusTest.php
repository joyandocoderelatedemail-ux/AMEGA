<?php

use App\Models\ImmigrationClient;
use App\Models\User;
use App\Models\VisaApplication;

function civilAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('the client form has a civil status, saved on create and changed on edit', function () {
    $admin = civilAdmin();

    $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()
        ->assertSee('name="civil_status"', false)->assertSee('Civil Status')
        ->assertSeeInOrder(['Single', 'Married', 'Annulled', 'Separated', 'Widowed', 'Divorced']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['civil_status' => 'married']))
        ->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();
    expect($client->civil_status)->toBe('married');

    $this->actingAs($admin)->get(route('admin.users.edit', $client))->assertOk()->assertSee('value="married" selected', false);
    $this->actingAs($admin)->get(route('admin.users.show', $client))->assertOk()->assertSee('Civil Status')->assertSee('Married');

    $this->actingAs($admin)->put(route('admin.users.update', $client), [
        'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => $client->email, 'account_category' => 'Individual', 'role' => 'client',
        'phone' => $client->phone, 'address' => '12 Rizal St', 'civil_status' => 'widowed',
    ])->assertRedirect();
    expect($client->fresh()->civil_status)->toBe('widowed');
});

test('civil status is optional, and only the listed statuses are accepted', function () {
    $admin = civilAdmin();

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['civil_status' => '']))
        ->assertSessionHasNoErrors();
    expect(User::where('email', 'juan.delacruz@example.com')->firstOrFail()->civil_status)->toBeNull();

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload(['email' => 'two@example.com', 'last_name' => 'Santos', 'phone' => '09170001111', 'civil_status' => 'complicated']))
        ->assertSessionHasErrors('civil_status');
});

test('a new immigration folder for a registered client starts with their civil status', function () {
    $client = User::factory()->create(['role' => 'client', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'civil_status' => 'married']);

    $this->actingAs(civilAdmin())->get(route('admin.client-sheets.create', ['client' => $client->id, 'service' => 'visa_extension']))
        ->assertOk()->assertSee('<option value="Married" selected>Married</option>', false);
});

test('a civil status typed on the immigration sheet fills a client who has none', function () {
    $this->actingAs(civilAdmin())->post(route('admin.client-sheets.store'), [
        'service' => 'visa_extension', 'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567',
        'nationality' => 'British', 'civil_status' => 'Divorced', 'email' => 'john@example.com',
    ])->assertRedirect();

    $user = ImmigrationClient::where('passport_number', 'GB1234567')->firstOrFail()->user;
    expect($user->civil_status)->toBe('divorced');

    // A status already on the client record is not overwritten by the sheet.
    $keeps = User::factory()->create(['role' => 'client', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'civil_status' => 'single', 'email' => 'ana@example.com']);
    $this->actingAs(civilAdmin())->post(route('admin.client-sheets.store'), [
        'service' => 'visa_extension', 'last_name' => 'Cruz', 'given_name' => 'Ana', 'passport_number' => 'P777', 'nationality' => 'Filipino',
        'civil_status' => 'Married', 'email' => 'ana@example.com',
    ])->assertRedirect();
    expect($keeps->fresh()->civil_status)->toBe('single');
});

test('a visa worksheet starts from the registered client\'s civil status', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    User::factory()->create(['role' => 'client', 'first_name' => 'Jane', 'last_name' => 'Tan', 'passport_number' => 'P1234567A', 'civil_status' => 'married']);

    $file = VisaApplication::create([
        'reference' => 'VSA-9001', 'client_name' => 'Jane Tan', 'service_type' => 'visit_visa', 'destination_country' => 'Schengen (Europe)',
        'purpose' => 'tourist', 'status' => 'requirements', 'created_by' => $officer->id,
    ]);
    $applicant = $file->applicants()->create(['applicant_number' => 1, 'is_primary' => true, 'first_name' => 'Jane', 'last_name' => 'Tan', 'passport_number' => 'P1234567A']);
    $stranger = $file->applicants()->create(['applicant_number' => 2, 'first_name' => 'Nobody', 'last_name' => 'Known', 'passport_number' => 'ZZ999']);

    expect($applicant->registeredClient()?->civil_status)->toBe('married')->and($stranger->registeredClient())->toBeNull();

    $this->actingAs($officer)->get(route('visa.worksheets.edit', [$file, $applicant]))->assertOk()->assertSee('value="married" selected', false);
    $this->actingAs($officer)->get(route('visa.worksheets.edit', [$file, $stranger]))->assertOk()->assertDontSee('value="married" selected', false);
});

test('clients that already have an immigration sheet start with its civil status after the migration', function () {
    $user = User::factory()->create(['role' => 'client']);
    ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'user_id' => $user->id, 'civil_status' => 'Married']);
    $odd = User::factory()->create(['role' => 'client']);
    ImmigrationClient::create(['last_name' => 'Lee', 'given_name' => 'Soo', 'user_id' => $odd->id, 'civil_status' => 'Live-in']);

    $migration = require database_path('migrations/2026_10_20_120000_add_civil_status_to_users_table.php');
    Schema::table('users', fn ($table) => $table->dropColumn('civil_status'));
    $migration->up();

    expect($user->fresh()->civil_status)->toBe('married')->and($odd->fresh()->civil_status)->toBeNull();
});
