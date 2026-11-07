<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Services\BookingAgreementDrafter;
use App\Services\ClientProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('filesystems.documents_disk', 'local'));
    Storage::fake('public');
});

function internationalWithFlyer(array $passenger): array
{
    $departure = Carbon::today()->addMonths(2);

    return [...bookingFlight(),
        'travel_type' => 'international',
        'package_type' => 'without_package',
        'destination_country' => 'Japan',
        'destination_city' => 'Tokyo',
        'arrival_airport' => 'NRT',
        'origin' => 'Manila (MNL)',
        'destination' => 'Tokyo, Japan',
        'trip_type' => 'round_trip',
        'travel_class' => 'economy',
        'departure_date' => $departure->toDateString(),
        'return_date' => $departure->copy()->addDays(7)->toDateString(),
        'total_passengers' => 1, 'adults_count' => 1, 'children_count' => 0, 'infants_count' => 0,
        'contact_name' => 'Jose Rizal', 'contact_email' => 'jose@example.com', 'contact_phone' => '09170001111',
        'emergency_contact_name' => 'Paciano Rizal', 'emergency_contact_relationship' => 'Brother', 'emergency_contact_phone' => '09180002222',
        'passengers' => [[
            'first_name' => 'Jose', 'last_name' => 'Rizal', 'date_of_birth' => '1990-06-19', 'gender' => 'male',
            'nationality_type' => 'filipino', 'passport_number' => 'P1122334A',
            'passport_expiry_date' => $departure->copy()->addYears(2)->toDateString(),
            'passport_file' => UploadedFile::fake()->image('passport.jpg'),
            'visa_status' => 'already_has_visa',
        ] + $passenger],
    ];
}

test('the passenger\'s frequent flyer membership is saved with the booking and printed in the agreement', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), internationalWithFlyer(['frequent_flyer_membership' => 'GetGo 1234567890']))
        ->assertSessionHasNoErrors();

    $ticket = TicketBooking::with('passengers')->firstOrFail();
    expect($ticket->passengers->first()->frequent_flyer_membership)->toBe('GetGo 1234567890');

    $agreement = app(BookingAgreementDrafter::class)->ensureFor($ticket, $officer);

    $this->actingAs($officer)->get(route('ticketing.agreements.show', $agreement))
        ->assertOk()->assertSee('Frequent flyer GetGo 1234567890');
});

test('a passenger without a membership shows no frequent flyer line', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), internationalWithFlyer(['frequent_flyer_membership' => '']))
        ->assertSessionHasNoErrors();

    $ticket = TicketBooking::with('passengers')->firstOrFail();
    expect($ticket->passengers->first()->frequent_flyer_membership)->toBeNull();

    $agreement = app(BookingAgreementDrafter::class)->ensureFor($ticket, $officer);
    $this->actingAs($officer)->get(route('ticketing.agreements.show', $agreement))->assertOk()->assertDontSee('Frequent flyer');
});

test('picking a registered client carries their membership into the wizard, for any trip type', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    // The passenger row is filled from the client's profile and the Step 5 field is bound to it.
    expect($html)->toContain("p.frequent_flyer_membership = client.frequent_flyer_membership || '';")
        ->toContain("frequent_flyer_membership: '',")
        ->toContain("passengers[' + idx + '][frequent_flyer_membership]")
        ->toContain('Frequent Flyer Membership');

    expect(ClientProfileService::ticketProfile(User::factory()->create(['role' => 'client', 'frequent_flyer_membership' => 'GetGo 99']))['frequent_flyer_membership'])
        ->toBe('GetGo 99');
});
