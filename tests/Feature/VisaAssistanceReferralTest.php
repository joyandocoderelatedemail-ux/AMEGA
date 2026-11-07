<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\VisaAssistanceRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('filesystems.documents_disk', 'local'));
    Storage::fake('public');
    Notification::fake();
});

function bookingWithVisaChoice(string $visaStatus): array
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
            'visa_status' => $visaStatus,
        ]],
    ];
}

test('a passenger who needs visa assistance notifies the Visa Assistance desk, not the other desks', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);
    $admin = User::factory()->create(['role' => 'admin']);
    $cashier = User::factory()->create(['role' => 'cashier']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithVisaChoice('needs_assistance'))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($visaOfficer, VisaAssistanceRequestedNotification::class, function ($notification) {
        return $notification->passengers->count() === 1 && $notification->passengers->first()->full_name === 'Jose Rizal';
    });
    Notification::assertNotSentTo($admin, VisaAssistanceRequestedNotification::class);
    Notification::assertNotSentTo($cashier, VisaAssistanceRequestedNotification::class);
    Notification::assertNotSentTo($agent, VisaAssistanceRequestedNotification::class);
});

test('the admins are told when no one else holds the Visa Assistance desk', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithVisaChoice('needs_assistance'))->assertSessionHasNoErrors();

    Notification::assertSentTo($admin, VisaAssistanceRequestedNotification::class);
});

test('nobody is notified when no passenger needs visa assistance', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    User::factory()->create(['role' => 'visa_assistance']);

    foreach (['already_has_visa', 'visa_not_required'] as $choice) {
        $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithVisaChoice($choice))->assertSessionHasNoErrors();
    }

    Notification::assertNotSentTo(User::where('role', 'visa_assistance')->first(), VisaAssistanceRequestedNotification::class);
});

test('a passenger who needs visa assistance is not held for the visa papers, only for the signed waiver', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithVisaChoice('needs_assistance'))->assertSessionHasNoErrors();

    $ticket = TicketBooking::with('passengers.documents')->firstOrFail();

    expect($ticket->passengers->first()->visa_status)->toBe('needs_assistance')
        ->and($ticket->missingDocuments())->toBeEmpty()
        ->and($ticket->visaWaiverOutstanding())->toBeTrue()
        ->and($ticket->hasAllRequiredDocuments())->toBeFalse();
});

test('the visa step offers Need Visa Assistance between the other two choices', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($agent)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('value="needs_assistance"')
        ->toContain('Need Visa Assistance')
        ->toContain('The Visa Assistance team will be notified when this booking is saved');

    // Already Has Visa, then Need Visa Assistance, then Visa Not Required.
    expect(strpos($html, 'value="already_has_visa"'))->toBeLessThan(strpos($html, 'value="needs_assistance"'))
        ->and(strpos($html, 'value="needs_assistance"'))->toBeLessThan(strpos($html, 'value="visa_not_required"'));
});

test('the visa assistance email names the passengers and the trip', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance', 'name' => 'Vera Visa']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithVisaChoice('needs_assistance'))->assertSessionHasNoErrors();

    $ticket = TicketBooking::firstOrFail();
    $mail = (new VisaAssistanceRequestedNotification($ticket, $ticket->passengers))->toMail($visaOfficer);
    $text = collect([$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines])->implode("\n");

    expect($text)->toContain('Visa assistance needed - '.$ticket->booking_reference)
        ->toContain('Hello Vera Visa')
        ->toContain('Jose Rizal')
        ->toContain('P1122334A')
        ->toContain('Tokyo, Japan')
        ->and($mail->actionUrl)->toBe(route('visa.dashboard'));
});
