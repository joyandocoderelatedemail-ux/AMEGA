<?php

use App\Models\Destination;
use App\Models\TicketBooking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('ticketing officer can access new ticket creation wizard', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'));

    $response->assertStatus(200);
    $response->assertSee('New Ticket Booking Wizard');

    // Requirements are gathered before the destination, so the first step only
    // collects what the document rules depend on.
    $response->assertSee('Step 1: Travel Type &amp; Passengers', false);
    $response->assertSee('Step 2: Passport Validation &amp; Upload', false);
    // Trip & Flight lives inside the destination step, and visa, insurance,
    // services and special requests share the contact step.
    $response->assertSee('Step 3: Destination &amp; Flight', false);
    $response->assertSee('Step 5: Visa, Contact &amp; Extras', false);
    $response->assertDontSee('Step 4: Trip &amp; Flight Specifications', false);
});

test('the wizard offers an upload field for every document the server requires', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'));

    $response->assertStatus(200);

    // Without these inputs a domestic booking can never satisfy validation.
    foreach (['passport_file', 'government_id_file', 'birth_cert_file', 'school_id_file', 'exit_clearance_file'] as $field) {
        $response->assertSee("][{$field}]", false);
    }
});

test('the wizard collects the booker contact details domestic bookings require', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'));

    $response->assertStatus(200);
    $response->assertSee('name="contact_name"', false);
    $response->assertSee('name="contact_email"', false);
    $response->assertSee('name="contact_phone"', false);
});

test('validation rejects mismatched passenger counts', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay',
        'trip_type' => 'one_way',
        'departure_date' => Carbon::tomorrow()->toDateString(),
        'total_passengers' => 3,
        'adults_count' => 1,
        'children_count' => 1,
        'infants_count' => 0, // Sum = 2, total = 3
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'passengers' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('total_passengers');
});

test('validation rejects filipino passport with less than 6 months validity', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $departureDate = Carbon::today()->addMonths(1);
    $invalidExpiry = $departureDate->copy()->addMonths(3); // Less than 6 months

    $passportFile = UploadedFile::fake()->create('passport.pdf', 500);
    $govIdFile = UploadedFile::fake()->image('govid.jpg');

    $response = $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Siargao',
        'trip_type' => 'round_trip',
        'departure_date' => $departureDate->toDateString(),
        'return_date' => $departureDate->copy()->addDays(5)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'passengers' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                'passport_number' => 'P1234567A',
                'passport_expiry_date' => $invalidExpiry->toDateString(),
                'passport_file' => $passportFile,
                'government_id_file' => $govIdFile,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('passengers.0.passport_expiry_date');
});

test('ticketing officer can create a domestic ticket booking with passengers and files', function () {
    Storage::fake('public');
    Storage::fake(config('filesystems.documents_disk', 'local'));
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $destination = Destination::create([
        'name' => 'El Nido Paradise',
        'location' => 'Palawan',
        'type' => 'domestic',
        'description' => 'Test description',
        'image' => 'images/test.jpg',
        'starting_price' => '₱15,000',
    ]);

    $package = TravelPackage::create([
        'title' => 'El Nido 4D3N Special',
        'destination_id' => $destination->id,
        'description' => 'Test package description',
        'image' => 'images/test.jpg',
        'price' => '₱15,000',
        'duration' => '4 Days 3 Nights',
    ]);

    $departureDate = Carbon::today()->addMonths(2);
    $validPassportExpiry = $departureDate->copy()->addYears(2);

    $passportFile = UploadedFile::fake()->create('passport_scan.pdf', 500, 'application/pdf');
    $govIdFile = UploadedFile::fake()->image('govid.jpg');

    $passportFile2 = UploadedFile::fake()->create('passport_us.pdf', 500, 'application/pdf');
    $visaFile2 = UploadedFile::fake()->image('evisa.jpg');

    $response = $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'with_package',
        'travel_package_id' => $package->id,
        'origin' => 'Manila (MNL)',
        'destination' => 'El Nido Paradise',
        'trip_type' => 'round_trip',
        'departure_date' => $departureDate->toDateString(),
        'return_date' => $departureDate->copy()->addDays(4)->toDateString(),
        'total_passengers' => 2,
        'adults_count' => 2,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria@example.com',
        'contact_phone' => '09181112222',
        'travel_tax_included' => true,
        'special_requests' => 'Window seats preferred',
        'passengers' => [
            [
                'first_name' => 'Maria',
                'middle_name' => 'Clara',
                'last_name' => 'Santos',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                'passport_number' => 'P9876543B',
                'passport_expiry_date' => $validPassportExpiry->toDateString(),
                'travel_tax_included' => true,
                'passport_file' => $passportFile,
                'government_id_file' => $govIdFile,
            ],
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'passenger_type' => 'adult',
                'nationality_type' => 'foreign_national',
                'passport_number' => 'US12345678',
                'passport_expiry_date' => $validPassportExpiry->toDateString(),
                'visa_type' => 'e_visa',
                'stay_duration_months' => 2,
                'passport_file' => $passportFile2,
                'visa_file' => $visaFile2,
            ],
        ],
    ]);

    $ticket = TicketBooking::where('contact_email', 'maria@example.com')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->travel_type)->toBe('domestic');
    expect($ticket->total_passengers)->toBe(2);
    expect($ticket->package_name)->toBe('El Nido 4D3N Special');
    expect($ticket->passengers)->toHaveCount(2);

    $passenger1 = $ticket->passengers->first();
    expect($passenger1->first_name)->toBe('Maria');
    expect($passenger1->documents)->toHaveCount(2);

    $passenger2 = $ticket->passengers->last();
    expect($passenger2->first_name)->toBe('John');
    expect($passenger2->documents)->toHaveCount(2);

    $response->assertRedirect(route('ticketing.tickets.show', $ticket));
    $response->assertSessionHas('success');
});

test('a domestic filipino adult needs a government ID rather than a passport', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $departureDate = Carbon::today()->addMonths(2);

    $response = $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay',
        'trip_type' => 'one_way',
        'departure_date' => $departureDate->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'passengers' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                // No documents provided at all.
            ],
        ],
    ]);

    // Per the domestic document matrix a passport is not required here; the
    // government ID is what holds back the ticket, not the booking.
    $response->assertSessionHasNoErrors();
    expect(array_keys(TicketBooking::firstOrFail()->missingDocuments()->first()['missing']))->toBe(['government_id']);
});

test('a filipino adult without a government ID is booked but flagged as missing it', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $departureDate = Carbon::today()->addMonths(2);
    $passportFile = UploadedFile::fake()->create('passport.pdf', 500);

    $response = $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay',
        'trip_type' => 'one_way',
        'departure_date' => $departureDate->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'passengers' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                'passport_file' => $passportFile,
                // No government_id_file provided
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    expect(TicketBooking::firstOrFail()->hasAllRequiredDocuments())->toBeFalse();
});

test('an infant without a birth certificate is booked but flagged as missing it', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $departureDate = Carbon::today()->addMonths(2);
    $passportFile = UploadedFile::fake()->create('passport.pdf', 500);
    $govIdFile = UploadedFile::fake()->image('govid.jpg');

    $response = $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu',
        'trip_type' => 'one_way',
        'departure_date' => $departureDate->toDateString(),
        'total_passengers' => 2,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'passengers' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                'passport_file' => $passportFile,
                'government_id_file' => $govIdFile,
            ],
            [
                'first_name' => 'Baby',
                'last_name' => 'Dela Cruz',
                'passenger_type' => 'infant',
                'nationality_type' => 'filipino',
                'passport_file' => $passportFile,
                // No birth_cert_file provided
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $gaps = TicketBooking::firstOrFail()->missingDocuments();
    expect($gaps)->toHaveCount(1)
        ->and($gaps->first()['passenger']->first_name)->toBe('Baby')
        ->and(array_keys($gaps->first()['missing']))->toBe(['birth_certificate']);
});

test('ticket details show view renders successfully', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-202609-TEST',
        'created_by' => $ticketing->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Batanes Islands',
        'trip_type' => 'one_way',
        'departure_date' => Carbon::tomorrow(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Pedro Penduko',
        'contact_email' => 'pedro@example.com',
        'contact_phone' => '09199998888',
        'status' => 'pending',
    ]);

    $ticket->passengers()->create([
        'passenger_number' => 1,
        'passenger_type' => 'adult',
        'nationality_type' => 'filipino',
        'first_name' => 'Pedro',
        'last_name' => 'Penduko',
    ]);

    $response = $this->actingAs($ticketing)->get(route('ticketing.tickets.show', $ticket));

    $response->assertStatus(200);
    $response->assertSee('TKT-DOM-202609-TEST');
    $response->assertSee('Pedro Penduko');
    $response->assertSee('Batanes Islands');
});

test('wizard step panels carry no x-transition, which freezes their display', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'));
    $html = $response->getContent();

    // An opacity transition on the step containers leaves `display` frozen when
    // a travel-type change re-renders the wizard mid-transition: the header
    // advances to the next step but the panel never swaps. Guard against it
    // coming back, since nothing else in the suite can catch client behaviour.
    expect($html)->not->toMatch('/x-show="(?:[^"]*&& )?currentStep[^"]*"\s+x-transition/');

    // And every step in the sequence must still have a panel to render.
    foreach ([1, 2, 3, 13, 5, 6, 10, 12] as $step) {
        expect($html)->toContain('currentStep === '.$step);
    }
});

test('a failed step check keeps the user on that step instead of bouncing them back', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))->getContent();

    // A step's Continue button checks that same step, and a failed check shows
    // the problems on the step it checked. If a panel checked another step's
    // fields, an empty field would throw the user to a different part of the form.
    preg_match_all('/<div x-show="currentStep === (\d+)"(.*?)(?=<div x-show="currentStep === |<\/form>)/s', $html, $panels, PREG_SET_ORDER);

    expect($panels)->not->toBeEmpty();

    foreach ($panels as [$whole, $step, $body]) {
        preg_match_all('/checkStep\((\d+)\)/', $body, $checks);
        foreach ($checks[1] as $checked) {
            expect($checked)->toBe($step, "panel {$step} checks step {$checked}");
        }
    }

    // Problems are listed on the page, not raised one at a time in pop-ups.
    expect($html)->not->toContain("alert('Step")
        ->and($html)->toContain('id="wizard-errors"');
});

test('each checked field can show its own problem', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))->getContent();

    foreach (['origin', 'destination', 'departure_date', 'return_date', 'contact_name', 'contact_email', 'contact_phone', 'emergency_contact_name'] as $field) {
        expect($html)->toContain('data-error-key="'.$field.'"')
            ->and($html)->toContain('x-text="'.e("errors['{$field}']").'"');
    }

    expect($html)->toContain(":data-error-key=\"'passengers.' + idx + '.first_name'\"");
});

test('optional extras are collapsed sections of the contact step', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))->getContent();

    // Insurance, services and special requests no longer each cost a step.
    expect($html)->toContain('Optional Extras')
        ->and(substr_count($html, '<details'))->toBe(3)
        ->and($html)->toContain('name="has_insurance"')
        ->and($html)->toContain("'selected_services[]'")
        ->and($html)->toContain('name="special_requests"');
});

test('ticketing officer can create a booking with customized package specifications', function () {
    Storage::fake('public');
    Storage::fake(config('filesystems.documents_disk', 'local'));
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $departureDate = Carbon::today()->addMonths(2);
    $govIdFile1 = UploadedFile::fake()->image('govid1.jpg');
    $govIdFile2 = UploadedFile::fake()->image('govid2.jpg');

    $response = $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'custom_package',
        'travel_package_id' => 'custom',
        'custom_hotel_name' => 'Paradise Seaview Resort',
        'custom_preferred_hotel' => 'Station 1 Beachfront',
        'custom_has_breakfast' => '1',
        'custom_bed_config' => '1 King Bed + 1 Extra Bed',
        'custom_check_in_date' => $departureDate->toDateString(),
        'custom_check_out_date' => $departureDate->copy()->addDays(3)->toDateString(),
        'custom_smoking_preference' => 'non_smoking',
        'custom_pet_friendly' => '0',
        'custom_has_transportation' => '1',
        'custom_transportation_type' => 'private_van',
        'custom_special_requests' => 'High floor, ocean view room requested.',
        'custom_estimated_budget' => '45000',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay Island',
        'trip_type' => 'round_trip',
        'departure_date' => $departureDate->toDateString(),
        'return_date' => $departureDate->copy()->addDays(3)->toDateString(),
        'total_passengers' => 2,
        'adults_count' => 2,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Ana Reyes',
        'contact_email' => 'ana.reyes@example.com',
        'contact_phone' => '09170001122',
        'estimated_fare' => 45000,
        'total_amount' => 45000,
        'passengers' => [
            [
                'first_name' => 'Ana',
                'last_name' => 'Reyes',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                'government_id_file' => $govIdFile1,
            ],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Reyes',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
                'government_id_file' => $govIdFile2,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $booking = TicketBooking::where('contact_email', 'ana.reyes@example.com')->first();
    expect($booking)->not->toBeNull();
    expect($booking->package_type)->toBe('custom_package');
    expect($booking->isCustomPackage())->toBeTrue();
    expect($booking->custom_package_specs['hotel_name'])->toBe('Paradise Seaview Resort');
    expect($booking->custom_package_specs['has_breakfast'])->toBeTrue();
    expect($booking->custom_package_specs['bed_config'])->toBe('1 King Bed + 1 Extra Bed');
    expect($booking->custom_package_specs['transportation_type'])->toBe('private_van');

    $this->assertDatabaseHas('custom_package_inquiries', [
        'client_email' => 'ana.reyes@example.com',
        'status' => 'booked',
    ]);

    $showHtml = $this->actingAs($ticketing)->get(route('ticketing.tickets.show', $booking))->getContent();
    expect($showHtml)->toContain('Customized Package Specifications');
    expect($showHtml)->toContain('Paradise Seaview Resort');
    expect($showHtml)->toContain('Breakfast Included');
});

test('optional extras are offered on domestic bookings as well as international', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))->getContent();

    // The extras wrapper must not be hidden behind the international travel type.
    expect($html)->toMatch('/<div class="space-y-3 pt-4 border-t border-gray-100">\s*<div>\s*<h3[^>]*>Optional Extras<\/h3>/');
});

test('a domestic booking keeps the insurance, services and requests chosen as extras', function () {
    Storage::fake('public');
    Storage::fake(config('filesystems.documents_disk', 'local'));
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $departureDate = Carbon::today()->addMonths(2);

    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu',
        'trip_type' => 'one_way',
        'departure_date' => $departureDate->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'extras@example.com',
        'contact_phone' => '09181112222',
        'has_insurance' => '1',
        'insurance_plan' => 'basic',
        'selected_services' => ['hotel_booking', 'airport_transfer'],
        'special_requests_list' => ['preferred_seat'],
        'passengers' => [[
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'passenger_type' => 'adult',
            'nationality_type' => 'filipino',
            'government_id_file' => UploadedFile::fake()->image('govid.jpg'),
        ]],
    ])->assertSessionHasNoErrors();

    $ticket = TicketBooking::where('contact_email', 'extras@example.com')->firstOrFail();

    expect($ticket->has_insurance)->toBeTrue()
        ->and($ticket->insurance_plan)->toBe('basic')
        ->and($ticket->selected_services)->toBe(['hotel_booking', 'airport_transfer'])
        ->and($ticket->special_requests_list)->toBe(['preferred_seat']);
});

test('the concierge services include e-travel, arrival card and flight delays', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))
        ->assertSee("key: 'e_travel', label: 'E-Travel'", false)
        ->assertSee("key: 'arrival_card', label: 'Arrival Card'", false)
        ->assertSee("key: 'flight_delays', label: 'Flight Delays'", false);
});

test('the wizard has an airline restrictions step right after the flight', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))
        ->assertSee('[1, 2, 3, 13, 5, 10, 12]', false)
        ->assertSee('name="airline_restrictions[]"', false);
});

test('a booking keeps the airline restrictions entered in the wizard, without blank rows', function () {
    Storage::fake('public');
    Storage::fake(config('filesystems.documents_disk', 'local'));
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu',
        'trip_type' => 'one_way',
        'departure_date' => Carbon::today()->addMonths(2)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'restrictions@example.com',
        'contact_phone' => '09181112222',
        'airline_restrictions' => ['Non-refundable', '  ', ' No name changes allowed '],
        'passengers' => [[
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'passenger_type' => 'adult',
            'nationality_type' => 'filipino',
            'government_id_file' => UploadedFile::fake()->image('govid.jpg'),
        ]],
    ])->assertSessionHasNoErrors();

    $ticket = TicketBooking::where('contact_email', 'restrictions@example.com')->firstOrFail();

    expect($ticket->airline_restrictions)->toBe(['Non-refundable', 'No name changes allowed']);

    $this->get(route('ticketing.tickets.voucher', $ticket))
        ->assertOk()
        ->assertSee('Airline restrictions')
        ->assertSee('No name changes allowed');
});

test('airline restrictions can be edited from the ticket page', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-202609-RST1',
        'created_by' => $ticketing->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Coron, Palawan',
        'trip_type' => 'one_way',
        'departure_date' => Carbon::tomorrow(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'status' => 'pending',
        'airline_restrictions' => ['Non-refundable'],
    ]);

    $this->actingAs($ticketing)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Airline Restrictions')
        ->assertSee(route('ticketing.tickets.restrictions', $ticket), false);

    $this->put(route('ticketing.tickets.restrictions', $ticket), [
        'airline_restrictions' => ['Refundable less a ₱1,500 fee', ''],
    ])->assertRedirect()->assertSessionHas('success');

    expect($ticket->fresh()->airline_restrictions)->toBe(['Refundable less a ₱1,500 fee']);

    $this->put(route('ticketing.tickets.restrictions', $ticket), ['airline_restrictions' => ['']]);

    expect($ticket->fresh()->airline_restrictions)->toBeNull();
});
