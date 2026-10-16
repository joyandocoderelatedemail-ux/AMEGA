<?php

use App\Models\ImmigrationClient;
use App\Models\TouristVisaApplication;
use App\Models\TouristVisaPayment;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaPricingTier;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;

/**
 * The CGAF as the counter posts it, with the changes on top.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function cgafForm(array $changes = []): array
{
    return array_merge([
        'application_method' => 'personal',
        'application_mode' => 'regular',
        'months_requested' => 1,
        'reason' => 'To continue my vacation.',
        'mobile_number' => '09171234567',
        'email' => 'john.smith@example.com',
        'last_name' => 'Smith',
        'first_name' => 'John',
        'middle_name' => 'Edward',
        'date_of_birth' => '1985-03-14',
        'sex' => 'M',
        'birthplace' => 'London',
        'citizenship' => 'British',
        'height_cm' => '180',
        'weight_kg' => '78',
        'civil_status' => 'married',
        'address_street' => 'Unit 12B, 88 Ayala Avenue',
        'barangay' => 'San Lorenzo',
        'city' => 'Makati City',
        'province' => 'Metro Manila',
        'zip_code' => '1223',
        'passport_number' => 'GB1234567',
        'passport_validity' => '2031-06-30',
        'latest_arrival_date' => '2026-09-20',
        'flight_number' => 'PR 721',
        'checklist' => ['cgaf', 'passport'],
        'filed_on' => '2026-10-12',
    ], $changes);
}

function counterAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('who can apply and what to bring are shown before the form', function () {
    $html = $this->actingAs(counterAdmin())->get(route('visa.tourist-visa.create'))->assertOk()->getContent();

    $who = strpos($html, 'Who can apply?');
    $bring = strpos($html, 'What to bring?');
    $checklist = strpos($html, 'Checklist of Documentary Requirements</h2>');
    $form = strpos($html, 'Application Form (CGAF)');

    expect($who)->toBeInt()->toBeLessThan($bring)
        ->and($bring)->toBeLessThan($checklist)
        ->and($checklist)->toBeLessThan($form)
        ->and($html)->toContain('Non-visa required tourists admitted initially for')
        ->toContain('twenty-nine (29) days')
        ->toContain(route('visa.tourist-visa.blank-checklist'))
        ->toContain(route('visa.tourist-visa.blank-form'));
});

test('the checklist offers every BI requirement, the overstay letter behind its question', function () {
    $html = $this->actingAs(counterAdmin())->get(route('visa.tourist-visa.create'))->getContent();

    foreach (TouristVisaApplication::CHECKLIST + TouristVisaApplication::OVERSTAY_CHECKLIST as $key => $item) {
        expect($html)->toContain('value="'.$key.'"');
    }
    expect($html)->toContain('x-show="overstayed"');
});

test('the counter files a CGAF with its checklist', function () {
    $admin = counterAdmin();

    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $application = TouristVisaApplication::sole();

    expect($application->reference)->toStartWith('TVE-')
        ->and($application->created_by)->toBe($admin->id)
        ->and($application->formal_name)->toBe('Smith, John, Edward')
        ->and($application->months_requested)->toBe(1)
        ->and($application->checklist)->toBe(['cgaf', 'passport'])
        ->and($application->missingChecklist())->toBe([])
        ->and($application->latest_arrival_date->toDateString())->toBe('2026-09-20');
});

test('the overstay letter counts only when the applicant overstayed', function () {
    $admin = counterAdmin();

    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm(['checklist' => ['cgaf', 'overstay_letter']]));
    expect(TouristVisaApplication::sole()->checklist)->toBe(['cgaf']);

    TouristVisaApplication::query()->delete();
    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm(['overstayed' => 1, 'checklist' => ['cgaf', 'passport']]));

    expect(array_keys(TouristVisaApplication::sole()->missingChecklist()))->toBe(['overstay_letter']);
});

test('an authorized representative needs a name, and a personal filing drops it', function () {
    $admin = counterAdmin();

    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm(['application_method' => 'representative']))
        ->assertSessionHasErrors('representative_name');

    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm(['representative_name' => 'Cruz, Ana', 'bi_accreditation_number' => 'BI-1']))
        ->assertSessionHasNoErrors();

    expect(TouristVisaApplication::sole()->representative_name)->toBeNull();
});

test('the name, citizenship and passport are required, and the choices are the form\'s own', function () {
    $this->actingAs(counterAdmin())->post(route('visa.tourist-visa.store'), cgafForm([
        'last_name' => '', 'citizenship' => '', 'passport_number' => '',
        'months_requested' => 3, 'civil_status' => 'engaged', 'sex' => 'X',
    ]))->assertSessionHasErrors(['last_name', 'citizenship', 'passport_number', 'months_requested', 'civil_status', 'sex']);
});

test('the CGAF prints on the BI layout with the answers in place', function () {
    $admin = counterAdmin();
    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm());
    $application = TouristVisaApplication::sole();

    $this->actingAs($admin)->get(route('visa.tourist-visa.print-form', $application))
        ->assertOk()
        ->assertSee('CONSOLIDATED GENERAL APPLICATION FORM (CGAF)', false)
        ->assertSee('IRD04.QF.004 Rev. Lev. 4 Effective 07 AUG 2024')
        ->assertSee('size: 8.5in 13in', false)
        ->assertSee('GB1234567')
        ->assertSee('Unit 12B, 88 Ayala Avenue')
        ->assertSee('>MAR<', false)
        ->assertSee('>1985<', false)
        ->assertSee('12/OCT/2026');
});

test('the checklist prints with its ticks, certification and claim slip', function () {
    $admin = counterAdmin();
    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm());
    $application = TouristVisaApplication::sole();

    $html = $this->actingAs($admin)->get(route('visa.tourist-visa.print-checklist', $application))->assertOk()->getContent();

    expect($html)->toContain('CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR EXTENSION')
        ->toContain('IRD04.QF.007 Rev. Lev.03 Effective: 04 JUN 2026')
        ->toContain('CLAIM SLIP')
        ->toContain('Smith, John, Edward')
        ->toContain('12 OCT 2026')
        ->and(substr_count($html, '&#10003;'))->toBe(2);
});

test('blank forms print for clients to fill in by hand', function () {
    $admin = counterAdmin();

    $this->actingAs($admin)->get(route('visa.tourist-visa.blank-form'))->assertOk()->assertSee('CONSOLIDATED GENERAL APPLICATION FORM (CGAF)', false);
    $this->actingAs($admin)->get(route('visa.tourist-visa.blank-checklist'))->assertOk()->assertSee('CLAIM SLIP');
});

test('only the visa assistance counter can open the applications, and officers see their own', function () {
    $mine = User::factory()->create(['role' => 'visa_assistance']);
    $other = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($other)->post(route('visa.tourist-visa.store'), cgafForm(['last_name' => 'Elsewhere']));
    $this->actingAs($mine)->post(route('visa.tourist-visa.store'), cgafForm(['last_name' => 'Mine']));
    $theirs = TouristVisaApplication::withoutGlobalScopes()->where('last_name', 'Elsewhere')->sole();

    $this->actingAs($mine)->get(route('visa.tourist-visa.index'))
        ->assertOk()->assertSee('Mine, John')->assertDontSee('Elsewhere, John');
    $this->actingAs($mine)->get(route('visa.tourist-visa.edit', $theirs))->assertNotFound();

    foreach (['ticketing', 'client'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('visa.tourist-visa.index'))->assertRedirect();
    }
});

test('Non-Immigrant opens the country picker, from the menu and from the services list', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $dashboard = $this->actingAs($officer)->get(route('visa.dashboard'))->assertOk()->getContent();
    expect($dashboard)->not->toContain('Tourist Visa')
        ->toContain('href="'.route('visa.visit.new').'"')
        ->not->toContain('href="'.route('visa.tourist-visa.index').'"');

    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa']))
        ->assertRedirect(route('visa.visit.new'));
});

test('the folder list is empty until a client is added, and offers Add New Client', function () {
    $this->actingAs(User::factory()->create(['role' => 'visa_assistance']))
        ->get(route('visa.tourist-visa.index'))
        ->assertOk()->assertSee('No clients yet')->assertSee('Add New Client')
        ->assertSee(route('visa.visit.new'), false)
        ->assertDontSee('Who can apply?');
});

test('the Philippines form opens with who can apply and what to bring, with the form below', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'visa_assistance']))
        ->get(route('visa.tourist-visa.create'))
        ->assertOk()->assertSee('New Client · Philippines')->assertSee('Change country')->assertSee('Who can apply?')->getContent();

    expect(strpos($html, 'Who can apply?'))->toBeLessThan(strpos($html, 'Checklist of Documentary Requirements</h2>'));
});

test('every form filed under the same passport sits in one client folder', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'GB 123-4567']));
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'gb1234567', 'months_requested' => 2]));
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'AB9999999', 'last_name' => 'Reyes', 'first_name' => 'Maria']));

    $this->actingAs($officer)->get(route('visa.tourist-visa.index'))->assertOk()
        ->assertSeeText('2 clients')->assertSeeText('2 forms')->assertSeeText('1 form')
        ->assertSee('Smith, John, Edward')->assertSee('Reyes, Maria');

    $this->actingAs($officer)->get(route('visa.tourist-visa.index', ['search' => 'Reyes']))
        ->assertOk()->assertSee('Reyes, Maria')->assertDontSee('Smith, John');

    $folder = $this->actingAs($officer)->get(route('visa.tourist-visa.client', 'gb1234567'))->assertOk()
        ->assertSee('Smith, John, Edward')->assertSee('Client Details')->assertSee('London')->assertSee('Mar 14, 1985')
        ->assertSee(route('visa.tourist-visa.create', ['passport' => 'GB1234567']), false)
        ->assertDontSee('Reyes');

    expect(substr_count($folder->getContent(), 'TVE-'))->toBeGreaterThanOrEqual(2);
    $this->actingAs($officer)->get(route('visa.tourist-visa.client', 'ZZ0000000'))->assertNotFound();
});

test('a client folder is private to the officer who filed it', function () {
    $mine = User::factory()->create(['role' => 'visa_assistance']);
    $other = User::factory()->create(['role' => 'visa_assistance']);
    $this->actingAs($other)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'ZZ1111111']));

    $this->actingAs($mine)->get(route('visa.tourist-visa.client', 'ZZ1111111'))->assertNotFound();
    $this->actingAs($mine)->get(route('visa.tourist-visa.index'))->assertDontSee('ZZ1111111');
});

test("a new application from a folder starts from the client's details, not the old form", function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['reason' => 'First extension', 'checklist' => ['cgaf', 'passport'], 'filed_on' => '2026-01-05']));

    $html = $this->actingAs($officer)->get(route('visa.tourist-visa.create', ['passport' => 'GB1234567']))
        ->assertOk()->assertSee('New Application for Smith, John, Edward')
        ->assertSee('value="GB1234567"', false)->assertSee('value="London"', false)
        ->assertDontSee('First extension')->assertDontSee('Who can apply?')
        ->getContent();

    expect($html)->not->toContain('value="cgaf" checked')->not->toContain('2026-01-05');
});

test('a whole client folder can be deleted, but only by staff who can see it', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $other = User::factory()->create(['role' => 'visa_assistance']);
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm());
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['months_requested' => 2]));
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'AB9999999', 'last_name' => 'Reyes', 'first_name' => 'Maria']));

    $this->actingAs($officer)->get(route('visa.tourist-visa.client', 'GB1234567'))->assertOk()
        ->assertSee('Delete folder')->assertSee(route('visa.tourist-visa.client.destroy', 'GB1234567'), false);

    // Another officer cannot reach the folder, so cannot delete it.
    $this->actingAs($other)->delete(route('visa.tourist-visa.client.destroy', 'GB1234567'))->assertNotFound();
    expect(TouristVisaApplication::withoutGlobalScopes()->count())->toBe(3);

    $this->actingAs($officer)->delete(route('visa.tourist-visa.client.destroy', 'GB1234567'))
        ->assertRedirect(route('visa.tourist-visa.index'))->assertSessionHas('success');

    expect(TouristVisaApplication::withoutGlobalScopes()->pluck('passport_number')->all())->toBe(['AB9999999']);
    $this->actingAs($officer)->delete(route('visa.tourist-visa.client.destroy', 'GB1234567'))->assertNotFound();
});

test('deleting a form returns to the folder, or to the list after the last one', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm());
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['months_requested' => 2]));
    [$first, $second] = TouristVisaApplication::oldest('id')->get()->all();

    $this->actingAs($officer)->delete(route('visa.tourist-visa.destroy', $first))
        ->assertRedirect(route('visa.tourist-visa.client', 'GB1234567'));
    $this->actingAs($officer)->delete(route('visa.tourist-visa.destroy', $second))
        ->assertRedirect(route('visa.tourist-visa.index'));
});

test('the 2x2 photo is kept on the private disk, served only to staff who can open the application, and printed in the photo box', function () {
    Storage::fake(DocumentStorage::diskName());
    $mine = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($mine)->post(route('visa.tourist-visa.store'), cgafForm([
        'overstayed' => 1,
        'photo' => UploadedFile::fake()->image('photo.jpg', 600, 600),
    ]))->assertSessionHasNoErrors();

    $application = TouristVisaApplication::sole();
    expect($application->photo_path)->toStartWith('tourist-visa/'.$application->reference.'/');
    Storage::disk(DocumentStorage::diskName())->assertExists($application->photo_path);
    Storage::disk('public')->assertMissing($application->photo_path);

    $this->actingAs($mine)->get(route('visa.tourist-visa.photo', $application))->assertOk();
    $this->actingAs($mine)->get(route('visa.tourist-visa.edit', $application))
        ->assertSee(route('visa.tourist-visa.photo', $application), false);
    $this->actingAs($mine)->get(route('visa.tourist-visa.print-form', $application))
        ->assertSee(route('visa.tourist-visa.photo', $application), false)
        ->assertDontSee('kindly attach a colored photograph');

    $this->actingAs(User::factory()->create(['role' => 'visa_assistance']))
        ->get(route('visa.tourist-visa.photo', $application))->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'client']))
        ->get(route('visa.tourist-visa.photo', $application))->assertRedirect();
});

test('replacing or removing the photo deletes the old file', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['photo' => UploadedFile::fake()->image('a.jpg')]));
    $application = TouristVisaApplication::sole();
    $first = $application->photo_path;

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['photo' => UploadedFile::fake()->image('b.png')]));
    $second = $application->fresh()->photo_path;
    expect($second)->not->toBe($first);
    Storage::disk(DocumentStorage::diskName())->assertMissing($first);
    Storage::disk(DocumentStorage::diskName())->assertExists($second);

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['remove_photo' => 1]));
    expect($application->fresh()->photo_path)->toBeNull();
    Storage::disk(DocumentStorage::diskName())->assertMissing($second);

    $this->actingAs($officer)->get(route('visa.tourist-visa.print-form', $application))->assertSee('kindly attach a colored photograph');
    $this->actingAs($officer)->get(route('visa.tourist-visa.photo', $application))->assertNotFound();
});

test('only photos are accepted, and an update without one keeps the current photo', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['photo' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors('photo');
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['photo' => UploadedFile::fake()->image('huge.jpg')->size(6000)]))
        ->assertSessionHasErrors('photo');
    expect(TouristVisaApplication::count())->toBe(0);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['photo' => UploadedFile::fake()->image('a.jpg')]));
    $application = TouristVisaApplication::sole();
    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['reason' => 'Changed']));

    expect($application->fresh()->photo_path)->toBe($application->photo_path);

    $this->actingAs($officer)->delete(route('visa.tourist-visa.destroy', $application));
    Storage::disk(DocumentStorage::diskName())->assertMissing($application->photo_path);
});

test('the Personal Information section can search registered clients', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.tourist-visa.create'))
        ->assertOk()
        ->assertSee('touristClientPicker', false)
        ->assertSee((string) Js::from(route('visa.tourist-visa.clients')), false)
        ->assertSee('Search a registered client to fill this section');
});

test('a registered client comes back with the CGAF fields from their record', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $client = User::factory()->create([
        'role' => 'client', 'name' => 'John Edward Smith', 'first_name' => 'John', 'middle_name' => 'Edward', 'last_name' => 'Smith',
        'gender' => 'Male', 'date_of_birth' => '1985-03-14', 'nationality' => 'British', 'passport_number' => 'GB1234567',
        'passport_expiry' => '2031-06-30', 'phone' => '09171234567', 'email' => 'john.smith@example.com',
        'address_line' => 'Unit 12B, 88 Ayala Avenue', 'city' => 'Makati City', 'province' => 'Metro Manila', 'postal_code' => '1223',
    ]);
    ImmigrationClient::factory()->create(['user_id' => $client->id, 'height' => '180', 'weight' => '78', 'civil_status' => 'Married']);

    $json = $this->actingAs($officer)->getJson(route('visa.tourist-visa.clients', ['q' => 'gb1234']))->assertOk()->json('clients');

    expect($json)->toHaveCount(1)
        ->and($json[0]['label'])->toBe('John Edward Smith')
        ->and($json[0]['fields'])->toMatchArray([
            'last_name' => 'Smith', 'first_name' => 'John', 'middle_name' => 'Edward', 'date_of_birth' => '1985-03-14', 'sex' => 'M',
            'citizenship' => 'British', 'height_cm' => '180', 'weight_kg' => '78', 'civil_status' => 'married',
            'address_street' => 'Unit 12B, 88 Ayala Avenue', 'city' => 'Makati City', 'province' => 'Metro Manila', 'zip_code' => '1223',
            'passport_number' => 'GB1234567', 'passport_validity' => '2031-06-30', 'mobile_number' => '09171234567', 'email' => 'john.smith@example.com',
        ]);
});

test('fields the record lacks are left out, and a name held only whole is split', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    User::factory()->create(['role' => 'client', 'name' => 'Maria Clara Reyes', 'first_name' => null, 'last_name' => null, 'middle_name' => null,
        'gender' => null, 'date_of_birth' => null, 'nationality' => null, 'passport_number' => null, 'passport_expiry' => null,
        'phone' => null, 'address' => null, 'address_line' => null, 'city' => null, 'province' => null, 'postal_code' => null]);

    $fields = $this->actingAs($officer)->getJson(route('visa.tourist-visa.clients', ['q' => 'Reyes']))->json('clients.0.fields');

    expect($fields)->toHaveKeys(['last_name', 'first_name', 'email'])
        ->and($fields['last_name'])->toBe('Reyes')
        ->and($fields['first_name'])->toBe('Maria Clara')
        ->not->toHaveKeys(['sex', 'date_of_birth', 'passport_number', 'height_cm', 'civil_status']);
});

test('only registered clients are searched, short searches return nothing, and only visa staff may ask', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    User::factory()->create(['role' => 'agent', 'name' => 'Smithers Agent']);
    User::factory()->create(['role' => 'admin', 'name' => 'Smithson Admin']);

    $this->actingAs($officer)->getJson(route('visa.tourist-visa.clients', ['q' => 'Smith']))->assertOk()->assertJson(['clients' => []]);
    $this->actingAs($officer)->getJson(route('visa.tourist-visa.clients', ['q' => 'S']))->assertOk()->assertJson(['clients' => []]);

    $this->actingAs(User::factory()->create(['role' => 'client']))
        ->getJson(route('visa.tourist-visa.clients', ['q' => 'Smith']))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'ticketing']))
        ->get(route('visa.tourist-visa.clients', ['q' => 'Smith']))->assertRedirect();
});

test('every checklist requirement has its own upload button', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'visa_assistance']))
        ->get(route('visa.tourist-visa.create'))->assertOk()->getContent();

    foreach (array_keys(TouristVisaApplication::CHECKLIST + TouristVisaApplication::OVERSTAY_CHECKLIST) as $item) {
        expect($html)->toContain('name="documents['.$item.']"');
    }
    expect(substr_count($html, 'name="documents['))->toBe(4);
});

test('a file uploaded for a requirement is kept privately, ticks it, and opens only for staff who can see the application', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm([
        'checklist' => [],
        'documents' => ['passport' => UploadedFile::fake()->create('passport.pdf', 300, 'application/pdf')],
    ]))->assertSessionHasNoErrors();

    $application = TouristVisaApplication::sole();
    $document = $application->documentFor('passport');

    expect($document['name'])->toBe('passport.pdf')
        ->and($document['path'])->toStartWith('tourist-visa/'.$application->reference.'/documents/')
        ->and($application->checklist)->toBe(['passport'])
        ->and($application->hasChecked('passport'))->toBeTrue()
        ->and($application->hasChecked('cgaf'))->toBeFalse()
        ->and(array_keys($application->missingChecklist()))->toBe(['cgaf']);
    Storage::disk(DocumentStorage::diskName())->assertExists($document['path']);
    Storage::disk('public')->assertMissing($document['path']);

    $this->actingAs($officer)->get(route('visa.tourist-visa.document', [$application, 'passport']))->assertOk();
    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))->assertSee('passport.pdf')->assertSee('Replace file');
    $this->actingAs($officer)->get(route('visa.tourist-visa.document', [$application, 'cgaf']))->assertNotFound();
    $this->actingAs($officer)->get('/visa-assistance/tourist-visa/'.$application->id.'/documents/other')->assertNotFound();

    $this->actingAs(User::factory()->create(['role' => 'visa_assistance']))
        ->get(route('visa.tourist-visa.document', [$application, 'passport']))->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'client']))
        ->get(route('visa.tourist-visa.document', [$application, 'passport']))->assertRedirect();
});

test('replacing or removing a requirement file deletes the old one, and bad files are refused', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $disk = Storage::disk(DocumentStorage::diskName());

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['documents' => ['cgaf' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')]]));
    $application = TouristVisaApplication::sole();
    $first = $application->documentFor('cgaf')['path'];

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['documents' => ['cgaf' => UploadedFile::fake()->image('b.png')]]));
    $second = $application->fresh()->documentFor('cgaf')['path'];
    expect($second)->not->toBe($first);
    $disk->assertMissing($first);
    $disk->assertExists($second);

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['reason' => 'No new file']));
    expect($application->fresh()->documentFor('cgaf')['path'])->toBe($second);

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['remove_documents' => ['cgaf' => 1]]));
    expect($application->fresh()->documentFor('cgaf'))->toBeNull();
    $disk->assertMissing($second);

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['documents' => ['cgaf' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')]]))
        ->assertSessionHasErrors('documents.cgaf');
    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['documents' => ['cgaf' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf')]]))
        ->assertSessionHasErrors('documents.cgaf');
});

test('the overstay letter takes a file only when the applicant overstayed', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $letter = fn () => ['overstay_letter' => UploadedFile::fake()->create('letter.pdf', 80, 'application/pdf')];

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['documents' => $letter()]));
    expect(TouristVisaApplication::sole()->documentFor('overstay_letter'))->toBeNull();

    TouristVisaApplication::query()->delete();
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['overstayed' => 1, 'documents' => $letter()]));
    expect(TouristVisaApplication::sole()->documentFor('overstay_letter')['name'])->toBe('letter.pdf')
        ->and(TouristVisaApplication::sole()->checklist)->toContain('overstay_letter');
});

test('a fee paid in full records payments against the balance', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm([
        'total_fee' => 5000, 'payment_type' => 'full',
        'payment_amount' => 2000, 'payment_method' => 'gcash', 'payment_reference' => 'GC-123', 'payment_paid_on' => '2026-10-12',
    ]))->assertSessionHasNoErrors();

    $application = TouristVisaApplication::sole();
    expect((float) $application->total_fee)->toBe(5000.0)
        ->and($application->payment_type)->toBe('full')
        ->and($application->terms_count)->toBeNull()
        ->and($application->amountPaid())->toBe(2000.0)
        ->and($application->balance())->toBe(3000.0)
        ->and($application->paymentStatus())->toBe('partial')
        ->and($application->payments->first()->only(['method', 'reference']))->toBe(['method' => 'gcash', 'reference' => 'GC-123'])
        ->and($application->payments->first()->recorded_by)->toBe($officer->id);

    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))
        ->assertOk()->assertSee('₱3,000.00')->assertSee('Partly paid')->assertSee('GC-123')->assertSee('GCash');

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm([
        'total_fee' => 5000, 'payment_type' => 'full', 'payment_amount' => 3000, 'payment_method' => 'cash',
    ]));
    expect($application->fresh()->paymentStatus())->toBe('paid')->and($application->fresh()->balance())->toBe(0.0);
});

test('a fee in terms splits into the chosen number of payments and shows how far the payments reach', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm([
        'total_fee' => 10000, 'payment_type' => 'terms', 'terms_count' => 3,
        'payment_amount' => 4000, 'payment_method' => 'cash',
    ]))->assertSessionHasNoErrors();

    $application = TouristVisaApplication::sole();
    $terms = $application->installments();

    expect($application->payment_type)->toBe('terms')->and($application->terms_count)->toBe(3)
        ->and(array_column($terms, 'amount'))->toBe([3333.34, 3333.33, 3333.33])
        ->and(round(array_sum(array_column($terms, 'amount')), 2))->toBe(10000.0)
        ->and(array_column($terms, 'status'))->toBe(['paid', 'partial', 'unpaid'])
        ->and($terms[1]['paid'])->toBe(666.66);

    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))
        ->assertOk()->assertSee('Payment 3')->assertSee('₱3,333.34')->assertSee('₱666.66 paid')->assertSee('Not paid yet');
    $this->actingAs($officer)->get(route('visa.tourist-visa.create'))
        ->assertSee('Pay in how many times?')->assertSee('12 payments')->assertSee('touristVisaForm', false);
});

test('switching to full payment drops the terms, and the form checks the fee and the payment', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $post = fn (array $fields) => $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm($fields));

    $post(['total_fee' => 6000, 'payment_type' => 'terms', 'terms_count' => 2]);
    $application = TouristVisaApplication::sole();
    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['total_fee' => 6000, 'payment_type' => 'full', 'terms_count' => 2]));
    expect($application->fresh()->terms_count)->toBeNull()->and($application->fresh()->installments())->toBe([]);

    $post(['payment_type' => 'terms', 'total_fee' => 6000])->assertSessionHasErrors('terms_count');
    $post(['payment_type' => 'terms', 'terms_count' => 1, 'total_fee' => 6000])->assertSessionHasErrors('terms_count');
    $post(['payment_type' => 'terms', 'terms_count' => 13, 'total_fee' => 6000])->assertSessionHasErrors('terms_count');
    $post(['payment_type' => 'terms', 'terms_count' => 3, 'total_fee' => 0])->assertSessionHasErrors('total_fee');
    $post(['payment_type' => 'sometimes'])->assertSessionHasErrors('payment_type');
    $post(['total_fee' => -5])->assertSessionHasErrors('total_fee');
    $post(['total_fee' => 1000, 'payment_amount' => 1500, 'payment_method' => 'cash'])->assertSessionHasErrors('payment_amount');
    $post(['total_fee' => 0, 'payment_amount' => 100, 'payment_method' => 'cash'])->assertSessionHasErrors('payment_amount');
    $post(['total_fee' => 1000, 'payment_amount' => 100])->assertSessionHasErrors('payment_method');
    $post(['total_fee' => 1000, 'payment_amount' => 100, 'payment_method' => 'barter'])->assertSessionHasErrors('payment_method');
    $post(['total_fee' => 1000, 'payment_amount' => 0])->assertSessionHasErrors('payment_amount');

    expect(TouristVisaApplication::count())->toBe(1);
});

test('the fee cannot drop below what was paid, and a payment cannot overshoot the balance', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['total_fee' => 5000, 'payment_amount' => 4000, 'payment_method' => 'cash']));
    $application = TouristVisaApplication::sole();

    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['total_fee' => 3000]))->assertSessionHasErrors('total_fee');
    $this->actingAs($officer)->put(route('visa.tourist-visa.update', $application), cgafForm(['total_fee' => 5000, 'payment_amount' => 1000.01, 'payment_method' => 'cash']))->assertSessionHasErrors('payment_amount');

    expect($application->fresh()->amountPaid())->toBe(4000.0)->and((float) $application->fresh()->total_fee)->toBe(5000.0);
});

test('only an admin can take a payment off, and only from its own application', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['total_fee' => 5000, 'payment_amount' => 1000, 'payment_method' => 'cash']));
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'AB9999999', 'total_fee' => 800, 'payment_amount' => 800, 'payment_method' => 'cash']));
    [$application, $other] = TouristVisaApplication::oldest('id')->get()->all();
    $payment = $application->payments->first();

    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))->assertDontSee('Remove this payment');
    $this->actingAs($officer)->delete(route('visa.tourist-visa.payments.destroy', [$application, $payment]))->assertForbidden();

    $this->actingAs($admin)->get(route('visa.tourist-visa.edit', $application))->assertSee('Remove this payment');
    $this->actingAs($admin)->delete(route('visa.tourist-visa.payments.destroy', [$other, $payment]))->assertNotFound();
    expect($application->fresh()->amountPaid())->toBe(1000.0);

    $this->actingAs($admin)->delete(route('visa.tourist-visa.payments.destroy', [$application, $payment]))->assertRedirect(route('visa.tourist-visa.edit', $application));
    expect($application->fresh()->amountPaid())->toBe(0.0)->and($application->fresh()->paymentStatus())->toBe('unpaid');
});

test('the processing time follows the mode of application unless the counter types one, and the time filed is kept', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['application_mode' => 'regular', 'filed_time' => '14:30']));
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'AB1', 'application_mode' => 'express']));
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'AB2', 'processing_time' => '3 working days']));
    [$regular, $express, $custom] = TouristVisaApplication::oldest('id')->get()->all();

    expect($regular->processing_time)->toBe('7-10 working days')->and($regular->filed_time)->toBe('14:30')
        ->and($express->processing_time)->toBe('1 day')->and($express->filed_time)->toBeNull()
        ->and($custom->processing_time)->toBe('3 working days');

    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $regular))
        ->assertSee('Time Filed')->assertSee('Processing Time')->assertSee('value="14:30"', false)->assertSee('value="7-10 working days"', false);

    // Last: a refused post flashes its input into the session.
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['passport_number' => 'AB3', 'filed_time' => '25:99']))
        ->assertSessionHasErrors('filed_time');
});

test('Save & print checklist saves, then opens the checklist ready to print', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.tourist-visa.create'))->assertSee('Save &amp; print checklist', false);

    $response = $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['then' => 'checklist']));
    $application = TouristVisaApplication::sole();

    $response->assertRedirect(route('visa.tourist-visa.print-checklist', ['touristVisa' => $application, 'autoprint' => 1]));
});

test('the client folder shows each form with its time, processing time and payment', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm([
        'filed_time' => '09:05', 'total_fee' => 9000, 'payment_type' => 'terms', 'terms_count' => 3,
        'payment_amount' => 3000, 'payment_method' => 'cash',
    ]));
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm(['months_requested' => 2]));

    $this->actingAs($officer)->get(route('visa.tourist-visa.client', 'GB1234567'))->assertOk()
        ->assertSee('9:05 AM')->assertSee('7-10 working days')->assertSee('Partly paid')
        ->assertSeeText('₱3,000.00 of ₱9,000.00')->assertSeeText('3 terms')->assertSee('No fee set');
});

test('deleting an application deletes its files and payments', function () {
    Storage::fake(DocumentStorage::diskName());
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->post(route('visa.tourist-visa.store'), cgafForm([
        'photo' => UploadedFile::fake()->image('p.jpg'),
        'documents' => ['cgaf' => UploadedFile::fake()->create('a.pdf', 20, 'application/pdf')],
        'total_fee' => 500, 'payment_amount' => 500, 'payment_method' => 'cash',
    ]));
    $application = TouristVisaApplication::sole();
    $folder = 'tourist-visa/'.$application->reference;
    expect(Storage::disk(DocumentStorage::diskName())->allFiles($folder))->toHaveCount(2);

    $this->actingAs($admin)->delete(route('visa.tourist-visa.destroy', $application));

    expect(Storage::disk(DocumentStorage::diskName())->allFiles($folder))->toBe([])
        ->and(TouristVisaPayment::count())->toBe(0);
});

test('file sizes are written without the intl extension', function () {
    expect(TouristVisaApplication::fileSizeLabel(512))->toBe('512 B')
        ->and(TouristVisaApplication::fileSizeLabel(245000))->toBe('239 KB')
        ->and(TouristVisaApplication::fileSizeLabel(1500000))->toBe('1.4 MB');
});

function priceVisitVisa(string $country, float $serviceFee, float $visaFee, string $time = '1-3 weeks'): void
{
    VisaPricingTier::create([
        'service_type' => 'visit_visa', 'label' => 'Visit Visa - '.$country, 'country' => $country, 'amount' => $serviceFee + $visaFee,
        'service_fee' => $serviceFee, 'visa_fee' => $visaFee, 'processing_time' => $time, 'is_active' => true, 'needs_review' => false,
    ]);
}

test('Add New Client first asks which visa or country, since each has its own form', function () {
    priceVisitVisa('Japan', 2000, 3600);
    priceVisitVisa('Australia', 8000, 10000, '1-3 weeks');
    VisaPricingTier::create(['service_type' => 'visit_visa', 'label' => 'Draft', 'country' => 'Narnia', 'amount' => 1, 'is_active' => true, 'needs_review' => true]);

    $this->actingAs(User::factory()->create(['role' => 'visa_assistance']))->get(route('visa.visit.new'))
        ->assertOk()
        ->assertSee('Add New Client')->assertSee('Which visa is this for?')
        // A visa extension in the Philippines is the Immigration desk's Visa Extension service.
        ->assertDontSee(route('visa.tourist-visa.create'), false)->assertDontSee('Extension of stay for tourists')
        ->assertSee('Japan')->assertSeeText('₱5,600 per person')->assertSee('Australia')->assertSee('No embassy appearance needed')
        ->assertSee(e(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Japan'])), false)
        ->assertSee('Other country')
        ->assertDontSee('Narnia');
});

test('choosing a country opens the visit visa form for that destination, and Other country leaves it open', function () {
    priceVisitVisa('Japan', 2000, 3600);
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $html = $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Japan']))
        ->assertOk()->assertSee('New Non-Immigrant File · Japan')->assertSee('Change country')
        ->assertSee(route('visa.visit.new'), false)
        ->assertSee('Search registered clients')->assertDontSee('Who can apply?')
        ->getContent();
    expect($html)->toContain('\u0022destination\u0022:\u0022Japan\u0022');

    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => '']))
        ->assertOk()->assertSee('New Non-Immigrant File')->assertSee('Search registered clients')->assertSee('\u0022destination\u0022:\u0022\u0022', false);

    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa']))
        ->assertRedirect(route('visa.visit.new'));
});

test('visit visa files for other countries are listed with the Philippines folders', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $other = User::factory()->create(['role' => 'visa_assistance']);
    $file = fn (User $owner, string $name, string $country) => VisaApplication::create([
        'reference' => 'VSA-'.fake()->unique()->numerify('####'), 'client_name' => $name, 'service_type' => 'visit_visa',
        'destination_country' => $country, 'status' => 'pending', 'created_by' => $owner->id,
    ]);
    $file($officer, 'Ana Reyes', 'Japan');
    $file($other, 'Someone Else', 'Canada');
    VisaApplication::create(['reference' => 'VSA-E', 'client_name' => 'Ev Visa', 'service_type' => 'e_visa', 'status' => 'pending', 'created_by' => $officer->id]);

    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), cgafForm());

    $this->actingAs($officer)->get(route('visa.tourist-visa.index'))->assertOk()
        ->assertSee('Philippines')->assertSee('Smith, John, Edward')
        ->assertSee('Other countries')->assertSee('Ana Reyes')->assertSee('Japan')
        ->assertDontSee('Someone Else')->assertDontSee('Ev Visa');

    $this->actingAs($officer)->get(route('visa.tourist-visa.index', ['search' => 'Japan']))->assertOk()
        ->assertSee('Ana Reyes')->assertDontSee('Smith, John');

    // A client with only an other-country file is not an empty list.
    $this->actingAs($other)->get(route('visa.tourist-visa.index'))->assertOk()->assertSee('Someone Else')->assertDontSee('No clients yet');
});
