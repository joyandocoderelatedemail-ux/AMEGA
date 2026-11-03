<?php

use App\Models\ImmigrationClient;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\ClientCaseNotification;
use App\Support\MessageTemplates;
use Illuminate\Support\Facades\Notification;

test('admins edit the client message wording, with a preview; others cannot', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.message-templates.index'))->assertOk()
        ->assertSee('Flight Reminder')->assertSee('Passport Pick-up Notice')->assertSee('Case Notice: On-going Preparation')->assertSee('Case Notice: Deferred by Applicant')->assertSee('Default wording');
    expect(MessageTemplates::TEMPLATES)->toHaveCount(11);
    $this->actingAs($admin)->get(route('admin.message-templates.edit', 'passport_pickup'))->assertOk()
        ->assertSee('{valid_until}')->assertSee('Preview')->assertSee('Astrofield Building');

    $this->actingAs($admin)->put(route('admin.message-templates.update', 'passport_pickup'), ['subject' => 'Ready!', 'body' => 'Valid until {valid_until}.'])
        ->assertRedirect(route('admin.message-templates.edit', 'passport_pickup'));
    expect(MessageTemplate::where('key', 'passport_pickup')->value('body'))->toBe('Valid until {valid_until}.');
    $this->actingAs($admin)->get(route('admin.message-templates.index'))->assertSee('Edited');

    $this->actingAs($admin)->put(route('admin.message-templates.update', 'passport_pickup'), ['subject' => '', 'body' => ''])
        ->assertSessionHasErrors(['subject', 'body']);
    $this->actingAs($admin)->get('/admin/message-templates/unknown/edit')->assertNotFound();

    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration', 'users']]);
    $this->actingAs($agent)->get(route('admin.message-templates.index'))->assertRedirect();
    $this->actingAs($agent)->put(route('admin.message-templates.update', 'passport_pickup'), ['subject' => 'x', 'body' => 'y'])->assertRedirect();
    expect(MessageTemplate::where('key', 'passport_pickup')->value('body'))->toBe('Valid until {valid_until}.');
});

test('the folder offers the passport pick-up notice to copy and email', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Shey']);
    $client = ImmigrationClient::factory()->create(['email' => 'john@example.com', 'visa_expiry_date' => '2026-12-20']);

    $this->actingAs($admin)->get(route('admin.client-sheets.edit', $client))->assertOk()
        ->assertSee('<span>Notice</span>', false)->assertDontSee('Passport pick-up notice')
        ->assertSee('Your passport and visa are now ready for collection and are valid until December 20, 2026.')
        ->assertSee('Copy for text / Messenger')->assertSee('Notify client');

    $this->actingAs($admin)->post(route('admin.client-sheets.pickup-notice', $client), ['subject' => 'Your passport and visa are ready for collection', 'message' => 'Valid until December 20, 2026.'])
        ->assertSessionHas('success');
    Notification::assertSentOnDemand(ClientCaseNotification::class, function ($notification, $channels, $notifiable) {
        return array_key_exists('john@example.com', $notifiable->routes['mail'])
            && str_contains((string) $notification->toMail($notifiable)->render(), 'December 20, 2026');
    });

    // No email on the folder: only the copy is offered, and emailing is refused.
    $noEmail = ImmigrationClient::factory()->create(['email' => null]);
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', $noEmail))->assertSee('No email on the folder');
    $this->actingAs($admin)->post(route('admin.client-sheets.pickup-notice', $noEmail), ['subject' => 'x', 'message' => 'y'])->assertSessionHas('error');
});
