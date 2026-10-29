<?php

use App\Models\SrrvServiceRequest;
use App\Models\SrrvServiceRequestDocument;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Scans on an SRRV Termination or Conversion request: uploaded beside each requirement (which ticks
 * it) or as other documents, kept on the private disk, and opened only by staff who can see the request.
 */
function conversionForm(array $more = []): array
{
    return [
        'type' => 'conversion', 'retiree_name' => 'Mary Jones', 'visa_class' => 'classic',
        'details' => ['property_address' => 'Unit 5, Clark', 'sublessor_name' => 'Clark Homes Inc.', 'sublease_amount' => 60000, 'sublease_years' => 25, 'srrv_issued_on' => now()->subDays(60)->toDateString()],
    ] + $more;
}

test('a scan uploaded beside a requirement is filed privately and ticks it; others are filed too', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->get(route('srrv.requests.create', ['type' => 'conversion', 'step' => 3]))->assertOk()
        ->assertSee('name="documents[sublease_agreement]"', false)->assertSee('name="other_documents[]"', false)
        ->assertSee('enctype="multipart/form-data"', false);

    $this->actingAs($officer)->post(route('srrv.requests.store'), conversionForm([
        'documents' => ['sublease_agreement' => UploadedFile::fake()->create('sublease.pdf', 200, 'application/pdf')],
        'other_documents' => [UploadedFile::fake()->image('ocular-photo.jpg')],
    ]))->assertRedirect();

    $file = SrrvServiceRequest::withoutGlobalScopes()->firstOrFail();
    expect($file->checklist)->toContain('sublease_agreement')
        ->and($file->documents()->pluck('requirement')->sort()->values()->all())->toBe(['other', 'sublease_agreement']);
    $document = $file->documents()->where('requirement', 'sublease_agreement')->first();
    Storage::disk(DocumentStorage::diskName())->assertExists($document->file_path);

    $this->actingAs($officer)->get(route('srrv.requests.show', $file))->assertOk()->assertSee('sublease.pdf')->assertSee('ocular-photo.jpg');
    $this->actingAs($officer)->get(route('srrv.requests.documents.download', $document))->assertOk();

    // Another officer cannot open it.
    $this->actingAs(User::factory()->create(['role' => 'srrv']))->get(route('srrv.requests.documents.download', $document))->assertNotFound();

    // A wrong upload can be taken off by its owner.
    $this->actingAs($officer)->delete(route('srrv.requests.documents.destroy', $document))->assertSessionHas('success');
    expect(SrrvServiceRequestDocument::find($document->id))->toBeNull();
    Storage::disk(DocumentStorage::diskName())->assertMissing($document->file_path);
});

test('only PDFs and images are filed', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->post(route('srrv.requests.store'), conversionForm([
        'documents' => ['sublease_agreement' => UploadedFile::fake()->create('virus.exe', 10)],
    ]))->assertSessionHasErrors('documents.sublease_agreement');
    expect(SrrvServiceRequest::withoutGlobalScopes()->count())->toBe(0);
});
