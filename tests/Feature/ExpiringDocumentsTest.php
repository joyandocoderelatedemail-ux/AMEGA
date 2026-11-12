<?php

use App\Models\ExpiryReminder;
use App\Models\ImmigrationClient;
use App\Models\User;
use App\Notifications\DocumentExpiryReminderNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function expiringClient(array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => 'client', 'phone' => '09171234567'], $attributes));
}

test('the page lists visas, passports and ACR cards soonest first, with Remind', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $passport = expiringClient(['name' => 'Pat Passport', 'email' => 'pat@example.com', 'passport_number' => 'P1234567', 'passport_expiry' => today()->addDays(40)]);
    expiringClient(['name' => 'Ann Acr', 'email' => 'ann@example.com', 'acr_number' => 'E7654321', 'acr_valid_until' => today()->subDays(5)]);
    ImmigrationClient::create(['last_name' => 'Visaholder', 'given_name' => 'Vic', 'email' => 'vic@example.com', 'visa_expiry_date' => today()->addDays(10)]);
    expiringClient(['name' => 'Far Future', 'email' => 'far@example.com', 'passport_expiry' => today()->addYears(3)]);

    $this->actingAs($admin)->get(route('admin.expiring-documents.index'))->assertOk()
        ->assertSeeInOrder(['Ann Acr', 'ACR I-Card', 'Expired 5 days ago', 'Vic Visaholder', 'Visa', 'In 10 days', 'Pat Passport', 'Passport', 'P1234567', 'In 40 days'])
        ->assertSee('Remind')
        ->assertDontSee('Far Future');

    // Filtered to passports, all dates.
    $this->actingAs($admin)->get(route('admin.expiring-documents.index', ['document' => 'passport', 'within' => 'all']))->assertOk()
        ->assertSee('Pat Passport')->assertSee('Far Future')->assertDontSee('Ann Acr');

    // The dashboard sums it up and links to the page.
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Expiring Client Documents')
        ->assertSee(route('admin.expiring-documents.index'), false);

    expect($passport)->not->toBeNull();
});

test('Remind emails the client and records who sent it', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $client = expiringClient(['name' => 'Pat Passport', 'email' => 'pat@example.com', 'passport_expiry' => today()->addDays(40)]);

    $this->actingAs($admin)->post(route('admin.expiring-documents.remind'), ['key' => "passport:user:{$client->id}"])
        ->assertSessionHas('success');

    Notification::assertSentTo(new AnonymousNotifiable, DocumentExpiryReminderNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['pat@example.com' => 'Pat Passport'] && $notification->document === 'passport');

    $reminder = ExpiryReminder::sole();
    expect($reminder->sent_by)->toBe($admin->id)->and($reminder->channel)->toBe('email');

    $this->actingAs($admin)->get(route('admin.expiring-documents.index'))->assertSee('By '.$admin->name);
});

test('a client with no email cannot be reminded by email', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $client = expiringClient(['name' => 'No Mail', 'email' => 'nomail'.User::PLACEHOLDER_EMAIL_DOMAIN, 'passport_expiry' => today()->addDays(10)]);

    $this->actingAs($admin)->post(route('admin.expiring-documents.remind'), ['key' => "passport:user:{$client->id}"])
        ->assertSessionHas('error');
    expect(ExpiryReminder::count())->toBe(0);
});

test('the daily job reminds each document once, a month ahead, and skips ones staff already reminded', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $due = expiringClient(['email' => 'due@example.com', 'passport_expiry' => today()->addDays(25)]);
    $already = expiringClient(['email' => 'done@example.com', 'acr_valid_until' => today()->addDays(20)]);
    expiringClient(['email' => 'later@example.com', 'passport_expiry' => today()->addDays(45)]);
    expiringClient(['email' => 'gone@example.com', 'passport_expiry' => today()->subDays(3)]);

    $this->actingAs($admin)->post(route('admin.expiring-documents.remind'), ['key' => "acr:user:{$already->id}"]);
    Notification::fake();

    $this->artisan('clients:send-expiry-reminders')->assertSuccessful();
    $this->artisan('clients:send-expiry-reminders')->assertSuccessful();

    Notification::assertSentTimes(DocumentExpiryReminderNotification::class, 1);
    expect(ExpiryReminder::where('doc_key', "passport:user:{$due->id}")->whereNull('sent_by')->count())->toBe(1);
});
