<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use App\Models\ImmigrationClientExtension;
use App\Models\User;
use App\Services\ClientAccountService;
use App\Support\DocumentStorage;
use Database\Seeders\ImmigrationPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('guests cannot reach the counter', function () {
    $this->get('/admin/client-sheets')->assertRedirect('/login');
});

test('the counter screen loads with a client search box', function () {
    $response = $this->actingAs(admin())->get('/admin/client-sheets');

    $response->assertStatus(200);
    $response->assertSee('Look up a client by name or passport');
});

test('clients can be found by name, email or mobile as well as passport', function (string $term) {
    ImmigrationClient::factory()->create([
        'given_name' => 'Chin Chin',
        'last_name' => 'Santos',
        'email' => 'chinchin@example.com',
        'mobile_number' => '09171234567',
        'passport_number' => 'P7654321A',
    ]);
    ImmigrationClient::factory()->create(['given_name' => 'Other', 'last_name' => 'Person', 'email' => 'other@example.com', 'mobile_number' => '09000000000', 'passport_number' => 'ZZ9999999']);

    $this->actingAs(admin())->get('/admin/client-sheets?passport='.urlencode($term))
        ->assertOk()
        ->assertSee('Chin Chin Santos')
        ->assertDontSee('ZZ9999999');
})->with(['chin', 'CHIN', 'chin santos', 'santos chin', 'chinchin@example', '0917123', 'p765 4321']);

test('while typing, only the results are sent back', function () {
    ImmigrationClient::factory()->create(['given_name' => 'Chin Chin', 'last_name' => 'Santos']);

    $this->actingAs(admin())
        ->get('/admin/client-sheets?passport=chin', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Chin Chin Santos')
        ->assertDontSee('Look up a client by name or passport');
});

test('a name with no match starts the new sheet with that name', function () {
    $this->actingAs(admin())->get('/admin/client-sheets?passport='.urlencode('Maria Clara'))
        ->assertOk()
        ->assertSee(route('admin.client-sheets.create', ['name' => 'Maria Clara']), false);

    $this->actingAs(admin())->get(route('admin.client-sheets.create', ['name' => 'Maria Clara']))
        ->assertOk()
        ->assertSee('value="Maria"', false)
        ->assertSee('value="Clara"', false);
});

test('searching a known passport finds the client', function () {
    $client = ImmigrationClient::factory()->create([
        'last_name' => 'Nakamura',
        'given_name' => 'Kenji',
        'passport_number' => 'TR1234567',
    ]);
    ImmigrationClient::factory()->create(['last_name' => 'Other', 'passport_number' => 'ZZ9999999']);

    $response = $this->actingAs(admin())->get('/admin/client-sheets?passport=TR1234567');

    $response->assertStatus(200);
    $response->assertSee('Kenji Nakamura');
    $response->assertDontSee('ZZ9999999');
    $response->assertSee(route('admin.client-sheets.print', $client), false);
});

test('passport search ignores case, spaces and dashes', function () {
    ImmigrationClient::factory()->create(['last_name' => 'Rossi', 'passport_number' => 'YA-123 456']);

    $this->actingAs(admin())->get('/admin/client-sheets?passport=ya123456')
        ->assertStatus(200)
        ->assertSee('Rossi');
});

test('an unknown passport offers the new-client path', function () {
    $response = $this->actingAs(admin())->get('/admin/client-sheets?passport=UNKNOWN123');

    $response->assertStatus(200);
    $response->assertSee('No client on file');
    $response->assertSee('Print blank form');
    $response->assertSee('Create client record');
});

test('the new client form is prefilled with the searched passport', function () {
    $this->actingAs(admin())->get('/admin/client-sheets/create?passport=NEWPASS99')
        ->assertStatus(200)
        ->assertSee('NEWPASS99', false);
});

test('agent can create a client sheet with documents and ledger rows', function () {
    $response = $this->actingAs(admin())->post('/admin/client-sheets', [
        'last_name' => 'Bituonon',
        'given_name' => 'Maria',
        'address' => 'Balibago, Angeles City',
        'email' => 'maria@example.com',
        'mobile_number' => '+63 917 000 1111',
        'height' => '165 cm',
        'weight' => '58 kg',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'date_of_birth' => '1990-04-12',
        'passport_number' => 'PH8877665',
        'documents' => [
            'acr_icard' => ['reference_number' => 'ACR-99881', 'date_paid' => '2026-02-10', 'ssrn_number' => 'SSRN-4471', 'validity' => 'Feb 2027'],
            'crtv' => ['reference_number' => '', 'date_paid' => '', 'ssrn_number' => '', 'validity' => ''],
            'annual_report' => ['reference_number' => 'AR-2026', 'date_paid' => '', 'ssrn_number' => '', 'validity' => ''],
        ],
        'extensions' => [
            1 => ['soa_or_number' => 'OR-1001', 'extension_date' => '2026-01-15', 'details' => '29 days visa waiver', 'amount_paid' => '2930', 'annual_report' => '', 'refund' => ''],
            2 => ['soa_or_number' => '', 'extension_date' => '', 'details' => '', 'amount_paid' => '', 'annual_report' => '', 'refund' => ''],
        ],
    ]);

    $client = ImmigrationClient::firstWhere('passport_number', 'PH8877665');
    $response->assertRedirect(route('admin.client-sheets.edit', $client));

    $this->assertDatabaseHas('immigration_clients', [
        'last_name' => 'Bituonon',
        'given_name' => 'Maria',
        'height' => '165 cm',
    ]);

    // Blank grid columns and blank ledger rows are not stored
    expect($client->documents()->count())->toBe(2);
    expect($client->extensions()->count())->toBe(1);

    $this->assertDatabaseHas('immigration_client_documents', [
        'document_type' => 'acr_icard',
        'ssrn_number' => 'SSRN-4471',
    ]);
    $this->assertDatabaseHas('immigration_client_extensions', [
        'sequence' => 1,
        'soa_or_number' => 'OR-1001',
        'amount_paid' => 2930,
    ]);
});

test('creating a client sheet requires both names', function () {
    $this->actingAs(admin())
        ->post('/admin/client-sheets', ['last_name' => '', 'given_name' => ''])
        ->assertSessionHasErrors(['last_name', 'given_name']);
});

test('agent can update a sheet, and clearing a row removes it', function () {
    $client = ImmigrationClient::factory()->create(['last_name' => 'Original']);
    ImmigrationClientDocument::factory()->for($client, 'client')->create(['document_type' => 'crtv']);
    ImmigrationClientExtension::factory()->for($client, 'client')->create(['sequence' => 1]);

    $this->actingAs(admin())->get("/admin/client-sheets/{$client->id}/edit")->assertStatus(200);

    $this->actingAs(admin())->put("/admin/client-sheets/{$client->id}", [
        'last_name' => 'Updated',
        'given_name' => $client->given_name,
        'documents' => [
            'crtv' => ['reference_number' => '', 'date_paid' => '', 'ssrn_number' => '', 'validity' => ''],
        ],
        'extensions' => [
            1 => ['soa_or_number' => '', 'extension_date' => '', 'details' => '', 'amount_paid' => '', 'annual_report' => '', 'refund' => ''],
        ],
    ])->assertRedirect(route('admin.client-sheets.edit', $client));

    $this->assertDatabaseHas('immigration_clients', ['id' => $client->id, 'last_name' => 'Updated']);
    expect($client->documents()->count())->toBe(0);
    expect($client->extensions()->count())->toBe(0);
});

test('the printed sheet carries the client details and ledger history', function () {
    $client = ImmigrationClient::factory()->create([
        'last_name' => 'Yamada',
        'given_name' => 'Haruki',
        'nationality' => 'Japanese',
        'height' => '172 cm',
    ]);
    ImmigrationClientDocument::factory()->for($client, 'client')->create([
        'document_type' => 'acr_icard',
        'ssrn_number' => 'SSRN-778899',
    ]);
    ImmigrationClientExtension::factory()->for($client, 'client')->create([
        'sequence' => 1,
        'details' => 'Visa waiver, 29 days',
        'amount_paid' => 2930,
    ]);

    $response = $this->actingAs(admin())->get("/admin/client-sheets/{$client->id}/print");

    $response->assertStatus(200);
    $response->assertSee('CLIENT INFORMATION SHEET');
    $response->assertSee('Yamada');
    $response->assertSee('Haruki');
    $response->assertSee('172 cm');
    $response->assertSee('SSRN-778899');
    $response->assertSee('Visa waiver, 29 days');
    $response->assertSee('2,930.00');
    // All ten ledger positions are printed, filled or not
    $response->assertSee('10<sup>th</sup>', false);
});

test('the blank sheet prints every field empty', function () {
    ImmigrationClient::factory()->create(['last_name' => 'ShouldNotAppear']);

    $response = $this->actingAs(admin())->get('/admin/client-sheets/blank');

    $response->assertStatus(200);
    $response->assertSee('CLIENT INFORMATION SHEET');
    $response->assertSee('PERSONAL INFORMATION');
    $response->assertSee('VISA EXTENSION INFORMATION');
    $response->assertDontSee('ShouldNotAppear');
});

test('deleting a client removes their documents and ledger', function () {
    $client = ImmigrationClient::factory()->create();
    ImmigrationClientDocument::factory()->for($client, 'client')->create();
    ImmigrationClientExtension::factory()->for($client, 'client')->create();

    $this->actingAs(admin())->delete("/admin/client-sheets/{$client->id}")
        ->assertRedirect(route('admin.client-sheets.index'));

    expect(ImmigrationClientDocument::count())->toBe(0);
    expect(ImmigrationClientExtension::count())->toBe(0);
});

test('the next extension number follows the ledger', function () {
    $client = ImmigrationClient::factory()->create();
    expect($client->next_extension_number)->toBe(1);

    ImmigrationClientExtension::factory()->for($client, 'client')->create(['sequence' => 1]);
    ImmigrationClientExtension::factory()->for($client, 'client')->create(['sequence' => 2]);

    expect($client->fresh()->next_extension_number)->toBe(3);
});

test('ordinal suffixes match the printed form', function () {
    expect(ImmigrationClientExtension::ordinalSuffix(1))->toBe('st')
        ->and(ImmigrationClientExtension::ordinalSuffix(2))->toBe('nd')
        ->and(ImmigrationClientExtension::ordinalSuffix(3))->toBe('rd')
        ->and(ImmigrationClientExtension::ordinalSuffix(4))->toBe('th')
        ->and(ImmigrationClientExtension::ordinalSuffix(10))->toBe('th');
});

test('client sheet actions are written to the activity log', function () {
    $staff = admin();
    $client = ImmigrationClient::factory()->create();

    $this->actingAs($staff)->get("/admin/client-sheets/{$client->id}/print");

    $this->assertDatabaseHas('activity_logs', [
        'module' => 'Client Sheets',
        'action' => 'PRINT',
        'user_id' => $staff->id,
    ]);
});

test('agent can mark a sheet as expired and with penalty', function () {
    $client = ImmigrationClient::factory()->create();

    $this->actingAs(admin())->put("/admin/client-sheets/{$client->id}", [
        'last_name' => $client->last_name,
        'given_name' => $client->given_name,
        'visa_expiry_date' => now()->subDays(5)->format('Y-m-d'),
        'is_expired' => '1',
        'has_penalty' => '1',
        'status_note' => 'Overstayed by 5 days',
    ])->assertRedirect(route('admin.client-sheets.edit', $client));

    $client->refresh();
    expect($client->is_expired)->toBeTrue()
        ->and($client->has_penalty)->toBeTrue()
        ->and($client->status_note)->toBe('Overstayed by 5 days')
        ->and($client->isFlagged())->toBeTrue();
});

test('unticking the marks clears them', function () {
    $client = ImmigrationClient::factory()->create(['is_expired' => true, 'has_penalty' => true]);

    $this->actingAs(admin())->put("/admin/client-sheets/{$client->id}", [
        'last_name' => $client->last_name,
        'given_name' => $client->given_name,
    ]);

    $client->refresh();
    expect($client->is_expired)->toBeFalse()->and($client->has_penalty)->toBeFalse();
});

test('the marks are stamped on the printed sheet', function () {
    $client = ImmigrationClient::factory()->create([
        'is_expired' => true,
        'has_penalty' => true,
        'status_note' => 'Penalty settled on release',
    ]);

    $response = $this->actingAs(admin())->get("/admin/client-sheets/{$client->id}/print");

    $response->assertSee('VISA EXPIRED');
    $response->assertSee('WITH PENALTY');
    $response->assertSee('Penalty settled on release');
});

test('an unmarked sheet prints without stamps', function () {
    $client = ImmigrationClient::factory()->create(['visa_expiry_date' => now()->addMonths(2)]);

    $response = $this->actingAs(admin())->get("/admin/client-sheets/{$client->id}/print");

    $response->assertDontSee('VISA EXPIRED');
    $response->assertDontSee('WITH PENALTY');
});

test('the validity band follows the pricing sheet thresholds', function () {
    $regular = ImmigrationClient::factory()->create(['visa_expiry_date' => now()->addDays(20)]);
    $express = ImmigrationClient::factory()->create(['visa_expiry_date' => now()->addDays(3)]);
    $expired = ImmigrationClient::factory()->create(['visa_expiry_date' => now()->subDays(2)]);
    $unknown = ImmigrationClient::factory()->create(['visa_expiry_date' => null]);

    expect($regular->validity_band['key'])->toBe('regular')
        ->and($express->validity_band['key'])->toBe('express')
        ->and($expired->validity_band['key'])->toBe('expired')
        ->and($unknown->validity_band)->toBeNull();
});

test('a lapsed expiry date flags the sheet even when nobody ticked the box', function () {
    $client = ImmigrationClient::factory()->create([
        'visa_expiry_date' => now()->subDay(),
        'is_expired' => false,
        'has_penalty' => false,
    ]);

    expect($client->isFlagged())->toBeTrue()
        ->and($client->status_marks)->toContain('VISA EXPIRED');
});

test('the counter can list only flagged sheets', function () {
    ImmigrationClient::factory()->create(['last_name' => 'CleanClient', 'visa_expiry_date' => now()->addMonth()]);
    ImmigrationClient::factory()->create(['last_name' => 'PenaltyClient', 'has_penalty' => true]);

    $response = $this->actingAs(admin())->get('/admin/client-sheets?flagged=1');

    $response->assertStatus(200);
    $response->assertSee('PenaltyClient');
    $response->assertDontSee('CleanClient');
});

test('an immigration-only agent lands on the counter dashboard', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);

    expect($agent->isImmigrationAgent())->toBeTrue()
        ->and($agent->staffHomeRoute())->toBe('admin.immigration.dashboard');
});

test('a general agent still lands on the main admin dashboard', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings', 'immigration']]);

    expect($agent->isImmigrationAgent())->toBeFalse()
        ->and($agent->staffHomeRoute())->toBe('admin.dashboard');
});

test('an admin lands on the main admin dashboard', function () {
    expect(admin()->staffHomeRoute())->toBe('admin.dashboard');
});

test('the counter dashboard shows only immigration reporting', function () {
    ImmigrationClient::factory()->create(['visa_expiry_date' => now()->addDays(3)]);
    ImmigrationClient::factory()->create(['last_name' => 'Penalised', 'has_penalty' => true]);

    $response = $this->actingAs(admin())->get('/admin/immigration');

    $response->assertStatus(200);
    $response->assertSee('Clients on file');
    $response->assertSee('Expiring in 7 days');
    $response->assertSee('Expired / penalty');
    $response->assertSee('Extensions this month');
    // The two buttons through to the rest of the counter
    $response->assertSee(route('admin.client-sheets.index'), false);
    $response->assertSee(route('admin.immigration-pricing.index'), false);
});

test('counter screens render without the main admin sidebar', function () {
    $client = ImmigrationClient::factory()->create();

    foreach (['/admin/immigration', '/admin/client-sheets', "/admin/client-sheets/{$client->id}/edit"] as $url) {
        $response = $this->actingAs(admin())->get($url);
        $response->assertStatus(200);
        $response->assertSee('Immigration Counter');
        // Main admin sidebar entries must not appear
        $response->assertDontSee('Real-Time Audit Logs');
        $response->assertDontSee('Travel Packages');
    }
});

test('an agent without the immigration permission is turned away', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);

    $this->actingAs($agent)->get('/admin/immigration')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($agent)->get('/admin/client-sheets')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($agent)->get('/admin/immigration-pricing')->assertRedirect(route('admin.dashboard'));
});

test('an immigration agent can reach the whole counter', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);

    $this->actingAs($agent)->get('/admin/immigration')->assertStatus(200);
    $this->actingAs($agent)->get('/admin/client-sheets')->assertStatus(200);
    $this->actingAs($agent)->get('/admin/immigration-pricing')->assertStatus(200);
});

test('no counter page renders a top nav bar', function () {
    $client = ImmigrationClient::factory()->create();

    $urls = [
        '/admin/immigration',
        '/admin/client-sheets',
        '/admin/immigration-pricing',
        '/admin/immigration-categories',
        "/admin/client-sheets/{$client->id}/edit",
    ];

    foreach ($urls as $url) {
        $response = $this->actingAs(admin())->get($url);
        $response->assertStatus(200);
        $response->assertDontSee('sticky top-0 z-40', false);
    }
});

test('the hub keeps the section tabs and the Services menu, like the visa counter', function () {
    $response = $this->actingAs(admin())->get('/admin/immigration');

    $response->assertStatus(200);
    $response->assertSee('aria-label="Counter sections"', false)->assertSee('aria-haspopup="menu"', false);
    // Every destination is still reachable from the hub
    $response->assertSee(route('admin.client-sheets.index'), false);
    $response->assertSee(route('admin.immigration-pricing.index'), false);
    $response->assertSee(route('admin.immigration-categories.index'), false);
    $response->assertSee(route('admin.client-sheets.blank'), false);
    $response->assertSee(route('admin.client-sheets.start'), false);
});

test('sub-pages get the segmented section switcher with the current one marked', function () {
    $pages = [
        '/admin/client-sheets' => route('admin.client-sheets.index'),
        '/admin/immigration-pricing' => route('admin.immigration-pricing.index'),
        '/admin/immigration-categories' => route('admin.immigration-categories.index'),
    ];

    foreach ($pages as $url => $currentRoute) {
        $response = $this->actingAs(admin())->get($url);

        $response->assertStatus(200);
        $response->assertSee('aria-label="Counter sections"', false);
        $response->assertSee('aria-current="page"', false);
        // Every other section stays one click away
        $response->assertSee(route('admin.immigration.dashboard'), false);
        $response->assertSee($currentRoute, false);
    }
});

test('a registered client without a sheet shows up in the search', function () {
    $client = User::factory()->create(['role' => 'client', 'name' => 'Chin Chin Chin', 'first_name' => 'Chin Chin', 'last_name' => 'Chin']);
    $withSheet = User::factory()->create(['role' => 'client', 'name' => 'Chin Sheeted']);
    ImmigrationClient::factory()->create(['user_id' => $withSheet->id, 'given_name' => 'Chin', 'last_name' => 'Sheeted']);
    User::factory()->create(['role' => 'agent', 'name' => 'Chin Agent']);

    $this->actingAs(admin())->get('/admin/client-sheets?passport=chin')
        ->assertOk()
        ->assertSee('Registered clients without a client sheet')
        ->assertSee(route('admin.client-sheets.create', ['client' => $client->id]), false)
        ->assertDontSee(route('admin.client-sheets.create', ['client' => $withSheet->id]), false)
        ->assertDontSee('Chin Agent')
        ->assertDontSee('No client on file');
});

test('a sheet started from a registered client is filled from their profile', function () {
    $client = User::factory()->create([
        'role' => 'client',
        'first_name' => 'Chin Chin',
        'last_name' => 'Chin',
        'email' => 'chin@example.com',
        'phone' => '09171112222',
        'nationality' => 'Filipino',
        'date_of_birth' => '1995-02-03',
        'passport_number' => 'P1112223A',
    ]);

    $this->actingAs(admin())->get(route('admin.client-sheets.create', ['client' => $client->id]))
        ->assertOk()
        ->assertSee('value="Chin Chin"', false)
        ->assertSee('value="chin@example.com"', false)
        ->assertSee('value="09171112222"', false)
        ->assertSee('value="1995-02-03"', false)
        ->assertSee('value="P1112223A"', false);
});

test('the counter type-ahead returns sheets and registered clients without one', function () {
    $sheet = ImmigrationClient::factory()->create(['given_name' => 'Chin', 'last_name' => 'Santos', 'passport_number' => 'P7654321A']);
    $client = User::factory()->create(['role' => 'client', 'name' => 'Chin Chin Chin', 'first_name' => 'Chin Chin', 'last_name' => 'Chin']);
    ImmigrationClient::factory()->create(['given_name' => 'Other', 'last_name' => 'Person', 'passport_number' => 'ZZ9999999']);

    $response = $this->actingAs(admin())->getJson(route('admin.client-sheets.lookup', ['q' => 'chin']))->assertOk();

    expect($response->json('sheets'))->toHaveCount(1)
        ->and($response->json('sheets.0.name'))->toBe('Chin Santos')
        ->and($response->json('sheets.0.url'))->toBe(route('admin.client-sheets.edit', $sheet))
        ->and($response->json('clients'))->toHaveCount(1)
        ->and($response->json('clients.0.url'))->toBe(route('admin.client-sheets.create', ['client' => $client->id]));

    $this->actingAs(admin())->getJson(route('admin.client-sheets.lookup', ['q' => 'p765 4321']))
        ->assertJsonPath('sheets.0.name', 'Chin Santos');
});

test('the counter type-ahead waits for two characters', function () {
    ImmigrationClient::factory()->create(['given_name' => 'Chin', 'last_name' => 'Santos']);

    $this->actingAs(admin())->getJson(route('admin.client-sheets.lookup', ['q' => 'c']))
        ->assertOk()
        ->assertExactJson(['sheets' => [], 'clients' => []]);
});

test('the counter type-ahead is closed to clients', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)->getJson(route('admin.client-sheets.lookup', ['q' => 'chin']))
        ->assertStatus(403);
});

test('the dashboard search box is wired to the type-ahead', function () {
    $this->actingAs(admin())->get('/admin/immigration')
        ->assertOk()
        ->assertSee('client-sheets\/lookup', false)
        ->assertSee('role="combobox"', false);
});

test('a returning client is reviewed in the counter order, ending in save and print', function () {
    $client = ImmigrationClient::factory()->create(['given_name' => 'Kenji', 'last_name' => 'Nakamura']);

    $html = $this->actingAs(admin())->get(route('admin.client-sheets.edit', $client))->assertOk()->getContent();

    $order = ['Personal Details', 'Passport Details', 'ACR - I-Card', 'CRTV', 'Annual Report', 'Extension Ledger', 'Save &amp; Print'];
    $positions = array_map(fn (string $step) => strpos($html, $step), $order);

    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all())
        ->and($html)->toContain('name="then" value="print"')
        ->and($html)->toContain('name="then" value="stay"');
});

test('save and print goes straight to the print page with the print dialog', function () {
    $client = ImmigrationClient::factory()->create();

    $this->actingAs(admin())->put(route('admin.client-sheets.update', $client), [
        'last_name' => 'Nakamura',
        'given_name' => 'Kenji',
        'then' => 'print',
    ])->assertRedirect(route('admin.client-sheets.print', ['clientSheet' => $client, 'autoprint' => 1]));

    $this->actingAs(admin())->get(route('admin.client-sheets.print', ['clientSheet' => $client, 'autoprint' => 1]))
        ->assertOk()
        ->assertSee('window.print()', false);
});

test('save only stays on the record', function () {
    $client = ImmigrationClient::factory()->create();

    $this->actingAs(admin())->put(route('admin.client-sheets.update', $client), [
        'last_name' => 'Nakamura',
        'given_name' => 'Kenji',
        'then' => 'stay',
    ])->assertRedirect(route('admin.client-sheets.edit', $client));
});

test('a new client sheet can be saved and printed in one go', function () {
    $response = $this->actingAs(admin())->post(route('admin.client-sheets.store'), [
        'last_name' => 'Rossi',
        'given_name' => 'Luca',
        'passport_number' => 'YA1234567',
        'then' => 'print',
    ]);

    $client = ImmigrationClient::firstWhere('passport_number', 'YA1234567');

    $response->assertRedirect(route('admin.client-sheets.print', ['clientSheet' => $client, 'autoprint' => 1]));
});

test('search results open the record for review before printing', function () {
    $client = ImmigrationClient::factory()->create(['last_name' => 'Nakamura', 'passport_number' => 'TR1234567']);

    $html = $this->actingAs(admin())->get('/admin/client-sheets?passport=TR1234567')->assertOk()->getContent();

    expect(strpos($html, 'Review &amp; update'))->toBeLessThan(strpos($html, route('admin.client-sheets.print', $client)));
});

test('a new client at the counter is added to the admin client list', function () {
    $this->actingAs(admin())->post(route('admin.client-sheets.store'), [
        'last_name' => 'Rossi',
        'given_name' => 'Luca',
        'email' => 'luca.rossi@example.com',
        'date_of_birth' => '1988-06-02',
        'passport_number' => 'YA7777777',
    ])->assertSessionHasNoErrors();

    $sheet = ImmigrationClient::firstWhere('passport_number', 'YA7777777');
    $account = $sheet->user;

    expect($account)->not->toBeNull()
        ->and($account->role)->toBe('client')
        ->and($account->email)->toBe('luca.rossi@example.com')
        ->and($account->date_of_birth->toDateString())->toBe('1988-06-02');

    $this->actingAs(admin())->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('luca.rossi@example.com');
});

test('a client sheet never attaches to a staff account', function () {
    $staff = User::factory()->create(['role' => 'ticketing', 'email' => 'desk@example.com']);

    $this->actingAs(admin())->post(route('admin.client-sheets.store'), [
        'last_name' => 'Rossi',
        'given_name' => 'Luca',
        'email' => 'desk@example.com',
    ])->assertSessionHasNoErrors();

    $account = ImmigrationClient::firstWhere('last_name', 'Rossi')->user;

    expect($account->id)->not->toBe($staff->id)
        ->and($account->role)->toBe('client');
});

test('editing a linked sheet keeps the same client account', function () {
    $account = User::factory()->create(['role' => 'client', 'email' => 'luca@example.com']);
    $sheet = ImmigrationClient::factory()->create(['user_id' => $account->id, 'email' => 'luca@example.com']);

    $this->actingAs(admin())->put(route('admin.client-sheets.update', $sheet), [
        'last_name' => 'Rossi',
        'given_name' => 'Luca',
        'email' => 'luca.new@example.com',
    ])->assertSessionHasNoErrors();

    expect($sheet->fresh()->user_id)->toBe($account->id)
        ->and(User::where('role', 'client')->count())->toBe(1);
});

test('the account for a sheet is found or made from the sheet', function () {
    $sheet = ImmigrationClient::factory()->create(['user_id' => null, 'given_name' => 'Old', 'last_name' => 'Record', 'email' => 'old.record@example.com']);

    $account = ClientAccountService::clientForSheet($sheet);

    expect($account->role)->toBe('client')
        ->and($account->email)->toBe('old.record@example.com');
});

test('other attention required flags a sheet for the counter', function () {
    $sheet = ImmigrationClient::factory()->create([
        'given_name' => 'Kenji', 'last_name' => 'Nakamura',
        'is_expired' => false, 'has_penalty' => false, 'visa_expiry_date' => null,
    ]);

    $this->actingAs(admin())->put(route('admin.client-sheets.update', $sheet), [
        'last_name' => 'Nakamura',
        'given_name' => 'Kenji',
        'needs_attention' => '1',
        'status_note' => 'Waiting on BI release',
    ])->assertSessionHasNoErrors();

    $sheet->refresh();

    expect($sheet->needs_attention)->toBeTrue()
        ->and($sheet->isFlagged())->toBeTrue()
        ->and($sheet->status_marks)->toContain('NEEDS ATTENTION')
        ->and(ImmigrationClient::flagged()->pluck('id')->all())->toBe([$sheet->id]);

    $this->actingAs(admin())->get('/admin/immigration')
        ->assertOk()
        ->assertSee('Kenji Nakamura');
});

test('the ACR I-Card, CRTV and Annual Report steps take a scan and the date paid', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = admin();
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567']);
    $sheet->documents()->create(['document_type' => 'crtv', 'reference_number' => 'CRTV-OLD-1', 'ssrn_number' => 'SS-9', 'validity' => '2027']);
    $save = fn (array $documents) => $this->actingAs($admin)->put(route('admin.client-sheets.update', $sheet), [
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567', 'documents' => $documents, 'then' => 'stay',
    ]);

    // The step shows only the upload and the date paid.
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', $sheet))->assertOk()
        ->assertSee('name="documents[acr_icard][file]"', false)->assertSee('name="documents[acr_icard][date_paid]"', false)
        ->assertDontSee('name="documents[acr_icard][reference_number]"', false)->assertDontSee('name="documents[acr_icard][ssrn_number]"', false);

    $save([
        'acr_icard' => ['date_paid' => '2026-10-01', 'file' => UploadedFile::fake()->create('acr.pdf', 120, 'application/pdf')],
        'crtv' => ['date_paid' => '2026-09-15'],
    ])->assertSessionHasNoErrors();

    $acr = $sheet->fresh()->documentFor('acr_icard');
    expect($acr->original_name)->toBe('acr.pdf')->and($acr->date_paid->toDateString())->toBe('2026-10-01');
    Storage::disk(DocumentStorage::diskName())->assertExists($acr->file_path);

    // What the form no longer shows is kept, not wiped.
    $crtv = $sheet->fresh()->documentFor('crtv');
    expect($crtv->reference_number)->toBe('CRTV-OLD-1')->and($crtv->ssrn_number)->toBe('SS-9')->and($crtv->date_paid->toDateString())->toBe('2026-09-15');

    // The scan opens through the counter, and is listed on the step.
    $this->actingAs($admin)->get(route('admin.client-sheets.documents.file', [$sheet, 'acr_icard']))->assertOk();
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', $sheet))->assertSee('acr.pdf');

    // Only images and PDFs; then the file can be removed.
    $save(['acr_icard' => ['file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')]])->assertSessionHasErrors('documents.acr_icard.file');
    $save(['acr_icard' => ['date_paid' => '2026-10-01', 'remove_file' => 1]]);
    expect($sheet->fresh()->documentFor('acr_icard')->file_path)->toBeNull();
    Storage::disk(DocumentStorage::diskName())->assertMissing($acr->file_path);
    $this->actingAs($admin)->get(route('admin.client-sheets.documents.file', [$sheet, 'acr_icard']))->assertNotFound();
});

test('the extension ledger carries the price list so rows can price themselves, and keeps saved amounts', function () {
    $this->seed(ImmigrationPricingSeeder::class);
    $admin = admin();
    $sheet = ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567']);
    $sheet->extensions()->create(['sequence' => 1, 'extension_date' => '2026-01-10', 'amount_paid' => 3000, 'details' => 'Paid at the counter']);

    $html = $this->actingAs($admin)->get(route('admin.client-sheets.edit', $sheet))->assertOk()
        ->assertSee('ledgerRates(', false)->assertSee('Visa status (from step 2)')
        ->getContent();

    // The published Tourist Visa Extension rates ride along; ones still to confirm do not.
    expect($html)->toContain('2nd Extension')->and($html)->toContain('5600')->and($html)->not->toContain('9500.0,"');

    // A saved row is marked as already paid, so it is never re-priced.
    expect($html)->toContain('\u0022amount\u0022:\u00223000.00\u0022,\u0022details\u0022:\u0022Paid at the counter\u0022,\u0022manual\u0022:true');

    // Whatever is typed is what is saved.
    $this->actingAs($admin)->put(route('admin.client-sheets.update', $sheet), [
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567', 'then' => 'stay',
        'extensions' => [
            1 => ['extension_date' => '2026-01-10', 'amount_paid' => 3000, 'details' => 'Paid at the counter'],
            2 => ['extension_date' => '2026-03-10', 'amount_paid' => 5100, 'details' => '2nd Extension, 1 month (express)'],
        ],
    ])->assertSessionHasNoErrors();

    expect((float) $sheet->fresh()->extensionAt(2)->amount_paid)->toBe(5100.0);
});

test('saving a new extension moves the visa expiry on and clears the expired and penalty marks', function () {
    $admin = admin();
    $sheet = ImmigrationClient::create([
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567',
        'visa_expiry_date' => '2026-10-10', 'is_expired' => true, 'has_penalty' => true, 'expiry_reminder_sent_for' => '2026-10-10',
    ]);
    $sheet->extensions()->create(['sequence' => 1, 'extension_date' => '2026-09-11', 'amount_paid' => 2930, 'duration' => '29 days']);
    $save = fn (array $extensions, string $expiry = '2026-10-10', array $flags = ['is_expired' => 1, 'has_penalty' => 1]) => $this->actingAs($admin)->put(route('admin.client-sheets.update', $sheet), [
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567', 'visa_expiry_date' => $expiry, 'then' => 'stay',
        'extensions' => $extensions,
    ] + $flags);
    $first = ['extension_date' => '2026-09-11', 'amount_paid' => 2930];

    // The 2nd extension (2 months) is added: the visa runs two months past its old expiry.
    $save([1 => $first, 2 => ['extension_date' => '2026-10-15', 'amount_paid' => 6610, 'duration' => '2 months']])
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'The visa is now valid until December 10, 2026.'));

    $sheet->refresh();
    expect($sheet->visa_expiry_date->toDateString())->toBe('2026-12-10')
        ->and($sheet->is_expired)->toBeFalse()->and($sheet->has_penalty)->toBeFalse()
        ->and($sheet->expiry_reminder_sent_for)->toBeNull()
        ->and($sheet->extensionAt(2)->duration)->toBe('2 months');

    // Saving again, or editing a row already on file, does not extend it twice.
    $save([1 => $first, 2 => ['extension_date' => '2026-10-15', 'amount_paid' => 6000, 'duration' => '2 months']], '2026-12-10', []);
    expect($sheet->fresh()->visa_expiry_date->toDateString())->toBe('2026-12-10');

    // A date typed into step 2 wins over the one the new extension would give.
    $save([
        1 => $first, 2 => ['extension_date' => '2026-10-15', 'amount_paid' => 6000],
        3 => ['extension_date' => '2026-12-01', 'amount_paid' => 2730, 'duration' => '2 months'],
    ], '2027-01-31', []);
    expect($sheet->fresh()->visa_expiry_date->toDateString())->toBe('2027-01-31');
});

test('a first extension with no expiry on file runs from its own date', function () {
    $admin = admin();

    $this->actingAs($admin)->post(route('admin.client-sheets.store'), [
        'last_name' => 'Lee', 'given_name' => 'Min', 'passport_number' => 'M7654321', 'then' => 'stay',
        'extensions' => [1 => ['extension_date' => '2026-10-15', 'amount_paid' => 2930, 'duration' => '29 days']],
    ])->assertSessionHasNoErrors();

    expect(ImmigrationClient::where('passport_number', 'M7654321')->first()->visa_expiry_date->toDateString())->toBe('2026-11-13');
});

test('the client sheet prints the BI extension of stay forms, filled from the sheet', function () {
    $admin = admin();
    $sheet = ImmigrationClient::create([
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567', 'nationality' => 'British',
        'civil_status' => 'Married', 'height' => '175 cm', 'weight' => '70 kg', 'address' => '12 Rizal St, Angeles City',
        'date_of_birth' => '1985-03-14', 'visa_expiry_date' => now()->addDays(3)->toDateString(), 'mobile_number' => '09171234567',
    ]);
    $sheet->extensions()->create(['sequence' => 1, 'extension_date' => now()->toDateString(), 'amount_paid' => 2930, 'duration' => '2 months']);

    $this->actingAs($admin)->get(route('admin.client-sheets.edit', $sheet))
        ->assertSee(route('admin.client-sheets.bi-form', [$sheet, 'cgaf']), false)
        ->assertSee(route('admin.client-sheets.bi-form', [$sheet, 'checklist']), false);

    $this->actingAs($admin)->get(route('admin.client-sheets.bi-form', [$sheet, 'cgaf']))->assertOk()
        ->assertSee('Smith')->assertSee('John')->assertSee('GB1234567')->assertSee('British')->assertSee('175')
        ->assertSee('Back to the client sheet')->assertDontSee('Back to applications');

    $this->actingAs($admin)->get(route('admin.client-sheets.bi-form', [$sheet, 'checklist']))->assertOk()
        ->assertSee('Back to the client sheet');

    $this->actingAs($admin)->get(route('admin.client-sheets.bi-blank', 'cgaf'))->assertOk()->assertSee('Back to the counter')->assertDontSee('GB1234567');
    $this->actingAs($admin)->get(route('admin.immigration.dashboard'))->assertSee(route('admin.client-sheets.bi-blank', 'checklist'), false);
});
