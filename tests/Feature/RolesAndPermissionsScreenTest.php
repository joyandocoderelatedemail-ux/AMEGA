<?php

use App\Models\ActivityLog;
use App\Models\StaffRole;
use App\Models\User;

/**
 * Users > Roles & Permissions: admins keep the named sets of permissions, give staff a role plus extras,
 * and every change to someone's access is logged.
 */
test('only admins open Roles & Permissions', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['staff', 'users']]);

    $this->actingAs($agent)->get(route('admin.roles.index'))->assertRedirect();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.roles.index'))->assertOk()
        ->assertSee('Supervisor')->assertSee('Ticketing Agent')->assertSee(route('admin.roles.create'), false);
});

test('an admin creates and edits a role; the change reaches everyone with it, and is logged', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Junior Ticketing', 'description' => 'Books flights, no refunds.', 'permissions' => ['ticketing.view', 'ticketing.create', 'not.real'],
    ])->assertSessionHasErrors('permissions.2');

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Junior Ticketing', 'description' => 'Books flights, no refunds.', 'permissions' => ['ticketing.view', 'ticketing.create'],
    ])->assertRedirect(route('admin.roles.index'));
    $role = StaffRole::where('name', 'Junior Ticketing')->firstOrFail();

    $agent = User::factory()->create(['role' => 'agent']);
    $agent->forceFill(['staff_role_id' => $role->id, 'permissions' => []])->save();
    expect($agent->fresh()->hasPermission('ticketing.edit'))->toBeFalse();

    $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertOk()->assertSee('1 person has this role', false);
    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'Junior Ticketing', 'permissions' => ['ticketing.view', 'ticketing.create', 'ticketing.edit'],
    ])->assertRedirect(route('admin.roles.index'));

    expect($agent->fresh()->hasPermission('ticketing.edit'))->toBeTrue()
        ->and(ActivityLog::where('action', 'UPDATE_ROLE')->latest('id')->value('description'))->toContain('added ticketing.edit');
});

test('standard roles and roles still given to someone are not deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $standard = StaffRole::where('name', 'Supervisor')->firstOrFail();
    $custom = StaffRole::create(['name' => 'Temp', 'permissions' => ['faqs.view']]);
    User::factory()->create(['role' => 'agent'])->forceFill(['staff_role_id' => $custom->id])->save();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $standard))->assertSessionHas('error');
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom))->assertSessionHas('error');
    expect(StaffRole::find($standard->id))->not->toBeNull()->and(StaffRole::find($custom->id))->not->toBeNull();

    User::where('staff_role_id', $custom->id)->update(['staff_role_id' => null]);
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom))->assertSessionHas('success');
    expect(StaffRole::find($custom->id))->toBeNull();
});

test('an admin gives a staff member a role and extras; the change is logged', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['inquiries']]);
    $frontDesk = StaffRole::where('name', 'Front Desk')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.agents.edit', $agent))->assertOk()->assertSee('name="access_role"', false);

    $this->actingAs($admin)->put(route('admin.agents.update', $agent), [
        'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => $agent->email, 'phone' => '0917 000 1111',
        'access_form' => 1, 'staff_role_id' => $frontDesk->id, 'permissions' => ['faqs.view', 'faqs.edit'],
    ])->assertSessionHasNoErrors();

    $agent->refresh();
    expect($agent->staff_role_id)->toBe($frontDesk->id)
        ->and($agent->canAccessPage('chats'))->toBeTrue()
        ->and($agent->hasPermission('faqs.edit'))->toBeTrue()
        ->and($agent->hasPermission('prices.manage'))->toBeFalse()
        ->and(ActivityLog::where('action', 'UPDATE_ACCESS')->latest('id')->value('description'))->toContain('Front Desk')->toContain('faqs.edit');
});

test('a new account is made with one Role choice: a staff role, Cashier, Custom or Administrator', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $html = $this->actingAs($admin)->get(route('admin.agents.create'))->assertOk()->getContent();
    expect(substr_count($html, '<select id="access_role" name="access_role"'))->toBe(1)
        ->and($html)->not->toContain('name="staff_role_id"')->not->toContain('Account Role');

    $make = fn (string $name, string $choice, array $extra = []) => $this->actingAs($admin)->post(route('admin.agents.store'), [
        'first_name' => $name, 'last_name' => 'Staff', 'email_name' => strtolower($name), 'phone' => '09171234567',
        'password' => 'secret123', 'password_confirmation' => 'secret123', 'access_form' => 1, 'access_role' => $choice,
    ] + $extra)->assertSessionHasNoErrors();

    $ticketingRole = StaffRole::where('name', 'Ticketing Agent')->first();
    $make('Tina', 'role:'.$ticketingRole->id, ['permissions' => ['faqs.view']]);
    $tina = User::where('email', 'like', 'tina@%')->firstOrFail();
    expect($tina->role)->toBe('agent')->and($tina->staff_role_id)->toBe($ticketingRole->id)
        ->and($tina->canAccessPage('ticketing'))->toBeTrue()->and($tina->canAccessPage('faqs'))->toBeTrue()
        ->and($tina->staffHomeRoute())->not->toBe('admin.dashboard');

    $make('Carla', 'role:'.StaffRole::where('name', 'Cashier')->value('id'));
    expect(User::where('email', 'like', 'carla@%')->first()->role)->toBe('cashier');

    $make('Cora', 'custom', ['permissions' => ['srrv.view', 'srrv.create']]);
    $cora = User::where('email', 'like', 'cora@%')->first();
    expect($cora->role)->toBe('agent')->and($cora->staff_role_id)->toBeNull()->and($cora->canAccessPage('srrv'))->toBeTrue();

    $make('Adam', 'admin', ['permissions' => ['faqs.view']]);
    $adam = User::where('email', 'like', 'adam@%')->first();
    expect($adam->role)->toBe('admin')->and($adam->getAttribute('permissions'))->toBeNull();
});

test('one Edit form changes any staff member\'s role, with the admin safeguards', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'ticketing', 'first_name' => 'Tess', 'last_name' => 'Lim']);
    $details = fn (User $u) => ['first_name' => $u->first_name ?: 'Staff', 'last_name' => $u->last_name ?: 'Person', 'email' => $u->email, 'phone' => '09171234567'];

    // A desk officer opens the same form, showing Custom (set up before roles).
    $this->actingAs($admin)->get(route('admin.agents.edit', $officer))->assertOk()
        ->assertSee("accessRole: 'custom'", false);

    // Give them the Supervisor role.
    $supervisor = StaffRole::where('name', 'Supervisor')->first();
    $this->actingAs($admin)->put(route('admin.agents.update', $officer), $details($officer) + ['access_role' => 'role:'.$supervisor->id])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.agents.index'));
    expect($officer->fresh()->role)->toBe('agent')->and($officer->fresh()->staff_role_id)->toBe($supervisor->id)
        ->and($officer->fresh()->hasPermission('srrv.approve'))->toBeTrue();

    // Make them an administrator.
    $this->actingAs($admin)->put(route('admin.agents.update', $officer), $details($officer) + ['access_role' => 'admin']);
    expect($officer->fresh()->isAdmin())->toBeTrue()->and($officer->fresh()->staff_role_id)->toBeNull();

    // Nobody removes their own admin access; another admin can.
    $this->actingAs($admin)->put(route('admin.agents.update', $admin), $details($admin) + ['access_role' => 'custom'])
        ->assertSessionHas('error', 'You cannot take away your own administrator access. Ask another admin.');
    expect($admin->fresh()->isAdmin())->toBeTrue();
    $this->actingAs($officer->fresh())->put(route('admin.agents.update', $admin), $details($admin) + ['access_role' => 'role:'.$supervisor->id])
        ->assertSessionHasNoErrors();
    expect($admin->fresh()->isAdmin())->toBeFalse()->and($admin->fresh()->staff_role_id)->toBe($supervisor->id);
});
