<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\ClientCaseNotification;
use App\Notifications\PassportPickupNotification;
use App\Support\CaseNotice;
use App\Support\CaseProgress;
use App\Support\ImmigrationCases;
use App\Support\MessageTemplates;
use Illuminate\Support\Facades\Notification;

/**
 * The Notice beside the status dropdown writes the message for the file's current status: copied for a
 * text or Messenger, or emailed. Only client-facing statuses have wording; admins can reword each one.
 */
function noticeAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Shey']);
}

function noticeCase(array $case = [], array $client = []): ImmigrationPetition
{
    $folder = ImmigrationClient::create($client + ['last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'P1234567A', 'email' => 'john@example.com']);

    return ImmigrationPetition::create($case + ['immigration_client_id' => $folder->id, 'type' => 'ecc', 'details' => []]);
}

function noticeEdit(ImmigrationPetition $case, array $extra = []): string
{
    return route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id] + $extra);
}

test('each client-facing status has its own template, a released file keeps the pick-up notice, and the checking steps have none', function (string $status, ?string $key) {
    $case = noticeCase(['workflow_status' => $status]);

    expect(CaseNotice::keyFor(ImmigrationCases::row($case, $case->client)))->toBe($key);
})->with([
    ['preparing', 'case_preparing'],
    ['for_validation', null],
    ['for_final_checking', null],
    ['for_submission', 'case_for_submission'],
    ['submitted', 'case_submitted'],
    ['awaiting_decision', 'case_awaiting_decision'],
    ['for_briefing', 'case_for_briefing'],
    ['approved', 'case_approved'],
    ['denied', 'case_denied'],
    ['withdrawn', 'case_withdrawn'],
    ['deferred', 'case_deferred'],
]);

test('released files send the pick-up notice, except a closing status which says how it ended', function () {
    $approved = noticeCase(['workflow_status' => 'approved', 'released_on' => now()->subDay()]);
    $denied = noticeCase(['workflow_status' => 'denied', 'released_on' => now()->subDay()]);

    expect(CaseNotice::keyFor(ImmigrationCases::row($approved, $approved->client)))->toBe('passport_pickup')
        ->and(CaseNotice::keyFor(ImmigrationCases::row($denied, $denied->client)))->toBe('case_denied');
});

test('every status template is registered for the Message templates page, with only known placeholders', function () {
    foreach (CaseNotice::STATUS_KEYS as $key) {
        expect(MessageTemplates::TEMPLATES)->toHaveKey($key);
        preg_match_all('/\{([a-z_]+)\}/', MessageTemplates::defaultBody($key).MessageTemplates::TEMPLATES[$key]['subject'], $found);
        expect(array_diff($found[1], array_keys(MessageTemplates::CASE_PLACEHOLDERS)))->toBe([])
            ->and(MessageTemplates::defaultBody($key))->not->toContain('amegatravelandtours');
    }

    $this->actingAs(noticeAdmin())->get(route('admin.message-templates.edit', 'case_submitted'))->assertOk()->assertSee('{filed_on}')->assertSee('{requirements}');
});

test('the Notice sits beside the status dropdown and writes the message for the current status', function () {
    $case = noticeCase(['workflow_status' => 'submitted', 'filed_on' => '2026-10-26']);

    $html = $this->actingAs(noticeAdmin())->get(noticeEdit($case))->assertOk()
        ->assertSee('<span>Notice</span>', false)->assertDontSee('Passport pick-up notice')
        ->assertSee('Good day, John Smith!')
        ->assertSee('was submitted to the Bureau of Immigration on October 26, 2026.')
        ->assertSee($case->reference().')', false)
        ->assertSee('Email to john@example.com')->assertSee('Edit wording')->getContent();

    // The button joins the status row; the panel wraps onto its own full-width line.
    expect($html)->toContain('class="contents"')->toContain('basis-full w-full mt-2')
        ->and(strpos($html, '<select name="status"'))->toBeLessThan(strpos($html, '<span>Notice</span>'));
});

test('On-going Preparation lists the documents still missing', function () {
    $case = noticeCase(['workflow_status' => 'preparing']);
    $missing = array_values($case->missingItems());

    $page = $this->actingAs(noticeAdmin())->get(noticeEdit($case))->assertOk()->assertSee('we still need the following from you');
    foreach (array_slice($missing, 0, 2) as $item) {
        $page->assertSee($item, false);
    }
});

test('a released file shows the passport pick-up wording, and the Notice says Released', function () {
    $case = noticeCase(['workflow_status' => 'approved', 'released_on' => now()->subDay()], ['visa_expiry_date' => '2026-12-20']);

    $this->actingAs(noticeAdmin())->get(noticeEdit($case))->assertOk()
        ->assertSee('Your passport and visa are now ready for collection and are valid until December 20, 2026.');
});

test('the internal checking steps need no notice', function (string $status) {
    $case = noticeCase(['workflow_status' => $status]);

    $this->actingAs(noticeAdmin())->get(noticeEdit($case))->assertOk()
        ->assertSee('No client notice is needed at this status.')->assertDontSee('Copy for text / Messenger');
})->with(['for_validation', 'for_final_checking']);

test('a file held for the admin offers no notice until it is approved', function (string $approval) {
    $case = noticeCase(['workflow_status' => 'submitted', 'approval_status' => $approval]);

    $this->actingAs(noticeAdmin())->get(noticeEdit($case))->assertOk()
        ->assertSee('Available once the admin approves this file.')->assertDontSee('Copy for text / Messenger');

    // The server refuses to email it too.
    Notification::fake();
    $this->actingAs(noticeAdmin())->post(route('admin.client-sheets.pickup-notice', $case->client), ['kind' => 'ecc', 'case' => $case->id, 'key' => 'case_submitted'])->assertSessionHas('error');
    Notification::assertNothingSent();
})->with(['pending', 'returned']);

test('the Notice is hidden in a preview', function () {
    $case = noticeCase(['workflow_status' => 'submitted']);

    $this->actingAs(noticeAdmin())->get(noticeEdit($case, ['preview' => 1]))->assertOk()
        ->assertDontSee('<span>Notice</span>', false)->assertDontSee('Copy for text / Messenger');
});

test('an admin-edited wording is the one used', function () {
    MessageTemplate::create(['key' => 'case_submitted', 'subject' => 'Filed: {reference}', 'body' => 'Hello {client_name}, filed {filed_on}, your passport {passport_number}. - {agent_name}']);
    $case = noticeCase(['workflow_status' => 'submitted', 'filed_on' => '2026-10-26']);

    $this->actingAs(noticeAdmin())->get(noticeEdit($case))->assertOk()
        ->assertSee('Hello John Smith, filed October 26, 2026, your passport P1234567A. - Shey');

    Notification::fake();
    $this->actingAs(noticeAdmin())->post(route('admin.client-sheets.pickup-notice', $case->client), ['kind' => 'ecc', 'case' => $case->id, 'key' => 'case_submitted'])->assertSessionHas('success');
    Notification::assertSentOnDemand(ClientCaseNotification::class, fn ($n) => $n->subject === 'Filed: '.$case->reference() && str_starts_with($n->body, 'Hello John Smith'));
});

test('the notice email is rendered on the server from the whitelisted key for the file status', function () {
    Notification::fake();
    $case = noticeCase(['workflow_status' => 'awaiting_decision', 'filed_on' => '2026-10-26']);
    $url = route('admin.client-sheets.pickup-notice', $case->client);

    $this->actingAs(noticeAdmin())->post($url, ['kind' => 'ecc', 'case' => $case->id, 'key' => 'case_awaiting_decision'])->assertSessionHas('success');
    Notification::assertSentOnDemand(ClientCaseNotification::class, function ($notification, $channels, $notifiable) use ($case) {
        $mail = $notification->toMail($notifiable);

        return array_key_exists('john@example.com', $notifiable->routes['mail'])
            && str_contains($mail->subject, 'awaiting the Bureau of Immigration decision')
            && str_contains((string) $mail->render(), $case->reference())
            && str_contains((string) $mail->render(), 'October 26, 2026');
    });
});

test('only whitelisted keys that match the file status are emailed', function () {
    Notification::fake();
    $case = noticeCase(['workflow_status' => 'preparing']);
    $url = route('admin.client-sheets.pickup-notice', $case->client);

    // Not a notice key at all (a template that exists but is not a client notice).
    $this->actingAs(noticeAdmin())->post($url, ['kind' => 'ecc', 'case' => $case->id, 'key' => 'flight_reminder'])->assertSessionHasErrors('key');
    $this->actingAs(noticeAdmin())->post($url, ['kind' => 'ecc', 'case' => $case->id, 'key' => 'nonsense'])->assertSessionHasErrors('key');
    // A real notice key, but not the one this status shows.
    $this->actingAs(noticeAdmin())->post($url, ['kind' => 'ecc', 'case' => $case->id, 'key' => 'case_approved'])->assertSessionHas('error', fn (string $m) => str_contains($m, 'another status'));
    // A status with no client notice.
    $internal = noticeCase(['workflow_status' => 'for_validation']);
    $this->actingAs(noticeAdmin())->post(route('admin.client-sheets.pickup-notice', $internal->client), ['kind' => 'ecc', 'case' => $internal->id])->assertSessionHas('error');
    // Another client's case is not reachable through this folder.
    $this->actingAs(noticeAdmin())->post($url, ['kind' => 'ecc', 'case' => $internal->id, 'key' => 'case_preparing'])->assertNotFound();

    Notification::assertNothingSent();
});

test('with no case the notice route still sends the passport pick-up notice, and a folder without an email is refused', function () {
    Notification::fake();
    $client = ImmigrationClient::factory()->create(['email' => 'john@example.com', 'visa_expiry_date' => '2026-12-20']);

    $this->actingAs(noticeAdmin())->post(route('admin.client-sheets.pickup-notice', $client))->assertSessionHas('success');
    Notification::assertSentOnDemand(PassportPickupNotification::class);

    $noEmail = ImmigrationClient::factory()->create(['email' => null]);
    $this->actingAs(noticeAdmin())->post(route('admin.client-sheets.pickup-notice', $noEmail))->assertSessionHas('error');
});

test('Visa Extension keeps its notice, labelled Notice, with the pick-up text', function () {
    $client = ImmigrationClient::factory()->create(['email' => 'john@example.com', 'visa_expiry_date' => '2026-12-20']);

    $this->actingAs(noticeAdmin())->get(route('admin.client-sheets.edit', $client))->assertOk()
        ->assertSee('<span>Notice</span>', false)->assertDontSee('Passport pick-up notice')
        ->assertSee('Your passport and visa are now ready for collection and are valid until December 20, 2026.')
        ->assertSee('Email to john@example.com');
});

test('PassportPickupNotification::message still renders the pick-up notice', function () {
    $client = ImmigrationClient::factory()->make(['visa_expiry_date' => '2026-12-20']);

    expect(PassportPickupNotification::message($client, 'Shey')['body'])->toContain('valid until December 20, 2026')
        ->and(CaseProgress::label(CaseProgress::RELEASED))->toBe('Released');
});
