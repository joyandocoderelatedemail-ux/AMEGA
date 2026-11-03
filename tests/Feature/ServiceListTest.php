<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationPetition;
use App\Models\SrrvApplication;
use App\Models\SrrvServiceRequest;
use App\Models\User;
use App\Models\VisaApplication;
use App\Support\CaseProgress;
use App\Support\ImmigrationCases;
use Illuminate\Support\Facades\DB;

/**
 * A desk's service list shows the files the admin is holding first, whatever their status: waiting for
 * approval, then returned (with the reason), then the work in progress, then the released ones in a closed
 * section. The dashboard card counts the same files and shows how many are waiting.
 */
function listAgent(): User
{
    return User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
}

/** A reference no other file in the test run shares. */
function listRef(string $prefix): string
{
    static $counter = 0;

    return $prefix.'-'.str_pad((string) ++$counter, 5, '0', STR_PAD_LEFT);
}

/** The data-state a file's row carries on the page (awaiting_admin, returned, progress, released), or null when it is not listed. */
function listRowState(string $html, string $name): ?string
{
    return preg_match('/<li data-state="([a-z_]+)"(?:(?!<\/li>).)*?'.preg_quote($name, '/').'/s', $html, $m) ? $m[1] : null;
}

/** Assert the dashboard card of a service shows these counts ($open null: any). */
function expectCard(string $html, string $label, ?int $open, int $waiting): void
{
    expect($html)->toMatch('/data-service-card="'.preg_quote(e($label), '/').'" data-open="'.($open ?? '\d+').'" data-waiting="'.$waiting.'"/');
}

/** An ECC in the agent's own folder, in the state given. */
function listEcc(User $agent, string $last, array $case = []): ImmigrationPetition
{
    $client = ImmigrationClient::forceCreate(['last_name' => $last, 'given_name' => 'Test', 'created_by' => $agent->id]);

    return ImmigrationPetition::withoutGlobalScopes()->forceCreate($case + [
        'immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'created_by' => $agent->id,
    ]);
}

test('Immigration: files held for the admin come first whatever their release date, then in progress, then released', function () {
    $agent = listAgent();
    $admin = User::factory()->create(['role' => 'admin']);

    // Released AND waiting: the agent saved a release date, so it went back to the admin.
    listEcc($agent, 'Waiting-Released', ['approval_status' => 'pending', 'approval_requested_at' => now(), 'acknowledged_by' => $agent->id, 'released_on' => now()->toDateString()]);
    listEcc($agent, 'Returned-One', ['approval_status' => 'returned', 'review_note' => 'Passport copy is blurry']);
    // Returned, then marked Released by the agent: finished, so it is listed as Released.
    listEcc($agent, 'Returned-Released', ['approval_status' => 'returned', 'review_note' => 'Old note', 'released_on' => now()->toDateString()]);
    listEcc($agent, 'Progress-One', ['approval_status' => 'approved']);
    listEcc($agent, 'Done-One', ['approval_status' => 'approved', 'released_on' => now()->toDateString()]);

    $page = $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Waiting-Released', 'Returned-One', 'In progress', 'Progress-One', 'Released', 'Done-One'])
        ->assertSee('Waiting for Admin Approval')->assertSee('Returned by Admin')
        ->assertSee('Returned:')->assertSee('Passport copy is blurry')
        ->assertSee(now()->format('M j, Y'))
        ->getContent();

    // Both held files are Needs action (a returned file is not the red the status badges use for denied).
    expect(listRowState($page, 'Waiting-Released'))->toBe(CaseProgress::AWAITING_ADMIN)
        ->and(listRowState($page, 'Returned-One'))->toBe(CaseProgress::RETURNED)
        ->and(listRowState($page, 'Progress-One'))->toBe('progress')
        ->and(listRowState($page, 'Done-One'))->toBe('released')
        ->and(listRowState($page, 'Returned-Released'))->toBe('released');

    $this->actingAs($admin)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSee('Waiting-Released')->assertSee('Done-One');
});

test('Immigration: another agent\'s folders stay hidden, and an empty range says so', function () {
    $agent = listAgent();
    $other = listAgent();
    listEcc($agent, 'Mine-Pending', ['approval_status' => 'pending', 'acknowledged_by' => $agent->id]);
    listEcc($other, 'Theirs-Pending', ['approval_status' => 'pending', 'acknowledged_by' => $other->id]);

    $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSee('Mine-Pending')->assertDontSee('Theirs-Pending');

    // A date range with nothing in it is not "no transactions yet".
    $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc', 'from' => '2020-01-01', 'to' => '2020-01-31']))->assertOk()
        ->assertSee('Nothing was opened in this date range.')->assertSee('Show all dates')->assertDontSee('Mine-Pending');
    $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'visa_downgrading']))->assertOk()
        ->assertSee('No Visa Downgrading transactions yet.');
});

test('Immigration: a Visa Extension held for the admin shows even when a newer extension is approved', function () {
    $agent = listAgent();
    $client = ImmigrationClient::forceCreate(['last_name' => 'Ext-Client', 'given_name' => 'Ana', 'created_by' => $agent->id]);
    ImmigrationClientExtension::withoutGlobalScopes()->forceCreate(['immigration_client_id' => $client->id, 'sequence' => 1, 'approval_status' => 'returned', 'review_note' => 'Receipt missing']);
    ImmigrationClientExtension::withoutGlobalScopes()->forceCreate(['immigration_client_id' => $client->id, 'sequence' => 2, 'approval_status' => 'approved']);

    $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'visa_extension']))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Ext-Client', 'Extension #1'])->assertSee('Returned by Admin')->assertSee('Receipt missing');
});

test('Immigration: the dashboard card counts the same files and shows how many are waiting', function () {
    $agent = listAgent();
    listEcc($agent, 'Waiting-Released', ['approval_status' => 'pending', 'acknowledged_by' => $agent->id, 'released_on' => now()->toDateString()]);
    listEcc($agent, 'Progress-One', ['approval_status' => 'approved']);
    listEcc($agent, 'Done-One', ['approval_status' => 'approved', 'released_on' => now()->toDateString()]);

    // Open (2: the one in progress and the released one still waiting) and 1 waiting.
    expect(ImmigrationCases::activeCounts()['ecc'])->toBe(['open' => 2, 'waiting' => 1]);

    $card = $this->actingAs($agent)->get(route('admin.immigration.dashboard'))->assertOk()->assertSee('</span> waiting', false)->getContent();
    expectCard($card, 'ECC', 2, 1);

    // Nothing held, no waiting figure.
    $quiet = listAgent();
    $this->actingAs($quiet)->get(route('admin.immigration.dashboard'))->assertOk()->assertDontSee('</span> waiting', false);
});

test('Immigration: an old file held for the admin is not cut off by more than 200 newer cases', function () {
    $agent = listAgent();
    $client = ImmigrationClient::forceCreate(['last_name' => 'Busy-Folder', 'given_name' => 'Test', 'created_by' => $agent->id]);
    $now = now()->toDateTimeString();
    DB::table((new ImmigrationPetition)->getTable())->insert(array_map(fn (int $i) => [
        'immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => '[]', 'created_by' => $agent->id,
        'approval_status' => 'approved', 'created_at' => $now, 'updated_at' => $now,
    ], range(1, 205)));

    $old = listEcc($agent, 'Old-Held', ['approval_status' => 'pending', 'acknowledged_by' => $agent->id]);
    DB::table($old->getTable())->where('id', $old->id)->update(['updated_at' => now()->subYear()]);

    $page = $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Old-Held', 'In progress'])->getContent();

    expect(listRowState($page, 'Old-Held'))->toBe(CaseProgress::AWAITING_ADMIN);
});

test('Immigration: a Visa Extension held for the admin shows past 200 clients, and a client with two counts once', function () {
    $agent = listAgent();
    $extension = fn (ImmigrationClient $client, int $sequence, string $approval) => ImmigrationClientExtension::withoutGlobalScopes()
        ->forceCreate(['immigration_client_id' => $client->id, 'sequence' => $sequence, 'approval_status' => $approval, 'review_note' => 'Fix the receipt']);

    foreach (range(1, 205) as $i) {
        $extension(ImmigrationClient::forceCreate(['last_name' => 'Aaa'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'given_name' => 'Test', 'created_by' => $agent->id]), 1, 'approved');
    }
    // Sorts last by name, with two extensions held.
    $held = ImmigrationClient::forceCreate(['last_name' => 'Zzz-Held', 'given_name' => 'Test', 'created_by' => $agent->id]);
    $extension($held, 1, 'pending');
    $extension($held, 2, 'returned');

    $page = $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'visa_extension']))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Zzz-Held'])->getContent();

    expect(listRowState($page, 'Zzz-Held'))->toBe(CaseProgress::AWAITING_ADMIN)
        ->and(preg_match_all('/<li data-state="(awaiting_admin|returned)"/', $page))->toBe(1);

    expectCard($this->actingAs($agent)->get(route('admin.immigration.dashboard'))->assertOk()->getContent(), 'Visa Extension', null, 1);
});

test('Immigration: an agent sees the cases they opened or last submitted, a supervisor and an admin see all, and the cards agree', function () {
    $a = listAgent();
    $b = listAgent();
    $folder = ImmigrationClient::forceCreate(['last_name' => 'Shared-Folder', 'given_name' => 'Test', 'created_by' => null]);
    $case = fn (string $name, ?User $creator, array $more = []) => ImmigrationPetition::withoutGlobalScopes()->forceCreate($more + [
        'immigration_client_id' => $folder->id, 'type' => 'ecc', 'details' => ['note' => $name], 'created_by' => $creator?->id, 'approval_status' => 'approved',
    ]);
    $mine = $case('mine', $a);
    $theirs = $case('theirs', $b);
    $handed = $case('handed', $b, ['approval_status' => 'pending', 'acknowledged_by' => $a->id]);

    expect(ImmigrationCases::search('ecc', 'service', '', 200, ImmigrationCases::ownerFilter($a))->pluck('id')->all())->toEqualCanonicalizing([$mine->id, $handed->id])
        ->and(ImmigrationCases::activeCounts(ImmigrationCases::ownerFilter($a))['ecc'])->toBe(['open' => 2, 'waiting' => 1]);

    $url = route('admin.client-sheets.applications', ['service' => 'ecc']);
    expect(substr_count($this->actingAs($a)->get($url)->assertOk()->getContent(), '<li data-state='))->toBe(2);
    expectCard($this->actingAs($a)->get(route('admin.immigration.dashboard'))->assertOk()->getContent(), 'ECC', 2, 1);

    $supervisor = staffWith('agent', ['immigration.view', 'immigration.view_all'], ['immigration']);
    $approver = staffWith('agent', ['immigration.view', 'immigration.approve'], ['immigration']);
    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([$supervisor, $approver, $admin] as $person) {
        expect(ImmigrationCases::ownerFilter($person))->toBeNull()
            ->and(substr_count($this->actingAs($person)->get($url)->assertOk()->getContent(), '<li data-state='))->toBe(3);
        expectCard($this->actingAs($person)->get(route('admin.immigration.dashboard'))->assertOk()->getContent(), 'ECC', 3, 1);
    }

    // B created two of the three, so B sees those and not A's own.
    expect(ImmigrationCases::search('ecc', 'service', '', 200, $b->id)->pluck('id')->all())->toEqualCanonicalizing([$theirs->id, $handed->id]);
});

test('Visa Assistance: a released file still waiting for approval is listed first, and counted on the card', function () {
    $agent = User::factory()->create(['role' => 'visa_assistance']);
    $make = fn (array $attributes) => VisaApplication::withoutGlobalScopes()->forceCreate($attributes + [
        'reference' => listRef('VSA'), 'client_phone' => '0917', 'service_type' => 'visit_visa', 'created_by' => $agent->id,
    ]);
    $make(['client_name' => 'Held-Released', 'status' => 'released', 'approval_status' => 'pending', 'acknowledged_by' => $agent->id]);
    $make(['client_name' => 'Back-Returned', 'status' => 'requirements', 'approval_status' => 'returned', 'review_note' => 'Wrong fee']);
    $make(['client_name' => 'In-Progress', 'status' => 'requirements', 'approval_status' => 'approved']);
    $make(['client_name' => 'Finished', 'status' => 'released', 'approval_status' => 'approved']);
    $make(['client_name' => 'Cancelled-Pending', 'status' => 'cancelled', 'approval_status' => 'pending']);

    $this->actingAs($agent)->get(route('visa.services', 'visit_visa'))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Held-Released', 'Back-Returned', 'In progress', 'In-Progress', 'Released', 'Finished'])
        ->assertSee('Wrong fee')->assertDontSee('Cancelled-Pending');

    expectCard($this->actingAs($agent)->get(route('visa.dashboard'))->assertOk()->getContent(), 'Non-Immigrant', 3, 2);
});

test('Visa Assistance: a supervisor with view_all sees another agent\'s file, an officer without it does not', function () {
    $owner = User::factory()->create(['role' => 'visa_assistance']);
    VisaApplication::withoutGlobalScopes()->forceCreate([
        'reference' => listRef('VSA'), 'client_phone' => '0917', 'service_type' => 'visit_visa', 'created_by' => $owner->id,
        'client_name' => 'Owners-File', 'status' => 'requirements', 'approval_status' => 'approved',
    ]);

    $supervisor = staffWith('visa_assistance', ['visa_assistance.view_all']);
    $other = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($supervisor)->get(route('visa.services', 'visit_visa'))->assertOk()->assertSee('Owners-File');
    $this->actingAs($other)->get(route('visa.services', 'visit_visa'))->assertOk()->assertDontSee('Owners-File');
});

test('SRRV: a released file still waiting for approval is listed first, and counted on the card', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $make = fn (array $attributes) => SrrvApplication::withoutEvents(fn () => SrrvApplication::withoutGlobalScopes()->forceCreate($attributes + [
        'reference' => listRef('SRRV'), 'service_type' => 'renewal_application', 'visa_class' => 'classic',
        'investment_amount' => 1500, 'created_by' => $officer->id,
    ]));
    $make(['retiree_name' => 'Held-Released', 'status' => 'released', 'approval_status' => 'pending', 'acknowledged_by' => $officer->id]);
    $make(['retiree_name' => 'Back-Returned', 'status' => 'pending', 'approval_status' => 'returned', 'review_note' => 'Sign page missing']);
    $make(['retiree_name' => 'In-Progress', 'status' => 'pending', 'approval_status' => 'approved']);
    $make(['retiree_name' => 'Finished', 'status' => 'released', 'approval_status' => 'approved']);

    $this->actingAs($officer)->get(route('srrv.services', 'classic'))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Held-Released', 'Back-Returned', 'In progress', 'In-Progress', 'Released', 'Finished'])
        ->assertSee('Sign page missing');

    expectCard($this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->getContent(), 'SRRV Classic', 3, 2);
});

test('SRRV: a cancelled application waiting for approval is neither listed nor counted', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $make = fn (array $attributes) => SrrvApplication::withoutEvents(fn () => SrrvApplication::withoutGlobalScopes()->forceCreate($attributes + [
        'reference' => listRef('SRRV'), 'service_type' => 'renewal_application', 'visa_class' => 'classic', 'investment_amount' => 1500, 'created_by' => $officer->id,
    ]));
    $make(['retiree_name' => 'Cancelled-Pending', 'status' => 'cancelled', 'approval_status' => 'pending']);
    $make(['retiree_name' => 'Live-One', 'status' => 'pending', 'approval_status' => 'approved']);

    $this->actingAs($officer)->get(route('srrv.services', 'classic'))->assertOk()->assertSee('Live-One')->assertDontSee('Cancelled-Pending');
    expectCard($this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->getContent(), 'SRRV Classic', 1, 0);
});

test('SRRV: a service request list groups waiting, in progress and completed files, and drops cancelled ones', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $make = fn (string $name, string $status, string $approval) => SrrvServiceRequest::withoutGlobalScopes()->forceCreate([
        'reference' => listRef('SRQ'), 'type' => 'termination', 'retiree_name' => $name, 'status' => $status, 'approval_status' => $approval, 'created_by' => $officer->id,
    ]);
    $make('Req-Done', 'completed', 'approved');
    $make('Req-Cancelled', 'cancelled', 'pending');
    $make('Req-Progress', 'processing', 'approved');
    $make('Req-Waiting', 'documents', 'pending');

    $page = $this->actingAs($officer)->get(route('srrv.services', 'termination'))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Req-Waiting', 'In progress', 'Req-Progress', 'Released', 'Req-Done'])
        ->assertDontSee('Req-Cancelled')->getContent();

    expect(listRowState($page, 'Req-Waiting'))->toBe(CaseProgress::AWAITING_ADMIN)
        ->and(listRowState($page, 'Req-Progress'))->toBe('progress')
        ->and(listRowState($page, 'Req-Done'))->toBe('released');
    expectCard($this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->getContent(), 'SRRV Termination of Membership', 2, 1);
});

test('Open goes to the read-only preview from the service list, the dashboard and the Cases page alike', function () {
    $agent = listAgent();
    $case = listEcc($agent, 'Preview-First', ['approval_status' => null]);
    $preview = e(route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]));

    foreach ([
        route('admin.client-sheets.applications', ['service' => 'ecc']),
        route('admin.immigration.dashboard'),
        route('admin.immigration.cases'),
    ] as $page) {
        $this->actingAs($agent)->get($page)->assertOk()->assertSee($preview, false);
    }

    // An editable file still has its Edit button on the preview.
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]))
        ->assertOk()->assertSee('Press Edit to change it.');
});

test('the preview offers Edit on a file an agent may change, the status dropdown on an approved one, and Request edit on a released one', function () {
    $agent = listAgent();
    $edit = fn ($case) => route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]);
    $plainEdit = fn ($case) => e(route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id]));

    // An approved file's details are locked for the agent: no Edit and no Request edit, but its status moves from the dropdown. An approver keeps Edit.
    $approved = listEcc($agent, 'Approved-InProgress', ['approval_status' => 'approved']);
    $this->actingAs($agent)->get($edit($approved))->assertOk()
        ->assertSee('you can still update its status until it is released')->assertDontSee('>Edit</a>', false)->assertDontSee('Request edit')
        ->assertSee('<select name="status"', false);
    $this->actingAs(staffWith('agent', ['immigration.approve'], ['immigration']))->get($edit($approved))->assertOk()
        ->assertSee('a change goes back to the admin for approval')->assertSee('>Edit</a>', false)
        ->assertSee($plainEdit($approved), false);

    $returned = listEcc($agent, 'Returned-One', ['approval_status' => 'returned', 'review_note' => 'Fix it']);
    $this->actingAs($agent)->get($edit($returned))->assertOk()->assertSee('>Edit</a>', false);

    $waiting = listEcc($agent, 'With-Admin', ['approval_status' => 'pending', 'acknowledged_by' => $agent->id]);
    $this->actingAs($agent)->get($edit($waiting))->assertOk()
        ->assertSee("It can't be changed while it is with the admin.", false)->assertDontSee('>Edit</a>', false);

    $released = listEcc($agent, 'Released-One', ['approval_status' => 'approved', 'released_on' => now()->toDateString()]);
    $this->actingAs($agent)->get($edit($released))->assertOk()
        ->assertSee('It is released and locked: use Request edit to ask the admin to unlock it.')->assertDontSee('>Edit</a>', false);
});

test("the admin's preview has Edit on a file that is not waiting for approval, and Approve and Return on one that is", function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = listAgent();
    $url = fn ($case) => route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]);

    $ongoing = listEcc($agent, 'Ongoing-One', ['approval_status' => 'approved']);
    $this->actingAs($admin)->get($url($ongoing))->assertOk()
        ->assertSee('Press Edit to change it.')->assertSee('>Edit</a>', false)->assertSee('Back to applications')
        ->assertDontSee('Preview only')->assertDontSee('>Approve</button>', false);

    $waiting = listEcc($agent, 'Waiting-One', ['approval_status' => 'pending', 'acknowledged_by' => $agent->id]);
    $this->actingAs($admin)->get($url($waiting))->assertOk()
        ->assertSee('Preview only')->assertSee('Back to approvals')->assertSee('>Approve</button>', false)->assertDontSee('>Edit</a>', false);
});
