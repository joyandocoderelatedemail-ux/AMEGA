<?php

use App\Models\User;
use App\Models\VisaApplication;
use App\Notifications\VisaStageDoneNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

/**
 * A local passporting file sitting at the given stage.
 *
 * @param  array<string, mixed>  $overrides
 */
function passportFileAt(User $officer, string $status, array $overrides = []): VisaApplication
{
    return VisaApplication::create(array_merge([
        'reference' => 'VSA-'.fake()->unique()->numerify('######'),
        'created_by' => $officer->id,
        'service_type' => 'passporting',
        'passport_type' => 'local',
        'status' => $status,
        'client_name' => 'Jane Tan',
        'client_email' => 'jane@example.com',
    ], $overrides));
}

test('the file lists each finished stage with a notify button', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = passportFileAt($officer, 'payment', ['appointment_at' => now()->addWeek()]);

    expect($application->completedStages())->toBe(['pending', 'requirements', 'appointment']);

    $this->actingAs($officer)->get(route('visa.applications.show', $application))
        ->assertOk()
        ->assertSee('Client Updates')
        ->assertSee('DFA Appointment done')
        ->assertSee(route('visa.applications.stages.notify', [$application, 'requirements']))
        ->assertSee(route('visa.applications.stages.notify', [$application, 'appointment']))
        ->assertDontSee(route('visa.applications.stages.notify', [$application, 'payment']));
});

test('staff can email the client that a stage is done, and the file remembers when', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = passportFileAt($officer, 'payment', ['appointment_at' => '2026-10-03 10:00:00']);

    $this->actingAs($officer)->post(route('visa.applications.stages.notify', [$application, 'appointment']))
        ->assertSessionHas('success', 'Emailed jane@example.com that DFA Appointment is done.');

    Notification::assertSentOnDemand(VisaStageDoneNotification::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);

        return array_key_exists('jane@example.com', $notifiable->routes['mail'])
            && $mail->subject === "DFA Appointment done - {$notification->application->reference}"
            && str_contains(implode(' ', $mail->introLines), 'Saturday, October 3, 2026 at 10:00 AM')
            && str_contains(implode(' ', $mail->introLines), '**Next step:** Payment');
    });

    expect($application->fresh()->stageNotifiedAt('appointment'))->not->toBeNull()
        ->and($application->fresh()->stageNotifiedAt('requirements'))->toBeNull();
});

test('a stage that is not finished yet cannot be sent', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = passportFileAt($officer, 'requirements');

    $this->actingAs($officer)->post(route('visa.applications.stages.notify', [$application, 'requirements']))
        ->assertSessionHas('error', 'Only a finished stage can be sent to the client.');

    Notification::assertNothingSent();
});

test('nothing is sent when the client has no email address', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = passportFileAt($officer, 'payment', ['client_email' => null]);

    $this->actingAs($officer)->post(route('visa.applications.stages.notify', [$application, 'requirements']))
        ->assertSessionHas('error');

    Notification::assertNothingSent();
});

test('a released passport tells the client to collect it', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = passportFileAt($officer, 'released');

    expect($application->completedStages())->toContain('released')
        ->and((new VisaStageDoneNotification($application, 'released'))->doneLine())
        ->toBe('Your passport has been released. Please visit our office to collect it.');
});
