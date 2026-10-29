<?php

use App\Models\SrrvApplication;
use App\Models\StaffRole;
use App\Models\User;
use App\Support\AdminNavigation;

/**
 * Maker-checker: a desk's Approve permission can be given to a supervisor; nobody approves a file they
 * sent; only a desk's sole approver has their own saves approved on the spot.
 */
function makerCheckerSrrvFile(object $test, User $by, string $name = 'Ramon Cruz'): SrrvApplication
{
    $test->actingAs($by)->post(route('srrv.applications.store'), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => $name, 'retiree_email' => str()->slug($name).'@example.com',
        'retiree_phone' => '09170000000', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'nationality' => 'Filipino', 'investment_amount' => 1500,
    ])->assertRedirect();

    return SrrvApplication::withoutGlobalScopes()->latest('id')->first();
}

function supervisor(): User
{
    $user = User::factory()->create(['role' => 'agent', 'name' => 'Sam Supervisor']);
    $user->forceFill(['staff_role_id' => StaffRole::where('name', 'Supervisor')->value('id')])->save();

    return $user->refresh();
}

test('a supervisor approves other people\'s files from the approvals page, but never their own', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $boss = supervisor();
    $admin = User::factory()->create(['role' => 'admin']);

    $file = makerCheckerSrrvFile($this, $officer);
    expect($file->approval_status)->toBe('pending');

    // The supervisor has the Approvals menu and page, and sees the officer's file to check it.
    $this->actingAs($boss);
    $labels = collect(AdminNavigation::for($boss))->flatMap(fn (array $group) => collect($group['items'])->pluck('label'))->all();
    expect($labels)->toContain('SRRV Approvals', 'Ticket Approvals');
    $this->actingAs($boss)->get(route('admin.srrv-approvals.index'))->assertOk()->assertSee('Ramon Cruz');

    $this->actingAs($boss)->post(route('admin.srrv-approvals.approve', ['application', $file->id]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    expect($file->fresh()->approval_status)->toBe('approved')->and($file->fresh()->reviewed_by)->toBe($boss->id);

    // Their own file waits for someone else (the admin), and they cannot approve it.
    $own = makerCheckerSrrvFile($this, $boss, 'Lourdes Garcia');
    expect($own->approval_status)->toBe('pending');
    $this->actingAs($boss)->post(route('admin.srrv-approvals.approve', ['application', $own->id]), ['admin_acknowledged' => 1])
        ->assertSessionHas('error', 'You sent this file yourself, so another approver must approve it.');
    expect($own->fresh()->approval_status)->toBe('pending');

    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['application', $own->id]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    expect($own->fresh()->approval_status)->toBe('approved');
});

test('a desk\'s only approver has their own saves approved on the spot; with a second approver they wait', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $alone = makerCheckerSrrvFile($this, $admin);
    expect($alone->approval_status)->toBe('approved');

    $second = User::factory()->create(['role' => 'admin']);
    $now = makerCheckerSrrvFile($this, $admin, 'Second File');
    expect($now->approval_status)->toBe('pending');

    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['application', $now->id]), ['admin_acknowledged' => 1])->assertSessionHas('error');
    $this->actingAs($second)->post(route('admin.srrv-approvals.approve', ['application', $now->id]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    expect($now->fresh()->approval_status)->toBe('approved');
});

test('without the Approve permission the approvals pages are closed', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['srrv', 'dashboard']]);

    $this->actingAs($agent)->get(route('admin.srrv-approvals.index'))->assertForbidden();
    $this->actingAs($agent);
    $labels = collect(AdminNavigation::for($agent))->flatMap(fn (array $group) => collect($group['items'])->pluck('label'))->all();
    expect($labels)->not->toContain('SRRV Approvals');
});
