<?php

use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('the admin menu reads Services, Approvals, Clients, Users, Reports & Analytics, Contents', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'));

    $groups = collect(AdminNavigation::for($admin))->keyBy('key');

    expect($groups->pluck('label')->all())->toBe(['Services', 'Approvals', 'Clients', 'Users', 'Reports & Analytics', 'Contents'])
        ->and(collect($groups['services']['items'])->pluck('label')->all())
        ->toBe(['Ticketing System', 'Immigration Counter', 'Visa Assistance', 'SRRV Desk', 'Cashier'])
        ->and(collect($groups['approvals']['items'])->pluck('label')->all())
        ->toBe(['Ticket Approvals', 'Immigration Approvals', 'Visa Approvals', 'SRRV Approvals'])
        ->and($groups['clients']['type'])->toBe('link')
        ->and($groups['users']['type'])->toBe('link')
        ->and(collect($groups['reports']['items'])->pluck('label')->all())->toBe(['Dashboard & Analytics', 'Audit Logs', 'Message Templates'])
        ->and(collect($groups['contents']['items'])->pluck('label')->all())->toContain('Travel Packages', 'Bookings', 'Destinations', 'Inquiries', 'Testimonials');
});

test('the admin top bar renders the new menu', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

    $positions = array_map(fn (string $label) => strpos($html, '<span class="whitespace-nowrap">'.$label.'</span>'), ['Services', 'Clients', 'Users', 'Reports &amp; Analytics', 'Contents']);

    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all())
        ->and($html)->not->toContain('Portals &amp; Desks')
        ->and($html)->not->toContain('>Operations<');
});

test('an agent sees only the menu entries they may open', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['dashboard', 'bookings', 'ticketing']]);
    $this->actingAs($agent);

    $groups = collect(AdminNavigation::for($agent))->keyBy('key');

    expect($groups->keys()->all())->toBe(['services', 'reports', 'contents'])
        ->and(collect($groups['services']['items'])->pluck('label')->all())->toBe(['Ticketing System'])
        ->and(collect($groups['reports']['items'])->pluck('label')->all())->toBe(['Dashboard & Analytics'])
        // The guest chats only when ticked.
        ->and(collect($groups['contents']['items'])->pluck('label')->all())->toBe(['Bookings']);
});

test('editing a staff account from the Agents list goes back to the Agents list, a client to the Clients list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($admin)->get(route('admin.users.edit', $agent))->assertRedirect(route('admin.agents.edit', $agent));
    $this->actingAs($admin)->get(route('admin.agents.edit', $agent))->assertOk()
        ->assertSee('Back to Staff List')->assertSee(route('admin.agents.index'), false)->assertDontSee('Back to Client List');

    $this->actingAs($admin)->get(route('admin.users.edit', $client))->assertOk()
        ->assertSee('Back to Client List')->assertSee(route('admin.users.index'), false)->assertDontSee('Back to Staff List');
});

test('a staff account\'s edit page shows no client profile, and still saves', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'name' => 'Ana Reyes', 'account_category' => 'Individual']);
    $client = User::factory()->create(['role' => 'client']);

    // A staff account opens the staff form: no client profile there.
    $this->actingAs($admin)->get(route('admin.users.edit', $agent))->assertRedirect(route('admin.agents.edit', $agent));
    $this->actingAs($admin)->get(route('admin.agents.edit', $agent))->assertOk()
        ->assertSee('Role')->assertSee('First Name')
        ->assertDontSee('Account Category')->assertDontSee('Citizenship / Nationality')->assertDontSee('Passport Number')->assertDontSee('Emergency');

    $this->actingAs($admin)->get(route('admin.users.edit', $client))->assertOk()
        ->assertSee('Account Category')->assertSee('Citizenship / Nationality')->assertSee('Full Legal Name');

    // Saving the slimmer staff form works and keeps them on the Agents list.
    $this->actingAs($admin)->put(route('admin.users.update', $agent), [
        'role' => 'agent', 'account_category' => 'Individual', 'first_name' => 'Ana', 'last_name' => 'Cruz',
        'email' => 'ana.cruz@example.com', 'phone' => '09171234567', 'allowed_pages' => ['immigration'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.agents.index'));

    expect(preg_replace('/\s+/', ' ', $agent->fresh()->name))->toBe('Ana Cruz')->and($agent->fresh()->allowed_pages)->toBe(['immigration']);
});

test('the CRM is gone, and the Clients page has no Sync Desk Clients or date range', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    expect(Route::has('admin.crm.index'))->toBeFalse()
        ->and(array_key_exists('crm', Permissions::MODULES))->toBeFalse();
    $this->actingAs($admin)->get('/admin/crm')->assertNotFound();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('CRM Pipeline');

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
        ->assertDontSee('Sync Desk Clients')->assertDontSee('name="range"', false)->assertSee('name="search"', false);
});
