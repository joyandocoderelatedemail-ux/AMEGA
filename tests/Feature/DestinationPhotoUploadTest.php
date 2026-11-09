<?php

use App\Models\Destination;
use App\Models\User;
use App\Support\DestinationImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('uploads'));

function photoDestination(array $overrides = []): Destination
{
    return Destination::create(array_merge([
        'name' => 'Boracay Island', 'location' => 'Aklan, Philippines', 'description' => 'White sand beaches.',
        'image' => 'newassets/boracay.jpg', 'starting_price' => '₱12,000', 'type' => 'domestic', 'is_featured' => true,
    ], $overrides));
}

function photoPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Siargao', 'location' => 'Surigao del Norte', 'type' => 'domestic', 'starting_price' => '₱9,000',
        'description' => 'Surf capital.', 'is_featured' => '1',
    ], $overrides);
}

test('the destination form asks for a photo upload, not an image path or URL', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $create = $this->actingAs($admin)->get(route('admin.destinations.create'))->assertOk()
        ->assertSee('Destination Photo')->assertSee('name="image_file"', false)->assertSee('enctype="multipart/form-data"', false)
        ->assertDontSee('Image Path / URL')->assertDontSee('name="image"', false);
    expect($create->getContent())->toContain('type="file"')->toMatch('/name="image_file"[^>]*required|required[^>]*name="image_file"/');
});

test('the edit form shows the current photo and does not make a new one compulsory', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = photoDestination();

    $html = $this->actingAs($admin)->get(route('admin.destinations.edit', $destination))->assertOk()
        ->assertSee('Choose a new photo to replace the current one')->assertSee('boracay', false)->getContent();

    // The file box is not "required" when the destination already has a photo.
    expect($html)->not->toMatch('/name="image_file"[^>]*required/');
});

test('a new destination takes an uploaded photo and the home page shows it', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.destinations.store'), photoPayload(['image_file' => UploadedFile::fake()->image('Siargao Surf.jpg', 1200, 700)]))
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.destinations.index'));

    $destination = Destination::where('name', 'Siargao')->firstOrFail();
    expect($destination->image)->toStartWith('uploads/destinations/')->toEndWith('.jpg')->toContain('siargao-surf');
    Storage::disk('uploads')->assertExists(str_replace('uploads/', '', $destination->image));

    $this->get(route('home'))->assertOk()->assertSee('Siargao')->assertSee('uploads/destinations/', false);
});

test('a photo is required for a new destination', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.destinations.store'), photoPayload())->assertSessionHasErrors('image_file');
    expect(Destination::count())->toBe(0);
});

test('only a real photo of a sensible size is accepted', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([
        UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        UploadedFile::fake()->create('virus.exe', 10),
        UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg'),
    ] as $bad) {
        $this->actingAs($admin)->post(route('admin.destinations.store'), photoPayload(['image_file' => $bad]))->assertSessionHasErrors('image_file');
    }

    expect(Destination::count())->toBe(0);
});

test('editing without a new photo keeps the current one', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = photoDestination();

    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), photoPayload(['name' => 'Boracay']))
        ->assertSessionHasNoErrors();

    expect($destination->fresh()->name)->toBe('Boracay')->and($destination->fresh()->image)->toBe('newassets/boracay.jpg');
});

test('uploading a new photo replaces an uploaded one and deletes the old file', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = photoDestination(['image' => DestinationImageStorage::store(UploadedFile::fake()->image('old.jpg'))]);
    $old = $destination->image;
    Storage::disk('uploads')->assertExists(str_replace('uploads/', '', $old));

    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), photoPayload(['image_file' => UploadedFile::fake()->image('new.png')]))
        ->assertSessionHasNoErrors();

    $new = $destination->fresh()->image;
    expect($new)->not->toBe($old)->and($new)->toStartWith('uploads/destinations/');
    Storage::disk('uploads')->assertMissing(str_replace('uploads/', '', $old));
    Storage::disk('uploads')->assertExists(str_replace('uploads/', '', $new));
});

test('replacing a photo that ships with the site never deletes a file from the site', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = photoDestination(['image' => 'newassets/boracay.jpg']);

    $this->actingAs($admin)->put(route('admin.destinations.update', $destination), photoPayload(['image_file' => UploadedFile::fake()->image('new.jpg')]))
        ->assertSessionHasNoErrors();

    expect($destination->fresh()->image)->toStartWith('uploads/destinations/');
    // The seeded path is untouched on disk (and was never a managed upload).
    expect(DestinationImageStorage::owns('newassets/boracay.jpg'))->toBeFalse();
});

test('deleting a destination removes the photo that was uploaded for it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $destination = photoDestination(['image' => DestinationImageStorage::store(UploadedFile::fake()->image('gone.jpg'))]);
    $path = str_replace('uploads/', '', $destination->image);
    Storage::disk('uploads')->assertExists($path);

    $this->actingAs($admin)->delete(route('admin.destinations.destroy', $destination))->assertRedirect(route('admin.destinations.index'));

    expect(Destination::count())->toBe(0);
    Storage::disk('uploads')->assertMissing($path);
});

test('only paths the application wrote can be deleted', function () {
    expect(DestinationImageStorage::owns('uploads/destinations/a.jpg'))->toBeTrue()
        ->and(DestinationImageStorage::owns('uploads/packages/a.jpg'))->toBeFalse()
        ->and(DestinationImageStorage::owns('newassets/a.jpg'))->toBeFalse()
        ->and(DestinationImageStorage::owns('uploads/destinations/../packages/a.jpg'))->toBeFalse()
        ->and(DestinationImageStorage::owns('https://example.com/a.jpg'))->toBeFalse()
        ->and(DestinationImageStorage::owns(null))->toBeFalse();
});
