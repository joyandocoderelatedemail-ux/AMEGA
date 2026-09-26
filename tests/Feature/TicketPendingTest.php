<?php

use App\Models\TicketBooking;
use App\Models\TicketDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** The wizard's form as the browser sends it when saving as pending. */
function pendingForm(array $overrides = []): array
{
    return array_merge([
        'travel_type' => 'international',
        'origin' => 'Manila (MNL)',
        'destination' => 'Tokyo, Japan',
        'total_passengers' => 2,
        'contact_name' => 'Chin Chin Chin',
        'clients' => [['id' => 13, 'name' => 'Chin Chin Chin', 'passenger_type' => 'adult']],
        'passengers' => [['first_name' => 'Chin Chin', 'last_name' => 'Chin', 'passenger_type' => 'adult', 'passport_file_name' => 'passport.jpg']],
    ], $overrides);
}

test('saving as pending keeps the whole form and frees the wizard for the next client', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($officer)->postJson(route('ticketing.tickets.pending.store'), [
        'step' => 4,
        'payload' => pendingForm(),
    ])->assertOk()
        // The wizard then opens empty for the next client, with a confirmation.
        ->assertJsonPath('redirect', route('ticketing.tickets.create'))
        ->assertSessionHas('success', "Chin Chin Chin's ticket is saved as pending. Continue it any time from the Ticket Directory.");

    $pending = TicketDraft::findOrFail($response->json('id'));

    expect($pending->created_by)->toBe($officer->id)
        ->and($pending->client_name)->toBe('Chin Chin Chin')
        ->and($pending->summary)->toBe('International · Manila (MNL) → Tokyo, Japan · 2 pax')
        ->and($pending->step)->toBe(4)
        ->and($pending->payload['clients'][0]['name'])->toBe('Chin Chin Chin');
});

test('saving again updates the same pending ticket', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $id = $this->actingAs($officer)->postJson(route('ticketing.tickets.pending.store'), ['step' => 2, 'payload' => pendingForm()])->json('id');
    $this->actingAs($officer)->postJson(route('ticketing.tickets.pending.store'), [
        'draft_id' => $id,
        'step' => 5,
        'payload' => pendingForm(['destination' => 'Osaka, Japan']),
    ])->assertOk()->assertJsonPath('id', $id);

    expect(TicketDraft::count())->toBe(1)
        ->and(TicketDraft::first()->step)->toBe(5)
        ->and(TicketDraft::first()->payload['destination'])->toBe('Osaka, Japan');
});

test('a pending ticket is listed to continue and reopens with its form and step', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $pending = TicketDraft::create(['created_by' => $officer->id, 'client_name' => 'Chin Chin Chin', 'summary' => 'International', 'step' => 4, 'payload' => pendingForm()]);

    $this->actingAs($officer)->get(route('ticketing.tickets.index'))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Chin Chin Chin')
        ->assertSee(route('ticketing.tickets.create', ['pending' => $pending->id]), false);

    $this->actingAs($officer)->get(route('ticketing.tickets.create', ['pending' => $pending->id]))
        ->assertOk()
        ->assertViewHas('pendingTicket', fn (array $ticket) => $ticket['id'] === $pending->id
            && $ticket['step'] === 4
            && $ticket['payload']['destination'] === 'Tokyo, Japan');
});

test('pending tickets are private to the officer who saved them', function () {
    $ana = User::factory()->create(['role' => 'ticketing']);
    $ben = User::factory()->create(['role' => 'ticketing']);
    $pending = TicketDraft::create(['created_by' => $ana->id, 'client_name' => 'Ana Client', 'step' => 1, 'payload' => pendingForm()]);

    $this->actingAs($ben)->get(route('ticketing.tickets.index'))->assertDontSee('Ana Client');
    $this->actingAs($ben)->get(route('ticketing.tickets.create', ['pending' => $pending->id]))
        ->assertViewHas('pendingTicket', null);
    $this->actingAs($ben)->delete(route('ticketing.tickets.pending.destroy', $pending))->assertNotFound();

    // Ben saving "over" Ana's pending ticket starts his own instead.
    $this->actingAs($ben)->postJson(route('ticketing.tickets.pending.store'), ['draft_id' => $pending->id, 'step' => 1, 'payload' => pendingForm()]);
    expect(TicketDraft::withoutGlobalScopes()->find($pending->id)->created_by)->toBe($ana->id)
        ->and(TicketDraft::withoutGlobalScopes()->count())->toBe(2);
});

test('a pending ticket can be discarded', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $pending = TicketDraft::create(['created_by' => $officer->id, 'client_name' => 'Chin', 'step' => 1, 'payload' => pendingForm()]);

    $this->actingAs($officer)->delete(route('ticketing.tickets.pending.destroy', $pending))->assertRedirect();

    expect(TicketDraft::count())->toBe(0);
});

test('saving the ticket for real removes its pending copy', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $pending = TicketDraft::create(['created_by' => $officer->id, 'client_name' => 'Juan Dela Cruz', 'step' => 4, 'payload' => pendingForm()]);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), [
        'save_as_quotation' => 1,
        'pending_ticket_id' => $pending->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay',
        'trip_type' => 'one_way',
        'departure_date' => now()->addMonth()->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
    ])->assertRedirect()->assertSessionHas('clear_booking_draft', true);

    expect(TicketBooking::count())->toBe(1)
        ->and(TicketDraft::count())->toBe(0);
});

test('the wizard links to the pending tickets waiting on requirements', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    TicketDraft::create(['created_by' => $officer->id, 'client_name' => 'Chin', 'step' => 5, 'payload' => pendingForm()]);

    $this->actingAs($officer)->get(route('ticketing.tickets.create'))
        ->assertOk()
        ->assertSee('Pending (1)');
});
