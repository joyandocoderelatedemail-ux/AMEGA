<?php

use App\Models\Airline;
use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\TicketApprovalRequestedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Quotations: the list of trips saved without documents, and Convert to booking, which asks for
 * what a booking needs, runs the wizard's checks and sends it to the admin for approval.
 */
function quotedTrip(User $agent, array $changes = []): TicketBooking
{
    test()->actingAs($agent)->post(route('ticketing.tickets.store'), array_merge([
        'travel_type' => 'domestic', 'package_type' => 'without_package', 'origin' => 'Manila (MNL)', 'destination' => 'Cebu',
        'trip_type' => 'one_way', 'departure_date' => Carbon::today()->addMonths(2)->toDateString(),
        'total_passengers' => 2, 'adults_count' => 1, 'children_count' => 1, 'infants_count' => 0,
        'contact_name' => 'Maria Santos', 'fare_prices' => ['adult' => '5000', 'child' => '3000'],
        // Only the adult is named on the quote.
        'passengers' => [
            ['first_name' => 'Maria', 'last_name' => 'Santos', 'passenger_type' => 'adult', 'nationality_type' => 'filipino'],
            ['first_name' => '', 'last_name' => '', 'passenger_type' => 'child', 'nationality_type' => 'filipino'],
        ],
        'save_as_quotation' => true,
    ], $changes))->assertSessionHasNoErrors();

    return TicketBooking::where('contact_name', 'Maria Santos')->latest('id')->firstOrFail();
}

function quoteConversionForm(TicketBooking $quote, Airline $airline, array $changes = []): array
{
    $adult = $quote->passengers->first();

    return array_merge([
        'contact_email' => 'maria@example.com', 'contact_phone' => '09181112222',
        'airline_id' => $airline->id, 'flight_number' => 'pr 1845', 'departure_time' => '08:00', 'arrival_time' => '09:20',
        'passengers' => [
            ['id' => $adult->id, 'passenger_type' => 'adult', 'first_name' => 'Maria', 'last_name' => 'Santos', 'nationality_type' => 'filipino',
                'passport_expiry_date' => now()->addYears(3)->toDateString()],
            ['passenger_type' => 'child', 'first_name' => 'Ben', 'last_name' => 'Santos', 'nationality_type' => 'filipino',
                'date_of_birth' => now()->subYears(8)->toDateString(), 'passport_expiry_date' => now()->addYears(3)->toDateString()],
        ],
        'agent_acknowledged' => 1,
    ], $changes);
}

test('the Quotations page lists open quotations, not bookings', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $quote = quotedTrip($agent);

    $this->actingAs($agent)->get(route('ticketing.dashboard'))->assertSee(route('ticketing.quotations.index'), false)->assertSee('Quotations');
    $this->actingAs($agent)->get(route('ticketing.quotations.index'))->assertOk()
        ->assertSee($quote->booking_reference)->assertSee('1 of 2 named')->assertSee(route('ticketing.quotations.convert', $quote), false);
    $this->actingAs($agent)->get(route('ticketing.tickets.show', $quote))->assertSee('Convert to booking');
});

test('converting asks for the flight, the contact, every passenger and the acknowledgement', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $quote = quotedTrip($agent);
    $airline = Airline::create(['name' => 'Philippine Airlines', 'code' => 'PR', 'is_active' => true]);

    $this->actingAs($agent)->get(route('ticketing.quotations.convert', $quote))->assertOk()
        ->assertSee('Passenger #2 · Child')->assertSee('Not named on the quotation')->assertSee('value="Maria"', false);

    $this->actingAs($agent)->post(route('ticketing.quotations.store-conversion', $quote), [])
        ->assertSessionHasErrors(['contact_email', 'contact_phone', 'airline_id', 'flight_number', 'passengers', 'agent_acknowledged']);

    // A passport that expires within six months of departure is refused.
    $form = quoteConversionForm($quote, $airline);
    $form['passengers'][1]['passport_expiry_date'] = now()->addMonths(3)->toDateString();
    $this->actingAs($agent)->post(route('ticketing.quotations.store-conversion', $quote), $form)
        ->assertSessionHasErrors('passengers.1.passport_expiry_date');

    expect($quote->fresh()->isQuotation())->toBeTrue();
});

test('a converted quotation is a booking waiting for approval, with every passenger and the quoted prices', function () {
    Notification::fake();
    $agent = User::factory()->create(['role' => 'ticketing']);
    User::factory()->create(['role' => 'admin']);
    $quote = quotedTrip($agent);
    $total = (float) $quote->total_amount;
    $airline = Airline::create(['name' => 'Philippine Airlines', 'code' => 'PR', 'is_active' => true]);

    $this->actingAs($agent)->post(route('ticketing.quotations.store-conversion', $quote), quoteConversionForm($quote, $airline))
        ->assertRedirect(route('ticketing.tickets.show', $quote))->assertSessionHas('success');

    $booking = $quote->fresh('passengers');
    expect($booking->isQuotation())->toBeFalse()
        ->and($booking->approval_status)->toBe(TicketBooking::APPROVAL_PENDING)
        ->and($booking->agent_acknowledged_by)->toBe($agent->id)
        ->and($booking->flight_number)->toBe('PR1845')
        ->and($booking->contact_email)->toBe('maria@example.com')
        ->and($booking->passengers)->toHaveCount(2)
        ->and($booking->passengers->pluck('first_name')->all())->toBe(['Maria', 'Ben'])
        ->and((float) $booking->total_amount)->toBe($total);

    Notification::assertSentTimes(TicketApprovalRequestedNotification::class, 1);
    $this->actingAs($agent)->get(route('ticketing.quotations.index'))->assertDontSee(route('ticketing.quotations.convert', $booking), false);
    // Only once.
    $this->actingAs($agent)->get(route('ticketing.quotations.convert', $booking))->assertRedirect(route('ticketing.tickets.show', $booking));
});

test('international travel needs each passport number and scan to convert', function () {
    Storage::fake('local');
    $agent = User::factory()->create(['role' => 'ticketing']);
    $quote = quotedTrip($agent, ['travel_type' => 'international', 'destination' => 'Tokyo', 'destination_country' => 'Japan', 'destination_city' => 'Tokyo', 'arrival_airport' => 'NRT']);
    $airline = Airline::create(['name' => 'Philippine Airlines', 'code' => 'PR', 'is_active' => true]);
    $emergency = ['emergency_contact_name' => 'Jose Santos', 'emergency_contact_relationship' => 'Spouse', 'emergency_contact_phone' => '0917'];

    $this->actingAs($agent)->post(route('ticketing.quotations.store-conversion', $quote), quoteConversionForm($quote, $airline, $emergency))
        ->assertSessionHasErrors(['passengers.0.passport_number', 'passengers.0.passport_file']);

    $form = quoteConversionForm($quote, $airline, $emergency);
    foreach ([0, 1] as $i) {
        $form['passengers'][$i]['passport_number'] = 'P00'.$i.'1234A';
        $form['passengers'][$i]['passport_file'] = UploadedFile::fake()->create("passport{$i}.pdf", 100, 'application/pdf');
    }
    $this->actingAs($agent)->post(route('ticketing.quotations.store-conversion', $quote), $form)->assertSessionHasNoErrors();

    expect($quote->fresh()->isQuotation())->toBeFalse()
        ->and($quote->fresh('passengers.documents')->passengers->every(fn ($p) => $p->documents->contains('document_type', 'passport_scan')))->toBeTrue();
});

test('only staff who may create bookings can convert', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $quote = quotedTrip($agent);
    $viewer = User::factory()->create(['role' => 'ticketing', 'permissions' => ['ticketing.view', 'ticketing.view_all']]);

    $this->actingAs($viewer)->get(route('ticketing.quotations.index'))->assertOk()->assertDontSee('Convert to booking');
    $this->actingAs($viewer)->get(route('ticketing.quotations.convert', $quote))->assertForbidden();
});
