<?php

use App\Models\AgentCommission;
use App\Models\StaffRole;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function earned(User $agent, float $amount, string $reference, string $on, string $desk = 'ticketing', string $client = 'Juan Dela Cruz'): AgentCommission
{
    $commission = AgentCommission::create([
        'desk' => $desk, 'kind' => 'ticket', 'file_id' => random_int(1, 999999), 'agent_id' => $agent->id,
        'amount' => $amount, 'reference' => $reference, 'client_name' => $client, 'set_by' => null,
    ]);
    $commission->forceFill(['created_at' => Carbon::parse($on), 'updated_at' => Carbon::parse($on)])->save();

    return $commission;
}

test('the Commissions page adds up what each agent earned, per agent and per month', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ana = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Agent']);
    $ben = User::factory()->create(['role' => 'visa_assistance', 'name' => 'Ben Visa']);

    earned($ana, 500, 'TKT-A-1', '2026-09-10');
    earned($ana, 250.50, 'TKT-A-2', '2026-10-05');
    earned($ana, 100, 'TKT-A-3', '2026-10-20');
    earned($ben, 300, 'VSA-1001', '2026-10-12', 'visa_assistance', 'Jane Tan');

    $page = $this->actingAs($admin)->get(route('admin.commissions.index'))->assertOk()
        ->assertSee('What each agent earned')
        // Grand totals: 1,150.50 over 4 services, 2 agents.
        ->assertSee('₱1,150.50', false)
        // Per agent: Ana 850.50 over 3, Ben 300 over 1.
        ->assertSeeInOrder(['By agent', 'Ana Agent', '₱850.50', 'Ben Visa', '₱300.00'], false)
        // Per month: October 650.50 (3 services), September 500.
        ->assertSeeInOrder(['By month', 'October 2026', '₱650.50', 'September 2026', '₱500.00'], false)
        // And file by file.
        ->assertSee('TKT-A-1')->assertSee('VSA-1001')->assertSee('Jane Tan')->assertSee('Visa Assistance');

    $page->assertSee('Agents paid');
});

test('the page can be narrowed by agent, by service, by search and by date', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ana = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Agent']);
    $ben = User::factory()->create(['role' => 'visa_assistance', 'name' => 'Ben Visa']);

    earned($ana, 500, 'TKT-A-1', '2026-09-10');
    earned($ana, 250, 'TKT-A-2', '2026-10-05');
    earned($ben, 300, 'VSA-1001', '2026-10-12', 'visa_assistance', 'Jane Tan');

    $this->actingAs($admin)->get(route('admin.commissions.index', ['agent' => $ben->id]))->assertOk()
        ->assertSee('VSA-1001')->assertDontSee('TKT-A-1')->assertSee('₱300.00', false);

    $this->actingAs($admin)->get(route('admin.commissions.index', ['desk' => 'ticketing']))->assertOk()
        ->assertSee('TKT-A-2')->assertDontSee('VSA-1001')->assertSee('₱750.00', false);

    $this->actingAs($admin)->get(route('admin.commissions.index', ['search' => 'Jane']))->assertOk()
        ->assertSee('VSA-1001')->assertDontSee('TKT-A-1');

    // October only.
    $this->actingAs($admin)->get(route('admin.commissions.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()
        ->assertSee('TKT-A-2')->assertSee('VSA-1001')->assertDontSee('TKT-A-1')->assertSee('₱550.00', false);
});

test('an empty page and an empty filter say so', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.commissions.index'))->assertOk()->assertSee('No commissions yet.');

    $ana = User::factory()->create(['role' => 'ticketing']);
    earned($ana, 500, 'TKT-A-1', '2026-09-10');
    $this->actingAs($admin)->get(route('admin.commissions.index', ['search' => 'nothing-matches']))->assertOk()->assertSee('No commissions match these filters.');
});

test('a commission with no agent recorded is totalled on its own line', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ana = User::factory()->create(['role' => 'ticketing', 'name' => 'Ana Agent']);
    earned($ana, 500, 'TKT-A-1', '2026-09-10');
    AgentCommission::create(['desk' => 'srrv', 'kind' => 'application', 'file_id' => 9, 'agent_id' => null, 'amount' => 75, 'reference' => 'SRRV-9', 'client_name' => 'Ramon Cruz']);

    $this->actingAs($admin)->get(route('admin.commissions.index'))->assertOk()
        ->assertSee('No agent recorded')->assertSee('₱75.00', false);
    $this->actingAs($admin)->get(route('admin.commissions.index', ['agent' => 'none']))->assertOk()
        ->assertSee('SRRV-9')->assertDontSee('TKT-A-1');
});

test('only an admin can open the Commissions page, and only an admin sees it in the menu', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $supervisor = User::factory()->create(['role' => 'agent', 'name' => 'Sam Supervisor']);
    $supervisor->forceFill(['staff_role_id' => StaffRole::where('name', 'Supervisor')->value('id')])->save();
    $agent = User::factory()->create(['role' => 'ticketing']);
    $cashier = User::factory()->create(['role' => 'cashier']);
    $client = User::factory()->create(['role' => 'client']);

    foreach ([$supervisor, $agent, $cashier, $client] as $user) {
        $response = $this->actingAs($user)->get(route('admin.commissions.index'));
        expect($response->status())->not->toBe(200);
    }
    $this->get(route('admin.commissions.index'))->assertRedirect();

    $labels = fn (User $user) => collect(AdminNavigation::for($user))->flatMap(fn (array $group) => collect($group['items'])->pluck('label'))->all();
    expect($labels($admin))->toContain('Agent Commissions')->and($labels($supervisor))->not->toContain('Agent Commissions');
});
