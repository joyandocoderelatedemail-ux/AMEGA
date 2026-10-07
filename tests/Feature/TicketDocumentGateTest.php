<?php

use App\Models\TicketBooking;
use App\Models\TicketPassenger;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function gateTicket(User $officer, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $officer->id,
        'travel_type' => 'domestic',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
    ], $overrides));
}

function gatePassenger(TicketBooking $ticket, array $attributes = []): TicketPassenger
{
    return $ticket->passengers()->create($attributes + [
        'passenger_number' => 1,
        'passenger_type' => 'adult',
        'nationality_type' => 'filipino',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);
}

test('a booking missing documents can be paid in full but the ticket cannot be issued until they are uploaded', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = gateTicket($officer);
    $passenger = gatePassenger($ticket);
    $ticket->recordPayment(10000);

    expect($ticket->fresh()->isFullyPaid())->toBeTrue()
        ->and($ticket->fresh()->canBeIssued())->toBeFalse();

    // The ticket page names what is missing and offers an upload for it.
    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Required documents are still missing')
        ->assertSee('Government ID')
        ->assertSee('Upload missing documents')
        ->assertSee('id="missing-documents-'.$passenger->id.'"', false)
        ->assertDontSee('name="data_privacy_consent"', false);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1'])
        ->assertSessionHas('error');
    expect($ticket->fresh()->isIssued())->toBeFalse();

    // Uploading the missing document opens the issue action.
    $this->actingAs($officer)->post(route('ticketing.tickets.passengers.documents.store', [$ticket, $passenger]), [
        'document_type' => 'government_id',
        'file' => UploadedFile::fake()->image('umid.jpg'),
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($passenger->documents()->where('document_type', 'government_id')->count())->toBe(1)
        ->and($ticket->fresh()->canBeIssued())->toBeTrue();

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('name="data_privacy_consent"', false);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1'])
        ->assertSessionHas('success');
    expect($ticket->fresh()->isIssued())->toBeTrue();
});

test('uploading a document again replaces the old one, and bad files are refused', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = gateTicket($officer);
    $passenger = gatePassenger($ticket);
    $url = route('ticketing.tickets.passengers.documents.store', [$ticket, $passenger]);

    $this->actingAs($officer)->post($url, ['document_type' => 'government_id', 'file' => UploadedFile::fake()->image('first.jpg')]);
    $first = $passenger->documents()->firstOrFail()->file_path;

    $this->actingAs($officer)->post($url, ['document_type' => 'government_id', 'file' => UploadedFile::fake()->image('second.jpg')]);

    expect($passenger->documents()->count())->toBe(1)
        ->and($passenger->documents()->first()->original_name)->toBe('second.jpg');
    Storage::disk(DocumentStorage::diskName())->assertMissing($first);

    $this->actingAs($officer)->post($url, ['document_type' => 'government_id', 'file' => UploadedFile::fake()->create('id.exe', 10)])
        ->assertSessionHasErrors('file');
    $this->actingAs($officer)->post($url, ['document_type' => 'not_a_document', 'file' => UploadedFile::fake()->image('x.jpg')])
        ->assertSessionHasErrors('document_type');
    expect($passenger->documents()->count())->toBe(1);
});

test('documents cannot be added once the ticket is issued, or to a passenger of another booking', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = gateTicket($officer);
    $stranger = gatePassenger(gateTicket($officer), ['first_name' => 'Maria', 'last_name' => 'Clara']);
    $upload = fn () => ['document_type' => 'government_id', 'file' => UploadedFile::fake()->image('id.jpg')];

    $this->actingAs($officer)->post(route('ticketing.tickets.passengers.documents.store', [$ticket, $stranger]), $upload())
        ->assertNotFound();

    $passenger = gatePassenger($ticket);
    $ticket->recordPayment(10000);
    $ticket->markAsIssued($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.passengers.documents.store', [$ticket, $passenger]), $upload())
        ->assertSessionHas('error');
    expect($passenger->documents()->count())->toBe(0);
});

test('what a passenger must hand in follows the trip and the traveller', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $domestic = gateTicket($officer);
    $international = gateTicket($officer, ['travel_type' => 'international']);
    $missing = fn (TicketPassenger $passenger): array => array_keys($passenger->load('booking')->missingDocuments());

    expect($missing(gatePassenger($domestic)))->toBe(['government_id'])
        ->and($missing(gatePassenger($domestic, ['passenger_type' => 'child'])))->toBe(['school_id'])
        ->and($missing(gatePassenger($domestic, ['passenger_type' => 'infant'])))->toBe(['birth_certificate'])
        ->and($missing(gatePassenger($domestic, ['nationality_type' => 'foreign_national', 'visa_type' => 'e_visa', 'stay_duration_months' => 8])))
        ->toBe(['passport_scan', 'visa_scan', 'exit_clearance'])
        ->and($missing(gatePassenger($international)))->toBe(['passport_scan'])
        ->and($missing(gatePassenger($international, ['visa_status' => 'needs_assistance'])))
        ->toBe(['passport_scan', 'passport_photo', 'supporting_documents']);

    // A child's birth certificate stands in for the school ID.
    $child = gatePassenger($domestic, ['passenger_type' => 'child']);
    $child->documents()->create(['document_type' => 'birth_certificate', 'file_path' => 'x', 'original_name' => 'psa.jpg', 'status' => 'uploaded']);
    expect($missing($child->fresh()))->toBe([]);
});
