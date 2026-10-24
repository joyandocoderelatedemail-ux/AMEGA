<?php

use App\Models\User;

test('every staff screen uses the ticketing font, Inter', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([route('admin.users.index'), route('admin.immigration.dashboard'), route('srrv.dashboard')] as $url) {
        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSee('<body class="staff-type', false)
            ->assertSee('family=Inter:wght@400;500;600;700', false)
            ->assertDontSee('family=Montserrat', false);
    }

    // The desks on the Modern Minimal theme keep it, which carries the same font.
    $this->actingAs($admin)->get(route('visa.dashboard'))->assertOk()->assertSee('<body class="portal-minimal', false);
});

test('the staff logins use Inter, and the public website keeps its own fonts', function () {
    $this->get(route('admin.login'))->assertOk()->assertSee('<body class="staff-type', false)->assertSee('family=Inter', false);
    $this->get(route('agent.login'))->assertOk()->assertSee('class="staff-type', false)->assertSee('family=Inter', false);

    $this->get(route('home'))->assertOk()
        ->assertSee('family=Montserrat', false)->assertDontSee('staff-type', false)->assertDontSee('family=Inter', false);
});
