<?php

use App\Models\Destination;
use App\Models\TicketBooking;
use App\Models\TravelPackage;
use App\Models\User;
use App\Services\BookingAgreementDrafter;
use Illuminate\Support\Carbon;

function pricedPackage(): TravelPackage
{
    $destination = Destination::create([
        'name' => 'El Nido Paradise',
        'location' => 'Palawan',
        'type' => 'domestic',
        'description' => 'Test description',
        'image' => 'images/test.jpg',
        'starting_price' => '₱15,000',
    ]);

    return TravelPackage::create([
        'title' => 'El Nido 4D3N Special',
        'destination_id' => $destination->id,
        'description' => 'Test package description',
        'image' => 'images/test.jpg',
        'price' => '₱15,000',
        'duration' => '4 Days 3 Nights',
    ]);
}

/**
 * A domestic booking for two adults with the given extra fields.
 *
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function packageBookingPayload(array $fields): array
{
    $departure = Carbon::today()->addMonths(2);

    return [...bookingFlight(),
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'El Nido Paradise',
        'trip_type' => 'round_trip',
        'departure_date' => $departure->toDateString(),
        'return_date' => $departure->copy()->addDays(4)->toDateString(),
        'total_passengers' => 2,
        'adults_count' => 2,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria@example.com',
        'contact_phone' => '09181112222',
        'estimated_fare' => 10000,
        'passengers' => [
            ['first_name' => 'Maria', 'last_name' => 'Santos', 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'date_of_birth' => '1990-01-01', 'passport_number' => 'P1234567A', 'passport_expiry_date' => $departure->copy()->addYears(2)->toDateString()],
            ['first_name' => 'Jose', 'last_name' => 'Santos', 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'date_of_birth' => '1991-01-01', 'passport_number' => 'P1234567B', 'passport_expiry_date' => $departure->copy()->addYears(2)->toDateString()],
        ],
        ...$fields,
    ];
}

test('a ready-made package price is added to the booking total', function () {
    $package = pricedPackage();

    $this->actingAs(User::factory()->create(['role' => 'ticketing']))
        ->post(route('ticketing.tickets.store'), packageBookingPayload([
            'package_type' => 'with_package',
            'travel_package_id' => $package->id,
            'package_amount' => 30000,
            'service_fee' => 500,
        ]))->assertRedirect();

    $ticket = TicketBooking::where('contact_email', 'maria@example.com')->firstOrFail();

    expect((float) $ticket->package_amount)->toBe(30000.0)
        ->and((float) $ticket->total_amount)->toBe(40500.0);
});

test('a package price is ignored without a ready-made package', function () {
    $this->actingAs(User::factory()->create(['role' => 'ticketing']))
        ->post(route('ticketing.tickets.store'), packageBookingPayload([
            'package_type' => 'without_package',
            'package_amount' => 30000,
        ]))->assertRedirect();

    $ticket = TicketBooking::where('contact_email', 'maria@example.com')->firstOrFail();

    expect((float) $ticket->package_amount)->toBe(0.0)
        ->and((float) $ticket->total_amount)->toBe(10000.0);
});

test('the package price shows in the breakdown, the voucher and the booking agreement', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $package = pricedPackage();

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), packageBookingPayload([
        'package_type' => 'with_package',
        'travel_package_id' => $package->id,
        'package_amount' => 30000,
    ]));
    $ticket = TicketBooking::where('contact_email', 'maria@example.com')->firstOrFail();

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()->assertSee('Tour Package')->assertSee('₱30,000.00');

    $items = app(BookingAgreementDrafter::class)->defaults($ticket)['pricingItems'];

    expect(array_column($items, 'airfare_description'))
        ->toContain('Tour Package: El Nido 4D3N Special')
        ->and(collect($items)->firstWhere('airfare_description', 'Tour Package: El Nido 4D3N Special'))
        ->toMatchArray(['price_details' => '₱15,000.00 per person', 'pax_count' => 2, 'amount' => 30000.0])
        ->and(round(array_sum(array_column($items, 'amount')), 2))->toBe(40000.0);
});

test('the wizard counts the package price in the grand total and breakdown', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'ticketing']))
        ->get(route('ticketing.tickets.create'))->getContent();

    expect($html)->toContain('name="package_amount" x-model.number="formData.package_amount"')
        ->toContain('x-show="hasReadyMadePackage()"')
        ->toContain('const packagePrice = this.hasReadyMadePackage()');
});
