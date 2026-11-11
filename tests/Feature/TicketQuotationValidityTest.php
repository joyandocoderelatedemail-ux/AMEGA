<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\TicketQuotationExpiringNotification;
use App\Support\TicketNotifications;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/** A saved quotation, made by $agent, valid until $validUntil. */
function quoteEndingOn(User $agent, ?string $validUntil, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-Q-'.fake()->unique()->numerify('####'),
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay',
        'departure_date' => today()->addMonths(2),
        'total_passengers' => 2,
        'contact_name' => 'Juan Dela Cruz',
        'total_amount' => 12000,
        'is_quotation' => true,
        'quote_valid_until' => $validUntil,
        'created_by' => $agent->id,
    ], $overrides));
}

test('a quotation is saved with the date the agent picked, or 7 days by default', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), quotePayload(['quote_valid_until' => today()->addDays(3)->toDateString()]))->assertSessionHasNoErrors();
    expect(TicketBooking::latest('id')->first()->quote_valid_until->toDateString())->toBe(today()->addDays(3)->toDateString());

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), quotePayload())->assertSessionHasNoErrors();
    expect(TicketBooking::latest('id')->first()->quote_valid_until->toDateString())->toBe(today()->addDays(TicketBooking::QUOTE_VALID_DAYS)->toDateString());
});

test('the date cannot be in the past', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), quotePayload(['quote_valid_until' => today()->subDay()->toDateString()]))
        ->assertSessionHasErrors('quote_valid_until');
});

test('the wizard asks until when the quotation is valid', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->get(route('ticketing.tickets.create'))->assertOk()
        ->assertSee('Quotation valid until')->assertSee('name="quote_valid_until"', false)
        ->assertSee('value="'.today()->addDays(TicketBooking::QUOTE_VALID_DAYS)->toDateString().'"', false);
});

test('the quotations list and the quotation page show the date', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ending = quoteEndingOn($agent, today()->toDateString());
    $ended = quoteEndingOn($agent, today()->subDays(2)->toDateString());

    $this->actingAs($agent)->get(route('ticketing.quotations.index'))->assertOk()
        ->assertSee('Valid until')->assertSee('Ends today')->assertSee('Expired');

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ended))->assertOk()
        ->assertSee('This quotation ended on '.today()->subDays(2)->format('M j, Y'))->assertSee('Change date');
});

test('the agent can give the quotation a new date, which starts the reminders over', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $quote = quoteEndingOn($agent, today()->toDateString());
    $quote->forceFill(['quote_expiry_notified_at' => now(), 'quote_expiry_seen_at' => now()])->save();

    $this->actingAs($agent)->post(route('ticketing.quotations.validity', $quote), ['quote_valid_until' => today()->addDays(10)->toDateString()])
        ->assertRedirect(route('ticketing.tickets.show', $quote));

    expect($quote->fresh())
        ->quote_valid_until->toDateString()->toBe(today()->addDays(10)->toDateString())
        ->quote_expiry_notified_at->toBeNull()
        ->quote_expiry_seen_at->toBeNull();

    $this->actingAs($agent)->post(route('ticketing.quotations.validity', $quote), ['quote_valid_until' => today()->subDay()->toDateString()])
        ->assertSessionHasErrors('quote_valid_until');
});

test('the agent bell shows their quotation from the day before it ends, until they open it', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $later = quoteEndingOn($agent, today()->addDays(5)->toDateString());
    $tomorrow = quoteEndingOn($agent, today()->addDay()->toDateString());
    $ended = quoteEndingOn($agent, today()->subDay()->toDateString());
    quoteEndingOn(User::factory()->create(['role' => 'ticketing']), today()->toDateString()); // someone else's

    $bell = TicketNotifications::forAgent($agent);
    $titles = collect($bell['latest'])->pluck('title')->implode(' | ');

    expect($bell['total'])->toBe(2)
        ->and($titles)->toContain("Quotation {$tomorrow->booking_reference} ends tomorrow")
        ->and($titles)->toContain("Quotation {$ended->booking_reference} ended on")
        ->and($titles)->not->toContain($later->booking_reference)
        ->and(collect($bell['latest'])->pluck('type')->unique()->all())->toBe(['quote']);

    // Opening it clears it from the bell.
    $this->actingAs($agent)->get(route('ticketing.tickets.show', $tomorrow))->assertOk();
    expect(TicketNotifications::forAgent($agent)['total'])->toBe(1);

    // Opening one that is not ending yet does not clear a future reminder.
    $this->actingAs($agent)->get(route('ticketing.tickets.show', $later))->assertOk();
    expect($later->fresh()->quote_expiry_seen_at)->toBeNull();
});

test('a quotation converted to a booking or cancelled is not flagged', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    quoteEndingOn($agent, today()->toDateString(), ['is_quotation' => false]);
    quoteEndingOn($agent, today()->toDateString(), ['status' => TicketBooking::STATUS_CANCELLED]);

    expect(TicketNotifications::forAgent($agent)['total'])->toBe(0);
});

test('the morning job emails the agent once for each quotation ending tomorrow, today or already ended', function () {
    Notification::fake();
    $agent = User::factory()->create(['role' => 'ticketing']);
    $tomorrow = quoteEndingOn($agent, today()->addDay()->toDateString());
    $ended = quoteEndingOn($agent, today()->subDays(3)->toDateString());
    $later = quoteEndingOn($agent, today()->addDays(4)->toDateString());
    $noDate = quoteEndingOn($agent, null);

    $this->artisan('ticketing:send-quotation-reminders')->assertSuccessful()->expectsOutputToContain('Sent 2');

    Notification::assertSentTo($agent, TicketQuotationExpiringNotification::class, fn ($n) => $n->ticket->is($tomorrow));
    Notification::assertSentTo($agent, TicketQuotationExpiringNotification::class, fn ($n) => $n->ticket->is($ended));
    Notification::assertSentToTimes($agent, TicketQuotationExpiringNotification::class, 2);
    expect($later->fresh()->quote_expiry_notified_at)->toBeNull()->and($noDate->fresh()->quote_expiry_notified_at)->toBeNull();

    // Not again the next morning.
    $this->artisan('ticketing:send-quotation-reminders')->expectsOutputToContain('Sent 0');
    Notification::assertSentToTimes($agent, TicketQuotationExpiringNotification::class, 2);
});

test('the reminder email names the quotation, the client and the end date', function () {
    $agent = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Agent']);
    $quote = quoteEndingOn($agent, today()->addDay()->toDateString());

    $mail = (new TicketQuotationExpiringNotification($quote))->toMail($agent);

    expect($mail->subject)->toBe("Quotation {$quote->booking_reference} ends tomorrow (".today()->addDay()->format('M j, Y').')')
        ->and(implode(' ', $mail->introLines))->toContain('Juan Dela Cruz')->toContain('₱12,000.00')
        ->and($mail->actionUrl)->toBe(route('ticketing.tickets.show', $quote));
});

test('the reminder runs every morning at 8', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'ticketing:send-quotation-reminders'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 8 * * *')->and($event->timezone)->toBe('Asia/Manila');
});

test('the booking agreement no longer mentions COVID-19', function () {
    foreach (['show', 'pdf'] as $view) {
        expect(file_get_contents(resource_path("views/ticketing/agreements/{$view}.blade.php")))
            ->not->toContain('Covid')->toContain('quarantine requirements and other minimum health protocols');
    }
});

test('the booking agreement carries the fares and Book & Buy term after the rules term', function () {
    foreach (['show', 'pdf'] as $view) {
        $html = file_get_contents(resource_path("views/ticketing/agreements/{$view}.blade.php"));

        expect($html)->toContain('I understand that all fares quoted are subject to change and seat availability, and are not guaranteed until full payment is received and the ticket is issued.')
            ->toContain('Book &amp; Buy (instant purchase) fares cannot be held')
            ->and(strpos($html, 'Book &amp; Buy'))->toBeGreaterThan(strpos($html, 'minimum health protocols'))
            ->and(strpos($html, 'Book &amp; Buy'))->toBeLessThan(strpos($html, 'might cancel and re-schedule'));
    }
});

test('the quotation says its date does not hold the airfare', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $quote = quoteEndingOn($agent, today()->addDays(5)->toDateString());

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $quote))->assertOk()
        ->assertSee('Airfares are subject to change and seat availability, and are not guaranteed until full payment is received and the ticket is issued.');
    $this->actingAs($agent)->get(route('ticketing.tickets.create'))->assertOk()
        ->assertSee('Book &amp; Buy fares cannot be held', false);
});
