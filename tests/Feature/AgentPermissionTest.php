<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('agent can log in via dedicated agent portal login page', function () {
    $agent = User::factory()->create([
        'email' => 'agent.test@amegatravel.com',
        'role' => 'agent',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/agent/login', [
        'email' => 'agent.test@amegatravel.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticatedAs($agent);
});

test('admin can update agent allowed page permissions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['dashboard', 'bookings']]);

    $response = $this->actingAs($admin)->put("/admin/users/{$agent->id}", [
        'first_name' => $agent->first_name ?? 'Test',
        'last_name' => $agent->last_name ?? 'Agent',
        'email' => $agent->email,
        'phone' => '09171112222',
        'account_category' => 'Individual',
        'role' => 'agent',
        'allowed_pages' => ['bookings', 'inquiries', 'users'],
    ]);

    $response->assertRedirect('/admin/agents');
    $agent->refresh();

    expect($agent->canAccessPage('inquiries'))->toBeTrue();
    expect($agent->canAccessPage('services'))->toBeFalse();
});

test('non-admin agent cannot access staff agent management or audit logs', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['bookings', 'users'],
    ]);

    $this->actingAs($agent)->get('/admin/agents')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($agent)->get('/admin/activity-logs')->assertRedirect(route('admin.dashboard'));
});

test('agent without package permission cannot access package management routes', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['bookings'],
    ]);

    $this->actingAs($agent)->get('/admin/packages')->assertRedirect(route('admin.dashboard'));
});

test('page permissions are shown only when editing a staff agent', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);

    // Hidden (and not submitted) for a client; admins can still switch the role to agent to reveal it.
    $this->actingAs($admin)->get(route('admin.users.edit', $client))
        ->assertOk()
        ->assertSee('style="display: none" disabled', false);

    $this->actingAs($admin)->get(route('admin.users.edit', $agent))
        ->assertOk()
        ->assertDontSee('style="display: none" disabled', false)
        ->assertSee('Agent Dashboard Page Access Permissions');
});

test('a staff account that cannot be saved says why on the form', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'taken@example.com']);

    $this->actingAs($admin)->from(route('admin.agents.create'))
        ->post(route('admin.agents.store'), [
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'taken@example.com',
            'phone' => '0917 000 1111', 'password' => 'abc',
        ])->assertRedirect(route('admin.agents.create'));

    $this->actingAs($admin)->get(route('admin.agents.create'))
        ->assertOk()
        ->assertSee('The account was not saved')
        ->assertSee('The email has already been taken.')
        ->assertSee('The password field must be at least 6 characters.');
});

test('an administrator account can be created straight from the staff form', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.agents.store'), [
        'role' => 'admin', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'ana.admin@example.com',
        'phone' => '0917 000 1111', 'password' => 'secret12', 'allowed_pages' => ['bookings'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.agents.index'));

    $created = User::where('email', 'ana.admin@example.com')->firstOrFail();

    expect($created->role)->toBe('admin')
        ->and($created->allowed_pages)->toBeNull();
});

test('each staff role can be chosen when creating, and a travel agent stays the default', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (['ticketing', 'visa_assistance', 'srrv'] as $i => $role) {
        $this->actingAs($admin)->post(route('admin.agents.store'), [
            'role' => $role, 'first_name' => 'Staff', 'last_name' => "N{$i}", 'email' => "{$role}@example.com",
            'phone' => "0917 000 10{$i}0", 'password' => 'secret12',
        ])->assertSessionHasNoErrors();

        expect(User::where('email', "{$role}@example.com")->value('role'))->toBe($role);
    }

    $this->actingAs($admin)->post(route('admin.agents.store'), [
        'first_name' => 'Def', 'last_name' => 'Ault', 'email' => 'default@example.com',
        'phone' => '0917 000 2222', 'password' => 'secret12', 'allowed_pages' => ['bookings'],
    ])->assertSessionHasNoErrors();

    $agent = User::where('email', 'default@example.com')->firstOrFail();

    expect($agent->role)->toBe('agent')
        ->and($agent->allowed_pages)->toBe(['bookings']);
});

test('the staff form cannot be used to create a client account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.agents.store'), [
        'role' => 'client', 'first_name' => 'Not', 'last_name' => 'Staff', 'email' => 'notstaff@example.com',
        'phone' => '0917 000 3333', 'password' => 'secret12',
    ])->assertSessionHasErrors('role');

    expect(User::where('email', 'notstaff@example.com')->exists())->toBeFalse();
});

test('the staff form offers every staff role, and the list can edit an agent role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent']);

    $this->actingAs($admin)->get(route('admin.agents.create'))
        ->assertOk()
        ->assertSee('<option value="admin"', false)
        ->assertSee('<option value="ticketing"', false)
        ->assertSee('<option value="visa_assistance"', false)
        ->assertSee('<option value="srrv"', false);

    $this->actingAs($admin)->get(route('admin.agents.index'))
        ->assertOk()
        ->assertSee(route('admin.users.edit', $agent), false);
});
