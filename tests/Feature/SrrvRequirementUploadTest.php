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
    ];
}

test('the Requirements step has an Upload document button, and the form posts files', function () {
    $this->actingAs(User::factory()->create(['role' => 'srrv']))->get(route('srrv.applications.create'))->assertOk()
        ->assertSee('name="documents[police_clearance][file]"', false)->assertSee('name="documents[passport][file]"', false)->assertSee('Other Documents')->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('Proof of pension')->assertSee('PRA fees for this option')->assertSee('name="courtesy_type"', false);
});

test('documents uploaded in the wizard are filed privately on the new file, and on edit', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload([
        'documents' => [
            // Beside each requirement, keyed by its type (the hidden type comes along) ...
            'passport' => ['type' => 'passport', 'file' => UploadedFile::fake()->create('checklist.pdf', 200, 'application/pdf')],
            'pension_proof' => ['type' => 'pension_proof'],
            // ... and in Other Documents.
            0 => ['type' => 'police_clearance', 'file' => UploadedFile::fake()->image('nbi.jpg')],
        ],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $file = SrrvApplication::latest('id')->first();
    expect($file->documents()->pluck('document_type')->all())->toBe(['passport', 'police_clearance']);
    Storage::disk(DocumentStorage::diskName())->assertExists($file->documents()->first()->file_path);

    // The checklist counts them as received.
    $received = collect($file->fresh()->documentChecklist())->where('received', true)->pluck('type')->all();
    expect($received)->toContain('passport', 'police_clearance');

    // Editing lists what is filed and adds more.
    $this->actingAs($officer)->get(route('srrv.applications.edit', $file))->assertOk()->assertSee('Already filed')->assertSee('checklist.pdf');
    $this->actingAs($officer)->put(route('srrv.applications.update', $file), uploadPayload([
        'documents' => [['type' => 'pension_proof', 'file' => UploadedFile::fake()->create('pension.pdf', 100, 'application/pdf')]],
    ]))->assertRedirect()->assertSessionHasNoErrors();
    expect($file->documents()->count())->toBe(3);
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

    $response = $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload(['then' => 'print']));
    $file = SrrvApplication::latest('id')->first();
    $response->assertRedirect(route('srrv.applications.forms', [$file, 'application', 'autoprint' => 1]));

    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application', 'autoprint' => 1]))->assertOk()
        ->assertSee('window.print()', false)->assertSee("addEventListener('load'", false);

    // The file page tracks only the PRA's processing after the wizard.
    expect($file->stages())->toBe(['pending', 'documentation', 'oath', 'awaiting_release', 'released']);
    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertOk()
        ->assertSee('Lodged with PRA')->assertSee('Finish it in the wizard')->assertDontSee('Supporting Documents');
});

test('the printed form goes back into the wizard on its last step, not the old file page', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->post(route('srrv.applications.store'), uploadPayload());
    $file = SrrvApplication::latest('id')->first();
    $back = route('srrv.applications.edit', [$file, 'step' => 6]);

    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application']))->assertOk()
        ->assertSee('Back to the application')->assertSee($back, false);

    // The wizard opens on step 6 (Remarks & Save), where the Print button is.
    $this->actingAs($officer)->get($back)->assertOk()->assertSee('step: 6,', false);
});
