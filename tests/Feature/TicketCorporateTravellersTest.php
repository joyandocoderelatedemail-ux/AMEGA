<?php

use App\Models\CorporateAccount;
use App\Models\User;

function corporateWithMembers(): CorporateAccount
{
    $company = CorporateAccount::create([
        'company_name' => 'Acme Logistics Inc.', 'contact_person' => 'Liza Santos',
        'contact_email' => 'travel@acme.example.com', 'contact_phone' => '0288881234',
    ]);
    foreach ([['Ana', 'Reyes'], ['Ben', 'Cruz']] as [$first, $last]) {
        User::factory()->create([
            'role' => 'client', 'name' => "{$first} {$last}", 'first_name' => $first, 'last_name' => $last,
            'account_category' => 'Corporate', 'corporate_account_id' => $company->id,
        ]);
    }

    return $company;
}

test('the traveller search finds a company and lists its members to add together', function () {
    $company = corporateWithMembers();
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->getJson(route('ticketing.clients.search', ['q' => 'acme']))->assertOk()
        ->assertJsonPath('companies.0.name', 'Acme Logistics Inc.')
        ->assertJsonPath('companies.0.members_count', 2)
        ->assertJsonPath('companies.0.contact_person', 'Liza Santos');

    $this->actingAs($officer)->getJson(route('ticketing.companies.members', $company))->assertOk()
        ->assertJsonCount(2, 'members')
        ->assertJsonPath('members.0.name', 'Ben Cruz')
        ->assertJsonPath('company.contact_email', 'travel@acme.example.com');

    $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()
        ->assertSee('Choose travellers')->assertSee('Register an employee')->assertSee('name="corporate_account_id"', false);
});

test('a new employee registered at the ticketing desk joins their company', function () {
    $company = corporateWithMembers();
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.clients.create', ['corporate' => $company->id]))->assertOk()
        ->assertSee('Corporate Account')->assertSee('Acme Logistics Inc.');
});

test('only the ticketing desk reaches a company member list', function () {
    $company = corporateWithMembers();

    $this->actingAs(User::factory()->create(['role' => 'srrv']))->getJson(route('ticketing.companies.members', $company))->assertForbidden();
});
