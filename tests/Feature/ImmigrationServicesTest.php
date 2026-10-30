<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Support\ImmigrationServices;

test('the immigration counter lists every service, with the client-sheet services open', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $services = ImmigrationServices::all();
    expect(array_column($services, 'label'))->toBe(['ECC', 'Re Stamping Amendment', 'ACR I-Card Renewal', '13/A Spouse Visa', 'SEC 13/A Permanent',        'Visa Overstaying', 'Visa Downgrading', 'Visa Extension', 'Dual Citizenship'])->and(array_filter($services, fn ($service) => ! $service['available']))->toBe([]);

    // Every service is a card on the dashboard (no Services menu in the tab bar), opening its applications.
    $page = $this->actingAs($admin)->get(route('admin.immigration.dashboard'))->assertOk()->getContent();
    expect($page)->not->toContain('<span>Services</span>')
        ->and($page)->not->toContain('Coming soon')
        ->and($page)->not->toContain('Recognition as Filipino Citizen')->and($page)->not->toContain('Missionary Visa')
        ->and($page)->toContain('href="'.e(route('admin.client-sheets.applications', ['service' => 'visa_extension'])).'"');
});

test('the new-file page speaks for the service picked from the menu', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.client-sheets.create', ['service' => 'visa_extension']))->assertOk()
        ->assertSee('New Visa Extension File')
        ->assertSee('Extension of stay with the Bureau of Immigration.')
        ->assertSee('name="service" value="visa_extension"', false)
        ->assertDontSee('New Client Information Sheet');

    // A service that is not offered, or none at all, falls back to Visa Extension.
    $this->actingAs($admin)->get(route('admin.client-sheets.create', ['service' => 'missionary_visa']))->assertOk()->assertSee('New Visa Extension File');
    $this->actingAs($admin)->get(route('admin.client-sheets.create'))->assertOk()->assertSee('New Visa Extension File');
});

test('picking a service lists every folder: open one, search existing clients, or add a new one', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'P7654321']);

    $this->actingAs($admin)->get(route('admin.client-sheets.start', ['service' => 'visa_extension']))->assertOk()
        ->assertSee('New Visa Extension File')->assertSee('Client folders')
        ->assertSee('John Smith')->assertSee('Passport P7654321')
        ->assertSee(e(route('admin.client-sheets.edit', $sheet)), false)
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

test('there is no Cases or Services tab; a folder being worked on keeps the Counter tab marked', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John']);
    $active = fn (string $html, string $label) => (bool) preg_match('/aria-current="page"[^>]*>\s*<i data-lucide="[^"]+"[^>]*><\/i>\s*<span>'.preg_quote($label, '/').'<\/span>/', $html)
        || (bool) preg_match('/border-navy-700 text-navy-700[^"]*"[^>]*>\s*<i data-lucide="[^"]+"[^>]*><\/i>\s*<span>'.preg_quote($label, '/').'<\/span>/', $html);

    // The top bar has no Cases tab on any counter page.
    foreach ([route('admin.immigration.dashboard'), route('admin.client-sheets.applications'), route('admin.immigration-pricing.index')] as $url) {
        $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
        expect($html)->not->toContain('<span>Cases</span>')->and($html)->not->toContain('<span>Services</span>');
    }

    foreach ([route('admin.client-sheets.start'), route('admin.client-sheets.edit', $sheet)] as $url) {
        $html = $this->actingAs($admin)->get($url)->getContent();
        expect($active($html, 'Cases'))->toBeFalse()->and($active($html, 'Counter'))->toBeTrue();
    }
});

test('deleting a folder goes back to the folder list of its service', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John']);

    $this->actingAs($admin)->delete(route('admin.client-sheets.destroy', $sheet), ['service' => 'ecc'])
        ->assertRedirect(route('admin.client-sheets.applications', ['service' => 'ecc']));

    expect(ImmigrationClient::find($sheet->id))->toBeNull();
});

test('the visa validity line shows on the Visa Extension folder only, not on other services', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John', 'visa_expiry_date' => now()->addDay()->toDateString()]);
    ImmigrationPetition::create(['immigration_client_id' => $sheet->id, 'type' => 'spouse']);

    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id]))->assertOk()
        ->assertSee('of validity left');
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => '13a_spouse']))->assertOk()
        ->assertDontSee('Express only')->assertDontSee('of validity left');
});

test('the status of an open case is a dropdown in the folder header, in the preview too', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John']);
    $case = ImmigrationPetition::create(['immigration_client_id' => $sheet->id, 'type' => 'spouse', 'approval_status' => 'approved']);

    foreach ([[], ['preview' => 1]] as $extra) {
        $html = $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => '13a_spouse', 'case' => $case->id] + $extra))
            ->assertOk()->getContent();
        expect($html)->toContain(route('admin.immigration.cases.status', ['spouse', $case->id]))
            ->and($html)->toMatch('/<option value="preparing"[^>]*selected/')
            ->and($html)->toContain('Submitted');
    }

    // Changing it from there works.
    $this->actingAs($admin)->post(route('admin.immigration.cases.status', ['spouse', $case->id]), ['status' => 'for_submission'])->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('for_submission');
});
