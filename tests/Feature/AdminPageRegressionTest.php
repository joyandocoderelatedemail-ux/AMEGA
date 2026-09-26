<?php

use App\Models\BookingAgreement;
use App\Models\CrmLead;
use App\Models\Destination;
use App\Models\TicketBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Pages a full GET sweep of the app found returning HTTP 500.
 */

function regressionDestination(): Destination
{
    return Destination::create([
        'name' => 'Boracay Island',
        'location' => 'Aklan, Philippines',
        'description' => 'White sand beaches.',
        'image' => 'newassets/boracay.jpg',
        'starting_price' => '₱12,000',
        'type' => 'domestic',
        'is_featured' => true,
    ]);
}

test('a destination can be opened for editing and updated', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = regressionDestination();

    $this->actingAs($admin)->get(route('admin.destinations.edit', $destination))
        ->assertOk()
        ->assertSee('Boracay Island', false)
        ->assertSee('Aklan, Philippines', false);

    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), [
        'name' => 'Boracay',
        'location' => 'Malay, Aklan',
        'type' => 'domestic',
        'starting_price' => '₱15,000',
        'description' => 'Updated.',
        'image' => 'newassets/boracay.jpg',
    ])->assertRedirect(route('admin.destinations.index'));

    expect($destination->fresh())
        ->location->toBe('Malay, Aklan')
        ->is_featured->toBeFalse();
});

test('a destination can be created from the form fields the page sends', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.destinations.store'), [
        'name' => 'Siargao',
        'location' => 'Surigao del Norte',
        'type' => 'domestic',
        'starting_price' => '₱9,000',
        'description' => 'Surf capital.',
        'image' => 'newassets/siargao.jpg',
        'is_featured' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.destinations.index'));

    expect(Destination::where('name', 'Siargao')->value('location'))->toBe('Surigao del Norte');
});

test('admin resources without a detail page answer 405 rather than 500', function (string $uri) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get($uri)->assertMethodNotAllowed();
})->with([
    '/admin/packages/1',
    '/admin/destinations/1',
    '/admin/services/1',
    '/admin/agents/1',
]);

test('a crm lead with intake notes shows its activity timeline', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $lead = CrmLead::create([
        'reference_code' => CrmLead::generateReferenceCode(),
        'client_name' => 'Samantha Cruz',
        'service_type' => 'custom_tour',
        'source' => 'phone',
        'title' => 'Boracay Weekend Trip',
        'stage' => 'new',
        'priority' => 'medium',
        'notes' => 'Wants a beach-front hotel.',
    ]);
    $lead->notes()->create(['user_id' => $admin->id, 'action_type' => 'call', 'content' => 'Called to confirm dates.']);

    $this->actingAs($admin)->get(route('admin.crm.leads.show', $lead))
        ->assertOk()
        ->assertSee('Wants a beach-front hotel.', false)
        ->assertSee('Called to confirm dates.', false);
});

test('an officer cannot open the agreement on another officer\'s ticket', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);

    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-AGR-PRIVATE',
        'travel_type' => 'domestic',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Carlos Yulo',
        'total_amount' => 5000,
        'status' => 'pending',
        'created_by' => $owner->id,
    ]);
    $agreement = BookingAgreement::create([
        'ticket_booking_id' => $ticket->id,
        'agreement_number' => 'AGR-202609-PRIV',
        'client_names' => 'Carlos Yulo',
        'agreement_date' => Carbon::today(),
    ]);

    $this->actingAs($other)->get(route('ticketing.agreements.show', $agreement))->assertNotFound();
    $this->actingAs($other)->get(route('ticketing.agreements.edit', $agreement))->assertNotFound();
    $this->actingAs($owner)->get(route('ticketing.agreements.show', $agreement))->assertOk();
});
