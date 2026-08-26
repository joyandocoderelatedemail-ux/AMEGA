<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use App\Models\ImmigrationClientExtension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('guests cannot reach the counter', function () {
    $this->get('/admin/client-sheets')->assertRedirect('/login');
});

test('the counter screen loads with a passport search box', function () {
    $response = $this->actingAs(admin())->get('/admin/client-sheets');

    $response->assertStatus(200);
    $response->assertSee('Look up a client by passport');
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
    $response->assertSee('Expiring within 7 days');
    $response->assertSee('Expired or with penalty');
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
