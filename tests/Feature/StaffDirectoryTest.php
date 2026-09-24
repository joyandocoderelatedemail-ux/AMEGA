<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff directory lists every staff role but no clients', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Main Admin Person']);
    User::factory()->create(['role' => 'agent', 'name' => 'Agent Alma']);
    User::factory()->create(['role' => 'ticketing', 'name' => 'Ticketing Tomas']);
    User::factory()->create(['role' => 'visa_assistance', 'name' => 'Visa Vera']);
    User::factory()->create(['role' => 'srrv', 'name' => 'Srrv Sofia']);
    User::factory()->create(['role' => 'client', 'name' => 'Client Carla']);

    $this->actingAs($admin)
        ->get(route('admin.agents.index'))
        ->assertOk()
        ->assertSee('Staff Accounts')
        ->assertSee('Main Admin Person')
        ->assertSee('Agent Alma')
        ->assertSee('Ticketing Tomas')
        ->assertSee('Visa Vera')
        ->assertSee('Srrv Sofia')
        ->assertDontSee('Client Carla');
});

test('staff directory can be filtered by role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'agent', 'name' => 'Agent Alma']);
    User::factory()->create(['role' => 'srrv', 'name' => 'Srrv Sofia']);

    $this->actingAs($admin)
        ->get(route('admin.agents.index', ['role' => 'srrv']))
        ->assertOk()
        ->assertSee('Srrv Sofia')
        ->assertDontSee('Agent Alma');
});

test('desk officers link to the general account editor', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'ticketing']);
    $agent = User::factory()->create(['role' => 'agent']);

    $this->actingAs($admin)
        ->get(route('admin.agents.index'))
        ->assertSee(route('admin.users.edit', $officer), false)
        ->assertSee(route('admin.agents.edit', $agent), false);
});

test('deleting a desk officer returns to the staff directory', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $officer))
        ->assertRedirect(route('admin.agents.index'));

    expect(User::find($officer->id))->toBeNull();
});
