<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\SrrvApplication;
use App\Models\User;
use App\Models\VisaApplication;
use App\Support\AdminNotifications;

/**
 * Files an agent sends for approval at the Immigration, Visa Assistance and SRRV desks reach the
 * admin inside the panel too (the bell and the Approvals menu), not only by email.
 */
test('the admin bell and menu count Immigration, Visa and SRRV files waiting for approval', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent']);

    $client = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun']);
    ImmigrationPetition::withoutGlobalScopes()->forceCreate(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [],
        'approval_status' => 'pending', 'approval_requested_at' => now(), 'acknowledged_by' => $agent->id]);
    VisaApplication::withoutGlobalScopes()->forceCreate(['reference' => 'VSA-T1', 'client_name' => 'Ana Cruz', 'client_phone' => '0917', 'service_type' => 'e_visa',
        'status' => 'requirements', 'approval_status' => 'pending', 'approval_requested_at' => now(), 'acknowledged_by' => $agent->id, 'created_by' => $agent->id]);
    SrrvApplication::withoutEvents(fn () => SrrvApplication::withoutGlobalScopes()->forceCreate(['reference' => 'SRRV-T1', 'service_type' => 'renewal_application',
        'visa_class' => 'classic', 'retiree_name' => 'Ramon Cruz', 'status' => 'pending', 'investment_amount' => 1500,
        'approval_status' => 'pending', 'approval_requested_at' => now(), 'acknowledged_by' => $agent->id, 'created_by' => $agent->id]));
    // Already approved: not counted.
    SrrvApplication::withoutEvents(fn () => SrrvApplication::withoutGlobalScopes()->forceCreate(['reference' => 'SRRV-T2', 'service_type' => 'renewal_application',
        'visa_class' => 'classic', 'retiree_name' => 'Lito Santos', 'status' => 'pending', 'investment_amount' => 1500, 'approval_status' => 'approved']));

    $notes = AdminNotifications::for($admin);
    expect($notes['immigration_approvals'])->toBe(1)
        ->and($notes['visa_approvals'])->toBe(1)
        ->and($notes['srrv_approvals'])->toBe(1)
        ->and($notes['total'])->toBeGreaterThanOrEqual(3)
        ->and(collect($notes['latest'])->pluck('title')->all())
        ->toContain('1 Immigration file is waiting for your approval')
        ->toContain('1 Visa file is waiting for your approval')
        ->toContain('1 SRRV file is waiting for your approval');

    // The Approvals menu shows the counts, and the bell's poll keeps them current.
    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
    expect($html)->toContain('data-notify-count="immigration_approvals"')->toContain('data-notify-count="srrv_approvals"');
    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertOk()->assertJsonPath('immigration_approvals', 1);

    // The agent who sent them is not told to approve their own files.
    expect(AdminNotifications::for($agent)['immigration_approvals'])->toBe(0);
});

test('an immigration file an agent saves shows for the admin on the dashboard, the Services menu and the desk bell', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
    $sheet = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123456']);

    // Saved the way the counter's form sends it.
    $this->actingAs($agent)->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'ecc', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'acknowledgement_required' => 1, 'acknowledged' => 1, 'then' => 'stay',
        'petition' => ['details' => ['ecc_kind' => 'ecc']],
    ])->assertSessionHasNoErrors();
    expect(ImmigrationPetition::withoutGlobalScopes()->sole()->approval_status)->toBe('pending');

    // The dashboard lists every kind of notification, with the immigration file waiting.
    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
    expect($html)->toContain('id="notifications-heading"')
        ->toContain('data-notification-kind="immigration_approvals"')
        ->toContain('data-notification-kind="inquiries"')
        ->toContain('1 Immigration file is waiting for your approval')
        // The Services menu's Immigration Counter carries the count too, beside the Approvals menu.
        ->and(substr_count($html, 'data-notify-count="immigration_approvals"'))->toBeGreaterThanOrEqual(2);

    // Working inside the Immigration counter, the admin still has the bell.
    $this->actingAs($admin)->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee('deskBell(', false)->assertSee('1 Immigration file is waiting for your approval');

    // The agent who sent it gets no bell for it.
    $this->actingAs($agent)->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertDontSee('waiting for your approval');
});

test('a desk approver who only works at the SRRV desk has the bell there, and its poll answers', function () {
    $supervisor = User::factory()->create(['role' => 'srrv', 'permissions' => ['srrv.view', 'srrv.create', 'srrv.edit', 'srrv.approve']]);

    $this->actingAs($supervisor)->get(route('srrv.dashboard'))->assertOk()->assertSee('deskBell(', false);
    $this->actingAs($supervisor)->getJson(route('admin.notifications'))->assertOk()->assertJsonPath('srrv_approvals', 0);

    // An officer with nothing to approve gets neither.
    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->assertDontSee('deskBell(', false);
    $this->actingAs($officer)->getJson(route('admin.notifications'))->assertForbidden();
});
