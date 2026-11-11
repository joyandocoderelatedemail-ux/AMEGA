<?php

use App\Models\User;

it('tells a signed-out visitor to a staff page that the session ended', function () {
    $this->get('/admin')
        ->assertRedirect(route('admin.login'))
        ->assertSessionHas('warning');

    $this->get(route('admin.login'))->assertOk();
});

it('shows the notice on the sign-in page', function () {
    $this->withSession(['warning' => 'Your session has ended and you were signed out. Please sign in again to continue.'])
        ->get(route('admin.login'))
        ->assertSee('Your session has ended');
});

it('answers the bell poll of a signed-out page with 401', function () {
    $this->getJson('/admin/notifications')->assertUnauthorized();
});

it('includes the session-ended dialog in the admin layout', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('session-ended-title', false);
});
