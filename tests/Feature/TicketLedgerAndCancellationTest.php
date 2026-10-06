<?php

use App\Models\ActivityLog;
use App\Models\TicketBooking;
use App\Models\TicketPayment;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function ledgerTicket(User $officer, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $officer->id,
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
    ], $overrides));
}

function ledgerInput(string $method = 'cash', array $more = []): array
{
    return array_merge(['method' => $method], $more);
}

// ---------------------------------------------------------------------------
// Payment history
// ---------------------------------------------------------------------------

test('a payment is kept as an entry with its method, reference and who took it', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.payment', $ticket), ledgerInput('gcash', [
        'amount' => 4000,
        'reference' => 'GC-778899',
        'received_at' => now()->subDay()->toDateString(),
        'note' => 'Down payment',
    ]))->assertSessionHas('success');

    $entry = $ticket->payments()->firstOrFail();

    expect($entry->type)->toBe(TicketPayment::TYPE_PAYMENT)
        ->and((float) $entry->amount)->toBe(4000.0)
        ->and($entry->method)->toBe('gcash')
        ->and($entry->reference)->toBe('GC-778899')
        ->and($entry->note)->toBe('Down payment')
        ->and($entry->received_by)->toBe($officer->id)
        ->and($entry->received_at->toDateString())->toBe(now()->subDay()->toDateString());

    $ticket->refresh();

    expect((float) $ticket->amount_paid)->toBe(4000.0)
        ->and($ticket->payment_status)->toBe(TicketBooking::PAYMENT_PARTIAL);
});

test('payments add up and the last one settles the booking', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.payment', $ticket), ledgerInput('cash', ['amount' => 4000]));
    $this->actingAs($officer)->post(route('ticketing.tickets.payment', $ticket), ledgerInput('bank_transfer', ['amount' => 6000]));

    $ticket->refresh();

    expect($ticket->payments)->toHaveCount(2)
        ->and((float) $ticket->amount_paid)->toBe(10000.0)
        ->and($ticket->isFullyPaid())->toBeTrue();
});

test('an amount above the balance or a bad method is refused and records nothing', function (array $input, string $field) {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.payment', $ticket), $input)->assertSessionHasErrors($field);

    expect($ticket->payments()->count())->toBe(0)
        ->and((float) $ticket->fresh()->amount_paid)->toBe(0.0);
})->with([
    'over the balance' => [['amount' => 10000.01, 'method' => 'cash'], 'amount'],
    'zero' => [['amount' => 0, 'method' => 'cash'], 'amount'],
    'unknown method' => [['amount' => 100, 'method' => 'barter'], 'method'],
    'no method' => [['amount' => 100], 'method'],
    'future date' => [['amount' => 100, 'method' => 'cash', 'received_at' => '2999-01-01'], 'received_at'],
]);

test('two agents recording at once add to each other instead of overwriting', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);

    // The second agent's page was loaded before the first payment went in.
    $stale = TicketBooking::findOrFail($ticket->id);

    $ticket->receivePayment(4000, 'cash', $officer);
    $stale->receivePayment(3000, 'gcash', $officer);

    expect((float) $ticket->fresh()->amount_paid)->toBe(7000.0)
        ->and($ticket->payments()->count())->toBe(2);

    // And the balance is checked against the true total, not the stale page.
    expect(fn () => $stale->receivePayment(4000, 'cash', $officer))->toThrow(DomainException::class);
});

test('the ticket page lists the history and each entry has a printable receipt', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);
    $first = $ticket->receivePayment(4000, 'cash', $officer, 'OR-77');
    $second = $ticket->receivePayment(1500, 'gcash', $officer);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Payment History')
        ->assertSee($first->receiptNumber())
        ->assertSee(route('ticketing.tickets.payments.receipt', [$ticket, $second]), false);

    $this->actingAs($officer)->get(route('ticketing.tickets.payments.receipt', [$ticket, $second]))
        ->assertOk()
        ->assertSee('Official Receipt')
        ->assertSee($second->receiptNumber())
        ->assertSee('₱1,500.00')
        ->assertSee('₱5,500.00')   // received to date
        ->assertSee('₱4,500.00');  // balance after this payment
});

test('a receipt cannot be opened through another booking', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $one = ledgerTicket($officer);
    $two = ledgerTicket($officer);
    $payment = $two->receivePayment(1000, 'cash', $officer);

    $this->actingAs($officer)->get(route('ticketing.tickets.payments.receipt', [$one, $payment]))->assertNotFound();
});

test('a booking already showing money received keeps one opening entry for it', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer, ['amount_paid' => 2500, 'payment_status' => 'partially_paid']);

    $migration = require base_path('database/migrations/2026_10_06_105224_create_ticket_payments_table.php');
    Schema::drop('ticket_payments');
    $migration->up();

    $entries = TicketPayment::where('ticket_booking_id', $ticket->id)->get();

    expect($entries)->toHaveCount(1)
        ->and((float) $entries->first()->amount)->toBe(2500.0)
        ->and($entries->first()->note)->toContain('before payment history');
});

// ---------------------------------------------------------------------------
// Cancelling
// ---------------------------------------------------------------------------

test('a booking is cancelled with a reason, who did it and when', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.cancel', $ticket), ['cancellation_reason' => 'Client changed travel dates'])
        ->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->isCancelled())->toBeTrue()
        ->and($ticket->cancellation_reason)->toBe('Client changed travel dates')
        ->and($ticket->cancelled_by)->toBe($officer->id)
        ->and($ticket->cancelled_at)->not->toBeNull();

    expect(ActivityLog::where('action', 'CANCEL')->first()->description)
        ->toContain($ticket->booking_reference)
        ->toContain('Client changed travel dates');

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Client changed travel dates')
        ->assertDontSee('Cancel this booking');
});

test('cancelling needs a reason', function (string $reason) {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.cancel', $ticket), ['cancellation_reason' => $reason])
        ->assertSessionHasErrors('cancellation_reason');

    expect($ticket->fresh()->isCancelled())->toBeFalse();
})->with(['empty' => [''], 'too short' => ['no']]);

test('cancelling a booking with money received says how much is owed back', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);
    $ticket->receivePayment(3000, 'cash', $officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.cancel', $ticket), ['cancellation_reason' => 'Client withdrew'])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '₱3,000.00') && str_contains($message, 'refund'));

    // Cancelling does not move any money.
    expect((float) $ticket->fresh()->amount_paid)->toBe(3000.0);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertSee('is still held')
        ->assertSee('Record refund');
});

test('a cancelled booking takes no more payments but can be refunded up to what was received', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);
    $ticket->receivePayment(3000, 'cash', $officer);
    $ticket->cancel($officer, 'Client withdrew');

    $this->actingAs($officer)->post(route('ticketing.tickets.payment', $ticket), ledgerInput('cash', ['amount' => 100]))
        ->assertSessionHas('error');

    $this->actingAs($officer)->post(route('ticketing.tickets.refund', $ticket), ledgerInput('cash', ['amount' => 3500]))
        ->assertSessionHasErrors('amount');

    $this->actingAs($officer)->post(route('ticketing.tickets.refund', $ticket), ledgerInput('bank_transfer', ['amount' => 2000, 'reference' => 'BT-1']))
        ->assertSessionHas('success');

    $refund = $ticket->payments()->where('type', TicketPayment::TYPE_REFUND)->firstOrFail();

    expect((float) $refund->amount)->toBe(2000.0)
        ->and($refund->receiptNumber())->toStartWith('REF-')
        ->and((float) $ticket->fresh()->amount_paid)->toBe(1000.0);

    $this->actingAs($officer)->get(route('ticketing.tickets.payments.receipt', [$ticket, $refund]))
        ->assertOk()
        ->assertSee('Refund Receipt');
});

test('only a cancelled booking can be refunded', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);
    $ticket->receivePayment(3000, 'cash', $officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.refund', $ticket), ledgerInput('cash', ['amount' => 1000]))
        ->assertSessionHas('error');

    expect((float) $ticket->fresh()->amount_paid)->toBe(3000.0);
});

test('an issued ticket can be cancelled, with a reminder to cancel it with the airline', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);
    $ticket->receivePayment(10000, 'cash', $officer);
    $ticket->markAsIssued($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.cancel', $ticket), ['cancellation_reason' => 'Flight no longer needed'])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'airline'));

    expect($ticket->fresh()->isCancelled())->toBeTrue();
});

test('a cancelled booking cannot be cancelled again or issued', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($officer);
    $ticket->receivePayment(10000, 'cash', $officer);
    $ticket->cancel($officer, 'Client withdrew');

    $this->actingAs($officer)->post(route('ticketing.tickets.cancel', $ticket), ['cancellation_reason' => 'Again for good measure'])
        ->assertSessionHas('error');

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1'])
        ->assertSessionHas('error');
});

test('an officer cannot cancel another officer\'s booking', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($owner);

    $this->actingAs($other)->post(route('ticketing.tickets.cancel', $ticket), ['cancellation_reason' => 'Not mine to cancel'])
        ->assertNotFound();

    expect($ticket->fresh()->isCancelled())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Documents are checked on the server
// ---------------------------------------------------------------------------

function documentBooking(array $passenger = []): array
{
    return [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu',
        'trip_type' => 'one_way',
        'departure_date' => now()->addMonths(2)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'passengers' => [array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'passenger_type' => 'adult',
            'nationality_type' => 'filipino',
            'government_id_file' => UploadedFile::fake()->image('umid.jpg'),
        ], $passenger)],
    ];
}

test('a passenger document of the wrong type or size is refused', function (string $field, UploadedFile $file) {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), documentBooking([$field => $file]))
        ->assertSessionHasErrors("passengers.0.{$field}");

    expect(TicketBooking::count())->toBe(0);
})->with([
    'passport as a program' => ['passport_file', fn () => UploadedFile::fake()->create('passport.exe', 10)],
    'visa as a script' => ['visa_file', fn () => UploadedFile::fake()->create('visa.php', 10)],
    'birth certificate over 5 MB' => ['birth_cert_file', fn () => UploadedFile::fake()->create('birth.pdf', 6000, 'application/pdf')],
    'government id as a document' => ['government_id_file', fn () => UploadedFile::fake()->create('id.docx', 10)],
]);

test('passenger documents within the limits are accepted', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), documentBooking([
        'passport_file' => UploadedFile::fake()->create('passport.pdf', 4000, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    expect(TicketBooking::firstOrFail()->documents)->toHaveCount(2);
});

test('an officer cannot open another officer\'s receipt or refund their booking', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = ledgerTicket($owner);
    $payment = $ticket->receivePayment(1000, 'cash', $owner);
    $ticket->cancel($owner, 'Client withdrew');

    $this->actingAs($other)->get(route('ticketing.tickets.payments.receipt', [$ticket, $payment]))->assertNotFound();
    $this->actingAs($other)->post(route('ticketing.tickets.refund', $ticket), ledgerInput('cash', ['amount' => 500]))->assertNotFound();
    $this->actingAs($other)->post(route('ticketing.tickets.payment', $ticket), ledgerInput('cash', ['amount' => 500]))->assertNotFound();

    expect((float) $ticket->fresh()->amount_paid)->toBe(1000.0);
});
