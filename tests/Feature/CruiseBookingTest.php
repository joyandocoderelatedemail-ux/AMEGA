<?php

use App\Models\CruiseBooking;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\Permissions;

/** An agent whose only desk is Cruise Services. */
function cruiseAgent(array $extra = []): User
{
    return User::factory()->create(['role' => 'agent', 'permissions' => [...Permissions::work('cruise_services'), ...$extra]]);
}

/** Step 1 filled in, as the form sends it. */
function cruiseStepOne(array $overrides = []): array
{
    return $overrides + [
        'client_name' => 'Maria Santos',
        'contact_number' => '0918 111 2222',
        'destination' => 'Singapore, Penang and Phuket',
        'departure_date' => now()->addMonths(3)->toDateString(),
        'return_date' => now()->addMonths(3)->addDays(6)->toDateString(),
        'adults' => 2,
        'children' => 1,
        'infants' => 1,
        'cruise_line' => 'Royal Caribbean International',
        'ship' => 'Navigator of the Seas',
    ];
}

it('keeps the desk to staff who were given it, and puts it in the Services menu', function () {
    $this->get('/cruise-services')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get('/cruise-services')->assertRedirect();

    $agent = cruiseAgent();
    $this->actingAs($agent)->get('/cruise-services')->assertOk()->assertSee('Cruise bookings');
    expect($agent->staffHomeRoute())->toBe('cruise-services.dashboard');
    $this->get('/admin')->assertRedirect(route('cruise-services.dashboard'));

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/cruise-services')->assertOk();
    $services = collect(AdminNavigation::for($admin))->firstWhere('key', 'services');
    expect(collect($services['items'])->pluck('label'))->toContain('Cruise Services');
});

it('saves step 1 without the slot confirmed, and registers the client', function () {
    $agent = cruiseAgent();

    $this->actingAs($agent)->get(route('cruise-services.bookings.create'))->assertOk()
        ->assertSee('Step 1 - Check Availability')->assertSee('Slot confirmed');

    $this->post(route('cruise-services.bookings.store'), cruiseStepOne(['slot_confirmed' => '0', 'action' => 'save']))
        ->assertSessionHas('success');

    $booking = CruiseBooking::firstOrFail();
    expect($booking->reference)->toStartWith('CR-')
        ->and($booking->status)->toBe('availability')
        ->and($booking->created_by)->toBe($agent->id)
        ->and($booking->guestsLabel())->toBe('2 adults, 1 child, 1 infant')
        ->and($booking->isSlotConfirmed())->toBeFalse()
        ->and(User::find($booking->user_id)?->role)->toBe('client');
});

it('does not let the agent continue until the slot is confirmed', function () {
    $agent = cruiseAgent();

    $this->actingAs($agent)->post(route('cruise-services.bookings.store'), cruiseStepOne(['slot_confirmed' => '0', 'action' => 'continue']))
        ->assertSessionHasErrors(['slot_confirmed' => 'Confirm the slot before continuing.']);
    expect(CruiseBooking::count())->toBe(0);

    $this->post(route('cruise-services.bookings.store'), cruiseStepOne(['slot_confirmed' => '1', 'action' => 'continue']))
        ->assertSessionHas('success');

    $booking = CruiseBooking::firstOrFail();
    expect($booking->isSlotConfirmed())->toBeTrue()
        ->and($booking->slot_confirmed_by)->toBe($agent->id)
        ->and($booking->status)->toBe('quotation');
});

/** A booking past step 1: slot confirmed, at Prepare the Quotation. */
function cruiseAtQuotation(User $agent, array $attributes = []): CruiseBooking
{
    $booking = new CruiseBooking($attributes + [
        'reference' => CruiseBooking::generateReference(), 'status' => 'quotation', 'client_name' => 'Maria Santos',
        'destination' => 'Singapore, Penang and Phuket', 'departure_date' => '2027-03-16', 'return_date' => '2027-03-22',
        'adults' => 2, 'children' => 1, 'cruise_line' => 'Royal Caribbean International', 'ship' => 'Navigator of the Seas',
    ]);
    $booking->created_by = $agent->id;
    $booking->slot_confirmed_at = now();
    $booking->save();

    return $booking;
}

it('opens the quotation with one itinerary day per day of the sailing', function () {
    $agent = cruiseAgent();
    $booking = cruiseAtQuotation($agent);

    expect($booking->itineraryRows())->toHaveCount(7)
        ->and($booking->itineraryRows()[6])->toBe(['day' => 7, 'date' => '2027-03-22', 'description' => '']);

    $this->actingAs($agent)->get(route('cruise-services.bookings.show', $booking))->assertOk()
        ->assertSee('Step 2 - Prepare the Quotation')->assertSee('Save and print quotation');

    // Step 1 can still be opened from the stepper.
    $this->get(route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'availability']))->assertOk()->assertSee('Step 1 - Check Availability');
});

it('adds up the quotation, then saves and prints it onto the Quotations page', function () {
    $agent = cruiseAgent();
    $booking = cruiseAtQuotation($agent);
    $quote = [
        'currency' => 'USD', 'base_fare' => 2400, 'tipping' => 160, 'insurance' => 90, 'travel_taxes' => 210,
        'add_ons' => [['description' => 'Shore excursion in Penang', 'amount' => 120], ['description' => '', 'amount' => '']],
        'itinerary' => [
            ['date' => '2027-03-16', 'description' => 'Embark in Singapore'],
            ['date' => '2027-03-17', 'description' => 'At sea'],
        ],
    ];

    // Saving keeps it off the Quotations page.
    $this->actingAs($agent)->put(route('cruise-services.bookings.quotation.update', $booking), $quote + ['action' => 'save'])->assertSessionHas('success');
    $booking->refresh();
    expect((float) $booking->total_amount)->toBe(2980.0)
        ->and($booking->add_ons)->toBe([['description' => 'Shore excursion in Penang', 'amount' => 120]])
        ->and($booking->itinerary[1])->toBe(['day' => 2, 'date' => '2027-03-17', 'description' => 'At sea'])
        ->and($booking->hasQuotation())->toBeFalse();
    $this->get(route('cruise-services.quotations'))->assertOk()->assertDontSee($booking->reference);
    $this->get(route('cruise-services.bookings.quotation', $booking))->assertRedirect();

    $this->put(route('cruise-services.bookings.quotation.update', $booking), $quote + ['action' => 'print'])
        ->assertRedirect(route('cruise-services.bookings.quotation', ['booking' => $booking, 'autoprint' => 1]));
    expect($booking->fresh()->quotation_at)->not->toBeNull()
        ->and($booking->fresh()->quotation_by)->toBe($agent->id);

    $this->get(route('cruise-services.quotations'))->assertOk()->assertSee($booking->reference)->assertSee('US$2,980.00');
    $this->get(route('cruise-services.bookings.quotation', $booking))->assertOk()
        ->assertSee('Cruise Quotation')->assertSee('Add-on: Shore excursion in Penang')->assertSee('Embark in Singapore')->assertSee('US$2,980.00');
});

it('needs the base fare to print, and a confirmed slot to work on the quotation', function () {
    $agent = cruiseAgent();
    $booking = cruiseAtQuotation($agent);

    $this->actingAs($agent)->put(route('cruise-services.bookings.quotation.update', $booking), ['currency' => 'PHP', 'action' => 'print'])
        ->assertSessionHasErrors('base_fare');
    expect($booking->fresh()->hasQuotation())->toBeFalse();

    // Unconfirming the slot sends the booking back to step 1, and the quotation waits.
    $this->put(route('cruise-services.bookings.update', $booking), cruiseStepOne(['slot_confirmed' => '0', 'action' => 'save']));
    expect($booking->fresh()->status)->toBe('availability');
    $this->put(route('cruise-services.bookings.quotation.update', $booking), ['currency' => 'PHP', 'base_fare' => 1000, 'action' => 'save'])
        ->assertSessionHas('error');
    expect((float) $booking->fresh()->base_fare)->toBe(0.0);
});

it('needs the destination, dates and guest count to continue, but not to save', function () {
    $agent = cruiseAgent();
    $booking = new CruiseBooking(['reference' => CruiseBooking::generateReference(), 'status' => 'availability', 'client_name' => 'Maria Santos']);
    $booking->created_by = $agent->id;
    $booking->save();

    $this->actingAs($agent)->put(route('cruise-services.bookings.update', $booking), ['client_name' => 'Maria Santos', 'slot_confirmed' => '1', 'action' => 'continue'])
        ->assertSessionHasErrors(['destination', 'departure_date', 'return_date', 'adults']);

    $this->put(route('cruise-services.bookings.update', $booking), ['client_name' => 'Maria Santos', 'destination' => 'Japan', 'action' => 'save'])
        ->assertSessionHas('success');
    expect($booking->fresh()->destination)->toBe('Japan');

    // Unticking the box takes the confirmation off.
    $this->put(route('cruise-services.bookings.update', $booking), cruiseStepOne(['slot_confirmed' => '1', 'action' => 'save']));
    expect($booking->fresh()->isSlotConfirmed())->toBeTrue();
    $this->put(route('cruise-services.bookings.update', $booking), cruiseStepOne(['slot_confirmed' => '0', 'action' => 'save']));
    expect($booking->fresh()->isSlotConfirmed())->toBeFalse();
});

it('keeps a booking private to the agent who opened it, and counts slots on the dashboard', function () {
    $owner = cruiseAgent();
    $this->actingAs($owner)->post(route('cruise-services.bookings.store'), cruiseStepOne(['slot_confirmed' => '1', 'action' => 'save']));
    $this->post(route('cruise-services.bookings.store'), cruiseStepOne(['client_name' => 'Jose Rizal', 'slot_confirmed' => '0', 'action' => 'save']));
    $booking = CruiseBooking::firstOrFail();

    $this->get(route('cruise-services.dashboard', ['slot' => 'waiting']))->assertOk()->assertSee('Jose Rizal')->assertDontSee('Maria Santos');
    $this->get(route('cruise-services.dashboard'))->assertViewHas('counts', ['all' => 2, 'waiting' => 1, 'confirmed' => 1]);

    $this->actingAs(cruiseAgent())->get(route('cruise-services.bookings.show', $booking))->assertNotFound();
    $this->actingAs(cruiseAgent(['cruise_services.view_all']))->get(route('cruise-services.bookings.show', $booking))->assertOk();
});

it('lets someone who can only open the desk look but not save', function () {
    $viewer = User::factory()->create(['role' => 'agent', 'permissions' => ['cruise_services.view', 'cruise_services.view_all']]);

    $this->actingAs($viewer)->post(route('cruise-services.bookings.store'), cruiseStepOne(['action' => 'save']))->assertRedirect();
    expect(CruiseBooking::count())->toBe(0);
});

it('finds registered clients by name and links the booking to the one picked', function () {
    $agent = cruiseAgent();
    $client = User::factory()->create(['role' => 'client', 'name' => 'Teresa Magbanua', 'phone' => '0917 555 1234']);
    User::factory()->create(['role' => 'agent', 'name' => 'Teresa Staff']);

    $this->actingAs($agent)->getJson(route('cruise-services.clients.search', ['q' => 'teresa']))
        ->assertOk()->assertJsonCount(1, 'clients')->assertJsonPath('clients.0.id', $client->id)->assertJsonPath('clients.0.phone', '0917 555 1234');

    $this->post(route('cruise-services.bookings.store'), cruiseStepOne(['client_id' => $client->id, 'client_name' => 'Teresa Magbanua', 'action' => 'save']));
    expect(CruiseBooking::firstOrFail()->user_id)->toBe($client->id)
        ->and(User::where('role', 'client')->count())->toBe(1);

    // Only a client account can be linked.
    $staff = User::where('role', 'agent')->where('name', 'Teresa Staff')->first();
    $this->post(route('cruise-services.bookings.store'), cruiseStepOne(['client_id' => $staff->id, 'action' => 'save']))->assertSessionHasErrors('client_id');
});

it('offers the cruise lines and their ships, and keeps one typed in under Other', function () {
    $agent = cruiseAgent();

    $this->actingAs($agent)->get(route('cruise-services.bookings.create'))->assertOk()
        ->assertSee('Royal Caribbean International')->assertSee('Navigator of the Seas')
        ->assertSee('<option value="Taiwan and Japan">', false)->assertSee('Other (type it)');

    $this->post(route('cruise-services.bookings.store'), cruiseStepOne(['destination' => 'Alaska', 'cruise_line' => 'Celebrity Cruises', 'ship' => 'Celebrity Solstice', 'action' => 'save']));
    $booking = CruiseBooking::firstOrFail();
    expect($booking->cruiseLabel())->toBe('Celebrity Cruises · Celebrity Solstice')
        ->and($booking->destination)->toBe('Alaska');

    // A destination typed in shows again under Other when the booking is opened.
    $this->get(route('cruise-services.bookings.show', $booking))->assertOk()->assertSee('Alaska');
});
