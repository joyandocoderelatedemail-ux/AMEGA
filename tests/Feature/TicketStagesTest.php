<?php

use App\Models\TicketBooking;
use App\Models\User;

/** A booking at a given point of agent → admin → cashier → agent. */
function stagedTicket(User $agent, array $attributes): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $agent->id,
        'travel_type' => 'domestic',
        'trip_type' => 'one_way',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addMonth()->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'total_amount' => 5000,
        'approval_status' => TicketBooking::APPROVAL_APPROVED,
        'payment_status' => TicketBooking::PAYMENT_UNPAID,
    ], $attributes));
}

/** @return array<string, TicketBooking> one booking per stage */
function oneOfEachStage(User $agent): array
{
    return [
        'returned' => stagedTicket($agent, ['approval_status' => TicketBooking::APPROVAL_REJECTED, 'contact_name' => 'Returned Rita']),
        'approval' => stagedTicket($agent, ['approval_status' => TicketBooking::APPROVAL_PENDING, 'contact_name' => 'Pending Pia']),
        'cashier' => stagedTicket($agent, ['contact_name' => 'Cashier Carlo']),
        'ready' => stagedTicket($agent, ['payment_status' => TicketBooking::PAYMENT_FULL, 'amount_paid' => 5000, 'contact_name' => 'Ready Rey']),
        'issued' => stagedTicket($agent, ['payment_status' => TicketBooking::PAYMENT_FULL, 'amount_paid' => 5000, 'status' => TicketBooking::STATUS_ISSUED, 'contact_name' => 'Issued Ida']),
        'cancelled' => stagedTicket($agent, ['status' => TicketBooking::STATUS_CANCELLED, 'contact_name' => 'Cancelled Cy']),
        'quotation' => stagedTicket($agent, ['is_quotation' => true, 'approval_status' => null, 'contact_name' => 'Quote Quinn']),
    ];
}

test('each booking has one stage, and the stage query finds exactly the same bookings', function () {
    $tickets = oneOfEachStage(User::factory()->create(['role' => 'ticketing']));
    // Made before approvals existed: no approval status, still unpaid, so it is with the cashier.
    $legacy = stagedTicket(User::factory()->create(['role' => 'ticketing']), ['approval_status' => null]);

    foreach ($tickets as $stage => $ticket) {
        expect($ticket->stage())->toBe($stage);
    }
    expect($legacy->stage())->toBe('cashier');

    foreach (array_keys(TicketBooking::STAGES) as $stage) {
        $expected = collect(TicketBooking::withoutGlobalScopes()->get())->filter(fn ($t) => $t->stage() === $stage)->pluck('id')->sort()->values()->all();
        expect(TicketBooking::withoutGlobalScopes()->inStage($stage)->pluck('id')->sort()->values()->all())->toBe($expected, "stage {$stage}");
    }
});

test('the dashboard shows where the bookings stand, the money collected and the open quotations', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    oneOfEachStage($admin);

    $response = $this->actingAs($admin)->get(route('ticketing.dashboard'))->assertOk()->assertSee('Where your tickets are');

    expect($response->viewData('flow'))->toBe(['returned' => 1, 'approval' => 1, 'cashier' => 1, 'ready' => 1, 'issued' => 1])
        ->and($response->viewData('stats')['collected'])->toBe(10000.0)
        ->and($response->viewData('quotes')['count'])->toBe(1);
});

test('the quotations tabs pick out the expired and the unpriced ones', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    stagedTicket($agent, ['is_quotation' => true, 'approval_status' => null, 'contact_name' => 'Expired Ella', 'quote_valid_until' => today()->subDay()]);
    stagedTicket($agent, ['is_quotation' => true, 'approval_status' => null, 'contact_name' => 'Unpriced Uma', 'total_amount' => 0]);
    stagedTicket($agent, ['is_quotation' => true, 'approval_status' => null, 'contact_name' => 'Fine Fe', 'quote_valid_until' => today()->addDays(5)]);

    $response = $this->actingAs($agent)->get(route('ticketing.quotations.index'))->assertOk();
    expect(collect($response->viewData('views'))->map(fn ($v) => $v['count'])->all())->toBe(['all' => 3, 'ending' => 0, 'expired' => 1, 'unpriced' => 1])
        ->and($response->viewData('summary')['value'])->toBe(10000.0);

    $expired = $this->actingAs($agent)->get(route('ticketing.quotations.index', ['view' => 'expired']))->assertOk();
    expect($expired->viewData('quotations')->pluck('contact_name')->all())->toBe(['Expired Ella']);

    $unpriced = $this->actingAs($agent)->get(route('ticketing.quotations.index', ['view' => 'unpriced']))->assertOk()->assertSee('Not priced');
    expect($unpriced->viewData('quotations')->pluck('contact_name')->all())->toBe(['Unpriced Uma']);
});
