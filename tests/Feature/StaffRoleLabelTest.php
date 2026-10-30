<?php

use App\Models\StaffRole;
use App\Models\User;

/**
 * A staff account is shown by the role it was given (SRRV Officer, Ticketing Agent, Cashier...), not
 * by the kind of account behind it ("Travel Agent"), in the message, the list, its filter and its page.
 */
test('each role chosen on New Staff is the one shown for the account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (StaffRole::orderBy('id')->get() as $i => $role) {
        $this->actingAs($admin)->post(route('admin.agents.store'), [
            'first_name' => 'Staff', 'last_name' => 'Number'.$i, 'email_name' => 'staff'.$i, 'phone' => '0917000000'.$i,
            'password' => 'secret123', 'access_role' => 'role:'.$role->id,
        ])->assertRedirect(route('admin.agents.index'))
            ->assertSessionHas('success', "{$role->name} account created successfully!");

        $staff = User::where('email', 'staff'.$i.'@amegatravel.com')->firstOrFail();
        expect($staff->roleLabel())->toBe($role->name);

        // The list's filter finds them under that role.
        $this->actingAs($admin)->get(route('admin.agents.index', ['role' => 'role:'.$role->id]))->assertOk()
            ->assertSee('Staff Number'.$i);
        $this->actingAs($admin)->get(route('admin.agents.show', $staff))->assertOk()->assertSee($role->name);
    }

    $srrv = StaffRole::where('name', 'SRRV Officer')->firstOrFail();
    $this->actingAs($admin)->get(route('admin.agents.index', ['role' => 'role:'.$srrv->id]))->assertOk()
        ->assertDontSee('Travel Agent');
});

test('administrators, custom and older accounts are labelled sensibly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $legacy = User::factory()->create(['role' => 'srrv', 'name' => 'Old Srrv Officer']);
    $custom = User::factory()->create(['role' => 'agent', 'permissions' => ['srrv.view']]);

    expect($admin->roleLabel())->toBe('Administrator')
        ->and($legacy->roleLabel())->toBe('SRRV Officer')
        ->and($custom->roleLabel())->toBe('Custom');

    // An account set up before roles is found under the role that does its job.
    $srrv = StaffRole::where('name', 'SRRV Officer')->firstOrFail();
    $this->actingAs($admin)->get(route('admin.agents.index', ['role' => 'role:'.$srrv->id]))->assertSee('Old Srrv Officer');
});
