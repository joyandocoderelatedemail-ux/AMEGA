<?php

use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;
use App\Services\ClientAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Desk files are private to the staff member who opened them. Another
 * officer at the same desk never sees them; admins see everything.
 */

function ownTicket(User $creator, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
        'created_by' => $creator->id,
    ], $overrides));
}

function ownVisaFile(User $creator, string $client): VisaApplication
{
    return VisaApplication::create([
        'reference' => VisaApplication::generateReference(),
        'created_by' => $creator->id,
        'service_type' => 'visit_visa',
        'status' => 'pending',
        'client_name' => $client,
    ]);
}

test('a ticketing officer sees only the bookings they opened', function () {
    $ana = User::factory()->create(['role' => 'ticketing']);
    $ben = User::factory()->create(['role' => 'ticketing']);
    $anasBooking = ownTicket($ana, ['contact_name' => 'Ana Client']);
    $bensBooking = ownTicket($ben, ['contact_name' => 'Ben Client']);

    $this->actingAs($ana)->get(route('ticketing.tickets.index'))
        ->assertOk()
        ->assertSee('Ana Client')
        ->assertDontSee('Ben Client');

    $this->actingAs($ana)->get(route('ticketing.tickets.show', $anasBooking))->assertOk();
    $this->actingAs($ana)->get(route('ticketing.tickets.show', $bensBooking))->assertNotFound();
});

test('the ticketing dashboard counts only the officer\'s own bookings', function () {
    $ana = User::factory()->create(['role' => 'ticketing']);
    $ben = User::factory()->create(['role' => 'ticketing']);
    ownTicket($ana);
    ownTicket($ben);
    ownTicket($ben);

    $this->actingAs($ana);

    expect(TicketBooking::count())->toBe(1);
});

test('an admin sees every desk file', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'ticketing']);
    $booking = ownTicket($officer, ['contact_name' => 'Officer Client']);

    $this->actingAs($admin)->get(route('ticketing.tickets.index'))->assertSee('Officer Client');
    $this->actingAs($admin)->get(route('ticketing.tickets.show', $booking))->assertOk();
});

test('a visa officer cannot open, search or download from another officer\'s file', function () {
    $ana = User::factory()->create(['role' => 'visa_assistance']);
    $ben = User::factory()->create(['role' => 'visa_assistance']);
    $bensFile = ownVisaFile($ben, 'Chin Chin Chin');
    $document = VisaApplicationDocument::create([
        'visa_application_id' => $bensFile->id,
        'document_type' => 'passport_scan',
        'file_path' => 'visa/x/passport.jpg',
        'original_name' => 'passport.jpg',
        'status' => 'uploaded',
    ]);

    $this->actingAs($ana)->get(route('visa.applications.show', $bensFile))->assertNotFound();
    $this->actingAs($ana)->post(route('visa.applications.advance', $bensFile))->assertNotFound();
    $this->actingAs($ana)->get(route('visa.documents.download', $document))->assertNotFound();
    $this->actingAs($ana)->getJson(route('visa.lookup', ['q' => 'chin']))->assertJsonCount(0, 'files');

    $this->actingAs($ben)->get(route('visa.applications.show', $bensFile))->assertOk();
});

test('SRRV files and renewals are private to the officer who opened them', function () {
    $ana = User::factory()->create(['role' => 'srrv']);
    $ben = User::factory()->create(['role' => 'srrv']);
    $file = SrrvApplication::create([
        'reference' => SrrvApplication::generateReference(),
        'created_by' => $ben->id,
        'service_type' => 'renewal_application',
        'status' => 'pending',
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
    ]);
    $renewal = SrrvRenewal::create([
        'reference' => SrrvRenewal::generateReference(),
        'created_by' => $ben->id,
        'status' => 'pending',
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 1,
        'fee_amount' => 360,
    ]);

    $this->actingAs($ana)->get(route('srrv.applications.show', $file))->assertNotFound();
    $this->actingAs($ana)->get(route('srrv.renewals.show', $renewal))->assertNotFound();
    $this->actingAs($ana)->get(route('srrv.applications.index'))->assertDontSee('Ramon Dela Cruz');

    $this->actingAs($ben)->get(route('srrv.applications.show', $file))->assertOk();
    $this->actingAs($ben)->get(route('srrv.renewals.show', $renewal))->assertOk();
});

test('another desk is still sent back to its own desk, not shown a 404', function () {
    $srrv = User::factory()->create(['role' => 'srrv']);
    $visa = User::factory()->create(['role' => 'visa_assistance']);
    $file = ownVisaFile($visa, 'Chin Chin Chin');

    $this->actingAs($srrv)->get(route('visa.applications.show', $file))
        ->assertRedirect(route('srrv.dashboard'));
});

test('the client account sync still sees every desk file', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    ownTicket(User::factory()->create(['role' => 'ticketing']), ['contact_email' => 'other@example.com', 'contact_name' => 'Other Client']);

    $this->actingAs($officer);
    ClientAccountService::syncAllDeskClients();

    expect(User::where('email', 'other@example.com')->where('role', 'client')->exists())->toBeTrue();
});

test('an admin can hand a file to another officer at the same desk', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ana = User::factory()->create(['role' => 'ticketing']);
    $ben = User::factory()->create(['role' => 'ticketing']);
    $booking = ownTicket($ana);

    $this->actingAs($admin)->post(route('admin.files.owner', ['type' => 'ticket', 'id' => $booking->id]), ['created_by' => $ben->id])
        ->assertSessionHasNoErrors();

    $this->actingAs($ben)->get(route('ticketing.tickets.show', $booking))->assertOk();
    $this->actingAs($ana)->get(route('ticketing.tickets.show', $booking))->assertNotFound();
});

test('a file can only go to staff who work that desk', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $srrv = User::factory()->create(['role' => 'srrv']);
    $file = ownVisaFile($officer, 'Chin Chin Chin');

    $this->actingAs($admin)->post(route('admin.files.owner', ['type' => 'visa', 'id' => $file->id]), ['created_by' => $srrv->id])
        ->assertSessionHasErrors('created_by');

    expect($file->fresh()->created_by)->toBe($officer->id);
});

test('a file left without an owner can be picked up by an admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $leaver = User::factory()->create(['role' => 'srrv']);
    $stayer = User::factory()->create(['role' => 'srrv']);
    $renewal = SrrvRenewal::create([
        'reference' => SrrvRenewal::generateReference(),
        'created_by' => $leaver->id,
        'status' => 'pending',
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 1,
        'fee_amount' => 360,
    ]);

    $leaver->delete();
    expect($renewal->fresh()->created_by)->toBeNull();

    $this->actingAs($admin)->get(route('srrv.renewals.show', $renewal))
        ->assertOk()
        ->assertSee('No owner');

    $this->actingAs($admin)->post(route('admin.files.owner', ['type' => 'srrv-renewal', 'id' => $renewal->id]), ['created_by' => $stayer->id]);

    $this->actingAs($stayer)->get(route('srrv.renewals.show', $renewal))->assertOk();
});

test('only admins can reassign files', function () {
    $ana = User::factory()->create(['role' => 'ticketing']);
    $ben = User::factory()->create(['role' => 'ticketing']);
    $booking = ownTicket($ana);

    $this->actingAs($ana)->post(route('admin.files.owner', ['type' => 'ticket', 'id' => $booking->id]), ['created_by' => $ben->id]);

    expect($booking->fresh()->created_by)->toBe($ana->id);
});

test('the owner card shows admins the reassign control and officers only their own name', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'visa_assistance', 'name' => 'Ana Reyes']);
    $file = ownVisaFile($officer, 'Chin Chin Chin');

    $this->actingAs($admin)->get(route('visa.applications.show', $file))
        ->assertSee('Ana Reyes')
        ->assertSee('Reassign');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))
        ->assertSee('Owner')
        ->assertDontSee('Reassign to');
});

test('an admin can take a file on themselves', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'ticketing']);
    $booking = ownTicket($officer);

    $this->actingAs($admin)->get(route('ticketing.tickets.show', $booking))->assertSee('Assign to me');

    $this->actingAs($admin)->post(route('admin.files.owner', ['type' => 'ticket', 'id' => $booking->id]), ['created_by' => $admin->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'You now own this file.');

    expect($booking->fresh()->created_by)->toBe($admin->id);
    $this->actingAs($officer)->get(route('ticketing.tickets.show', $booking))->assertNotFound();
});
