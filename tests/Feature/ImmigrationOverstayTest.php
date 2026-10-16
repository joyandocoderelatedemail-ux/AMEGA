<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\User;
use App\Support\DocumentStorage;
use App\Support\ImmigrationServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function overstayOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function overstaySheet(array $attributes = []): ImmigrationClient
{
    return ImmigrationClient::create($attributes + [
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB1234567', 'nationality' => 'British',
        'civil_status' => 'Married', 'date_of_birth' => '1985-03-14', 'mobile_number' => '09171234567',
        'visa_expiry_date' => now()->subMonths(8)->toDateString(), 'is_expired' => true,
    ]);
}

/** @return array<string, mixed> */
function overstayPayload(ImmigrationClient $sheet, array $overstay = []): array
{
    return [
        'service' => 'visa_overstaying',
        'last_name' => $sheet->last_name,
        'given_name' => $sheet->given_name,
        'passport_number' => $sheet->passport_number,
        'nationality' => $sheet->nationality,
        'visa_expiry_date' => $sheet->visa_expiry_date?->toDateString(),
        'is_expired' => '1',
        'overstay' => $overstay + [
            'expired_on' => $sheet->visa_expiry_date?->toDateString(),
            'reason_failed' => 'I was hospitalized and could not reach the BI.',
            'reason_stay' => 'To finish my treatment.',
            'by_representative' => '0',
        ],
    ];
}

test('Visa Overstaying is offered in the services menu and opens the folder start page', function () {
    $service = ImmigrationServices::find('visa_overstaying');

    expect($service['available'])->toBeTrue()
        ->and($service['url'])->toBe(route('admin.client-sheets.start', ['service' => 'visa_overstaying']));

    $this->actingAs(overstayOfficer())->get($service['url'])->assertOk()->assertSee('New Visa Overstaying File');
});

test('a client sheet opened for overstaying shows the overstay steps instead of the document steps', function () {
    $sheet = overstaySheet();

    $this->actingAs(overstayOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'visa_overstaying']))
        ->assertOk()
        ->assertSee('Overstay Details')->assertSee('BI Requirements')->assertSee('Updating Ledger')
        ->assertDontSee('ACR I-Card file')
        ->assertSee('name="service" value="visa_overstaying"', false)
        ->assertSee('Request for Reconsideration')
        ->assertSee('New overstay case');

    // Opened as a Visa Extension, the folder keeps its document steps.
    $this->actingAs(overstayOfficer())->get(route('admin.client-sheets.edit', $sheet))
        ->assertOk()->assertSee('Extension Ledger')->assertDontSee('Overstay Details');
});

test('saving an overstay records the case, its checklist and files, and keeps the folder in overstay mode', function () {
    Storage::fake(DocumentStorage::diskName());
    $sheet = overstaySheet();

    $response = $this->actingAs(overstayOfficer())->put(route('admin.client-sheets.update', $sheet), overstayPayload($sheet, [
        'checklist' => ['cgaf', 'passport'],
        'files' => ['overstay_letter' => UploadedFile::fake()->create('letter.pdf', 50, 'application/pdf')],
    ]));

    $response->assertRedirect(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'visa_overstaying']));

    $case = $sheet->fresh()->openOverstay();
    expect($case)->not->toBeNull()
        ->and($case->expired_on->toDateString())->toBe($sheet->visa_expiry_date->toDateString())
        ->and($case->reason_failed)->toContain('hospitalized')
        ->and($case->checklist)->toBe(['cgaf', 'passport'])
        ->and($case->needsExplanationLetter())->toBeTrue()
        ->and($case->hasChecked('overstay_letter'))->toBeTrue()
        ->and($case->missingItems())->toHaveKey('reconsideration')->not->toHaveKey('overstay_letter');

    Storage::disk(DocumentStorage::diskName())->assertExists($case->documentFor('overstay_letter')['path']);

    $this->actingAs(overstayOfficer())->get(route('admin.client-sheets.overstay-file', [$sheet, $case, 'overstay_letter']))->assertOk();

    // A second save updates the same case rather than opening another.
    $this->actingAs(overstayOfficer())->put(route('admin.client-sheets.update', $sheet), overstayPayload($sheet, ['checklist' => ['cgaf']]));
    expect($sheet->overstays()->count())->toBe(1)
        ->and($sheet->fresh()->openOverstay()->documentFor('overstay_letter'))->not->toBeNull();
});

test('a release date closes the case, so the next overstay starts a new one', function () {
    $sheet = overstaySheet();

    $this->actingAs(overstayOfficer())->put(route('admin.client-sheets.update', $sheet), overstayPayload($sheet, [
        'filed_on' => now()->subDays(3)->toDateString(),
        'released_on' => now()->toDateString(),
    ]))->assertSessionHas('success', fn (string $message) => str_contains($message, 'closed as released'));

    expect($sheet->fresh()->openOverstay())->toBeNull();

    $this->actingAs(overstayOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'visa_overstaying']))
        ->assertSee('New overstay case')->assertSee('Earlier overstays');
});

test('a release date before the filing date is refused', function () {
    $sheet = overstaySheet();

    $this->actingAs(overstayOfficer())->put(route('admin.client-sheets.update', $sheet), overstayPayload($sheet, [
        'filed_on' => now()->toDateString(),
        'released_on' => now()->subDays(2)->toDateString(),
    ]))->assertSessionHasErrors('overstay.released_on');

    expect($sheet->overstays()->count())->toBe(0);
});

test('a visa extension save leaves overstay cases alone', function () {
    $sheet = overstaySheet();

    $payload = overstayPayload($sheet);
    $payload['service'] = 'visa_extension';

    $this->actingAs(overstayOfficer())->put(route('admin.client-sheets.update', $sheet), $payload);

    expect($sheet->overstays()->count())->toBe(0);
});

test('a new client can be started straight into an overstay case', function () {
    $this->actingAs(overstayOfficer())->post(route('admin.client-sheets.store'), [
        'service' => 'visa_overstaying',
        'last_name' => 'Tanaka',
        'given_name' => 'Yuki',
        'visa_expiry_date' => '2026-01-10',
        'overstay' => ['reason_failed' => 'Lost my passport.', 'by_representative' => '0'],
    ])->assertRedirect();

    $sheet = ImmigrationClient::where('last_name', 'Tanaka')->firstOrFail();
    expect($sheet->openOverstay()->expired_on->toDateString())->toBe('2026-01-10');
});

test('the request for reconsideration prints filled from the folder, and blank', function () {
    $sheet = overstaySheet();
    ImmigrationOverstay::create([
        'immigration_client_id' => $sheet->id,
        'expired_on' => '2026-02-01',
        'reason_failed' => 'I was hospitalized.',
        'reason_stay' => 'To finish my treatment.',
        'departure_date' => '2026-11-20',
        'destination' => 'London',
        'by_representative' => true,
        'representative_name' => 'Maria Cruz',
        'representative_accreditation_no' => 'ACC-778',
        'representative_relationship' => 'Liaison officer',
    ]);

    $this->actingAs(overstayOfficer())->get(route('admin.client-sheets.reconsideration', $sheet))->assertOk()
        ->assertSee('BI FORM 2014-01-011 Rev 0')
        ->assertSee('Smith, John')->assertSee('14 MAR 1985')->assertSee('British')
        ->assertSee('I was hospitalized.')->assertSee('01 FEB 2026')->assertSee('20 NOV 2026')->assertSee('London')
        ->assertSee('MARIA CRUZ')->assertSee('ACC-778')->assertSee('Liaison officer')
        ->assertSee('Back to the client sheet');

    $this->actingAs(overstayOfficer())->get(route('admin.client-sheets.reconsideration-blank'))->assertOk()
        ->assertSee('REQUEST FOR RECONSIDERATION ON THE')->assertDontSee('Smith, John');

    $this->actingAs(overstayOfficer())->get(route('admin.immigration.dashboard'))
        ->assertSee(route('admin.client-sheets.reconsideration-blank'), false);
});

test('an open overstay case ticks the BI checklist and marks the CGAF overstayed', function () {
    $sheet = overstaySheet();
    ImmigrationOverstay::create([
        'immigration_client_id' => $sheet->id,
        'expired_on' => now()->subMonths(8)->toDateString(),
        'checklist' => ['cgaf', 'passport', 'overstay_letter', 'reconsideration'],
    ]);

    $html = $this->actingAs(overstayOfficer())->get(route('admin.client-sheets.bi-form', [$sheet, 'checklist']))->assertOk()->getContent();

    // Three of the BI checklist's boxes carry a tick: the CGAF, the passport and the letter.
    expect(substr_count($html, '&#10003;'))->toBe(3)
        ->and($html)->toContain(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'visa_overstaying']));
});

test('the overstay length and letter requirement follow the dates', function () {
    $case = new ImmigrationOverstay(['expired_on' => '2026-01-10', 'filed_on' => '2026-05-25']);

    expect($case->overstayLength())->toBe('4 months, 15 days')
        ->and($case->daysOverstayed())->toBe(135)
        ->and($case->needsExplanationLetter())->toBeFalse()
        ->and(array_keys($case->requiredItems()))->toBe(['reconsideration', 'cgaf', 'passport', 'downgrading_order']);

    $case->filed_on = '2026-07-11';
    $case->departure_date = '2026-08-01';
    $case->by_representative = true;

    expect($case->needsExplanationLetter())->toBeTrue()
        ->and(array_keys($case->requiredItems()))->toBe(['reconsideration', 'cgaf', 'passport', 'downgrading_order', 'overstay_letter', 'departure_ticket', 'spa']);
});

test('the lookup on the overstay start page opens existing folders in overstay mode', function () {
    $sheet = overstaySheet();

    $this->actingAs(overstayOfficer())->getJson(route('admin.client-sheets.lookup', ['q' => 'Smith', 'service' => 'visa_overstaying']))
        ->assertJsonPath('sheets.0.url', route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'visa_overstaying']));

    $this->actingAs(overstayOfficer())->getJson(route('admin.client-sheets.lookup', ['q' => 'Smith']))
        ->assertJsonPath('sheets.0.url', route('admin.client-sheets.edit', $sheet));
});
