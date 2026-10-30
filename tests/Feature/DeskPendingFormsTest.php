<?php

use App\Models\DeskDraft;
use App\Models\SrrvApplication;
use App\Models\User;

/**
 * Save as Pending at the Immigration, Visa Assistance and SRRV desks: a half-filled form is kept
 * without opening a file or sending it for approval, listed on the desk's dashboard, and reopened
 * as it was left; the form's own Save opens the file and the pending form goes.
 */
function pendingRetiree(array $overrides = []): array
{
    return $overrides + [
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'retiree_name' => 'Pending Retiree',
        'retiree_email' => 'pending.retiree@example.com',
        'retiree_phone' => '+63 917 555 0199',
        'date_of_birth' => now()->subYears(62)->format('Y-m-d'),
        'nationality' => 'British',
        'srrv_card_number' => 'SRRV-00999',
        'investment_amount' => 20000,
        'form' => ['last_name' => 'Retiree', 'first_name' => 'Pending'],
    ];
}

function savePendingRetiree(object $test, User $officer, array $extra = [])
{
    return $test->actingAs($officer)->post(route('srrv.pending.store'), pendingRetiree($extra) + [
        '_token' => 'x', 'then' => 'print',
        '_pending_title' => 'SRRV application',
        '_pending_url' => route('srrv.applications.create'),
    ]);
}

test('Save as Pending keeps the form without opening a file', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    savePendingRetiree($this, $officer)->assertRedirect(route('srrv.dashboard'))
        ->assertSessionHas('success', 'SRRV application saved as pending. Continue it from Pending on the dashboard.');

    expect(SrrvApplication::count())->toBe(0);
    $draft = DeskDraft::sole();
    expect($draft->desk)->toBe('srrv')
        ->and($draft->client_name)->toBe('Pending Retiree')
        ->and($draft->created_by)->toBe($officer->id)
        ->and($draft->payload)->not->toHaveKeys(['_token', 'then', '_pending_title', '_pending_url'])
        ->and($draft->payload['form']['first_name'])->toBe('Pending');

    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()
        ->assertSee('Pending &mdash; continue later', false)->assertSee('Pending Retiree')->assertSee('SRRV application')
        ->assertSee(route('srrv.pending.continue', $draft));
});

test('Continue reopens the form as it was left, and its Save opens the file and clears the pending form', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    savePendingRetiree($this, $officer);
    $draft = DeskDraft::sole();

    $this->actingAs($officer)->get(route('srrv.pending.continue', $draft))
        ->assertRedirect(route('srrv.applications.create').'?draft='.$draft->id)
        ->assertSessionHasInput('retiree_name', 'Pending Retiree');

    $this->actingAs($officer)->followingRedirects()->get(route('srrv.pending.continue', $draft))->assertOk()
        ->assertSee('value="Pending Retiree"', false)
        ->assertSee('name="_desk_draft" value="'.$draft->id.'"', false);

    // A save that fails keeps the pending form.
    $this->actingAs($officer)->post(route('srrv.applications.store'), pendingRetiree(['retiree_name' => '']) + ['_desk_draft' => $draft->id])
        ->assertSessionHasErrors('retiree_name');
    expect(DeskDraft::count())->toBe(1);

    $this->actingAs($officer)->post(route('srrv.applications.store'), pendingRetiree() + ['_desk_draft' => $draft->id])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(SrrvApplication::count())->toBe(1)->and(DeskDraft::count())->toBe(0);
});

test('saving as pending again updates the same pending form', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    savePendingRetiree($this, $officer);
    $draft = DeskDraft::sole();

    savePendingRetiree($this, $officer, ['retiree_name' => 'Renamed Retiree', '_desk_draft' => $draft->id]);

    expect(DeskDraft::count())->toBe(1)->and($draft->fresh()->client_name)->toBe('Renamed Retiree');
});

test('a pending form is only for the one who saved it, and an admin', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $other = User::factory()->create(['role' => 'srrv']);
    $admin = User::factory()->create(['role' => 'admin']);
    savePendingRetiree($this, $officer);
    $draft = DeskDraft::sole();

    $this->actingAs($other)->get(route('srrv.dashboard'))->assertOk()->assertDontSee('Pending Retiree');
    $this->actingAs($other)->get(route('srrv.pending.continue', $draft))->assertNotFound();
    $this->actingAs($other)->delete(route('srrv.pending.destroy', $draft))->assertNotFound();

    $this->actingAs($admin)->get(route('srrv.dashboard'))->assertOk()->assertSee('Pending Retiree')->assertSee('by '.$officer->name);

    // Not another desk's address either.
    $this->actingAs($admin)->get(route('visa.pending.continue', $draft))->assertNotFound();

    // Discarding is not a file delete: the officer may discard their own.
    $this->actingAs($officer)->delete(route('srrv.pending.destroy', $draft))->assertRedirect(route('srrv.dashboard'));
    expect(DeskDraft::count())->toBe(0);
});

test('a form from another site is not kept', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.pending.store'), pendingRetiree() + [
        '_pending_title' => 'SRRV application', '_pending_url' => 'https://evil.example.com/srrv/applications/create',
    ])->assertRedirect(route('srrv.dashboard'));

    expect(DeskDraft::count())->toBe(0);
});

test('every desk form has Save as Pending', function (string $route, array $params) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route($route, $params))->assertOk()
        ->assertSee('Save as Pending')->assertSee('data-save-pending', false);
})->with([
    'immigration' => ['admin.client-sheets.create', []],
    'visa file' => ['visa.applications.create', []],
    'tourist visa' => ['visa.tourist-visa.create', []],
    'SRRV application' => ['srrv.applications.create', []],
    'SRRV ID renewal' => ['srrv.renewals.create', []],
    'SRRV request' => ['srrv.requests.create', ['type' => 'termination']],
]);

test('the immigration and visa desks keep pending forms on their own dashboards', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->put(route('admin.immigration.pending.store'), [
        '_method' => 'PUT', 'last_name' => 'Dela Cruz', 'given_name' => 'Juan',
        '_pending_title' => '13A Permanent', '_pending_url' => route('admin.client-sheets.create'),
    ])->assertRedirect(route('admin.immigration.dashboard'));

    $this->actingAs($admin)->post(route('visa.pending.store'), [
        'client_name' => 'Maria Santos', '_pending_title' => 'Visit Visa file', '_pending_url' => route('visa.applications.create'),
    ])->assertRedirect(route('visa.dashboard'));

    expect(DeskDraft::where('desk', 'immigration')->sole()->client_name)->toBe('Juan Dela Cruz')
        ->and(DeskDraft::where('desk', 'visa')->sole()->client_name)->toBe('Maria Santos');

    $this->actingAs($admin)->get(route('admin.immigration.dashboard'))->assertOk()->assertSee('Juan Dela Cruz')->assertDontSee('Maria Santos');
    $this->actingAs($admin)->get(route('visa.dashboard'))->assertOk()->assertSee('Maria Santos')->assertDontSee('Juan Dela Cruz');
});
