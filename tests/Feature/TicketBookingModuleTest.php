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
    $response->assertSee('Select Travel Category');
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

test('validation rejects passenger without mandatory passport photo', function () {
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
                // No passport_file provided
            ],
        ],
    ]);

    $response->assertSessionHasErrors('passengers.0.passport_file');
});

test('validation rejects filipino adult without mandatory government ID photo', function () {
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

    $response->assertSessionHasErrors('passengers.0.government_id_file');
});

test('validation rejects infant without mandatory birth certificate photo', function () {
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

    $response->assertSessionHasErrors('passengers.1.birth_cert_file');
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
