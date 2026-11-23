<?php

use App\Models\SrrvApplication;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => autoApproveDeskFiles());

function uploadPayload(array $overrides = []): array
{
    return $overrides + [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Cruz', 'retiree_email' => 'ramon@example.com',
        'retiree_phone' => '09170000000', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'nationality' => 'Filipino', 'investment_amount' => 1500,
        'agreement_signed' => 1,
    ];
}

test('the Documents step is a Document Checklist: a type, a remark and a file, filed at once; no pre-listed requirements', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);

    // A new file: the card, nothing pre-listed, and no upload until it is saved.
    $this->actingAs($officer)->get(route('srrv.applications.create'))->assertOk()
        ->assertSee('Document Checklist')->assertSee('Nothing filed yet.')->assertSee('Acknowledgment of Documents')->assertSee('enctype="multipart/form-data"', false)
        ->assertDontSee('Other Documents')->assertDontSee('name="documents[passport][file]"', false)->assertDontSee('name="requirements[]"', false)
        ->assertDontSee('File Document')->assertDontSee('PRA fees for this option')->assertSee('name="courtesy_type"', false);

    // A saved pensioner file: the upload row offers its own PRA requirements first; its fields are the wizard's own.
    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload(['is_pensioner' => 1]))->assertSessionHasNoErrors();
    $file = SrrvApplication::latest('id')->firstOrFail();
    $html = $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'step' => 5]))->assertOk()
        ->assertSee('File Document')->assertSee('name="then" value="document"', false)
        ->assertSee('name="documents[new][type]"', false)->assertSee('name="documents[new][remarks]"', false)->assertSee('name="documents[new][file]"', false)
        ->assertDontSee('srrv-doc-upload', false)->assertSee('<option value="pension_proof"', false)->getContent();
    expect(strpos($html, '<option value="passport"'))->toBeLessThan(strpos($html, '<option value="srrv_card"'));

    // File Document saves the wizard with it (nothing typed is lost): a file and a remark are needed.
    $fileDocument = fn (array $document, array $typed = []) => $this->actingAs($officer)->from(route('srrv.applications.edit', [$file, 'step' => 5]))
        ->put(route('srrv.applications.update', $file), uploadPayload(['is_pensioner' => 1, 'then' => 'document', 'documents' => ['new' => $document]] + $typed));
    $fileDocument(['type' => 'passport', 'remarks' => 'Bio page'])->assertSessionHasErrors('documents.new.file');
    $fileDocument(['type' => 'passport', 'file' => UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf')])->assertSessionHasErrors('documents');
    $fileDocument(['type' => 'passport', 'remarks' => 'Bio page, renewed 2026', 'file' => UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf')], ['remarks' => 'Typed on Step 7'])
        ->assertRedirect(route('srrv.applications.edit', [$file, 'step' => 5]))->assertSessionHas('success');
    expect($file->documents()->sole()->remarks)->toBe('Bio page, renewed 2026')->and($file->fresh()->remarks)->toBe('Typed on Step 7');
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'step' => 5]))->assertOk()
        ->assertSee('passport.pdf')->assertSee('Passport: Bio page, renewed 2026')->assertSee('1 filed');
});

test('documents uploaded in the wizard are filed privately on the new file, and on edit', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload([
        'documents' => [
            // Beside each requirement, keyed by its type (the hidden type comes along) ...
            'passport' => ['type' => 'passport', 'file' => UploadedFile::fake()->create('checklist.pdf', 200, 'application/pdf')],
            'pension_proof' => ['type' => 'pension_proof'],
            // ... and in Other Documents, with a remark saying what it is.
            0 => ['type' => 'police_clearance', 'remarks' => 'Police clearance, apostilled', 'file' => UploadedFile::fake()->image('nbi.jpg')],
        ],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $file = SrrvApplication::latest('id')->first();
    expect($file->documents()->pluck('document_type')->all())->toBe(['passport', 'police_clearance']);
    Storage::disk(DocumentStorage::diskName())->assertExists($file->documents()->first()->file_path);

    // The checklist counts them as received.
    $received = collect($file->fresh()->documentChecklist())->where('received', true)->pluck('type')->all();
    expect($received)->toContain('passport', 'police_clearance');

    // Editing lists what is filed and adds more.
    $this->actingAs($officer)->get(route('srrv.applications.edit', $file))->assertOk()->assertSee('Document Checklist')->assertSee('checklist.pdf');
    // Other Documents each need a remark saying what the document is; it is kept with the file and listed.
    $this->actingAs($officer)->put(route('srrv.applications.update', $file), uploadPayload([
        'documents' => [['type' => 'other', 'file' => UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf')]],
    ]))->assertSessionHasErrors('documents');
    expect($file->documents()->count())->toBe(2);

    $this->actingAs($officer)->put(route('srrv.applications.update', $file), uploadPayload([
        'documents' => [['type' => 'pension_proof', 'remarks' => 'SSA pension letter, 2026', 'file' => UploadedFile::fake()->create('pension.pdf', 100, 'application/pdf')]],
    ]))->assertRedirect()->assertSessionHasNoErrors();
    expect($file->documents()->count())->toBe(3)
        ->and($file->documents()->where('document_type', 'pension_proof')->value('remarks'))->toBe('SSA pension letter, 2026');
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'step' => 5]))->assertOk()->assertSee('pension.pdf')->assertSee('Proof of pension: SSA pension letter, 2026');
});

test('a wrong file type or unknown document type is refused', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload([
        'documents' => [['type' => 'pra_checklist', 'file' => UploadedFile::fake()->create('virus.exe', 10)]],
    ]))->assertSessionHasErrors('documents.0.file');

    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload([
        'documents' => [['type' => 'bogus', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]],
    ]))->assertSessionHasErrors('documents.0.type');

    expect(SrrvApplication::count())->toBe(0);
});

test('every requirement row sends its type without blocking the save; only chosen files count', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);
    // What the wizard posts: a hidden type for every PRA requirement, one of them with a file.
    $rows = collect(SrrvApplication::REQUIREMENTS)->keys()->mapWithKeys(fn ($type) => [$type => ['type' => $type]])->all();
    $rows['passport']['file'] = UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf');

    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload(['documents' => $rows]))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(SrrvApplication::latest('id')->first()->documents()->pluck('document_type')->all())->toBe(['passport']);
});

test('the last step saves and prints the PRA Application Form, once the file is cleared', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->get(route('srrv.applications.create'))->assertOk()
        ->assertSee('name="then" value="print"', false)->assertSee('Open File &amp; Print Application Form', false)
        ->assertDontSee('Open Retiree File');

    $response = $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload(['then' => 'print', 'service_fee' => 1500]));
    $file = SrrvApplication::latest('id')->first();
    $response->assertRedirect(route('srrv.applications.forms', [$file, 'application', 'autoprint' => 1]));

    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application', 'autoprint' => 1]))->assertOk()
        ->assertSee('window.print()', false)->assertSee("addEventListener('load'", false);

    // After the wizard (Step 1) the file walks the agreement and fee, the documents, then the PRA Submission steps.
    expect($file->stages())->toBe(['pending', 'agreement', 'requirements', 'pra', 'released']);
    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertRedirect(route('srrv.applications.edit', $file));
});

test('the printed form goes back into the wizard on its last step, not the old file page', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload());
    $file = SrrvApplication::latest('id')->first();
    $back = route('srrv.applications.edit', [$file, 'step' => 7]);

    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application']))->assertOk()
        ->assertSee('Back to the application')->assertSee($back, false);

    // The wizard opens on step 7 (Remarks & Save), where the Print button is.
    $this->actingAs($officer)->get($back)->assertOk()->assertSee('step: 7,', false);
});

test('the Acknowledgment lists the documents filed and the requirements ticked as seen, and what is ticked on screen', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload([
        // Ticked as seen, one of them with its scan too.
        'requirements' => ['passport', 'police_clearance'],
        'documents' => [
            'passport' => ['type' => 'passport', 'file' => UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf')],
            0 => ['type' => 'other', 'remarks' => 'Bank certificate', 'file' => UploadedFile::fake()->create('bank.pdf', 50, 'application/pdf')],
            'signed_acknowledgement' => ['type' => 'signed_acknowledgement', 'file' => UploadedFile::fake()->image('signed.jpg')],
        ],
    ]))->assertSessionHasNoErrors();
    $file = SrrvApplication::latest('id')->firstOrFail();

    $html = $this->actingAs($officer)->get(route('srrv.applications.acknowledgement', $file))->assertOk()
        ->assertDontSee('None filed yet')->assertDontSee('Signed acknowledgment')->getContent();
    expect($html)->toContain('<td>Passport</td>')->toContain('<td>Other (Bank certificate)</td>')->toContain('<td>Police clearance</td>')
        // Filed and ticked: listed once.
        ->and(substr_count($html, '<td>Passport</td>'))->toBe(1);

    // Ticked on screen and not saved yet: listed too.
    $this->actingAs($officer)->get(route('srrv.applications.acknowledgement', [$file, 'received' => ['medical_certificate']]))->assertOk()
        ->assertSee('<td>PRA medical certificate</td>', false);
});

test('the Acknowledgment of a file with nothing uploaded lists the requirements ticked, saved or on screen', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload(['requirements' => ['police_clearance']]))->assertSessionHasNoErrors();
    $file = SrrvApplication::latest('id')->firstOrFail();

    $this->actingAs($officer)->get(route('srrv.applications.acknowledgement', [$file, 'received' => ['passport', 'police_clearance']]))->assertOk()
        ->assertSee('<td>Police clearance</td>', false)->assertSee('<td>Passport</td>', false)->assertDontSee('None filed yet');

    // Nothing ticked or filed at all: it says so.
    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload(['retiree_name' => 'Ana Cruz', 'retiree_email' => 'ana@example.com']))->assertSessionHasNoErrors();
    $this->actingAs($officer)->get(route('srrv.applications.acknowledgement', SrrvApplication::latest('id')->firstOrFail()))->assertOk()->assertSee('None filed yet');
});

test('each added document prints on the Acknowledgment with its remark, saved or still on screen', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    // A new file, before it is saved: the rows added on screen, with their remarks.
    $this->actingAs($officer)->get(route('srrv.acknowledgement-blank', [
        'visa_class' => 'classic', 'retiree_name' => 'Ramon Cruz', 'received' => ['passport'],
        'other' => [['type' => 'other', 'remarks' => 'Bank certificate'], ['type' => 'valid_id', 'remarks' => "Driver's licence"]],
    ]))->assertOk()
        ->assertSee('<td>Other (Bank certificate)</td>', false)->assertSee('<td>Other valid ID (Driver&#039;s licence)</td>', false)->assertSee('<td>Passport</td>', false);

    // A saved file: what is filed, plus the rows added on screen and not saved yet.
    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload())->assertSessionHasNoErrors();
    $file = SrrvApplication::latest('id')->firstOrFail();
    $this->actingAs($officer)->get(route('srrv.applications.acknowledgement', [$file, 'other' => [['type' => 'pension_proof', 'remarks' => 'SSA letter']]]))->assertOk()
        ->assertSee('<td>Proof of pension (SSA letter)</td>', false);

    // A row's type must be a known document type.
    $this->actingAs($officer)->get(route('srrv.applications.acknowledgement', [$file, 'other' => [['type' => 'bogus']]]))->assertSessionHasErrors('other.0.type');
});
