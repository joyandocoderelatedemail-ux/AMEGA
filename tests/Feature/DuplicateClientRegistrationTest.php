<?php

use App\Models\User;

require_once __DIR__.'/ClientTravelProfileTest.php';

function existingClient(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'client',
        'name' => 'Juan Reyes Dela Cruz',
        'first_name' => 'Juan',
        'middle_name' => 'Reyes',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.delacruz@example.com',
        'phone' => '0917 123 4567',
    ], $overrides));
}

test('a client email already on file cannot be registered again', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->from(route('admin.users.create'))
        ->post(route('admin.users.store'), clientRegistrationPayload([
            'first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Santos',
            'email' => 'JUAN.DelaCruz@example.com', 'phone' => '0999 888 7777',
        ]))
        ->assertRedirect(route('admin.users.create'))
        ->assertSessionHasErrors(['email' => 'This email address is already registered to Juan Reyes Dela Cruz (juan.delacruz@example.com).']);

    expect(User::where('first_name', 'Maria')->exists())->toBeFalse();
});

test('a contact number already on file cannot be registered again, however it is written', function (string $typed) {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Santos',
        'email' => 'maria@example.com', 'phone' => $typed,
    ]))->assertSessionHasErrors('phone');

    expect(User::where('email', 'maria@example.com')->exists())->toBeFalse();
})->with(['0917 123 4567', '09171234567', '+63 917-123-4567', '(0917) 123.4567']);

test('a different contact number is accepted', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Santos',
        'email' => 'maria@example.com', 'phone' => '0917 123 4568',
    ]))->assertSessionHasNoErrors();

    expect(User::where('email', 'maria@example.com')->exists())->toBeTrue();
});

test('a client with the same name cannot be registered again', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'first_name' => ' juan ', 'middle_name' => 'reyes', 'last_name' => 'DELA CRUZ',
        'email' => 'someone.else@example.com', 'phone' => '0999 888 7777',
    ]))->assertSessionHasErrors('last_name');

    expect(User::where('email', 'someone.else@example.com')->exists())->toBeFalse();
});

test('the same first and last name is refused even when a middle name is left out', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Dela Cruz',
        'email' => 'someone.else@example.com', 'phone' => '0999 888 7777',
    ]))->assertSessionHasErrors('last_name');
});

test('two different middle names are two different people', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'first_name' => 'Juan', 'middle_name' => 'Bautista', 'last_name' => 'Dela Cruz',
        'email' => 'juan.b@example.com', 'phone' => '0999 888 7777',
    ]))->assertSessionHasNoErrors();

    expect(User::where('email', 'juan.b@example.com')->exists())->toBeTrue();
});

test('a client saved with only a full name is matched by that name', function () {
    User::factory()->create(['role' => 'client', 'name' => 'Ana Reyes', 'first_name' => null, 'last_name' => null, 'email' => 'ana@example.com', 'phone' => null]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'first_name' => 'Ana', 'middle_name' => '', 'last_name' => 'Reyes',
        'email' => 'ana.new@example.com', 'phone' => '0999 888 7777',
    ]))->assertSessionHasErrors('last_name');
});

test('the admin form shows the duplicate as a notification and marks the fields', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->from(route('admin.users.create'))
        ->post(route('admin.users.store'), clientRegistrationPayload(['phone' => '+63 917 123 4567']));

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('The client was not saved')
        ->assertSee('This email address is already registered to')
        ->assertSee('This contact number is already registered to')
        ->assertSee('A client with this name is already registered');
});

test('the ticketing desk turns away a duplicate client too', function () {
    existingClient();
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.clients.store'), clientRegistrationPayload(['phone' => '+63 917 123 4567']))
        ->assertSessionHasErrors(['email', 'phone', 'last_name']);
});

test('typing an email already on file gets a warning naming the client', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson(route('admin.users.check-duplicate', ['field' => 'email', 'value' => 'Juan.DelaCruz@example.com']))
        ->assertOk()
        ->assertJson(['field' => 'email', 'message' => 'This email address is already registered to Juan Reyes Dela Cruz (juan.delacruz@example.com).']);
});

test('typing a contact number already on file gets a warning, and a new one does not', function () {
    existingClient();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson(route('admin.users.check-duplicate', ['field' => 'phone', 'value' => '+63 917 123 4567']))
        ->assertJsonPath('message', 'This contact number is already registered to Juan Reyes Dela Cruz (juan.delacruz@example.com).');

    $this->actingAs($admin)->getJson(route('admin.users.check-duplicate', ['field' => 'phone', 'value' => '0999 000 1111']))
        ->assertOk()
        ->assertJsonPath('message', null);
});

test('typing a name already on file gets a warning', function () {
    existingClient();
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->getJson(route('ticketing.clients.check-duplicate', [
        'field' => 'name', 'first_name' => 'juan', 'middle_name' => '', 'last_name' => 'dela cruz',
    ]))->assertJsonPath('message', 'A client with this name is already registered: Juan Reyes Dela Cruz (juan.delacruz@example.com).');

    $this->actingAs($officer)->getJson(route('ticketing.clients.check-duplicate', [
        'field' => 'name', 'first_name' => 'Juan', 'middle_name' => 'Bautista', 'last_name' => 'Dela Cruz',
    ]))->assertJsonPath('message', null);
});

test('the duplicate warning lookup is for signed-in staff only', function () {
    existingClient();

    $this->getJson(route('admin.users.check-duplicate', ['field' => 'email', 'value' => 'juan.delacruz@example.com']))
        ->assertUnauthorized();

    $client = User::factory()->create(['role' => 'client']);
    $this->actingAs($client)->getJson(route('admin.users.check-duplicate', ['field' => 'email', 'value' => 'juan.delacruz@example.com']))
        ->assertForbidden();
});

test('both registration forms carry the live warning', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertSee('data-duplicate-warning="email"', false)
        ->assertSee('data-duplicate-warning="phone"', false)
        ->assertSee('data-duplicate-warning="name"', false)
        ->assertSee(str_replace('/', '\/', route('admin.users.check-duplicate')), false);

    $this->actingAs($officer)->get(route('ticketing.clients.create'))
        ->assertSee(str_replace('/', '\/', route('ticketing.clients.check-duplicate')), false);
});
