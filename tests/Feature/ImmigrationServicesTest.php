<?php

use App\Models\ImmigrationClient;
use App\Models\User;
use App\Support\ImmigrationServices;

test('the immigration counter lists every service, with the client-sheet services open', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $services = ImmigrationServices::all();
    expect(array_column($services, 'label'))->toBe([
        'ECC', 'Re Stamping Amendment', 'Permanent Sec 13/A Series', 'SEC 13/A Spouse Visa Application', 'SEC 13/A Permanent',
        'Visa Application', 'Visa Overstaying', 'Visa Downgrading', 'Visa Extension', 'Dual Citizenship', 'Missionary Visa',
        'Application for Recognition as Filipino Citizen',
    ])->and(array_column(array_filter($services, fn ($service) => $service['available']), 'label'))->toBe(['Re Stamping Amendment', 'Permanent Sec 13/A Series', 'SEC 13/A Spouse Visa Application', 'SEC 13/A Permanent', 'Visa Overstaying', 'Visa Extension', 'Dual Citizenship']);

    // The Services menu sits in the tab bar on every counter page, the dashboard included (no grid).
    foreach ([route('admin.immigration.dashboard'), route('admin.client-sheets.index')] as $url) {
        $page = $this->actingAs($admin)->get($url)->assertOk()->getContent();
        expect($page)->toContain('aria-haspopup="menu"')
            ->and(substr_count($page, 'Coming soon'))->toBe(5)
            ->and($page)->toContain('Application for Recognition as Filipino Citizen')
            ->and($page)->toContain('Extension of stay, filed with the BI')
            // Choosing Visa Extension opens a new client sheet straight away.
            ->and($page)->toContain('href="'.e(route('admin.client-sheets.start', ['service' => 'visa_extension'])).'" role="menuitem"')
            ->and($page)->not->toContain('Visa Extension is open. The others are coming soon.');
    }
});

test('the new-file page speaks for the service picked from the menu', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.client-sheets.create', ['service' => 'visa_extension']))->assertOk()
        ->assertSee('New Visa Extension File')
        ->assertSee('Extension of stay with the Bureau of Immigration.')
        ->assertSee('name="service" value="visa_extension"', false)
        ->assertDontSee('New Client Information Sheet');

    // A service that is not running yet, or none at all, falls back to Visa Extension.
    $this->actingAs($admin)->get(route('admin.client-sheets.create', ['service' => 'ecc']))->assertOk()->assertSee('New Visa Extension File');
    $this->actingAs($admin)->get(route('admin.client-sheets.create'))->assertOk()->assertSee('New Visa Extension File');
});

test('picking a service first names the folder: search existing clients, or add a new one', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.client-sheets.start', ['service' => 'visa_extension']))->assertOk()
        ->assertSee('New Visa Extension File')->assertSee('Name the folder')
        ->assertSee('Search existing clients')->assertSee('Add a new client')
        ->assertSee('First / Given Name')->assertSee('No email')->assertSee('Citizenship / Nationality')
        ->assertDontSee('Residential Address');
});

test('the folder search finds existing folders and registered clients, and starts a folder for the service', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $registered = User::factory()->create(['role' => 'client', 'name' => 'Maria Reyes', 'first_name' => 'Maria', 'last_name' => 'Reyes', 'email' => 'maria@example.com', 'nationality' => 'Japan']);
    ImmigrationClient::create(['last_name' => 'Reyes', 'given_name' => 'Jose', 'passport_number' => 'P1234567', 'nationality' => 'Korea']);

    $results = $this->actingAs($admin)->getJson(route('admin.client-sheets.lookup', ['service' => 'visa_extension', 'q' => 'Reyes']))->assertOk();

    expect(collect($results->json('sheets'))->pluck('name'))->toContain('Jose Reyes')
        ->and(collect($results->json('clients'))->pluck('url'))->toContain(route('admin.client-sheets.create', ['client' => $registered->id, 'service' => 'visa_extension']));

    // A registered client's folder opens with step 1 filled from their profile.
    $this->actingAs($admin)->get(route('admin.client-sheets.create', ['client' => $registered->id, 'service' => 'visa_extension']))->assertOk()
        ->assertSee('value="Maria"', false)->assertSee('value="Reyes"', false)->assertSee('value="maria@example.com"', false);
});

test('a new client is added to the client list, refused when already registered, and fills step 1', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $new = fn (array $data) => $this->actingAs($admin)->post(route('admin.client-sheets.start.store'), $data + [
        'service' => 'visa_extension', 'first_name' => 'Juan', 'middle_name' => 'Dela Cruz', 'last_name' => 'Santos',
        'email' => 'juan@example.com', 'phone' => '+63 912 345 6789', 'nationality' => 'Philippines',
    ]);

    $response = $new([]);
    $client = User::where('email', 'juan@example.com')->firstOrFail();
    $response->assertRedirect(route('admin.client-sheets.create', ['client' => $client->id, 'service' => 'visa_extension']));
    expect($client->role)->toBe('client')->and($client->first_name)->toBe('Juan')->and($client->nationality)->toBe('Philippines');

    $this->actingAs($admin)->get(route('admin.client-sheets.create', ['client' => $client->id, 'service' => 'visa_extension']))
        ->assertSee('value="Juan"', false)->assertSee('value="Santos"', false)->assertSee('value="+63 912 345 6789"', false);

    // The same person again is refused, by name and by email.
    $new([])->assertSessionHasErrors(['last_name', 'email']);
    expect(User::where('role', 'client')->where('last_name', 'Santos')->count())->toBe(1);

    // No email and no number are allowed when the client has none.
    $new(['first_name' => 'Ana', 'last_name' => 'Lopez', 'email' => '', 'phone' => '', 'no_email' => 1, 'no_phone' => 1])->assertSessionHasNoErrors();
    expect(User::where('last_name', 'Lopez')->first()->phone)->toBeNull();

    // The duplicate warning the form shows while typing.
    $this->actingAs($admin)->getJson(route('admin.client-sheets.check-duplicate', ['field' => 'email', 'value' => 'juan@example.com']))
        ->assertOk()->assertJsonPath('field', 'email')->assertJson(fn ($json) => $json->whereType('message', 'string')->etc());
});

test('the Client Folders tab is lit only on the folder list; open folders sit under Services', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John']);
    $active = fn (string $html, string $label) => (bool) preg_match('/aria-current="page"[^>]*>\s*<i data-lucide="[^"]+"[^>]*><\/i>\s*<span>'.preg_quote($label, '/').'<\/span>/', $html)
        || (bool) preg_match('/border-navy-700 text-navy-700[^"]*"[^>]*>\s*<i data-lucide="[^"]+"[^>]*><\/i>\s*<span>'.preg_quote($label, '/').'<\/span>/', $html);

    $list = $this->actingAs($admin)->get(route('admin.client-sheets.index'))->getContent();
    expect($active($list, 'Client Folders'))->toBeTrue()->and($active($list, 'Services'))->toBeFalse();

    foreach ([route('admin.client-sheets.start'), route('admin.client-sheets.edit', $sheet)] as $url) {
        $html = $this->actingAs($admin)->get($url)->getContent();
        expect($active($html, 'Client Folders'))->toBeFalse()->and($active($html, 'Services'))->toBeTrue();
    }

    // Adding from the list goes through "Name the folder".
    expect($list)->toContain('href="'.route('admin.client-sheets.start').'"');
});
