<?php

use App\Models\LocalTourBooking;
use App\Models\LocalTourDocument;
use App\Models\User;
use App\Support\LocalTours;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** An agent whose only desk is Local Tours. */
function localToursAgent(array $extra = []): User
{
    return User::factory()->create(['role' => 'agent', 'permissions' => [...Permissions::work('local_tours'), ...$extra]]);
}

/** A booking opened by $agent, at the given step, with everything the earlier steps needed. */
function localTourBooking(User $agent, array $attributes = []): LocalTourBooking
{
    $booking = new LocalTourBooking($attributes + [
        'reference' => LocalTourBooking::generateReference(),
        'tour' => 'puning',
        'status' => 'availability',
        'lead_guest_name' => 'Juan Dela Cruz',
        'contact_phone' => '0917 000 0000',
        'adults' => 2,
        'children' => 1,
        'travel_date' => now()->addWeek()->toDateString(),
        'pickup_location' => 'Balibago',
        'dropoff_location' => 'Balibago',
        'transport' => 'amega',
        'total_amount' => 9000,
    ]);
    $booking->created_by = $agent->id;
    $booking->save();

    return $booking;
}

/** Tick every task of the booking's current step and send the step's form to $route. */
function workStep($test, LocalTourBooking $booking, string $route, array $fields = [])
{
    return $test->post(route("local-tours.bookings.{$route}", $booking), ['tasks' => array_keys($booking->tasks())] + $fields);
}

beforeEach(function () {
    Notification::fake();
    Storage::fake(config('filesystems.documents_disk'));
});

it('lists the four tours of the procedure with their suppliers', function () {
    expect(array_keys(LocalTours::TOURS))->toBe(['puning', 'corregidor', 'angeles_city', 'camayan'])
        ->and(LocalTours::TOURS['puning']['phone'])->toBe('0968 724 0148')
        ->and(LocalTours::TOURS['corregidor']['supplier'])->toBe('Guia Del Mar Travel and Tours')
        ->and(LocalTours::TOURS['camayan']['phone'])->toBe('(047) 252 8000')
        ->and(LocalTours::DRIVERS)->toHaveCount(3);
});

it('keeps the desk to staff who were given it', function () {
    $this->get('/local-tours')->assertRedirect('/admin/login');

    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $this->actingAs($ticketing)->get('/local-tours')->assertRedirect();

    $this->actingAs(localToursAgent())->get('/local-tours')->assertOk()->assertSee('Puning Hot Spring');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/local-tours')->assertOk();
});

it('lands a Local Tours agent on the desk and keeps them out of the admin panel', function () {
    $agent = localToursAgent();

    expect($agent->staffHomeRoute())->toBe('local-tours.dashboard');
    $this->actingAs($agent)->get('/admin')->assertRedirect(route('local-tours.dashboard'));
});

it('opens a booking at the first step and registers the lead guest as a client', function () {
    $agent = localToursAgent();

    $this->actingAs($agent)->post(route('local-tours.bookings.store'), [
        'tour' => 'corregidor',
        'lead_guest_name' => 'Maria Santos',
        'contact_phone' => '0918 111 2222',
        'adults' => 2,
        'children' => 0,
        'transport' => 'own',
        'travel_date' => now()->addDays(10)->toDateString(),
    ])->assertRedirect();

    $booking = LocalTourBooking::firstOrFail();
    expect($booking->status)->toBe('availability')
        ->and($booking->created_by)->toBe($agent->id)
        ->and($booking->approval_status)->toBeNull()
        ->and($booking->reference)->toStartWith('LT-')
        ->and($booking->client?->role)->toBe('client');

    $this->actingAs($agent)->get(route('local-tours.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Guia Del Mar Travel and Tours')
        ->assertSee('Verified the meeting point, ferry departure schedule and return schedule');
});

it('does not let a booking leave a step until its tasks are done', function () {
    $agent = localToursAgent();
    $booking = localTourBooking($agent);

    $this->actingAs($agent)->post(route('local-tours.bookings.advance', $booking), ['tasks' => ['availability_checked']])
        ->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe('availability')
        ->and($booking->fresh()->checklist)->toBe(['availability_checked']);

    workStep($this, $booking, 'advance', ['total_amount' => 9000])->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('quotation');
});

it('needs the amount quoted before the quotation step', function () {
    $agent = localToursAgent();
    $booking = localTourBooking($agent, ['total_amount' => 0]);

    workStep($this->actingAs($agent), $booking, 'advance')->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe('availability');
});

it('needs a guest ID before the booking goes for approval, then sends it to the approver', function () {
    $agent = localToursAgent();
    $admin = User::factory()->create(['role' => 'admin']);
    $booking = localTourBooking($agent, ['status' => 'guest_details']);

    workStep($this->actingAs($agent), $booking, 'advance')->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe('guest_details');

    $this->post(route('local-tours.documents.store', $booking), [
        'kind' => 'guest_id',
        'files' => [UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')],
    ])->assertSessionHas('success');

    workStep($this, $booking, 'advance')->assertSessionHas('success');

    $booking->refresh();
    expect($booking->status)->toBe('supplier')
        ->and($booking->isAwaitingApproval())->toBeTrue()
        ->and($booking->acknowledged_by)->toBe($agent->id);

    $this->actingAs($admin)->get(route('admin.local-tour-approvals.index'))->assertOk()->assertSee($booking->reference);
});

it('holds the supplier booking until an approver approves it', function () {
    $agent = localToursAgent();
    $admin = User::factory()->create(['role' => 'admin']);
    $booking = localTourBooking($agent, ['status' => 'supplier']);
    $booking->submitForApproval($agent);
    $booking->save();

    workStep($this->actingAs($agent), $booking, 'stage', ['supplier_confirmation' => 'PHS-123'])->assertSessionHas('error');
    expect($booking->fresh()->supplier_confirmation)->toBeNull();

    $this->actingAs($admin)->post(route('admin.local-tour-approvals.approve', ['booking', $booking->id]), ['admin_acknowledged' => '1'])
        ->assertSessionHas('success');
    expect($booking->fresh()->approval_status)->toBe('approved');

    workStep($this->actingAs($agent), $booking, 'advance', ['supplier_confirmation' => 'PHS-123', 'supplier_paid_amount' => 7000])
        ->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('voucher')
        ->and($booking->fresh()->supplier_confirmation)->toBe('PHS-123');
});

it('does not let the agent approve their own booking when another approver exists', function () {
    User::factory()->create(['role' => 'admin']);
    $supervisor = localToursAgent(['local_tours.approve']);
    $booking = localTourBooking($supervisor, ['status' => 'supplier']);
    $booking->submitForApproval($supervisor);
    $booking->save();

    $this->actingAs($supervisor)->post(route('admin.local-tour-approvals.approve', ['booking', $booking->id]), ['admin_acknowledged' => '1'])
        ->assertSessionHas('error');
    expect($booking->fresh()->isAwaitingApproval())->toBeTrue();
});

it('returns a booking with a reason, and saving the correction sends it again', function () {
    $agent = localToursAgent();
    $admin = User::factory()->create(['role' => 'admin']);
    $booking = localTourBooking($agent, ['status' => 'supplier']);
    $booking->submitForApproval($agent);
    $booking->save();

    $this->actingAs($admin)->post(route('admin.local-tour-approvals.reject', ['booking', $booking->id]), ['review_note' => 'Wrong number of guests.'])
        ->assertSessionHas('success');
    expect($booking->fresh()->isReturned())->toBeTrue();

    $this->actingAs($agent)->put(route('local-tours.bookings.update', $booking), [
        'tour' => 'puning', 'lead_guest_name' => 'Juan Dela Cruz', 'adults' => 3, 'children' => 1, 'transport' => 'amega', 'total_amount' => 9000,
        'travel_date' => $booking->travel_date->toDateString(),
    ])->assertRedirect(route('local-tours.bookings.show', $booking));

    expect($booking->fresh()->isAwaitingApproval())->toBeTrue()
        ->and($booking->fresh()->adults)->toBe(3);
});

it('sends an approved booking back to the approver when the amount quoted changes', function () {
    $agent = localToursAgent();
    User::factory()->create(['role' => 'admin']);
    $booking = localTourBooking($agent, ['status' => 'supplier']);
    $booking->forceFill(['approval_status' => 'approved'])->save();

    $fields = ['tour' => 'puning', 'lead_guest_name' => 'Juan Dela Cruz', 'adults' => 2, 'children' => 1, 'transport' => 'amega', 'travel_date' => $booking->travel_date->toDateString()];

    // A remark is not something the approver signed off on.
    $this->actingAs($agent)->put(route('local-tours.bookings.update', $booking), $fields + ['total_amount' => 9000, 'remarks' => 'Client prefers a morning start.']);
    expect($booking->fresh()->approval_status)->toBe('approved');

    $this->put(route('local-tours.bookings.update', $booking), $fields + ['total_amount' => 12000]);
    expect($booking->fresh()->isAwaitingApproval())->toBeTrue();
});

it('takes payment at the cashier only once approved, and releases the voucher only when paid in full', function () {
    $agent = localToursAgent();
    $cashier = User::factory()->create(['role' => 'cashier']);
    $booking = localTourBooking($agent, ['status' => 'voucher', 'supplier_confirmation' => 'PHS-123']);
    $booking->submitForApproval($agent);
    $booking->save();

    $payment = ['amount' => 4000, 'method' => 'cash', 'cashier_acknowledged' => '1'];

    // Not approved yet: the cashier cannot take it.
    $this->actingAs($cashier)->post(route('cashier.local-tours.store', $booking->id), $payment)->assertSessionHas('error');
    expect((float) $booking->fresh()->amount_paid)->toBe(0.0);

    $booking->forceFill(['approval_status' => 'approved', 'reviewed_at' => now()])->save();

    $this->get(route('cashier.local-tours.index'))->assertOk()->assertSee($booking->reference);
    $this->post(route('cashier.local-tours.store', $booking->id), $payment)->assertRedirect(route('cashier.local-tours.show', $booking->id));
    expect((float) $booking->fresh()->amount_paid)->toBe(4000.0);

    // Partly paid: no voucher, and the step does not close.
    $this->actingAs($agent)->get(route('local-tours.bookings.voucher', $booking))->assertRedirect(route('local-tours.bookings.show', $booking));
    workStep($this, $booking, 'advance')->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe('voucher')
        ->and($booking->fresh()->voucher_released_at)->toBeNull();

    // More than the balance is refused.
    $this->actingAs($cashier)->post(route('cashier.local-tours.store', $booking->id), ['amount' => 6000] + $payment)->assertSessionHasErrors('amount');
    $this->post(route('cashier.local-tours.store', $booking->id), ['amount' => 5000] + $payment);
    expect($booking->fresh()->isFullyPaid())->toBeTrue()
        ->and($booking->fresh()->payment_in_full_at)->not->toBeNull();

    $this->actingAs($agent)->get(route('local-tours.bookings.voucher', $booking))->assertOk()->assertSee('Booking Voucher')->assertSee('PHS-123');
    workStep($this, $booking, 'advance')->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('transport')
        ->and($booking->fresh()->voucher_released_at)->not->toBeNull();
});

it('needs an AMEGA driver before the booking is completed, unless the guests have their own transport', function () {
    $agent = localToursAgent();
    $booking = localTourBooking($agent, ['status' => 'transport']);
    $booking->forceFill(['approval_status' => 'approved', 'amount_paid' => 9000])->save();

    workStep($this->actingAs($agent), $booking, 'advance')->assertSessionHas('error');
    expect($booking->fresh()->status)->toBe('transport');

    workStep($this, $booking, 'advance', ['driver' => 'rey', 'vehicle' => 'Van'])->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('completed')
        ->and($booking->fresh()->driver)->toBe('rey')
        ->and($booking->fresh()->completed_at)->not->toBeNull();

    $own = localTourBooking($agent, ['status' => 'transport', 'transport' => 'own']);
    $own->forceFill(['approval_status' => 'approved', 'amount_paid' => 9000])->save();
    expect(array_keys($own->tasks()))->toBe(['instructions_sent']);
    workStep($this, $own, 'advance')->assertSessionHas('success');
    expect($own->fresh()->status)->toBe('completed');
});

it('keeps a booking private to the agent who opened it', function () {
    $owner = localToursAgent();
    $other = localToursAgent();
    $supervisor = localToursAgent(['local_tours.view_all']);
    $booking = localTourBooking($owner);

    $this->actingAs($other)->get(route('local-tours.bookings.show', $booking))->assertNotFound();
    $this->actingAs($other)->get(route('local-tours.dashboard'))->assertOk()->assertDontSee($booking->reference);
    $this->actingAs($supervisor)->get(route('local-tours.bookings.show', $booking))->assertOk();
});

it('serves a filed ID only to staff who can open the booking', function () {
    $owner = localToursAgent();
    $other = localToursAgent();
    $booking = localTourBooking($owner);

    $this->actingAs($owner)->post(route('local-tours.documents.store', $booking), [
        'kind' => 'guest_id',
        'files' => [UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf')],
    ]);
    $document = LocalTourDocument::firstOrFail();

    Storage::disk(config('filesystems.documents_disk'))->assertExists($document->file_path);
    $this->get(route('local-tours.documents.download', $document))->assertOk();
    $this->actingAs($other)->get(route('local-tours.documents.download', $document))->assertNotFound();

    // Taking a wrong scan off is a correction the agent may make; deleting the booking is not.
    $this->actingAs($owner)->delete(route('local-tours.documents.destroy', $document))->assertSessionHas('success');
    expect(LocalTourDocument::count())->toBe(0);

    $this->delete(route('local-tours.bookings.destroy', $booking))->assertSessionHas('error');
    expect(LocalTourBooking::count())->toBe(1);
});

it('lets someone who can only open the desk look but not change anything', function () {
    $viewer = User::factory()->create(['role' => 'agent', 'permissions' => ['local_tours.view', 'local_tours.view_all']]);
    $booking = localTourBooking(localToursAgent());

    $this->actingAs($viewer)->get(route('local-tours.bookings.show', $booking))->assertOk()->assertDontSee('Save and continue');
    $this->get(route('local-tours.bookings.create'))->assertRedirect();
    workStep($this, $booking, 'stage')->assertSessionHas('error');
    expect($booking->fresh()->checklist)->toBeNull();
});

it('cancels a booking with a reason and takes it off the pipeline', function () {
    $agent = localToursAgent();
    $booking = localTourBooking($agent);

    $this->actingAs($agent)->post(route('local-tours.bookings.cancel', $booking), ['reason' => 'Client changed plans.'])->assertSessionHas('success');

    expect($booking->fresh()->status)->toBe('cancelled')
        ->and($booking->fresh()->remarks)->toContain('Client changed plans.');
    workStep($this, $booking, 'stage')->assertSessionHas('error');
});

it('shows the procedure page with the drivers and reminders', function () {
    $this->actingAs(localToursAgent())->get(route('local-tours.procedures'))
        ->assertOk()
        ->assertSee('Sir Arnel')
        ->assertSee('0921 248 5035')
        ->assertSee('Never confirm a booking to the client without supplier confirmation.');
});
