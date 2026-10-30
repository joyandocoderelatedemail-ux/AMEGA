<?php

use App\Models\User;

/** The client forms take their text in capitals, so every record reads the same; staff forms do not. */
test('the New Client form, and editing a client, type in capitals', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);
    $staff = User::factory()->create(['role' => 'ticketing']);

    $create = $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->getContent();
    expect($create)->toContain('data-uppercase')->toContain('form[data-uppercase] input[type="text"]')->toContain('field.value.toUpperCase()');

    expect($this->actingAs($admin)->get(route('admin.users.edit', $client))->assertOk()->getContent())->toMatch('/<form[^>]*data-uppercase/');
    expect($this->actingAs($admin)->get(route('admin.users.edit', $staff))->getContent())->not->toMatch('/<form[^>]*data-uppercase/');
});

test('the staff account forms type in capitals too, except the email', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'ticketing']);

    $create = $this->actingAs($admin)->get(route('admin.agents.create'))->assertOk()->getContent();
    expect($create)->toMatch('/<form[^>]*data-uppercase/')
        ->toMatch('/name="email_name" data-keep-case/');
    expect($this->actingAs($admin)->get(route('admin.agents.edit', $staff))->assertOk()->getContent())->toMatch('/<form[^>]*data-uppercase/');
});
