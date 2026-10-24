<?php

use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function immigrationOfficerWithClients(): User
{
    return User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration', 'users']]);
}

test('giving an immigration agent the client directory adds only that page: no analytics, no guest chats', function () {
    $agent = immigrationOfficerWithClients();

    expect($agent->canAccessPage('immigration'))->toBeTrue()
        ->and($agent->canAccessPage('users'))->toBeTrue()
        ->and($agent->canAccessPage('dashboard'))->toBeFalse()
        ->and($agent->canAccessPage('chats'))->toBeFalse()
        ->and($agent->canAccessPage('bookings'))->toBeFalse()
        ->and($agent->staffHomeRoute())->toBe('admin.immigration.dashboard');

    $this->actingAs($agent);
    $labels = collect(AdminNavigation::for($agent))->flatMap(fn (array $group) => collect($group['items'])->pluck('label'))->all();
    expect($labels)->toContain('Immigration Counter', 'Clients')
        ->not->toContain('Dashboard & Analytics', 'Live Guest Chats', 'Bookings');
});

test('typing the address of a page that was not given sends the agent back to their own pages', function () {
    $agent = immigrationOfficerWithClients();

    foreach (['/admin', '/admin/dashboard', '/admin/chats', '/admin/bookings', '/admin/packages', '/admin/crm', '/admin/inquiries', '/admin/agents', '/admin/activity-logs', '/admin/immigration-approvals', '/admin/ticket-approvals'] as $url) {
        $this->actingAs($agent)->get($url)->assertRedirect();
    }
    $this->actingAs($agent)->get('/admin/dashboard')->assertRedirect(route('admin.immigration.dashboard'));
    $this->actingAs($agent)->getJson('/admin/chats')->assertForbidden();

    // What was given opens.
    $this->actingAs($agent)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($agent)->get(route('admin.immigration.dashboard'))->assertOk();
});

test('the client directory opens clients only: a staff or admin account number in the address is not found', function () {
    $agent = immigrationOfficerWithClients();
    $client = User::factory()->create(['role' => 'client']);
    $admin = User::factory()->create(['role' => 'admin']);
    $colleague = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);

    $this->actingAs($agent)->get(route('admin.users.show', $client))->assertOk();
    $this->actingAs($agent)->get(route('admin.users.edit', $client))->assertOk();

    foreach ([$admin, $colleague] as $staff) {
        $this->actingAs($agent)->get(route('admin.users.show', $staff))->assertNotFound();
        $this->actingAs($agent)->get(route('admin.users.edit', $staff))->assertNotFound();
        $this->actingAs($agent)->put(route('admin.users.update', $staff), ['first_name' => 'Hacked', 'last_name' => 'Name', 'email' => 'x@example.com', 'role' => 'client', 'account_category' => 'Individual'])->assertNotFound();
        $this->actingAs($agent)->delete(route('admin.users.destroy', $staff))->assertNotFound();
        $this->actingAs($agent)->get(route('users.passport', $staff))->assertForbidden();
    }

    expect(User::find($admin->id))->not->toBeNull()->and(User::find($colleague->id)->first_name)->not->toBe('Hacked');

    // An admin still manages every account.
    $this->actingAs($admin)->get(route('admin.users.edit', $colleague))->assertOk();
});

test('the dashboard and guest chats are granted only when ticked, like any other page', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['users', 'dashboard', 'chats']]);

    expect($agent->canAccessPage('dashboard'))->toBeTrue()->and($agent->canAccessPage('chats'))->toBeTrue()
        ->and($agent->staffHomeRoute())->toBe('admin.dashboard');
    $this->actingAs($agent)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($agent)->get(route('admin.chats.index'))->assertOk();
});

test('the staff forms list every page once, and only known pages can be granted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);

    $this->actingAs($admin)->get(route('admin.agents.create'))->assertOk()
        ->assertSee('Dashboard &amp; Analytics', false)->assertSee('Live Guest Chats')->assertSee('value="chats"', false)->assertSee('value="dashboard"', false);
    $this->actingAs($admin)->get(route('admin.agents.edit', $agent))->assertOk()->assertSee('value="chats"', false);

    $this->actingAs($admin)->put(route('admin.agents.update', $agent), [
        'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => $agent->email, 'phone' => '0917 000 1111', 'allowed_pages' => ['bookings', 'admin'],
    ])->assertSessionHasErrors('allowed_pages.1');
    expect($agent->fresh()->allowed_pages)->toBe(['bookings']);

    // Unticking everything leaves nothing granted.
    $this->actingAs($admin)->put(route('admin.agents.update', $agent), [
        'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => $agent->email, 'phone' => '0917 000 1111',
    ])->assertSessionHasNoErrors();
    expect($agent->fresh()->allowed_pages)->toBe([])->and($agent->fresh()->canAccessPage('bookings'))->toBeFalse();
});

test('the client directory and the staff directory are separate permissions', function () {
    $clientsOnly = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['users']]);
    $staffOnly = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['staff']]);
    $colleague = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);

    // Clients only: the client list opens, the staff list does not.
    $this->actingAs($clientsOnly)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($clientsOnly)->get(route('admin.agents.index'))->assertRedirect();
    $this->actingAs($clientsOnly)->get(route('admin.agents.show', $colleague))->assertRedirect();

    // Staff only: the staff list and a colleague's page open, the client list does not.
    $this->actingAs($staffOnly)->get(route('admin.agents.index'))->assertOk()->assertSee($colleague->name);
    $this->actingAs($staffOnly)->get(route('admin.agents.show', $colleague))->assertOk();
    $this->actingAs($staffOnly)->get(route('admin.users.index'))->assertRedirect();
    expect($staffOnly->staffHomeRoute())->toBe('admin.agents.index');

    $this->actingAs($staffOnly);
    $labels = collect(AdminNavigation::for($staffOnly))->flatMap(fn (array $group) => collect($group['items'])->pluck('label'))->all();
    expect($labels)->toContain('Staff Accounts')->not->toContain('Clients');
});

test('the staff directory is read-only: adding, editing, permissions and deleting stay with admins', function () {
    $viewer = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['staff']]);
    $colleague = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);

    $html = $this->actingAs($viewer)->get(route('admin.agents.index'))->assertOk()->getContent();
    expect($html)->not->toContain(route('admin.agents.create'))
        ->and($html)->not->toContain(route('admin.agents.edit', $colleague))
        ->and($html)->not->toContain('action="'.route('admin.agents.destroy', $colleague).'"');
    $this->actingAs($viewer)->get(route('admin.agents.show', $colleague))->assertOk()
        ->assertDontSee(route('admin.agents.edit', $colleague), false);

    // Typing the addresses does nothing either.
    $this->actingAs($viewer)->get(route('admin.agents.create'))->assertRedirect();
    $this->actingAs($viewer)->get(route('admin.agents.edit', $colleague))->assertRedirect();
    $this->actingAs($viewer)->post(route('admin.agents.store'), ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.com', 'phone' => '0917', 'password' => 'secret12', 'role' => 'admin'])->assertRedirect();
    $this->actingAs($viewer)->put(route('admin.agents.update', $viewer), ['first_name' => 'Me', 'last_name' => 'Me', 'email' => $viewer->email, 'phone' => '0917', 'allowed_pages' => ['staff', 'dashboard', 'chats']])->assertRedirect();
    $this->actingAs($viewer)->delete(route('admin.agents.destroy', $colleague))->assertRedirect();

    expect(User::where('email', 'x@example.com')->exists())->toBeFalse()
        ->and($viewer->fresh()->allowed_pages)->toBe(['staff'])
        ->and(User::find($colleague->id))->not->toBeNull();

    // The staff form offers the new permission.
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.agents.create'))->assertOk()
        ->assertSee('Staff Directory')->assertSee('value="staff"', false);
});

test('an immigration agent given Clients and Staff reaches them from the counter through Main Admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    // An older account saved with only a full name.
    $agent = User::factory()->create(['role' => 'agent', 'name' => 'Ira Mendoza', 'first_name' => null, 'last_name' => null, 'phone' => '09171234567', 'allowed_pages' => ['immigration']]);

    // The permissions form prefills the name, so ticking pages saves.
    $this->actingAs($admin)->get(route('admin.agents.edit', $agent))->assertOk()->assertSee('value="Ira"', false)->assertSee('value="Mendoza"', false);
    $this->actingAs($admin)->put(route('admin.agents.update', $agent), [
        'first_name' => 'Ira', 'last_name' => 'Mendoza', 'email' => $agent->email, 'phone' => '09171234567', 'allowed_pages' => ['immigration', 'users', 'staff'],
    ])->assertSessionHasNoErrors();
    $agent = $agent->fresh();
    expect($agent->allowed_pages)->toBe(['immigration', 'users', 'staff'])->and($agent->adminPanelRoute())->toBe('admin.users.index');

    // Signing in lands on the counter, which now has the way to the admin pages.
    $this->actingAs($agent)->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee('Main Admin')->assertSee('href="'.route('admin.users.index').'"', false);

    // There the menu holds exactly what was given.
    $this->actingAs($agent)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($agent)->get(route('admin.agents.index'))->assertOk();
    $labels = collect(AdminNavigation::for($agent))->flatMap(fn (array $group) => collect($group['items'])->pluck('label'))->all();
    expect($labels)->toContain('Immigration Counter', 'Clients', 'Staff Accounts')->not->toContain('Dashboard & Analytics', 'Live Guest Chats');

    // A desk-only agent still has no Main Admin button.
    $deskOnly = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
    expect($deskOnly->adminPanelRoute())->toBeNull();
    $this->actingAs($deskOnly)->get(route('admin.immigration.dashboard'))->assertOk()->assertDontSee('Main Admin');
});

test('the staff form groups every page under one category', function () {
    $grouped = collect(User::PAGE_GROUPS)->flatMap(fn ($group) => $group[1]);

    expect($grouped->duplicates())->toBeEmpty()
        ->and($grouped->sort()->values()->all())->toBe(collect(array_keys(User::PAGE_PERMISSIONS))->sort()->values()->all());

    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration', 'users']]);

    foreach ([route('admin.agents.create'), route('admin.agents.edit', $agent)] as $url) {
        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSeeInOrder(['Services', 'Clients', 'Staff / Users', 'Reports &amp; Analytics', 'Contents'], false)
            ->assertSee('name="allowed_pages[]" value="srrv"', false);
    }

    // Saving still sends the ticked pages as before.
    $this->actingAs($admin)->get(route('admin.agents.edit', $agent))->assertSee('1 of 5')->assertSee('1 of 4');
});
