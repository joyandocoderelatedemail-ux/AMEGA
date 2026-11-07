<?php

use App\Models\AgentCommission;
use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\StaffRole;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function commissionPendingTicket(User $agent): TicketBooking
{
    return TicketBooking::create([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $agent->id,
        'approval_status' => TicketBooking::APPROVAL_PENDING,
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(30),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
    ]);
}

function commissionPendingVisa(User $officer): VisaApplication
{
    return VisaApplication::create([
        'reference' => 'VSA-'.fake()->unique()->numerify('####'),
        'client_name' => 'Jane Tan',
        'client_email' => 'jane@example.com',
        'service_type' => 'visit_visa',
        'status' => 'pending',
        'approval_status' => 'pending',
        'total_amount' => 3500,
        'currency' => 'PHP',
        'created_by' => $officer->id,
    ]);
}

function commissionPendingSrrv(User $officer): SrrvApplication
{
    return SrrvApplication::create([
        'reference' => 'SRRV-'.fake()->unique()->numerify('####'),
        'retiree_name' => 'Ramon Cruz',
        'retiree_email' => 'ramon@example.com',
        'service_type' => 'restamping',
        'visa_class' => 'classic',
        'status' => 'pending',
        'approval_status' => 'pending',
        'service_fee' => 1000,
        'currency' => 'USD',
        'created_by' => $officer->id,
    ]);
}

function commissionSupervisor(): User
{
    $user = User::factory()->create(['role' => 'agent', 'name' => 'Sam Supervisor']);
    $user->forceFill(['staff_role_id' => StaffRole::where('name', 'Supervisor')->value('id')])->save();

    return $user->refresh();
}

test('an admin sets the commission of the agent who sent a ticket booking when approving it', function () {
    $agent = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Agent']);
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);   // a second approver, so nobody self-approves
    $ticket = commissionPendingTicket($agent);

    $this->actingAs($admin)->get(route('admin.ticket-approvals.show', $ticket))->assertOk()
        ->assertSee('Agent commission (₱)', false)->assertSee('For Ana Agent, who sent this booking.');

    $this->actingAs($admin)->post(route('admin.ticket-approvals.approve', $ticket), ['admin_acknowledged' => 1, 'commission' => '750.50'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $commission = AgentCommission::firstOrFail();
    expect($ticket->fresh()->approval_status)->toBe('approved')
        ->and($commission->desk)->toBe('ticketing')->and($commission->kind)->toBe('ticket')->and($commission->file_id)->toBe($ticket->id)
        ->and($commission->agent_id)->toBe($agent->id)->and((float) $commission->amount)->toBe(750.5)
        ->and($commission->set_by)->toBe($admin->id)
        ->and($commission->reference)->toBe($ticket->booking_reference)->and($commission->client_name)->toBe('Juan Dela Cruz');

    // The admin sees it on the approved booking.
    $this->actingAs($admin)->get(route('admin.ticket-approvals.show', $ticket))->assertOk()
        ->assertSee('Agent commission:')->assertSee('₱750.50', false)->assertSee('for Ana Agent', false);
});

test('approving without a commission is fine and records nothing', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $ticket = commissionPendingTicket($agent);

    $this->actingAs($admin)->post(route('admin.ticket-approvals.approve', $ticket), ['admin_acknowledged' => 1, 'commission' => ''])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($ticket->fresh()->approval_status)->toBe('approved')->and(AgentCommission::count())->toBe(0);
});

test('the commission is an amount only: text and negatives are refused and the booking stays pending', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $ticket = commissionPendingTicket($agent);

    foreach (['abc', '-50', '1e999'] as $bad) {
        $this->actingAs($admin)->post(route('admin.ticket-approvals.approve', $ticket), ['admin_acknowledged' => 1, 'commission' => $bad])
            ->assertSessionHasErrors('commission');
    }

    expect($ticket->fresh()->approval_status)->toBe('pending')->and(AgentCommission::count())->toBe(0);
});

test('a supervisor who may approve never sees the commission box and cannot set one', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $boss = commissionSupervisor();
    $ticket = commissionPendingTicket($agent);

    $this->actingAs($boss)->get(route('admin.ticket-approvals.show', $ticket))->assertOk()
        ->assertSee('Approve for payment')->assertDontSee('Agent commission', false)->assertDontSee('name="commission"', false);

    // Even a hand-made request is ignored.
    $this->actingAs($boss)->post(route('admin.ticket-approvals.approve', $ticket), ['admin_acknowledged' => 1, 'commission' => '999'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($ticket->fresh()->approval_status)->toBe('approved')->and(AgentCommission::count())->toBe(0);
});

test('a supervisor does not see commissions an admin set, on any approvals page', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $boss = commissionSupervisor();
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = commissionPendingTicket($agent);
    $this->actingAs($admin)->post(route('admin.ticket-approvals.approve', $ticket), ['admin_acknowledged' => 1, 'commission' => '1234'])->assertSessionHasNoErrors();

    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);
    $visa = commissionPendingVisa($visaOfficer);
    $this->actingAs($admin)->post(route('admin.visa-approvals.approve', ['file', $visa->id]), ['admin_acknowledged' => 1, 'commission' => '321'])->assertSessionHasNoErrors();

    $this->actingAs($boss)->get(route('admin.ticket-approvals.show', $ticket))->assertOk()->assertDontSee('1,234.00', false)->assertDontSee('Agent commission', false);
    $this->actingAs($boss)->get(route('admin.visa-approvals.index', ['tab' => 'approved']))->assertOk()
        ->assertSee($visa->reference)->assertDontSee('Agent commission', false)->assertDontSee('₱321.00', false);
});

test('visa assistance approvals record the commission for the officer who sent the file', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance', 'name' => 'Vera Visa']);
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $file = commissionPendingVisa($officer);

    $this->actingAs($admin)->get(route('admin.visa-approvals.index'))->assertOk()->assertSee('Agent commission (₱)', false);

    $this->actingAs($admin)->post(route('admin.visa-approvals.approve', ['file', $file->id]), ['admin_acknowledged' => 1, 'commission' => 400])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $commission = AgentCommission::firstOrFail();
    expect($commission->desk)->toBe('visa_assistance')->and($commission->kind)->toBe('file')->and($commission->file_id)->toBe($file->id)->and($commission->agent_id)->toBe($officer->id)
        ->and((float) $commission->amount)->toBe(400.0)->and($commission->reference)->toBe($file->reference);

    // On the Approved tab the admin sees the amount next to the file.
    $this->actingAs($admin)->get(route('admin.visa-approvals.index', ['tab' => 'approved']))->assertOk()
        ->assertSee($file->reference)->assertSee('Agent commission ₱400.00', false);
});

test('srrv approvals record the commission for the officer who sent the file', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $file = commissionPendingSrrv($officer);

    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['application', $file->id]), ['admin_acknowledged' => 1, 'commission' => '250'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $commission = AgentCommission::firstOrFail();
    expect($commission->desk)->toBe('srrv')->and($commission->kind)->toBe('application')->and($commission->agent_id)->toBe($officer->id)
        ->and((float) $commission->amount)->toBe(250.0)->and($commission->client_name)->toBe('Ramon Cruz');

    $this->actingAs($admin)->get(route('admin.srrv-approvals.index', ['tab' => 'approved']))->assertOk()
        ->assertSee('Agent commission ₱250.00', false);
});

test('a returned file records no commission', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $ticket = commissionPendingTicket($agent);

    $this->actingAs($admin)->post(route('admin.ticket-approvals.reject', $ticket), ['review_note' => 'Wrong dates', 'commission' => '500']);

    expect($ticket->fresh()->approval_status)->toBe('rejected')->and(AgentCommission::count())->toBe(0);
});

function commissionImmigrationSheet(): ImmigrationClient
{
    return ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123456', 'nationality' => 'Korean', 'visa_expiry_date' => '2026-10-21']);
}

test('immigration approvals record the commission for the agent who sent the case, and the folder\'s extension', function () {
    Notification::fake();
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration'], 'name' => 'Ivy Immigration']);
    $admin = User::factory()->create(['role' => 'admin']);

    // An ECC the agent saved, waiting for the admin.
    $sheet = commissionImmigrationSheet();
    $this->actingAs($agent)->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'ecc', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'acknowledgement_required' => 1, 'acknowledged' => 1, 'then' => 'stay',
        'petition' => ['details' => ['ecc_kind' => 'ecc']],
    ])->assertSessionHasNoErrors();
    $case = $sheet->fresh()->openPetition('ecc');

    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()->assertSee('Agent commission (₱)', false);

    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['ecc', $case->id]), ['admin_acknowledged' => 1, 'commission' => '300'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $commission = AgentCommission::where('desk', 'immigration')->where('kind', 'ecc')->firstOrFail();
    expect($commission->file_id)->toBe($case->id)->and($commission->agent_id)->toBe($agent->id)->and((float) $commission->amount)->toBe(300.0)
        ->and($commission->client_name)->toBe('Minjun Kim');

    $this->actingAs($admin)->get(route('admin.immigration-approvals.index', ['tab' => 'approved']))->assertOk()->assertSee('Agent commission ₱300.00', false);
});

test('a Visa Extension approval records the commission for the agent who sent it, kept on the client\'s folder', function () {
    Notification::fake();
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration'], 'name' => 'Ivy Immigration']);
    $admin = User::factory()->create(['role' => 'admin']);

    $folder = commissionImmigrationSheet();
    $this->actingAs($agent)->put(route('admin.client-sheets.update', $folder), [
        'last_name' => 'Kim', 'given_name' => 'Minjun', 'visa_expiry_date' => '2026-10-21', 'acknowledgement_required' => 1, 'acknowledged' => 1, 'then' => 'stay',
        'extensions' => [1 => ['extension_date' => '2026-10-15', 'amount_paid' => 2930, 'duration' => '29 days']],
        'extension_payment' => ['payment_type' => 'downpayment', 'amount_received' => '1000'],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['extensions', $folder->id]), ['admin_acknowledged' => 1, 'commission' => '150.25'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $extension = AgentCommission::where('desk', 'immigration')->where('kind', 'extensions')->firstOrFail();
    expect($extension->file_id)->toBe($folder->id)->and($extension->agent_id)->toBe($agent->id)->and((float) $extension->amount)->toBe(150.25)
        ->and($extension->reference)->toBe('Visa Extension');
});
