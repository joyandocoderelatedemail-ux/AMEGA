<?php

use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lookupTicket(User $agent, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $agent->id,
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(30),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '0917 123 4567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
    ], $overrides));
}

function lookupVisa(User $officer, array $overrides = []): VisaApplication
{
    return VisaApplication::create(array_merge([
        'reference' => 'VSA-'.fake()->unique()->numerify('####'),
        'client_name' => 'Juan Dela Cruz',
        'client_email' => 'juan@example.com',
        'client_phone' => '09171234567',
        'service_type' => 'visit_visa',
        'status' => 'pending',
        'total_amount' => 3500,
        'amount_paid' => 0,
        'currency' => 'PHP',
        'created_by' => $officer->id,
    ], $overrides));
}

function lookupSrrv(User $officer, array $overrides = []): SrrvApplication
{
    return SrrvApplication::create(array_merge([
        'reference' => 'SRRV-'.fake()->unique()->numerify('####'),
        'retiree_name' => 'Juan Dela Cruz',
        'retiree_email' => 'juan@example.com',
        'service_type' => 'restamping',
        'visa_class' => 'classic',
        'status' => 'pending',
        'service_fee' => 1000,
        'amount_paid' => 0,
        'currency' => 'USD',
        'created_by' => $officer->id,
    ], $overrides));
}

test('one search pulls a client\'s tickets, visa file and SRRV file with every payment', function () {
    $cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Cora Cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);
    $srrvOfficer = User::factory()->create(['role' => 'srrv']);

    $ticket = lookupTicket($agent);
    $ticket->receivePayment(3000, 'cash', $cashier);
    $ticket->receivePayment(2000, 'card', $cashier);

    $visa = lookupVisa($visaOfficer, ['reference' => 'VSA-5001', 'amount_paid' => 1000]);
    $visa->payments()->create(['amount' => 1000, 'method' => 'gcash', 'reference' => 'GC-778', 'received_by' => $cashier->id, 'received_at' => now()]);

    $srrv = lookupSrrv($srrvOfficer, ['reference' => 'SRRV-7001', 'amount_paid' => 400]);
    $srrv->payments()->create(['amount' => 400, 'method' => 'bank_transfer', 'received_by' => $cashier->id, 'received_at' => now()]);

    // Another client, who must not appear.
    lookupTicket($agent, ['contact_name' => 'Maria Santos', 'contact_email' => 'maria@example.com', 'contact_phone' => '09990001111', 'booking_reference' => 'TKT-OTHER-1']);

    // Searching the name finds the account and everything on it.
    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'Dela Cruz']))->assertOk()
        ->assertSee('Juan Dela Cruz')->assertSee('juan@example.com')
        ->assertSee($ticket->booking_reference)->assertSee('VSA-5001')->assertSee('SRRV-7001')
        // The ticket's payments, numbered, with the card's 4% charge.
        ->assertSee('1st payment')->assertSee('2nd payment')->assertSee('₱80.00', false)
        ->assertSee('GC-778')->assertSee('Cora Cashier')
        ->assertDontSee('Maria Santos')->assertDontSee('TKT-OTHER-1');
});

test('the account totals are kept per currency', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);
    $srrvOfficer = User::factory()->create(['role' => 'srrv']);

    $ticket = lookupTicket($agent);
    $ticket->receivePayment(4000, 'cash', $cashier);
    lookupSrrv($srrvOfficer, ['amount_paid' => 400]);

    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'juan@example.com']))->assertOk()
        ->assertSee('PHP · Billed', false)->assertSee('USD · Billed', false)
        ->assertSee('₱10,000.00', false)->assertSee('₱6,000.00', false)   // PHP: billed, balance
        ->assertSee('US$1,000.00', false)->assertSee('US$600.00', false);  // USD: billed, balance
});

test('a client is found by email, by phone however it is typed, by reference and by passport', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);

    $ticket = lookupTicket($agent, ['contact_phone' => '+63 917 123 4567']);
    $ticket->passengers()->create(['passenger_number' => 1, 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'passport_number' => 'P7788990C']);
    // The visa file wrote the same number another way: it still joins the same account through the email.
    lookupVisa($visaOfficer, ['reference' => 'VSA-6001', 'client_phone' => '09171234567']);

    foreach (['juan@example.com', '+63 917 123 4567', $ticket->booking_reference, 'P7788990C', 'VSA-6001'] as $term) {
        $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => $term]))->assertOk()
            ->assertSee($ticket->booking_reference)->assertSee('VSA-6001');
    }
});

test('two clients with the same name but different emails are separate accounts', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);

    lookupTicket($agent, ['booking_reference' => 'TKT-A-1']);
    lookupTicket($agent, ['booking_reference' => 'TKT-B-1', 'contact_email' => 'other.juan@example.com', 'contact_phone' => '09998887777']);

    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'Juan']))->assertOk()
        ->assertSee('2 accounts found')->assertSee('juan@example.com')->assertSee('other.juan@example.com');
});

test('quotations and cancelled bookings are listed but do not add to what the client owes', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);

    lookupTicket($agent, ['booking_reference' => 'TKT-LIVE-1', 'total_amount' => 10000]);
    lookupTicket($agent, ['booking_reference' => 'TKT-CXL-1', 'total_amount' => 70000, 'status' => TicketBooking::STATUS_CANCELLED]);

    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'juan@example.com']))->assertOk()
        ->assertSee('TKT-LIVE-1')->assertSee('TKT-CXL-1')->assertSee('Cancelled')
        ->assertDontSee('₱80,000.00', false)->assertSee('₱10,000.00', false);
});

test('an empty, short or unmatched search says so', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);

    $this->actingAs($cashier)->get(route('cashier.accounts'))->assertOk()->assertSee('Type at least two characters');
    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'j']))->assertOk()->assertSee('Type at least two characters');
    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'Nobody Atall']))->assertOk()->assertSee('No account matches');
});

test('each file links to its page, and a ticket to its Statement of Account', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);

    $ticket = lookupTicket($agent);
    $visa = lookupVisa($visaOfficer);

    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'juan@example.com']))->assertOk()
        ->assertSee(route('cashier.payments.show', $ticket), false)
        ->assertSee(route('cashier.payments.soa', $ticket), false)
        ->assertSee(route('cashier.visa.show', $visa->id), false);
});

test('the lookup is a cashier tool, and every cashier page has the search bar', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->get(route('cashier.accounts', ['search' => 'juan']))->assertRedirect();

    $this->actingAs($cashier)->get(route('cashier.dashboard'))->assertOk()
        ->assertSee('Search an account')->assertSee('action="'.route('cashier.accounts').'"', false);
});

test('a phone number shared by two clients who gave different emails does not merge their files by name alone', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $agent = User::factory()->create(['role' => 'ticketing']);

    lookupTicket($agent, ['booking_reference' => 'TKT-NOEMAIL-1', 'contact_email' => '', 'contact_phone' => '09175550000', 'contact_name' => 'Pedro Penduko']);
    lookupTicket($agent, ['booking_reference' => 'TKT-NOEMAIL-2', 'contact_email' => '', 'contact_phone' => '09175550000', 'contact_name' => 'Pedro Penduko']);

    // Same phone, no email: one account holding both files.
    $this->actingAs($cashier)->get(route('cashier.accounts', ['search' => 'Penduko']))->assertOk()
        ->assertSee('1 account found')->assertSee('TKT-NOEMAIL-1')->assertSee('TKT-NOEMAIL-2');
});
