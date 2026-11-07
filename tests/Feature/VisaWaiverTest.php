<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\TicketBookedNotification;
use App\Services\TicketDocumentPdf;
use App\Support\VisaWaiver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('filesystems.documents_disk', 'local'));
    Storage::fake('public');
    Notification::fake();
});

function waiverBooking(User $agent, string $visaStatus): TicketBooking
{
    $departure = Carbon::today()->addMonths(2);

    test()->actingAs($agent)->post(route('ticketing.tickets.store'), [...bookingFlight(),
        'travel_type' => 'international',
        'package_type' => 'without_package',
        'destination_country' => 'France',
        'destination_city' => 'Paris',
        'arrival_airport' => 'CDG',
        'origin' => 'Manila (MNL)',
        'destination' => 'Paris, France',
        'trip_type' => 'round_trip',
        'travel_class' => 'economy',
        'departure_date' => $departure->toDateString(),
        'return_date' => $departure->copy()->addDays(10)->toDateString(),
        'total_passengers' => 1, 'adults_count' => 1, 'children_count' => 0, 'infants_count' => 0,
        'contact_name' => 'Graciano Lopez Jaena', 'contact_email' => 'graciano@example.com', 'contact_phone' => '09179998888',
        'emergency_contact_name' => 'Mariano Ponce', 'emergency_contact_relationship' => 'Friend', 'emergency_contact_phone' => '09189997777',
        'passengers' => [[
            'first_name' => 'Graciano', 'last_name' => 'Lopez Jaena', 'date_of_birth' => '1985-12-18', 'gender' => 'male',
            'nationality_type' => 'filipino', 'passport_number' => 'P7788990C',
            'passport_expiry_date' => $departure->copy()->addYears(2)->toDateString(),
            'passport_file' => UploadedFile::fake()->image('passport.jpg'),
            'visa_status' => $visaStatus,
        ]],
    ])->assertSessionHasNoErrors();

    return TicketBooking::with('passengers')->latest('id')->firstOrFail();
}

test('a booking with a passenger who needs visa assistance has a Visa Waiver in the official AMEGA layout', function () {
    $agent = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Agent']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    $this->actingAs($agent)->get(route('ticketing.tickets.visa-waiver', $ticket))
        ->assertOk()
        // The consent form's letterhead, footer and signature block.
        ->assertSee('Endless Possibilities in Travel and Tourism')
        ->assertSee('Astrofield Building, Balibago', false)
        ->assertSee('Visa Waiver and Acknowledgment')
        ->assertSee($ticket->booking_reference)
        ->assertSee('Graciano Lopez Jaena')
        ->assertSee('P7788990C')
        ->assertSee('Paris, France')
        ->assertSee('not yet been approved or issued', false)
        ->assertSee('at my own risk', false)
        ->assertSee('Client')->assertSee('Handling Agent')->assertSee('Ana Agent');
});

test('the ticket page links to the waiver only when a passenger needs visa assistance', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $needs = waiverBooking($agent, 'needs_assistance');
    $this->actingAs($agent)->get(route('ticketing.tickets.show', $needs))->assertOk()
        ->assertSee(route('ticketing.tickets.visa-waiver', $needs), false);

    $none = waiverBooking($agent, 'visa_not_required');
    $this->actingAs($agent)->get(route('ticketing.tickets.show', $none))->assertOk()
        ->assertDontSee(route('ticketing.tickets.visa-waiver', $none), false);
});

test('there is no waiver for a booking where nobody needs visa assistance', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'already_has_visa');

    expect(VisaWaiver::forTicket($ticket))->toBeNull();

    $this->actingAs($agent)->get(route('ticketing.tickets.visa-waiver', $ticket))
        ->assertRedirect(route('ticketing.tickets.show', $ticket))->assertSessionHas('error');
    $this->actingAs($agent)->get(route('ticketing.tickets.visa-waiver.pdf', $ticket))
        ->assertRedirect(route('ticketing.tickets.show', $ticket));
});

test('the waiver downloads as a PDF', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    $response = $this->actingAs($agent)->get(route('ticketing.tickets.visa-waiver.pdf', $ticket))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('Visa-Waiver-'.$ticket->booking_reference.'.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('the waiver is attached to the client emails along with the consent form', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    $mail = new MailMessage;
    $attached = TicketDocumentPdf::attachTo($mail, $ticket);

    expect($attached)->toContain('your Visa Waiver')->toContain('your Data Privacy Consent Form')
        ->and(collect($mail->rawAttachments)->pluck('name')->all())->toContain('Visa-Waiver-'.$ticket->booking_reference.'.pdf');
});

test('another officer cannot print a waiver for a booking that is not theirs', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($owner, 'needs_assistance');

    $this->actingAs($other)->get(route('ticketing.tickets.visa-waiver', $ticket))->assertNotFound();
});

test('the ticket cannot be issued until the client signed the waiver and the signed copy is uploaded', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');
    $ticket->forceFill(['total_amount' => 10000, 'approval_status' => 'approved'])->save();
    $ticket->recordPayment(10000);

    $ticket = $ticket->fresh();
    expect($ticket->isFullyPaid())->toBeTrue()
        ->and($ticket->visaWaiverOutstanding())->toBeTrue()
        ->and($ticket->canBeIssued())->toBeFalse();

    $this->actingAs($agent)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => 1])
        ->assertSessionHas('error', 'The ticket cannot be issued until the client has signed the Visa Waiver and the signed copy is uploaded.');
    expect($ticket->fresh()->isIssued())->toBeFalse();

    $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), [
        'client_signed' => 1,
        'file' => UploadedFile::fake()->create('signed-waiver.pdf', 120, 'application/pdf'),
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $ticket = $ticket->fresh();
    expect($ticket->hasSignedVisaWaiver())->toBeTrue()
        ->and($ticket->visa_waiver_signed_by)->toBe($agent->id)
        ->and($ticket->visa_waiver_name)->toBe('signed-waiver.pdf')
        ->and($ticket->visaWaiverOutstanding())->toBeFalse()
        ->and($ticket->canBeIssued())->toBeTrue();
});

test('signing needs the tick box and the uploaded file', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), [
        'file' => UploadedFile::fake()->create('signed.pdf', 50, 'application/pdf'),
    ])->assertSessionHasErrors('client_signed');

    $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), ['client_signed' => 1])
        ->assertSessionHasErrors('file');

    $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), [
        'client_signed' => 1,
        'file' => UploadedFile::fake()->create('virus.exe', 10),
    ])->assertSessionHasErrors('file');

    expect($ticket->fresh()->hasSignedVisaWaiver())->toBeFalse();
});

test('the signed copy is private, downloadable by the officer and replaced on a new upload', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    $this->actingAs($agent)->get(route('ticketing.tickets.visa-waiver.signed', $ticket))->assertNotFound();

    $sign = fn (string $name) => $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), [
        'client_signed' => 1,
        'file' => UploadedFile::fake()->create($name, 40, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $sign('first.pdf');
    $first = $ticket->fresh()->visa_waiver_path;
    Storage::disk(config('filesystems.documents_disk', 'local'))->assertExists($first);

    $this->actingAs($agent)->get(route('ticketing.tickets.visa-waiver.signed', $ticket))->assertOk();
    $this->actingAs($other)->get(route('ticketing.tickets.visa-waiver.signed', $ticket))->assertNotFound();

    $sign('second.pdf');
    $second = $ticket->fresh()->visa_waiver_path;
    expect($second)->not->toBe($first)->and($ticket->fresh()->visa_waiver_name)->toBe('second.pdf');
    Storage::disk(config('filesystems.documents_disk', 'local'))->assertMissing($first);
    Storage::disk(config('filesystems.documents_disk', 'local'))->assertExists($second);
});

test('the booking page shows the waiver panel with the tick box and upload, and its signed state', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ticket))->assertOk()
        ->assertSee('The client signed the waiver')
        ->assertSee('Upload signed waiver')
        ->assertSee('before this ticket can be issued');

    $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), [
        'client_signed' => 1,
        'file' => UploadedFile::fake()->create('signed.pdf', 40, 'application/pdf'),
    ]);

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ticket))->assertOk()
        ->assertSee('Signed. Recorded')->assertSee('Replace signed waiver')->assertSee('signed.pdf');
});

test('a booking where nobody needs visa assistance has no waiver panel and is not held', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'visa_not_required');

    expect($ticket->needsVisaWaiver())->toBeFalse()->and($ticket->visaWaiverOutstanding())->toBeFalse();

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ticket))->assertOk()->assertDontSee('The client signed the waiver');
    $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), [
        'client_signed' => 1,
        'file' => UploadedFile::fake()->create('signed.pdf', 40, 'application/pdf'),
    ])->assertRedirect(route('ticketing.tickets.show', $ticket))->assertSessionHas('error');
});

test('the booking-received email carries the waiver for the client to print and sign', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    // The email really goes out when the booking is saved.
    Notification::assertSentOnDemand(TicketBookedNotification::class);

    $mail = (new TicketBookedNotification($ticket))->toMail(new stdClass);
    $text = collect([...$mail->introLines, ...$mail->outroLines])->implode("\n");

    expect(collect($mail->rawAttachments)->pluck('name')->all())->toBe(['Visa-Waiver-'.$ticket->booking_reference.'.pdf'])
        ->and(substr($mail->rawAttachments[0]['data'], 0, 4))->toBe('%PDF')
        ->and($text)->toContain('print the attached Visa Waiver, sign it and send it back to us')
        ->toContain('cannot be issued until we have the signed copy');
});

test('the booking-received email has no waiver when nobody needs visa assistance', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'visa_not_required');

    $mail = (new TicketBookedNotification($ticket))->toMail(new stdClass);

    expect($mail->rawAttachments)->toBe([])
        ->and(collect($mail->introLines)->implode(' '))->not->toContain('Visa Waiver');
});

test('once the signed waiver is on file the client emails no longer carry a blank one', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = waiverBooking($agent, 'needs_assistance');

    expect(TicketDocumentPdf::attachTo(new MailMessage, $ticket))->toContain('your Visa Waiver');

    $this->actingAs($agent)->post(route('ticketing.tickets.visa-waiver.sign', $ticket), [
        'client_signed' => 1,
        'file' => UploadedFile::fake()->create('signed.pdf', 40, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $ticket = $ticket->fresh();

    expect(TicketDocumentPdf::attachTo(new MailMessage, $ticket))->not->toContain('your Visa Waiver')
        ->and((new TicketBookedNotification($ticket))->toMail(new stdClass)->rawAttachments)->toBe([]);
});
