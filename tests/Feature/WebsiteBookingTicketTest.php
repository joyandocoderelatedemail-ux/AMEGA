<?php

use App\Models\Booking;
use App\Models\TicketBooking;
use App\Models\TravelPackage;
use App\Models\User;

function websiteBooking(array $overrides = []): Booking
{
    $package = TravelPackage::create([
        'title' => 'Manila to Japan (Miyakojima) Cruise - Adora Mediterranea',
        'duration' => '6 Days',
        'price' => '$957',
        'rating' => 5,
        'image' => 'images/japan.jpg',
        'description' => 'Cruise',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    return Booking::create(array_merge([
        'booking_reference' => Booking::generateReference(),
        'travel_package_id' => $package->id,
        'customer_name' => 'Maria Santos',
        'customer_email' => 'maria.santos@example.com',
        'customer_phone' => '+63 917 555 0101',
        'travel_date' => now()->addMonth(),
        'number_of_passengers' => 2,
        'special_requests' => 'Window seats please',
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ], $overrides));
}

test('Register this client opens the client form filled from the booking, and saving puts the booking on the client', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $booking = websiteBooking();

    $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk()
        ->assertSee('Register this client')->assertDontSee('Book in Ticketing');

    $this->get(route('admin.users.create', ['website_booking' => $booking->id]))->assertOk()
        ->assertSee('value="Maria"', false)
        ->assertSee('value="Santos"', false)
        ->assertSee('value="maria.santos@example.com"', false)
        ->assertSee('value="639175550101"', false)
        ->assertSee('name="website_booking" value="'.$booking->id.'"', false);

    // Nobody is registered until the form is saved.
    expect(User::where('role', 'client')->count())->toBe(0);

    $this->post(route('admin.users.store'), clientRegistrationPayload([
        'first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Santos', 'gender' => 'female',
        'email' => 'maria.santos@example.com', 'phone' => '639175550101', 'passport_number' => 'P7654321B',
        'website_booking' => $booking->id,
    ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.bookings.index'));

    $client = User::where('role', 'client')->where('email', 'maria.santos@example.com')->firstOrFail();
    expect($booking->fresh()->user_id)->toBe($client->id);

    $this->get(route('admin.bookings.index'))
        ->assertSee(route('ticketing.tickets.create', ['client' => $client->id, 'website_booking' => $booking->id]))
        ->assertDontSee('Register this client');
});

test('a customer already registered is put on the booking instead of registered twice', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client', 'email' => 'old@example.com', 'phone' => '0917 555 0101']);
    $booking = websiteBooking(['customer_email' => 'new@example.com']);

    $this->actingAs($admin)->get(route('admin.users.create', ['website_booking' => $booking->id]))
        ->assertRedirect(route('admin.bookings.index'));

    expect($booking->fresh()->user_id)->toBe($client->id);
});

test('the follow-up records whether the customer was contacted, by whom, and the remarks', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $booking = websiteBooking();

    $this->actingAs($admin)->patch(route('admin.bookings.update', $booking), ['contacted' => 1, 'remarks' => 'Called, sending the quote'])
        ->assertSessionHas('success');
    expect($booking->fresh())->contacted_by->toBe($admin->id)->remarks->toBe('Called, sending the quote')->contacted_at->not->toBeNull();

    $this->get(route('admin.bookings.index'))->assertSee('Called, sending the quote')->assertSee('by '.$admin->name);

    $this->patch(route('admin.bookings.update', $booking), ['remarks' => ''])->assertSessionHas('success');
    expect($booking->fresh())->contacted_at->toBeNull()->remarks->toBeNull();
});

test('staff without the client and ticketing rights get neither button', function () {
    $bookingsOnly = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);
    websiteBooking();

    $this->actingAs($bookingsOnly)->get(route('admin.bookings.index'))->assertOk()
        ->assertDontSee('Register this client')->assertDontSee('Book in Ticketing');
});

test('the saved ticket is linked, the booking status follows it, and it is counted once', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'ticketing']);
    $client = User::factory()->create(['role' => 'client', 'email' => 'maria.santos@example.com']);
    $booking = websiteBooking(['user_id' => $client->id]);

    $this->actingAs($officer)->get(route('ticketing.tickets.create', ['client' => $client->id, 'website_booking' => $booking->id]))
        ->assertOk()->assertSee($booking->booking_reference)->assertSee('Window seats please');

    $this->post(route('ticketing.tickets.store'), [
        'save_as_quotation' => true,
        'website_booking_id' => $booking->id,
        'travel_type' => 'international',
        'package_type' => 'with_package',
        'travel_package_id' => $booking->travel_package_id,
        'origin' => 'MNL',
        'destination' => 'Miyakojima',
        'destination_country' => 'Japan',
        'destination_city' => 'Miyakojima',
        'arrival_airport' => 'Miyako (MMY)',
        'trip_type' => 'one_way',
        'travel_class' => 'economy',
        'departure_date' => now()->addMonth()->format('Y-m-d'),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria.santos@example.com',
        'contact_phone' => '+63 917 555 0101',
        'passengers' => [['passenger_type' => 'adult', 'first_name' => 'Maria', 'last_name' => 'Santos', 'date_of_birth' => '1988-02-20', 'gender' => 'female', 'passport_expiry_date' => now()->addYears(3)->toDateString()]],
    ])->assertSessionHasNoErrors();

    $ticket = TicketBooking::withoutGlobalScopes()->latest('id')->firstOrFail();
    $status = fn () => $booking->fresh()->only('ticket_booking_id', 'status');

    expect($status())->toBe(['ticket_booking_id' => $ticket->id, 'status' => 'pending']);

    $ticket->forceFill(['is_quotation' => false, 'approval_status' => TicketBooking::APPROVAL_APPROVED, 'total_amount' => 9000])->save();
    expect($status()['status'])->toBe('confirmed');

    $ticket->forceFill(['status' => TicketBooking::STATUS_ISSUED])->save();
    expect($status()['status'])->toBe('completed');

    $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk()
        ->assertSee($ticket->booking_reference)->assertDontSee('Book in Ticketing');

    // The dashboard counts the trip once, as the ticket.
    expect(Booking::notTicketed()->count())->toBe(0);
    $this->get(route('admin.dashboard'))->assertOk();
});
