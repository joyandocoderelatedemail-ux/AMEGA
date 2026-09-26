<?php

use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Support\DataPrivacyConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function consentTicket(User $owner, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Irwan Tumu',
        'total_amount' => 5000,
        'status' => 'pending',
        'created_by' => $owner->id,
    ], $overrides));
}

test('a plain ticket ticks only the ticket service', function () {
    $consent = DataPrivacyConsent::forTicket(consentTicket(User::factory()->create(['role' => 'ticketing'])));

    expect($consent->services)->toBe(['ticket']);
});

test('a ticket with a package, visa assistance and a day tour ticks each of them', function () {
    $consent = DataPrivacyConsent::forTicket(consentTicket(User::factory()->create(['role' => 'ticketing']), [
        'package_type' => 'custom_package',
        'visa_assistance_fee' => 1500,
        'selected_services' => ['tour_package', 'pocket_wifi'],
    ]));

    expect($consent->services)->toBe(['ticket', 'package', 'visa', 'day_tour']);
});

test('the ticketing desk prints the consent form with the client and the ticked service', function () {
    $officer = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Reyes']);
    $ticket = consentTicket($officer);

    $response = $this->actingAs($officer)->get(route('ticketing.tickets.consent', $ticket))
        ->assertOk()
        ->assertSee('Data Privacy Consent Form', false)
        ->assertSee('Irwan Tumu', false)
        ->assertSee('Ana Reyes', false);

    expect($response->viewData('consent')->services)->toBe(['ticket'])
        ->and(preg_match_all('/<input type="checkbox"[^>]*\bchecked\b/', $response->getContent()))->toBe(1);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertSee(route('ticketing.tickets.consent', $ticket), false);
});

test('visa files tick visa assistance, and passporting ticks the passport application', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $visa = VisaApplication::create(['reference' => 'VSA-1', 'client_name' => 'Ana Santos', 'service_type' => 'visit_visa', 'created_by' => $officer->id]);
    $passport = VisaApplication::create(['reference' => 'VSA-2', 'client_name' => 'Ana Santos', 'service_type' => 'passporting', 'created_by' => $officer->id]);

    expect(DataPrivacyConsent::forVisa($visa)->services)->toBe(['visa'])
        ->and(DataPrivacyConsent::forVisa($passport)->services)->toBe(['passport']);

    $this->actingAs($officer)->get(route('visa.applications.consent', $passport))
        ->assertOk()
        ->assertSee('Ana Santos', false);
});

test('srrv applications and renewals tick other documentation services', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $application = SrrvApplication::create(['reference' => 'SRRV-1', 'retiree_name' => 'Ramon Cruz', 'service_type' => 'renewal_application', 'visa_class' => 'classic', 'created_by' => $officer->id]);
    $renewal = SrrvRenewal::create(['reference' => 'REN-1', 'retiree_name' => 'Ramon Cruz', 'visa_class' => 'classic', 'created_by' => $officer->id]);

    expect(DataPrivacyConsent::forSrrvApplication($application)->services)->toBe(['documentation']);

    $this->actingAs($officer)->get(route('srrv.applications.consent', $application))->assertOk()->assertSee('Ramon Cruz', false);
    $this->actingAs($officer)->get(route('srrv.renewals.consent', $renewal))->assertOk()->assertSee('Ramon Cruz', false);
});

test('immigration client sheets tick visa extension and immigration services', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sheet = ImmigrationClient::factory()->create(['given_name' => 'Irwan', 'last_name' => 'Tumu']);

    expect(DataPrivacyConsent::forImmigration($sheet)->services)->toBe(['immigration']);

    $this->actingAs($admin)->get(route('admin.client-sheets.consent', $sheet))
        ->assertOk()
        ->assertSee($sheet->full_name, false);
});

test('an officer cannot print consent for another officer\'s file', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($other)->get(route('ticketing.tickets.consent', consentTicket($owner)))->assertNotFound();
});

test('staff from another desk cannot open a desk\'s consent form', function () {
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);
    $ticket = consentTicket(User::factory()->create(['role' => 'ticketing']));

    $this->actingAs($visaOfficer)->get(route('ticketing.tickets.consent', $ticket))->assertRedirect();
});
