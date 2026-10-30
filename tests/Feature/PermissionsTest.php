<?php

use App\Models\StaffRole;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;

/**
 * Roles and permissions (App\Support\Permissions): one rule book. Accounts set up the old way keep
 * what they had; a role, plus extra permissions, decides for everyone else. Admins pass everything.
 */
test('accounts set up the old way keep exactly the pages they had', function (string $role, ?array $pages, array $opens, array $closed) {
    $user = User::factory()->create(['role' => $role, 'allowed_pages' => $pages]);

    foreach ($opens as $page) {
        expect($user->canAccessPage($page))->toBeTrue("{$role} should open {$page}");
    }
    foreach ($closed as $page) {
        expect($user->canAccessPage($page))->toBeFalse("{$role} should not open {$page}");
    }
})->with([
    'ticketing officer' => ['ticketing', null, ['ticketing'], ['immigration', 'dashboard', 'users', 'srrv']],
    'visa officer' => ['visa_assistance', null, ['visa_assistance'], ['ticketing', 'dashboard']],
    'srrv officer' => ['srrv', null, ['srrv'], ['ticketing', 'dashboard']],
    'cashier' => ['cashier', null, ['cashier'], ['ticketing', 'dashboard', 'users']],
    'immigration-only agent' => ['agent', ['immigration'], ['immigration'], ['dashboard', 'users', 'ticketing']],
    'multi-desk agent' => ['agent', ['ticketing', 'immigration', 'visa_assistance', 'srrv'], ['ticketing', 'immigration', 'visa_assistance', 'srrv'], ['dashboard', 'users']],
    'inquiries agent' => ['agent', ['inquiries'], ['inquiries'], ['bookings', 'dashboard']],
    'agent from before ticked pages' => ['agent', null, ['dashboard', 'bookings', 'inquiries', 'users', 'packages', 'destinations', 'chats'], ['staff', 'ticketing', 'faqs']],
    'client' => ['client', null, [], ['dashboard', 'users', 'ticketing']],
]);

test('the dedicated-desk routing is unchanged for old-style accounts', function () {
    $immigration = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
    expect($immigration->isImmigrationAgent())->toBeTrue()
        ->and($immigration->hasAdminAccess())->toBeFalse()
        ->and($immigration->staffHomeRoute())->toBe('admin.immigration.dashboard');

    $multi = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['ticketing', 'srrv']]);
    expect($multi->isDedicatedDeskStaff())->toBeFalse()->and($multi->hasAdminAccess())->toBeFalse();

    $office = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['users']]);
    expect($office->hasAdminAccess())->toBeTrue()->and($office->adminPanelRoute())->toBe('admin.users.index');
});

test('a role gives its permissions, extra permissions add to it, and nothing else is granted', function () {
    $role = StaffRole::create(['name' => 'Test Desk', 'permissions' => ['srrv.view', 'srrv.create', 'srrv.edit', 'not.a.permission']]);
    $user = User::factory()->create(['role' => 'agent']);
    $user->forceFill(['staff_role_id' => $role->id, 'permissions' => ['dashboard.view']])->save();
    $user->refresh();

    expect($user->effectivePermissions())->toBe(['srrv.view', 'srrv.create', 'srrv.edit', 'dashboard.view'])
        ->and($user->canAccessPage('srrv'))->toBeTrue()
        ->and($user->canAccessPage('dashboard'))->toBeTrue()
        ->and($user->canAccessPage('ticketing'))->toBeFalse()
        ->and($user->hasPermission('srrv.approve'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('srrv.edit'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('prices.manage'))->toBeFalse();

    // A role alone, no extras: the old ticked pages no longer count.
    $other = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['dashboard', 'users']]);
    $other->forceFill(['staff_role_id' => $role->id])->save();
    expect($other->refresh()->canAccessPage('dashboard'))->toBeFalse()->and($other->canAccessPage('srrv'))->toBeTrue();
});

test('admins pass every permission', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    expect($admin->effectivePermissions())->toBe(Permissions::all())
        ->and(Gate::forUser($admin)->allows('prices.manage'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('srrv.approve'))->toBeTrue();
});

test('the system roles are seeded and use only real permissions', function () {
    $roles = StaffRole::where('is_system', true)->pluck('name')->all();
    expect($roles)->toContain('Ticketing Agent', 'Immigration Agent', 'Visa Assistance Agent', 'SRRV Officer', 'Cashier', 'Front Desk', 'Supervisor', 'Content Editor');

    foreach (StaffRole::all() as $role) {
        expect($role->permissions)->toBe(Permissions::clean($role->permissions));
    }
    // The supervisor approves on every desk; desk agents see only their own files.
    $supervisor = StaffRole::where('name', 'Supervisor')->first();
    expect($supervisor->permissions)->toContain('srrv.approve', 'immigration.view_all', 'ticketing.approve');
    expect(StaffRole::where('name', 'Immigration Agent')->first()->permissions)->not->toContain('immigration.view_all', 'prices.manage');
});
