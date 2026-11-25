<?php

use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
use App\Models\User;

/**
 * The client agreements from SRRV_IMMIGRATION_SERVICES_AGREEMENT.docx: the Immigration Services
 * Booking Agreement from an immigration transaction, and the SRRV Assistance Booking Agreement from
 * a retiree file, an ID renewal or a service request, each filled from the file.
 */
test('the immigration agreement is filled from the folder and ticks the transaction\'s service', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ana Agent']);
    $client = ImmigrationClient::create([
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567', 'nationality' => 'British',
        'date_of_birth' => '1960-04-02', 'mobile_number' => '09171234567', 'email' => 'john@example.com', 'address' => '12 Rizal St, Angeles City',
    ]);

    $html = $this->actingAs($admin)->get(route('admin.client-sheets.agreement', ['clientSheet' => $client, 'service' => 'ecc', 'case' => 7]))
        ->assertOk()->getContent();

    expect($html)->toContain('IMMIGRATION SERVICES BOOKING AGREEMENT')
        ->and($html)->toContain('IMM-'.str_pad((string) $client->id, 5, '0', STR_PAD_LEFT).'-7')
        ->and($html)->toContain('John Smith')->and($html)->toContain('GB1234567')->and($html)->toContain('April 2, 1960')
        ->and($html)->toContain('12 Rizal St, Angeles City')->and($html)->toContain('Ana Agent')
        ->and($html)->toContain('<span class="box">✓</span>Emigration Clearance Certificate (ECC) Exit Clearance')
        ->and($html)->toContain('<span class="box"></span>Visa Downgrading')
        ->and($html)->toContain('images/agreements/services-letterhead.jpg');

    // Visa Extension ticks Visa Updating.
    $this->actingAs($admin)->get(route('admin.client-sheets.agreement', ['clientSheet' => $client]))->assertOk()
        ->assertSee('<span class="box">✓</span>Visa Updating', false);

    // The transaction page header offers the blank one; the filled one is listed on the last step once the file is approved.
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('Services agreement')->assertSee(route('admin.client-sheets.agreement-blank', ['service' => 'ecc']), false);
});

test('the SRRV agreement is filled from a new retiree file, before it is approved', function () {
    $officer = User::factory()->create(['role' => 'srrv', 'name' => 'Rhea Officer']);
    $file = SrrvApplication::create([
        'reference' => 'SRRV-2026-0001', 'created_by' => $officer->id, 'service_type' => 'renewal_application', 'status' => 'intake',
        'visa_class' => 'classic', 'retiree_name' => 'John Smith', 'retiree_email' => 'john@example.com', 'retiree_phone' => '+63 917 555 0101',
        'date_of_birth' => now()->subYears(62)->startOfDay(), 'nationality' => 'British', 'service_fee' => 25000, 'currency' => 'PHP',
        'approval_status' => 'pending',
        'form_details' => ['passport_no' => 'GB7654321', 'gender' => 'M', 'primary_address' => 'Clark, Pampanga', 'home_address' => 'London, UK', 'spouse_name' => 'Jane Smith'],
        'requirements_received' => ['passport'],
    ]);

    $html = $this->actingAs($officer)->get(route('srrv.applications.agreement', $file))->assertOk()->getContent();

    expect($html)->toContain('SRRV ASSISTANCE BOOKING AGREEMENT')->and($html)->toContain('SRRV-2026-0001')
        ->and($html)->toContain('GB7654321')->and($html)->toContain('Clark, Pampanga')
        ->and($html)->toContain('London, UK')->and($html)->toContain('Jane Smith (Spouse)')->and($html)->toContain('PHP 25,000.00')
        ->and($html)->toContain('New SRRV Application')->and($html)->toContain('SRRV Classic')
        ->and($html)->toContain('Rhea Officer')
        // One page: no always-blank fields, tick-box lists or document checklist.
        ->and($html)->not->toContain('Preferred Contact Method')->not->toContain('Sex (if required)')
        ->not->toContain('Client Responsibilities')->not->toContain('class="box"');

    // The wizard's Service Agreement step prints it.
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'step' => 3]))->assertOk()
        ->assertSee(route('srrv.applications.agreement', $file), false);
});

test('the SRRV agreement prints from an ID renewal and a service request too', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $renewal = SrrvRenewal::create(['reference' => 'REN-1', 'retiree_name' => 'Ramon Cruz', 'visa_class' => 'classic', 'created_by' => $officer->id, 'fee_amount' => 3000]);
    $request = SrrvServiceRequest::create(['reference' => 'REQ-1', 'type' => 'termination', 'retiree_name' => 'Ramon Cruz', 'created_by' => $officer->id, 'status' => 'intake', 'fee_usd' => 100]);

    $this->actingAs($officer)->get(route('srrv.renewals.agreement', $renewal))->assertOk()
        ->assertSee('SRRV ID renewal')->assertSee($renewal->fresh()->currency.' 3,000.00');
    $this->actingAs($officer)->get(route('srrv.requests.agreement', $request))->assertOk()
        ->assertSee('SRRV Termination of Membership')->assertSee('USD 100.00')->assertDontSee('Payable to the PRA');

    $this->actingAs($officer)->get(route('srrv.requests.show', $request))->assertOk()->assertSee(route('srrv.requests.agreement', $request), false);
});

test('another officer cannot print someone else\'s SRRV agreement', function () {
    $owner = User::factory()->create(['role' => 'srrv']);
    $other = User::factory()->create(['role' => 'srrv']);
    $renewal = SrrvRenewal::create(['reference' => 'REN-2', 'retiree_name' => 'Ramon Cruz', 'visa_class' => 'classic', 'created_by' => $owner->id]);

    $this->actingAs($other)->get(route('srrv.renewals.agreement', $renewal))->assertNotFound();
});
