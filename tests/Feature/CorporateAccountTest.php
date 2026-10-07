<?php

use App\Models\CorporateAccount;
use App\Models\User;

function corporatePayload(array $overrides = []): array
{
    return clientRegistrationPayload($overrides + ['account_category' => 'Corporate']);
}

test('a corporate account can be registered with its details', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.corporates.store'), [
        'company_name' => 'Acme Manufacturing Inc.',
        'registration_number' => 'CS201912345',
        'tin' => '123-456-789-000',
        'contact_person' => 'Ana Reyes',
        'contact_email' => 'ana@acme.test',
    ])->assertRedirect();

    $company = CorporateAccount::firstOrFail();
    expect($company->company_name)->toBe('Acme Manufacturing Inc.')
        ->and($company->tin)->toBe('123-456-789-000')
        ->and($company->contact_person)->toBe('Ana Reyes');
});

test('a company needs a name', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.corporates.store'), ['tin' => '1'])
        ->assertSessionHasErrors('company_name');
});

test('a corporate client can be registered under a new company in one step', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), corporatePayload([
        'corporate_mode' => 'new',
        'corporate' => ['company_name' => 'Globe Travel Corp', 'tin' => '999'],
    ]))->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();

    expect($client->corporateAccount->company_name)->toBe('Globe Travel Corp')
        ->and($client->account_category)->toBe('Corporate');
});

test('a corporate client can be registered under an existing company', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Acme']);

    $this->actingAs($admin)->post(route('admin.users.store'), corporatePayload([
        'corporate_mode' => 'existing',
        'corporate_account_id' => $company->id,
    ]))->assertRedirect(route('admin.users.index'));

    expect($company->members()->count())->toBe(1)
        ->and(CorporateAccount::count())->toBe(1);
});

test('a new corporate client must say which company, and a new company needs a name', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), corporatePayload(['corporate_mode' => 'existing']))
        ->assertSessionHasErrors('corporate_account_id');

    $this->actingAs($admin)->post(route('admin.users.store'), corporatePayload(['corporate_mode' => 'new', 'corporate' => ['tin' => '1']]))
        ->assertSessionHasErrors('corporate.company_name');

    expect(User::where('email', 'juan.delacruz@example.com')->exists())->toBeFalse();
});

test('an individual client is never put under a company', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Acme']);

    $this->actingAs($admin)->post(route('admin.users.store'), clientRegistrationPayload([
        'corporate_mode' => 'existing',
        'corporate_account_id' => $company->id,
    ]))->assertRedirect();

    expect(User::where('email', 'juan.delacruz@example.com')->firstOrFail()->corporate_account_id)->toBeNull();
});

test('an existing client can be assigned to a company and removed again', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Acme']);
    $client = User::factory()->create(['role' => 'client', 'name' => 'Pedro Penduko']);

    $this->actingAs($admin)->get(route('admin.corporates.show', [$company, 'find' => 'Penduko']))
        ->assertOk()->assertSee('Pedro Penduko');

    $this->actingAs($admin)->post(route('admin.corporates.members.store', $company), ['user_id' => $client->id])
        ->assertRedirect(route('admin.corporates.show', $company));

    expect($client->fresh()->corporate_account_id)->toBe($company->id)
        ->and($client->fresh()->account_category)->toBe('Corporate');

    $this->actingAs($admin)->delete(route('admin.corporates.members.destroy', [$company, $client]))
        ->assertRedirect();

    expect($client->fresh()->corporate_account_id)->toBeNull();
});

test('searching the client list by company name shows every account under that company', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Zephyr Holdings']);
    User::factory()->create(['role' => 'client', 'name' => 'Member One', 'corporate_account_id' => $company->id]);
    User::factory()->create(['role' => 'client', 'name' => 'Member Two', 'corporate_account_id' => $company->id]);
    User::factory()->create(['role' => 'client', 'name' => 'Outsider Person']);

    $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'zephyr']))
        ->assertOk()
        ->assertSee('Member One')
        ->assertSee('Member Two')
        ->assertDontSee('Outsider Person');
});

test('only clients can be assigned, and only members can be removed', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Acme']);
    $other = CorporateAccount::create(['company_name' => 'Other']);
    $staff = User::factory()->create(['role' => 'agent']);
    $member = User::factory()->create(['role' => 'client', 'corporate_account_id' => $other->id]);

    $this->actingAs($admin)->post(route('admin.corporates.members.store', $company), ['user_id' => $staff->id])
        ->assertNotFound();

    $this->actingAs($admin)->delete(route('admin.corporates.members.destroy', [$company, $member]))
        ->assertNotFound();
});

test('deleting a company keeps its members as clients', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Acme']);
    $member = User::factory()->create(['role' => 'client', 'corporate_account_id' => $company->id]);

    $this->actingAs($admin)->delete(route('admin.corporates.destroy', $company))->assertRedirect(route('admin.corporates.index'));

    expect($member->fresh())->not->toBeNull()
        ->and($member->fresh()->corporate_account_id)->toBeNull();
});

test('the corporate pages and the client form render', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Acme']);
    $member = User::factory()->create(['role' => 'client', 'corporate_account_id' => $company->id, 'account_category' => 'Corporate']);

    foreach ([
        route('admin.corporates.index'),
        route('admin.corporates.create'),
        route('admin.corporates.edit', $company),
        route('admin.corporates.show', $company),
        route('admin.users.create', ['corporate' => $company->id]),
        route('admin.users.edit', $member),
        route('admin.users.show', $member),
        route('admin.users.index'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }

    $this->actingAs($admin)->get(route('admin.users.create', ['corporate' => $company->id]))
        ->assertSee('name="corporate_mode"', false)
        ->assertSee('name="corporate[company_name]"', false)
        ->assertSee('name="from_corporate"', false);
});

test('an agent without the users permission cannot reach corporate accounts', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['inquiries']]);

    $this->actingAs($agent)->get(route('admin.corporates.index'))->assertRedirect();
});
