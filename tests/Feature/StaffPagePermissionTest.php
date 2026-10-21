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
