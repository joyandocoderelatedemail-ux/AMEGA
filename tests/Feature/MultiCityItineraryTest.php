<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\TicketBookedNotification;

function multiCityTicket(User $agent, array $legs): TicketBooking
{
    return TicketBooking::create([
        'booking_reference' => 'TKT-INT-'.fake()->unique()->numerify('########'),
        'created_by' => $agent->id,
        'travel_type' => 'international',
        'trip_type' => 'multi_city',
        'origin' => 'Manila (MNL)',
        'destination' => 'Tokyo (NRT)',
        'departure_date' => now()->addDays(20)->toDateString(),
        'multi_city_segments' => $legs,
        'total_passengers' => 1,
        'adults_count' => 1,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 30000,
    ]);
}

test('multi-city legs come out in travel order, with a chained route', function () {
    $ticket = multiCityTicket(User::factory()->create(['role' => 'ticketing']), [
        ['from' => 'KIX', 'to' => 'MNL', 'date' => now()->addDays(30)->toDateString()],
        ['from' => 'MNL', 'to' => 'NRT', 'date' => now()->addDays(20)->toDateString()],
        ['from' => 'NRT', 'to' => 'KIX', 'date' => now()->addDays(25)->toDateString()],
        ['from' => '', 'to' => ''],
    ]);

    expect(array_column($ticket->multiCityLegs(), 'from'))->toBe(['MNL', 'NRT', 'KIX'])
        ->and($ticket->routeSummary())->toBe('MNL → NRT → KIX → MNL')
        ->and($ticket->itineraryLines()[0])->toStartWith('1. MNL → NRT · ');

    // Without a date on every leg, the order entered is kept.
    $undated = multiCityTicket(User::factory()->create(['role' => 'ticketing']), [
        ['from' => 'CEB', 'to' => 'DVO', 'date' => null],
        ['from' => 'MNL', 'to' => 'CEB', 'date' => now()->addDays(20)->toDateString()],
    ]);
    expect(array_column($undated->multiCityLegs(), 'from'))->toBe(['CEB', 'MNL'])
        ->and($undated->routeSummary())->toBe('CEB → DVO, MNL → CEB');
});

test('the booking email and the ticket page list every leg in order', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = multiCityTicket($agent, [
        ['from' => 'NRT', 'to' => 'KIX', 'date' => now()->addDays(25)->toDateString()],
        ['from' => 'MNL', 'to' => 'NRT', 'date' => now()->addDays(20)->toDateString()],
    ]);

    $mail = (new TicketBookedNotification($ticket))->toMail($ticket);
    $text = implode("\n", $mail->introLines);
    expect($text)->toContain('**Itinerary:**')
        ->and(strpos($text, '1. MNL → NRT'))->toBeLessThan(strpos($text, '2. NRT → KIX'));

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSeeInOrder(['1. MNL &rarr; NRT', '2. NRT &rarr; KIX'], false);
});

test('the review step lists the multi-city legs', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('ticketing.tickets.create'))->getContent();

    expect($html)->toContain('get orderedLegs()')
        ->toContain('x-for="(leg, i) in orderedLegs"');
});
