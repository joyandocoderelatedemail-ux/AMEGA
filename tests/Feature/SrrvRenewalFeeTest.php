<?php

use App\Models\SrrvPayment;
use App\Models\SrrvRenewal;
use App\Models\SrrvRenewalDocument;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function renewalPayload(array $overrides = []): array
{
    return array_merge([
        'visa_class' => 'classic', 'retiree_name' => 'John Smith', 'retiree_email' => 'john@example.com',
        'retiree_phone' => '+63 917 555 0101', 'nationality' => 'British', 'date_of_birth' => '1958-03-14',
        'srrv_card_number' => 'SRRV-00777', 'years_paid' => 1, 'agreement_signed' => 1,
    ], $overrides);
}

function renewalFilePayload(array $overrides = []): array
{
    return renewalPayload(array_merge([
        'documents' => ['new' => ['type' => 'id_card', 'remarks' => 'SRRV ID scan']],
    ], $overrides));
}

test('a Classic renewal charges US$100 a year for each dependent beyond two', function (int $dependents, int $years, float $fee) {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['dependents' => $dependents, 'years_paid' => $years]))
        ->assertSessionHasNoErrors();

    expect((float) SrrvRenewal::latest('id')->firstOrFail()->fee_amount)->toBe($fee);
})->with([
    'no dependents' => [0, 1, 360.0],
    'two, covered by the fee' => [2, 1, 360.0],
    'four, two of them extra' => [4, 1, 560.0],
    'four, for two years' => [4, 2, 1120.0],
]);

test('a Courtesy renewal is not charged for dependents', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['visa_class' => 'courtesy', 'dependents' => 5]))
        ->assertSessionHasNoErrors();

    expect((float) SrrvRenewal::latest('id')->firstOrFail()->fee_amount)->toBe(SrrvRenewal::CLASS_FEES['courtesy']);
});

test('the renewal keeps the retiree details the application form asks for, and shows the fee with its dependents', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['dependents' => 3]))->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    expect($renewal)->retiree_phone->toBe('+63 917 555 0101')->nationality->toBe('British')
        ->and($renewal->date_of_birth->toDateString())->toBe('1958-03-14')->and($renewal->dependents)->toBe(3);

    $this->get(route('srrv.renewals.show', $renewal))->assertOk()->assertSee('USD 460.00 a year');
    $this->get(route('srrv.renewals.edit', $renewal))->assertOk()
        ->assertSee('Who the renewal is for, and how to reach them.')
        ->assertSee('Dependents on the SRRV')
        ->assertSee('value="+63 917 555 0101"', false);
});

test('only a retiree with an SRRV number can renew', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['srrv_card_number' => '']))
        ->assertSessionHasErrors(['srrv_card_number' => 'Enter the retiree\'s SRRV number: only an SRRV holder can renew the ID card.']);
    expect(SrrvRenewal::count())->toBe(0);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload())->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    $this->put(route('srrv.renewals.update', $renewal), renewalPayload(['srrv_card_number' => '']))->assertSessionHasErrors('srrv_card_number');
    expect($renewal->fresh()->srrv_card_number)->toBe('SRRV-00777');
});

test('the agent discounts the service fee by a percentage or an amount, never past the fee', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $save = fn (array $fees) => $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['service_fee' => 150] + $fees));

    $save(['discount_type' => 'percent', 'discount_value' => 10])->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();
    expect((float) $renewal->discount_amount)->toBe(15.0)->and($renewal->feeBalance())->toBe(360.0)
        ->and($renewal->serviceBalance())->toBe(135.0);

    $save(['discount_type' => 'amount', 'discount_value' => 50])->assertSessionHasNoErrors();
    expect(SrrvRenewal::latest('id')->firstOrFail()->serviceBalance())->toBe(100.0);

    $save(['discount_type' => 'percent', 'discount_value' => 120])->assertSessionHasErrors(['discount_value' => 'A percentage discount cannot be more than 100%.']);
    $save(['discount_type' => 'amount', 'discount_value' => 200])->assertSessionHasErrors('discount_value');
    expect(SrrvRenewal::count())->toBe(2);
});

test('the admin approves the fees, then the cashier takes the PRA fee and the service fee separately', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'srrv']);
    $cashier = User::factory()->create(['role' => 'cashier']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['service_fee' => 150, 'discount_type' => 'percent', 'discount_value' => 10]))
        ->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();
    expect($renewal->isAwaitingApproval())->toBeTrue();

    // Not at the cashier until the admin approves.
    $this->actingAs($cashier)->get(route('cashier.srrv.index'))->assertOk()->assertDontSee(route('cashier.srrv.renewals.show', $renewal->id), false);
    $this->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 360, 'apply_to' => 'fee', 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('error');

    $this->actingAs($admin)->get(route('admin.srrv-approvals.index'))->assertOk()->assertSee('PRA fee USD 360.00 + service fee PHP 150.00 less discount PHP 15.00 (10%)');
    $this->post(route('admin.srrv-approvals.approve', ['renewal', $renewal->id]), ['admin_acknowledged' => 1]);

    $this->actingAs($cashier)->get(route('cashier.srrv.index'))->assertSee($renewal->reference)->assertSee('USD 360.00 + PHP 135.00');
    $this->get(route('cashier.srrv.renewals.show', $renewal->id))->assertOk()->assertSee('PRA ID renewal fee')->assertSee('Discount (10%)')->assertSee('Apply to');

    // The PRA fee and the service fee are paid separately; each refuses more than its balance.
    $this->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 400, 'apply_to' => 'fee', 'method' => 'cash', 'cashier_acknowledged' => 1])
        ->assertSessionHasErrors('amount');
    $this->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 360, 'apply_to' => 'fee', 'method' => 'cash', 'reference' => 'OR-1001', 'cashier_acknowledged' => 1])
        ->assertSessionHasNoErrors();
    expect($renewal->fresh()->isFullyPaid())->toBeFalse()->and($renewal->fresh()->feeBalance())->toBe(0.0);

    $this->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 135, 'apply_to' => 'service', 'method' => 'cash', 'cashier_acknowledged' => 1])
        ->assertSessionHasNoErrors();

    expect($renewal->fresh()->isFullyPaid())->toBeTrue()
        ->and(SrrvPayment::where('srrv_renewal_id', $renewal->id)->where('currency', 'USD')->value('reference'))->toBe('OR-1001');
    $this->actingAs($officer)->get(route('srrv.renewals.show', $renewal))->assertOk()->assertDontSee('Record a payment');
});

test('Step 3 Documentation files scans like a new application: type, remark and file, with download and remove', function () {
    Storage::fake('local');
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload())->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    // The Document Checklist card is on Step 3, like a new application: Add file opens the upload row.
    // Refreshing keeps the step: it is synced to ?step= in the address bar.
    $this->get(route('srrv.renewals.edit', [$renewal, 'step' => 3]))->assertOk()
        ->assertSee('step: 3', false)->assertSee('Document Checklist')->assertSee('Add file')->assertSee('File Document');

    // Remarks are only asked for Other: a named type files without one, Other refuses without it.
    $this->put(route('srrv.renewals.update', $renewal), renewalFilePayload([
        'documents' => ['new' => ['type' => 'id_card', 'file' => UploadedFile::fake()->image('id.jpg')]],
        'then' => 'document',
    ]))->assertRedirect(route('srrv.renewals.edit', [$renewal, 'step' => 3]));
    expect(SrrvRenewalDocument::where('document_type', 'id_card')->sole()->remarks)->toBeNull();

    $this->put(route('srrv.renewals.update', $renewal), renewalFilePayload([
        'documents' => ['new' => ['type' => 'other', 'file' => UploadedFile::fake()->image('other.jpg')]],
        'then' => 'document',
    ]))->assertSessionHasErrors('documents.new.remarks');
    $this->put(route('srrv.renewals.update', $renewal), renewalFilePayload(['then' => 'document']))
        ->assertSessionHasErrors('documents.new.file');

    $this->put(route('srrv.renewals.update', $renewal), renewalFilePayload([
        'documents' => ['new' => ['type' => 'other', 'remarks' => 'Old passport copy', 'file' => UploadedFile::fake()->image('other.jpg')]],
        'then' => 'document',
    ]))->assertRedirect(route('srrv.renewals.edit', [$renewal, 'step' => 3]));

    $document = SrrvRenewalDocument::where('document_type', 'other')->sole();
    expect($document->remarks)->toBe('Old passport copy');
    Storage::disk('local')->assertExists($document->file_path);

    $this->get(route('srrv.renewals.documents.download', $document))->assertOk();
    $this->get(route('srrv.renewals.show', $renewal))->assertOk()->assertSee('Old passport copy')->assertSee('other.jpg');

    // An incorrect file is taken off with the X button (both this file and the earlier ID scan).
    $this->delete(route('srrv.renewals.documents.destroy', $document))->assertSessionHas('success');
    expect(SrrvRenewalDocument::count())->toBe(1);
    Storage::disk('local')->assertMissing($document->file_path);
});

test('Add file works while recording a renewal: the renewal saves first, then the file attaches to it', function () {
    Storage::fake('local');
    $officer = User::factory()->create(['role' => 'srrv']);

    // The upload row is on Step 3 even before the renewal exists.
    $this->actingAs($officer)->get(route('srrv.renewals.create'))->assertOk()->assertSee('Add file');

    $this->post(route('srrv.renewals.store'), renewalPayload([
        'documents' => ['new' => ['type' => 'online_form', 'remarks' => 'Filled request form', 'file' => UploadedFile::fake()->image('form.jpg')]],
        'then' => 'document',
    ]))->assertRedirect();

    $renewal = SrrvRenewal::latest('id')->firstOrFail();
    expect($renewal->documents)->toHaveCount(1);
});

test('the PRA fee stays in USD and the service fee stays in PHP with no conversion', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);

    // No currency switch and no exchange rate: the PRA fee is USD, the service fee PHP.
    $this->actingAs($officer)->get(route('srrv.renewals.create', ['visa_class' => 'classic']))->assertOk()
        ->assertDontSee('name="currency"', false)->assertDontSee('name="exchange_rate"', false)
        ->assertSee('Fee due (USD)')->assertSee('Service fee (PHP)');

    // Currency and exchange rate inputs are ignored: USD 1,120.00 (two years, two dependents beyond the two covered) plus ₱5,000 less ₱500.
    $this->post(route('srrv.renewals.store'), renewalPayload(['currency' => 'PHP', 'exchange_rate' => 58.2, 'dependents' => 4, 'years_paid' => 2,
        'service_fee' => 5000, 'discount_type' => 'amount', 'discount_value' => 500]))->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    expect($renewal->currency)->toBe('USD')->and($renewal->service_currency)->toBe('PHP')
        ->and((float) $renewal->fee_amount)->toBe(1120.0)->and($renewal->serviceDue())->toBe(4500.0);
    $this->get(route('srrv.renewals.show', $renewal))->assertOk()->assertSee('USD 1,120.00')->assertSee('4,500.00')->assertDontSee('Exchange rate');
});

test('the retiree signs a printed acknowledgment of the renewal documents, and its scan is filed', function () {
    Storage::fake(DocumentStorage::diskName());
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload([
        'documents' => ['new' => ['type' => 'id_card', 'file' => UploadedFile::fake()->image('id.jpg')]],
    ]))->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    $this->get(route('srrv.renewals.acknowledgement', $renewal))->assertOk()
        ->assertSee('ACKNOWLEDGMENT OF DOCUMENTS')->assertSee('SRRV ID card')->assertSee('Classic ID Renewal')->assertSee('SRRV ID renewal');

    $this->put(route('srrv.renewals.update', $renewal), renewalPayload([
        'acknowledgement_signed' => 1, 'signed_acknowledgement' => UploadedFile::fake()->image('signed.jpg'),
    ]))->assertSessionHasNoErrors();

    expect($renewal->fresh()->acknowledgement_signed_at)->not->toBeNull()
        ->and($renewal->documents()->where('document_type', 'signed_acknowledgement')->count())->toBe(1);
    $this->get(route('srrv.renewals.edit', $renewal))->assertSee('Signed copy (scan or photo)')->assertSee('signed.jpg');
});

test('the fingerprint card (PRA-SD-FORM-0016) prints with the officer details; the impressions are taken by hand', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload([
        'form' => ['height' => '175 cm', 'eye_color' => 'Brown', 'gender' => 'M', 'blood_type' => 'O+', 'impressions_by' => 'Officer Santos', 'impressions_on' => '2026-11-25'],
    ]))->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    $this->get(route('srrv.renewals.fingerprint-card', $renewal))->assertOk()
        ->assertSee('PRA-SD-FORM-0016')->assertSee('John Smith')->assertSee('SRRV-00777')
        ->assertSee('175 cm')->assertSee('Brown')->assertSee('Male')->assertSee('O+')->assertSee('Officer Santos')->assertSee('Nov 25, 2026')
        ->assertDontSee('data:image/', false);

    // Save & print saves what is typed first, then opens the card with it.
    $this->put(route('srrv.renewals.update', $renewal), renewalPayload(['then' => 'card', 'form' => ['eye_color' => 'Green']]))
        ->assertRedirect(route('srrv.renewals.fingerprint-card', $renewal));
    $this->get(route('srrv.renewals.fingerprint-card', $renewal))->assertSee('Green');
    $this->get(route('srrv.renewals.edit', $renewal))->assertDontSee('Upload a clear photo of each impression');
});

test('a renewal is saved only once the client signed the service agreement, which prints first', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);

    // Recording it unsigned is refused.
    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['agreement_signed' => 0]))
        ->assertSessionHasErrors('agreement_signed');
    expect(SrrvRenewal::count())->toBe(0);

    // Save & print agreement saves on the way and opens the filled agreement.
    $response = $this->post(route('srrv.renewals.store'), renewalPayload(['agreement_signed' => 0, 'then' => 'agreement']));
    $renewal = SrrvRenewal::latest('id')->firstOrFail();
    $response->assertRedirect(route('srrv.renewals.agreement', $renewal));
    expect($renewal->agreement_signed_at)->toBeNull();

    // Still unsigned: saving is refused; signed: it saves and the signature is recorded.
    $this->put(route('srrv.renewals.update', $renewal), renewalPayload(['agreement_signed' => 0]))->assertSessionHasErrors('agreement_signed');
    $this->put(route('srrv.renewals.update', $renewal), renewalPayload())->assertSessionHasNoErrors();
    expect($renewal->fresh()->agreement_signed_at)->not->toBeNull();
    $this->get(route('srrv.renewals.edit', [$renewal, 'step' => 5]))->assertSee('Service Agreement')->assertSee('Signed ');
});

test('the service agreement fills the retiree\'s birth date, nationality and passport from the renewal', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload([
        'form' => ['passport_no' => 'P9876543', 'passport_expires_on' => '2030-06-30'],
    ]))->assertSessionHasNoErrors();

    $this->get(route('srrv.renewals.agreement', SrrvRenewal::latest('id')->firstOrFail()))->assertOk()
        ->assertSee('March 14, 1958')->assertSee('British')->assertSee('P9876543 (valid until June 30, 2030)');
});

test('a renewal without a passport takes it from the retiree\'s client record, on the form and the agreement', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);
    User::factory()->create(['role' => 'client', 'srrv_number' => 'SRRV-00777', 'passport_number' => 'P5554443', 'passport_expiry' => '2031-01-31']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload())->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    $this->get(route('srrv.renewals.edit', $renewal))->assertSee('value="P5554443"', false);
    $this->get(route('srrv.renewals.agreement', $renewal))->assertSee('P5554443 (valid until January 31, 2031)');
});

test('Step 4 continues to Step 5 only once the fingerprint card has been saved and printed', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->get(route('srrv.renewals.create', ['visa_class' => 'classic']))->assertSee('locked: 4,', false);

    // Save & print opens the card in the same tab (no second copy of a new renewal); printing it unlocks Step 5.
    $response = $this->post(route('srrv.renewals.store'), renewalPayload(['agreement_signed' => 0, 'then' => 'card']));
    $renewal = SrrvRenewal::latest('id')->firstOrFail();
    $response->assertRedirect(route('srrv.renewals.fingerprint-card', $renewal));
    $this->get(route('srrv.renewals.edit', $renewal))->assertSee('locked: 4,', false)->assertSee('to continue to Step 5');

    $this->get(route('srrv.renewals.fingerprint-card', $renewal))->assertOk();
    expect($renewal->fresh()->card_printed_at)->not->toBeNull();
    $this->get(route('srrv.renewals.edit', $renewal))->assertSee('locked: null,', false)->assertDontSee('to continue to Step 5');
});

test('the cashier prints an SRRV renewal\'s statement of account, each due in its own currency', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);
    $admin = User::factory()->create(['role' => 'admin']);
    $cashier = User::factory()->create(['role' => 'cashier']);

    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload(['service_fee' => 1500, 'discount_type' => 'amount', 'discount_value' => 100]))->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['renewal', $renewal->id]), ['admin_acknowledged' => 1]);
    $this->actingAs($cashier)->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 360, 'apply_to' => 'fee', 'method' => 'cash', 'reference' => 'OR-77', 'cashier_acknowledged' => 1])
        ->assertSessionHasNoErrors();

    $this->get(route('cashier.srrv.renewals.show', $renewal->id))->assertSee(route('cashier.srrv.renewals.soa', $renewal->id));
    $this->get(route('cashier.srrv.renewals.soa', $renewal->id))->assertOk()
        ->assertSee('Statement of Account')->assertSee('John Smith')->assertSee('Classic ID Renewal')
        ->assertSee('PRA ID renewal fee')->assertSee('USD 360.00')
        ->assertSee('Service fee (less 100.00 discount)')->assertSee('₱1,400.00')
        ->assertSee('OR-77');
});

test('the new ID expiry is set at New ID Ready, not on the way there', function () {
    Notification::fake();
    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->post(route('srrv.renewals.store'), renewalPayload())->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    $renewal->forceFill(['status' => 'payment_confirmed', 'approval_status' => 'approved'])->save();
    $this->get(route('srrv.renewals.show', $renewal))->assertDontSee('New ID expiry');
    $this->post(route('srrv.renewals.card-expiry', $renewal), ['card_expiry_date' => now()->addYears(2)->toDateString()])->assertSessionHas('error');

    $renewal->forceFill(['status' => 'new_id_ready'])->save();
    $this->get(route('srrv.renewals.show', $renewal))->assertSee('New ID expiry');
    $this->post(route('srrv.renewals.card-expiry', $renewal), ['card_expiry_date' => '2029-03-31'])->assertSessionHas('success');
    expect($renewal->fresh()->card_expiry_date->toDateString())->toBe('2029-03-31')
        ->and(User::where('role', 'client')->where('srrv_number', 'SRRV-00777')->first()?->pra_id_valid_until?->toDateString())->toBe('2029-03-31');
});

test('the client profile lists the client\'s SRRV, Local Tours and Cruise files under Other Services', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client', 'name' => 'John Smith', 'email' => 'john@example.com']);

    $this->actingAs($admin)->post(route('srrv.renewals.store'), renewalPayload())->assertSessionHasNoErrors();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    $this->get(route('admin.users.show', $client))->assertOk()
        ->assertSee('Other Services (1)')->assertSee('Classic ID Renewal')->assertSee($renewal->reference)
        ->assertSee(route('srrv.renewals.show', $renewal), false);
});
