<?php

use App\Models\SrrvApplication;
use App\Models\User;

beforeEach(fn () => autoApproveDeskFiles());

function lookupOfficer(): User
{
    return User::factory()->create(['role' => 'srrv']);
}

function srrvRetireeFile(User $officer, array $overrides = []): SrrvApplication
{
    test()->actingAs($officer)->post(route('srrv.applications.store'), $overrides + [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Cruz', 'retiree_email' => 'ramon@example.com',
        'retiree_phone' => '09170000000', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'nationality' => 'Filipino', 'investment_amount' => 1500,
    ])->assertRedirect();

    return SrrvApplication::latest('id')->first();
}

test('the Services menu opens each active files list, and New names the folder first', function () {
    $officer = lookupOfficer();

    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()
        ->assertSee(route('srrv.services', 'application'), false)->assertSee(route('srrv.services', 'renewal'), false);

    foreach (['application' => 'SRRV Application', 'renewal' => 'ID Renewal'] as $service => $label) {
        $this->actingAs($officer)->get(route('srrv.start', $service))->assertOk()
            ->assertSee('Name the folder')->assertSee($label)->assertSee('Add client &amp; create folder', false)
            ->assertSee(route('srrv.start.store', $service), false);
    }

    $this->actingAs($officer)->get('/srrv/start/other')->assertNotFound();
});

test('the search lists open SRRV files and clients, each client starting a filled form', function () {
    $officer = lookupOfficer();
    $file = srrvRetireeFile($officer);
    // Saving the file added Ramon to the client list.
    $client = User::where('role', 'client')->where('email', 'ramon@example.com')->firstOrFail();

    $response = $this->actingAs($officer)->getJson(route('srrv.clients.search', ['q' => 'Ramon', 'service' => 'renewal']))->assertOk();

    expect($response->json('files.0.url'))->toBe(route('srrv.applications.show', $file))
        ->and($response->json('files.0.meta'))->toContain($file->reference)
        ->and($response->json('clients.0.id'))->toBe($client->id)
        ->and($response->json('clients.0.url'))->toBe(route('srrv.renewals.create', ['client' => $client->id]));

    // Another officer's files stay hidden; staff accounts never show; a short term returns nothing.
    expect($this->actingAs(lookupOfficer())->getJson(route('srrv.clients.search', ['q' => 'Ramon']))->json('files'))->toBe([]);
    User::factory()->create(['role' => 'agent', 'name' => 'Ramon Staff']);
    expect($this->actingAs($officer)->getJson(route('srrv.clients.search', ['q' => 'Ramon']))->json('clients'))->toHaveCount(1);
    expect($this->actingAs($officer)->getJson(route('srrv.clients.search', ['q' => 'R']))->json('clients'))->toBe([]);
});

test('a form opened for a client is filled from their record', function () {
    $client = User::factory()->create([
        'role' => 'client', 'name' => 'John Smith', 'first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@example.com',
        'phone' => '09171234567', 'nationality' => 'British', 'date_of_birth' => '1960-03-14', 'gender' => 'male', 'civil_status' => 'married',
        'height' => '178 cm', 'weight' => '80 kg', 'passport_number' => 'GB1234567', 'passport_expiry' => '2031-01-01', 'address' => '12 Rizal St, Makati',
    ]);

    $this->actingAs(lookupOfficer())->get(route('srrv.applications.create', ['client' => $client->id]))->assertOk()
        ->assertSee('value="John Smith"', false)->assertSee('value="john.smith@example.com"', false)->assertSee('value="09171234567"', false)
        ->assertSee('value="1960-03-14"', false)->assertSee('value="178 cm"', false)->assertSee('value="GB1234567"', false)
        ->assertSee('value="2031-01-01"', false)->assertSee('12 Rizal St, Makati');

    // A staff account's id fills nothing.
    $staff = User::factory()->create(['role' => 'agent', 'name' => 'Staff Person']);
    $this->actingAs(lookupOfficer())->get(route('srrv.applications.create', ['client' => $staff->id]))->assertOk()->assertDontSee('value="Staff Person"', false);
});

test('a made-up email is not filled, and a renewal brings the card number from their SRRV file', function () {
    $officer = lookupOfficer();
    $client = User::factory()->create(['role' => 'client', 'name' => 'No Mail', 'email' => 'nomail.'.uniqid().User::PLACEHOLDER_EMAIL_DOMAIN, 'phone' => '09179998888']);
    srrvRetireeFile($officer, ['retiree_name' => 'No Mail', 'retiree_email' => null, 'retiree_phone' => '09179998888', 'srrv_card_number' => 'SRRV-12345']);

    $this->actingAs($officer)->get(route('srrv.renewals.create', ['client' => $client->id]))->assertOk()
        ->assertSee('value="SRRV-12345"', false)->assertDontSee($client->email);
});

test('a new client is added to the client list, then the form opens filled', function () {
    $officer = lookupOfficer();

    $this->actingAs($officer)->post(route('srrv.start.store', 'application'), [
        'first_name' => 'Hana', 'last_name' => 'Tanaka', 'email' => 'hana.tanaka@example.com', 'phone' => '09175550123', 'nationality' => 'Japanese',
    ])->assertRedirect(route('srrv.applications.create', ['client' => $client = User::where('email', 'hana.tanaka@example.com')->value('id')]));

    expect(User::find($client)->role)->toBe('client');
    $this->actingAs($officer)->get(route('srrv.applications.create', ['client' => $client]))
        ->assertSee('value="Hana Tanaka"', false)->assertSee('value="Japanese"', false);

    $this->actingAs($officer)->from(route('srrv.start', 'application'))->post(route('srrv.start.store', 'application'), ['first_name' => 'No', 'no_email' => 1, 'no_phone' => 1])
        ->assertRedirect(route('srrv.start', 'application'))->assertSessionHasErrors(['last_name', 'nationality']);
});

test('only the SRRV desk (and admins) can name SRRV folders', function () {
    User::factory()->create(['role' => 'client', 'name' => 'John Smith']);
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->getJson(route('srrv.clients.search', ['q' => 'smith']))->assertForbidden();
    $this->actingAs($ticketing)->get(route('srrv.start', 'application'))->assertRedirect();
    $this->actingAs($ticketing)->post(route('srrv.start.store', 'application'), [
        'first_name' => 'X', 'last_name' => 'Y', 'no_email' => 1, 'no_phone' => 1, 'nationality' => 'British',
    ])->assertRedirect();
    expect(User::where('last_name', 'Y')->exists())->toBeFalse();

    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson(route('srrv.clients.search', ['q' => 'smith']))->assertOk()->assertJsonCount(1, 'clients');
});
