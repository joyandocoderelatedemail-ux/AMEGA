<?php

use App\Models\BookingAgreement;
use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Notifications\FileStatusChangedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\TicketBookedNotification;
use App\Notifications\TicketIssuedNotification;
use App\Notifications\VisaExpiryReminderNotification;
use App\Services\ClientNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

/**
 * @param  array<string, mixed>  $overrides
 */
function notifyTicket(User $owner, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria@example.com',
        'total_amount' => 10000,
        'status' => 'pending',
        'created_by' => $owner->id,
    ], $overrides));
}

test('a walk-in booking emails the client, but a quotation does not', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $payload = [
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'MNL',
        'destination' => 'CEB',
        'trip_type' => 'one_way',
        'travel_class' => 'economy',
        'departure_date' => now()->addDays(14)->format('Y-m-d'),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Arthur Dent',
        'contact_email' => 'arthur@example.com',
        'contact_phone' => '+63 920 444 5555',
        'passengers' => [['passenger_type' => 'adult', 'first_name' => 'Arthur', 'last_name' => 'Dent', 'gender' => 'male']],
    ];

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), $payload + ['save_as_quotation' => true])
        ->assertSessionHasNoErrors();
    Notification::assertNothingSent();

    $payload['passengers'][0]['government_id_file'] = UploadedFile::fake()->image('id.jpg');
    $this->actingAs($officer)->post(route('ticketing.tickets.store'), $payload)->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(TicketBookedNotification::class,
        fn ($notification, $channels, $notifiable) => array_key_exists('arthur@example.com', $notifiable->routes['mail']));
});

test('recording a ticket payment emails a receipt for the amount just received', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = notifyTicket($officer, ['amount_paid' => 2000, 'payment_status' => 'partially_paid']);

    $this->actingAs($officer)->post(route('ticketing.tickets.payment', $ticket), ['amount_paid' => 5000]);

    Notification::assertSentOnDemand(PaymentReceivedNotification::class, function ($notification, $channels, $notifiable) {
        return array_key_exists('maria@example.com', $notifiable->routes['mail'])
            && $notification->amountReceived === 3000.0
            && $notification->totalPaid === 5000.0
            && $notification->balance === 5000.0;
    });
});

test('lowering a ticket payment does not send a receipt', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = notifyTicket($officer, ['amount_paid' => 5000, 'payment_status' => 'partially_paid']);

    $this->actingAs($officer)->post(route('ticketing.tickets.payment', $ticket), ['amount_paid' => 4000]);

    Notification::assertNothingSent();
});

test('issuing a ticket emails the client', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = notifyTicket($officer);
    $ticket->recordPayment(10000);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1']);

    Notification::assertSentOnDemandTimes(TicketIssuedNotification::class, 1);
});

test('the issue email attaches the consent form, and the booking agreement once one exists', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = notifyTicket($officer, ['issued_by' => $officer->id]);

    $attachments = fn (): array => collect((new TicketIssuedNotification($ticket->fresh()))->toMail($ticket)->rawAttachments)
        ->pluck('name')
        ->all();

    expect($attachments())->toBe(["Data-Privacy-Consent-{$ticket->booking_reference}.pdf"]);

    BookingAgreement::create([
        'ticket_booking_id' => $ticket->id,
        'agreement_number' => 'AGR-202609-MAIL',
        'client_names' => 'Maria Santos',
        'agreement_date' => now(),
        'total_amount' => 10000,
        'status' => 'generated',
    ]);

    $mail = (new TicketIssuedNotification($ticket->fresh()))->toMail($ticket);

    expect($attachments())->toBe([
        "Data-Privacy-Consent-{$ticket->booking_reference}.pdf",
        'Booking-Agreement-AGR-202609-MAIL.pdf',
    ]);

    foreach ($mail->rawAttachments as $attachment) {
        expect($attachment['data'])->toStartWith('%PDF')
            ->and($attachment['options']['mime'])->toBe('application/pdf');
    }

    expect(implode(' ', $mail->introLines))->toContain('your Data Privacy Consent Form and your Booking Agreement');
});

test('clients without a real email address are never emailed', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    foreach (['', 'not-an-email', 'client.maria.abc123@clients.amegatravel.local'] as $email) {
        $ticket = notifyTicket($officer, ['contact_email' => $email]);
        $ticket->recordPayment(10000);
        $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1']);
    }

    Notification::assertNothingSent();
    expect(ClientNotifier::canReceive('maria@example.com'))->toBeTrue();
});

test('a visa file emails the client on payment, stage change and cancellation', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = VisaApplication::create([
        'reference' => 'VSA-NOTIFY',
        'client_name' => 'Jane Tan',
        'client_email' => 'jane@example.com',
        'service_type' => 'visit_visa',
        'status' => 'pending',
        'total_amount' => 3500,
        'amount_paid' => 0,
        'currency' => 'PHP',
        'created_by' => $officer->id,
    ]);
    $application->applicants()->create(['applicant_number' => 1, 'first_name' => 'Jane', 'last_name' => 'Tan', 'is_primary' => true]);

    $this->actingAs($officer)->post(route('visa.applications.payments', $application), ['amount' => 1000]);
    $this->actingAs($officer)->post(route('visa.applications.advance', $application))->assertSessionHas('success');
    $this->actingAs($officer)->post(route('visa.applications.cancel', $application));

    $requirements = $application->fresh()->stageLabel('requirements');

    Notification::assertSentOnDemand(PaymentReceivedNotification::class, fn ($n) => $n->service === 'visa application' && $n->balance === 2500.0);
    Notification::assertSentOnDemand(FileStatusChangedNotification::class, fn ($n) => $n->stageLabel === $requirements);
    Notification::assertSentOnDemand(FileStatusChangedNotification::class, fn ($n) => $n->stageLabel === 'Cancelled');
});

test('srrv payments and a renewal collection email the retiree', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $application = SrrvApplication::create([
        'reference' => 'SRRV-NOTIFY', 'retiree_name' => 'Ramon Cruz', 'retiree_email' => 'ramon@example.com',
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'status' => 'pending',
        'service_fee' => 1000, 'amount_paid' => 0, 'currency' => 'USD', 'created_by' => $officer->id,
    ]);
    $renewal = SrrvRenewal::create([
        'reference' => 'REN-NOTIFY', 'retiree_name' => 'Ramon Cruz', 'retiree_email' => 'ramon@example.com',
        'visa_class' => 'classic', 'status' => 'ready_for_collection', 'fee_amount' => 360, 'amount_paid' => 360,
        'currency' => 'USD', 'created_by' => $officer->id,
    ]);

    $this->actingAs($officer)->post(route('srrv.applications.payments', $application), ['amount' => 400]);
    $this->actingAs($officer)->post(route('srrv.renewals.collect', $renewal), ['collected_by_name' => 'Ramon Cruz'])
        ->assertSessionHas('success');

    Notification::assertSentOnDemand(PaymentReceivedNotification::class, fn ($n) => $n->service === 'SRRV application' && $n->currency === 'USD');
    Notification::assertSentOnDemand(FileStatusChangedNotification::class, fn ($n) => $n->service === 'SRRV renewal');
});

test('immigration clients are reminded once per expiry date', function () {
    $expiring = ImmigrationClient::factory()->create(['email' => 'expiring@example.com', 'visa_expiry_date' => now()->addDays(5)]);
    ImmigrationClient::factory()->create(['email' => 'later@example.com', 'visa_expiry_date' => now()->addDays(30)]);
    ImmigrationClient::factory()->create(['email' => 'lapsed@example.com', 'visa_expiry_date' => now()->subDay()]);

    $this->artisan('immigration:send-expiry-reminders')->assertSuccessful();
    $this->artisan('immigration:send-expiry-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(VisaExpiryReminderNotification::class, 1);
    Notification::assertSentOnDemand(VisaExpiryReminderNotification::class,
        fn ($n, $channels, $notifiable) => array_key_exists('expiring@example.com', $notifiable->routes['mail']));

    // An extension moves the expiry date, so the new date earns a new reminder.
    $expiring->update(['visa_expiry_date' => now()->addDays(6)]);
    $this->artisan('immigration:send-expiry-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(VisaExpiryReminderNotification::class, 2);
});

test('the reminder email reads correctly', function () {
    $client = ImmigrationClient::factory()->create(['given_name' => 'Irwan', 'visa_expiry_date' => now()->addDays(3)]);

    $mail = (new VisaExpiryReminderNotification($client))->toMail(new stdClass);

    expect($mail->subject)->toBe('Your visa expires in 3 days')
        ->and($mail->greeting)->toBe('Hello Irwan,');
});

test('a mail failure never breaks the desk action', function () {
    Notification::swap(new class
    {
        public function route(): static
        {
            return $this;
        }

        public function notifyNow(): void
        {
            throw new RuntimeException('SMTP down');
        }
    });

    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = notifyTicket($officer);
    $ticket->recordPayment(10000);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1'])
        ->assertSessionHas('success');

    expect($ticket->fresh()->isIssued())->toBeTrue();
});
