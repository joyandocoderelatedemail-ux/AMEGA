<?php

use App\Models\TicketBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Passenger documents land on the private document disk. Fake both disks so
    // the suite never writes real files into storage.
    Storage::fake(config('filesystems.documents_disk', 'local'));
    Storage::fake('public');
});

test('ticketing officer can access ticket wizard with international destinations', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($officer)->get(route('ticketing.tickets.create'));

    $response->assertStatus(200);
    $response->assertSee('International Tour Booking');
    $response->assertSee('Destination Country');
    $response->assertSee('Arrival Airport');
});

test('international booking requires passport upload and at least 6 months validity before departure', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $departure = Carbon::today()->addMonth(); // 1 month from now
    $shortPassportExpiry = Carbon::today()->addMonths(3); // only 3 months from now (less than 6 months after departure)

    $fakePassport = UploadedFile::fake()->image('passport.jpg');

    $response = $this->actingAs($officer)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'international',
        'package_type' => 'without_package',
        'destination_country' => 'Japan',
        'destination_city' => 'Tokyo',
        'arrival_airport' => 'NRT',
        'preferred_airline' => 'Philippine Airlines',
        'origin' => 'Manila (MNL)',
        'destination' => 'Tokyo, Japan',
        'trip_type' => 'round_trip',
        'travel_class' => 'economy',
        'preferred_flight_time' => 'morning',
        'departure_date' => $departure->toDateString(),
        'return_date' => $departure->copy()->addDays(7)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Maria Clara',
        'contact_email' => 'maria@example.com',
        'contact_phone' => '09171234567',
        'emergency_contact_name' => 'Crisostomo Ibarra',
        'emergency_contact_relationship' => 'Fiance',
        'emergency_contact_phone' => '09181234567',
        'passengers' => [
            [
                'first_name' => 'Maria',
                'last_name' => 'Clara',
                'date_of_birth' => '1995-05-15',
                'gender' => 'female',
                'nationality_type' => 'filipino',
                'passport_number' => 'P9876543A',
                'passport_expiry_date' => $shortPassportExpiry->toDateString(),
                'passport_file' => $fakePassport,
                'visa_status' => 'visa_not_required',
            ],
        ],
    ]);

    // Should fail validation due to 6-month validity rule
    $response->assertSessionHasErrors(['passengers.0.passport_expiry_date']);
});

test('international booking requires visa copy upload when already has visa is selected', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $departure = Carbon::today()->addMonths(2);
    $validPassportExpiry = $departure->copy()->addYears(3);

    $fakePassport = UploadedFile::fake()->image('passport.jpg');

    $response = $this->actingAs($officer)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'international',
        'package_type' => 'without_package',
        'destination_country' => 'United States',
        'destination_city' => 'New York',
        'arrival_airport' => 'JFK',
        'origin' => 'Manila (MNL)',
        'destination' => 'New York, USA',
        'trip_type' => 'round_trip',
        'travel_class' => 'economy',
        'departure_date' => $departure->toDateString(),
        'return_date' => $departure->copy()->addDays(14)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Jose Rizal',
        'contact_email' => 'jose@example.com',
        'contact_phone' => '09170001111',
        'emergency_contact_name' => 'Paciano Rizal',
        'emergency_contact_relationship' => 'Brother',
        'emergency_contact_phone' => '09180002222',
        'passengers' => [
            [
                'first_name' => 'Jose',
                'last_name' => 'Rizal',
                'date_of_birth' => '1990-06-19',
                'gender' => 'male',
                'nationality_type' => 'filipino',
                'passport_number' => 'P1122334A',
                'passport_expiry_date' => $validPassportExpiry->toDateString(),
                'passport_file' => $fakePassport,
                'visa_status' => 'already_has_visa', // Visa copy is missing!
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['passengers.0.visa_file']);
});

test('international booking saves successfully with all Phase 2 details', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $departure = Carbon::today()->addMonths(2);
    $validPassportExpiry = $departure->copy()->addYears(2);

    $fakePassport = UploadedFile::fake()->image('passport.jpg');
    $fakeVisa = UploadedFile::fake()->image('visa.jpg');

    $response = $this->actingAs($officer)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'international',
        'package_type' => 'without_package',
        'destination_country' => 'Japan',
        'destination_city' => 'Tokyo',
        'arrival_airport' => 'NRT (Narita)',
        'preferred_airline' => 'Japan Airlines',
        'origin' => 'Manila (MNL)',
        'destination' => 'Tokyo, Japan',
        'trip_type' => 'round_trip',
        'travel_class' => 'business',
        'preferred_flight_time' => 'morning',
        'departure_date' => $departure->toDateString(),
        'return_date' => $departure->copy()->addDays(7)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Luna',
        'contact_email' => 'juan.luna@example.com',
        'contact_phone' => '09173334444',
        'emergency_contact_name' => 'Antonio Luna',
        'emergency_contact_relationship' => 'Brother',
        'emergency_contact_phone' => '09185556666',
        'has_insurance' => 1,
        'insurance_plan' => 'standard',
        'selected_services' => ['hotel_booking', 'pocket_wifi', 'airport_transfer'],
        'special_requests_list' => ['special_meals', 'preferred_seat'],
        'special_requests' => 'Halal meal requested and window seat preferred.',
        'estimated_fare' => 45000.00,
        'taxes_amount' => 5500.00,
        'visa_assistance_fee' => 0,
        'insurance_fee' => 1850.00,
        'other_charges' => 1000.00,
        'total_amount' => 53350.00,
        'passengers' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Luna',
                'date_of_birth' => '1988-10-23',
                'gender' => 'male',
                'nationality_type' => 'filipino',
                'passport_number' => 'P5566778B',
                'passport_expiry_date' => $validPassportExpiry->toDateString(),
                'passport_file' => $fakePassport,
                'visa_status' => 'already_has_visa',
                'visa_file' => $fakeVisa,
            ],
        ],
    ]);

    $ticket = TicketBooking::where('contact_email', 'juan.luna@example.com')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->travel_type)->toBe('international');
    expect($ticket->destination_country)->toBe('Japan');
    expect($ticket->destination_city)->toBe('Tokyo');
    expect($ticket->travel_class)->toBe('business');
    expect($ticket->has_insurance)->toBeTrue();
    expect($ticket->insurance_plan)->toBe('standard');
    expect($ticket->emergency_contact_name)->toBe('Antonio Luna');
    expect((float) $ticket->total_amount)->toBe(53350.00);

    expect($ticket->passengers)->toHaveCount(1);
    $passenger = $ticket->passengers->first();
    expect($passenger->first_name)->toBe('Juan');
    expect($passenger->gender)->toBe('male');
    expect($passenger->documents)->toHaveCount(2); // passport and visa copy

    $response->assertRedirect(route('ticketing.tickets.show', $ticket));
    $response->assertSessionHas('success');
});

test('international booking requires passport photo and supporting documents when visa assistance is requested', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $departure = Carbon::today()->addMonths(3);
    $validPassportExpiry = $departure->copy()->addYears(2);

    $fakePassport = UploadedFile::fake()->image('passport.jpg');

    $response = $this->actingAs($officer)->post(route('ticketing.tickets.store'), [
        'travel_type' => 'international',
        'package_type' => 'without_package',
        'destination_country' => 'France',
        'destination_city' => 'Paris',
        'arrival_airport' => 'CDG',
        'origin' => 'Manila (MNL)',
        'destination' => 'Paris, France',
        'trip_type' => 'one_way',
        'travel_class' => 'economy',
        'departure_date' => $departure->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Graciano Lopez Jaena',
        'contact_email' => 'graciano@example.com',
        'contact_phone' => '09179998888',
        'emergency_contact_name' => 'Mariano Ponce',
        'emergency_contact_relationship' => 'Friend',
        'emergency_contact_phone' => '09189997777',
        'passengers' => [
            [
                'first_name' => 'Graciano',
                'last_name' => 'Lopez Jaena',
                'date_of_birth' => '1985-12-18',
                'gender' => 'male',
                'nationality_type' => 'filipino',
                'passport_number' => 'P7788990C',
                'passport_expiry_date' => $validPassportExpiry->toDateString(),
                'passport_file' => $fakePassport,
                'visa_status' => 'needs_assistance', // Missing passport photo & supporting doc
                'visa_assistance_type' => 'Schengen Tourist Visa',
                'intended_stay_days' => 30,
                'purpose_of_travel' => 'Conference',
            ],
        ],
    ]);

    $response->assertSessionHasErrors([
        'passengers.0.passport_photo_file',
        'passengers.0.supporting_doc_file',
    ]);
});
