<?php

use App\Models\CorporateAccount;
use App\Models\User;
use App\Services\CorporateAccountService;
use App\Support\CorporateReceivables;

/** A group (a family, a tour party): kept like a company, without the company extras. */
function groupAccount(array $details = []): CorporateAccount
{
    return CorporateAccountService::create($details + ['company_name' => 'Santos Family', 'contact_person' => 'Lita Santos', 'contact_phone' => '0917 555 0000'], CorporateAccount::KIND_GROUP);
}

function groupPayload(array $overrides = []): array
{
    return clientRegistrationPayload($overrides + ['account_category' => 'Group']);
}

test('a group can be created with its name and leader, and keeps no company details', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.corporates.create', ['kind' => 'group']))
        ->assertOk()->assertSee('Group Name')->assertDontSee('SEC / DTI Registration No.');

    $this->post(route('admin.corporates.store'), [
        'kind' => 'group',
        'company_name' => 'Reyes Barkada',
        'contact_person' => 'Marco Reyes',
        'tin' => '123-456-789-000',
    ])->assertRedirect();

    $group = CorporateAccount::firstOrFail();
    expect($group->isGroup())->toBeTrue()
        ->and($group->company_name)->toBe('Reyes Barkada')
        ->and($group->contact_person)->toBe('Marco Reyes')
        ->and($group->tin)->toBeNull()
        ->and($group->category())->toBe('Group');
});

test('groups and companies are listed apart', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    CorporateAccount::create(['company_name' => 'Acme Manufacturing Inc.']);
    groupAccount();

    $this->actingAs($admin)->get(route('admin.corporates.index'))
        ->assertOk()->assertSee('Acme Manufacturing Inc.')->assertDontSee('Santos Family');
    $this->get(route('admin.corporates.index', ['kind' => 'group']))
        ->assertOk()->assertSee('Santos Family')->assertDontSee('Acme Manufacturing Inc.')->assertSee('New Group');
});

test('a group client can be registered into a new group in one step', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.users.store'), groupPayload([
        'corporate_mode' => 'new',
        'corporate' => ['company_name' => 'Dela Cruz Family', 'contact_person' => 'Juan Dela Cruz', 'tin' => '999'],
    ]))->assertRedirect(route('admin.users.index'));

    $client = User::where('email', 'juan.delacruz@example.com')->firstOrFail();

    expect($client->account_category)->toBe('Group')
        ->and($client->corporateAccount->company_name)->toBe('Dela Cruz Family')
        ->and($client->corporateAccount->isGroup())->toBeTrue()
        ->and($client->corporateAccount->tin)->toBeNull();
});

test('a group client can join an existing group, and a new group needs a name', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $group = groupAccount();

    $this->actingAs($admin)->post(route('admin.users.store'), groupPayload([
        'corporate_mode' => 'existing',
        'corporate_account_id' => $group->id,
    ]))->assertRedirect(route('admin.users.index'));

    expect($group->members()->count())->toBe(1)->and(CorporateAccount::count())->toBe(1);

    $this->post(route('admin.users.store'), groupPayload(['email' => 'second@example.com', 'corporate_mode' => 'new', 'corporate' => ['company_name' => '']]))
        ->assertSessionHasErrors('corporate.company_name');
    $this->post(route('admin.users.store'), groupPayload(['email' => 'third@example.com', 'corporate_mode' => 'existing']))
        ->assertSessionHasErrors('corporate_account_id');
});

test('a group client cannot be put under a company, nor a corporate client into a group', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = CorporateAccount::create(['company_name' => 'Acme']);
    $group = groupAccount();

    $this->actingAs($admin)->post(route('admin.users.store'), groupPayload(['corporate_mode' => 'existing', 'corporate_account_id' => $company->id]))
        ->assertSessionHasErrors('corporate_account_id');
    $this->post(route('admin.users.store'), clientRegistrationPayload(['account_category' => 'Corporate', 'corporate_mode' => 'existing', 'corporate_account_id' => $group->id]))
        ->assertSessionHasErrors('corporate_account_id');

    expect(User::where('role', 'client')->count())->toBe(0);
});

test('the client form offers the group choice with the groups on file', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    CorporateAccount::create(['company_name' => 'Acme Manufacturing Inc.']);
    groupAccount();

    $this->actingAs($admin)->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('Existing group')
        ->assertSee('-- Select a group --')
        ->assertSee('Santos Family')
        ->assertSee('Group Name');
});

test('adding a client from a group page starts the form on that group', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $group = groupAccount();

    $this->actingAs($admin)->get(route('admin.users.create', ['corporate' => $group->id]))
        ->assertOk()
        ->assertSee('<option value="Group" selected>Group</option>', false)
        ->assertSee('<option value="'.$group->id.'" selected>Santos Family</option>', false);
});

test('an existing client assigned to a group becomes a Group client', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $group = groupAccount();
    $client = User::factory()->create(['role' => 'client', 'account_category' => 'Individual']);

    $this->actingAs($admin)->post(route('admin.corporates.members.store', $group), ['user_id' => $client->id])->assertRedirect();

    expect($client->fresh()->account_category)->toBe('Group')
        ->and($client->fresh()->corporate_account_id)->toBe($group->id);
});

test('a group page shows no credit account and a group can never be given one', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $group = groupAccount();

    $this->actingAs($admin)->get(route('admin.corporates.show', $group))
        ->assertOk()
        ->assertSee('Group Leader')
        ->assertSee('Lita Santos')
        ->assertDontSee('Credit Account (A/R)')
        ->assertDontSee('Registration No.');

    $this->post(route('admin.corporates.credit', $group), ['credit_enabled' => '1', 'credit_terms_days' => 30])->assertNotFound();

    // Even with the flag forced on, a group is never treated as having credit.
    $group->forceFill(['credit_enabled' => true])->save();
    expect($group->fresh()->hasCredit())->toBeFalse()
        ->and($group->fresh()->takesServiceTax())->toBeFalse()
        ->and(CorporateReceivables::available($group->fresh()))->not->toBeFalse();
});

test('the traveller search finds a group and lists its members to add together', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $group = groupAccount();
    User::factory()->create(['role' => 'client', 'first_name' => 'Lita', 'last_name' => 'Santos', 'name' => 'Lita Santos', 'corporate_account_id' => $group->id, 'account_category' => 'Group']);
    User::factory()->create(['role' => 'client', 'first_name' => 'Pedro', 'last_name' => 'Santos', 'name' => 'Pedro Santos', 'corporate_account_id' => $group->id, 'account_category' => 'Group']);

    $found = $this->actingAs($ticketing)->getJson(route('ticketing.clients.search', ['q' => 'Santos Fam']))->assertOk()->json('companies');

    expect($found)->toHaveCount(1)
        ->and($found[0]['kind'])->toBe('group')
        ->and($found[0]['members_count'])->toBe(2);

    $members = $this->getJson(route('ticketing.companies.members', $group))->assertOk()->json();
    expect($members['company']['kind'])->toBe('group')
        ->and(collect($members['members'])->pluck('name')->all())->toBe(['Lita Santos', 'Pedro Santos']);
});
