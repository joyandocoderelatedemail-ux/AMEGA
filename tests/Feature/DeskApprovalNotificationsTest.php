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
