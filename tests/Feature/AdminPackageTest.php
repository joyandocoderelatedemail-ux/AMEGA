<?php

use App\Models\TravelPackage;
use App\Models\User;
use App\Support\PackageImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('admin can view travel packages list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/packages');

    $response->assertStatus(200);
    $response->assertSee('Travel Packages Directory');
});

test('admin can create a new travel package', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/packages', [
        'title' => 'Test Paradise Tour',
        'duration' => '5 Days / 4 Nights',
        'price' => '$1,500',
        'rating' => 5,
        'image' => 'images/test.jpg',
        'description' => 'A wonderful test package.',
        'inclusions' => 'Flights, Hotel, Breakfast',
        'exclusions' => 'Personal items',
        'itinerary' => 'Day 1: Arrival',
        'available_dates' => 'Oct 1 - Oct 5',
        'category' => 'short_haul',
        'status' => 'active',
        'is_featured' => '1',
    ]);

    $response->assertRedirect('/admin/packages');
    $this->assertDatabaseHas('travel_packages', [
        'title' => 'Test Paradise Tour',
        'category' => 'short_haul',
    ]);
});

test('admin can update a travel package', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Old Title Tour',
        'duration' => '4 Days',
        'price' => '$999',
        'rating' => 4,
        'image' => 'images/old.jpg',
        'description' => 'Old description',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->put("/admin/packages/{$package->id}", [
        'title' => 'Updated Title Tour',
        'duration' => '5 Days',
        'price' => '$1,299',
        'rating' => 5,
        'image' => 'images/new.jpg',
        'description' => 'Updated description',
        'category' => 'long_haul',
        'status' => 'active',
    ]);

    $response->assertRedirect('/admin/packages');
    $this->assertDatabaseHas('travel_packages', [
        'id' => $package->id,
        'title' => 'Updated Title Tour',
        'price' => '$1,299',
    ]);
});

test('admin can create a travel package with an uploaded photo', function () {
    Storage::fake('uploads');

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/packages', [
        'title' => 'Boracay Escape',
        'duration' => '4 Days / 3 Nights',
        'price_amount' => 12500,
        'price_currency' => 'PHP',
        'rating' => 5,
        'image_file' => UploadedFile::fake()->image('Boracay Sunset.jpg', 1600, 900),
        'description' => 'White sand and sunsets.',
        'category' => 'domestic',
        'status' => 'active',
    ]);

    $response->assertRedirect('/admin/packages');

    $package = TravelPackage::where('title', 'Boracay Escape')->firstOrFail();

    expect($package->image)
        ->toStartWith('uploads/packages/boracay-sunset-')
        ->toEndWith('.jpg');

    Storage::disk('uploads')->assertExists(Str::after($package->image, 'uploads/'));
});

test('uploaded package photos reject non-image files', function () {
    Storage::fake('uploads');

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/packages', [
        'title' => 'Bad Upload Tour',
        'duration' => '3 Days',
        'price_amount' => 1000,
        'rating' => 4,
        'image_file' => UploadedFile::fake()->create('brochure.pdf', 120, 'application/pdf'),
        'description' => 'Should not be accepted.',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors('image_file');
    $this->assertDatabaseMissing('travel_packages', ['title' => 'Bad Upload Tour']);
});

test('a package needs either a photo upload or an image path', function () {
    Storage::fake('uploads');

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/packages', [
        'title' => 'No Photo Tour',
        'duration' => '3 Days',
        'price_amount' => 1000,
        'rating' => 4,
        'description' => 'Missing an image entirely.',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors('image');
    $this->assertDatabaseMissing('travel_packages', ['title' => 'No Photo Tour']);
});

test('replacing a package photo deletes the superseded upload', function () {
    Storage::fake('uploads');

    $admin = User::factory()->create(['role' => 'admin']);

    Storage::disk('uploads')->put('packages/old-photo.jpg', 'old bytes');

    $package = TravelPackage::create([
        'title' => 'Replace Photo Tour',
        'duration' => '3 Days',
        'price' => '$500',
        'rating' => 5,
        'image' => 'uploads/packages/old-photo.jpg',
        'description' => 'Photo gets swapped.',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->put("/admin/packages/{$package->id}", [
        'title' => $package->title,
        'duration' => $package->duration,
        'price_amount' => 500,
        'rating' => 5,
        'image_file' => UploadedFile::fake()->image('replacement.jpg', 1200, 800),
        'description' => $package->description,
        'category' => $package->category,
        'status' => 'active',
    ]);

    $response->assertRedirect('/admin/packages');

    Storage::disk('uploads')->assertMissing('packages/old-photo.jpg');

    $newPath = $package->fresh()->image;

    expect($newPath)->toStartWith('uploads/packages/replacement-');
    Storage::disk('uploads')->assertExists(Str::after($newPath, 'uploads/'));
});

test('a package keeps its photo when the edit form uploads nothing', function () {
    Storage::fake('uploads');

    $admin = User::factory()->create(['role' => 'admin']);

    Storage::disk('uploads')->put('packages/kept-photo.jpg', 'kept bytes');

    $package = TravelPackage::create([
        'title' => 'Keep Photo Tour',
        'duration' => '3 Days',
        'price' => '$500',
        'rating' => 5,
        'image' => 'uploads/packages/kept-photo.jpg',
        'description' => 'Photo must survive an edit.',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->put("/admin/packages/{$package->id}", [
        'title' => 'Keep Photo Tour (Renamed)',
        'duration' => $package->duration,
        'price_amount' => 600,
        'rating' => 5,
        'description' => $package->description,
        'category' => $package->category,
        'status' => 'active',
    ])->assertRedirect('/admin/packages');

    expect($package->fresh()->image)->toBe('uploads/packages/kept-photo.jpg');
    Storage::disk('uploads')->assertExists('packages/kept-photo.jpg');
});

test('deleting a package removes the photo it uploaded', function () {
    Storage::fake('uploads');

    $admin = User::factory()->create(['role' => 'admin']);

    Storage::disk('uploads')->put('packages/doomed.jpg', 'doomed bytes');

    $package = TravelPackage::create([
        'title' => 'Doomed Photo Tour',
        'duration' => '3 Days',
        'price' => '$500',
        'rating' => 5,
        'image' => 'uploads/packages/doomed.jpg',
        'description' => 'Photo goes with it.',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->delete("/admin/packages/{$package->id}")
        ->assertRedirect('/admin/packages');

    Storage::disk('uploads')->assertMissing('packages/doomed.jpg');
});

test('package image storage only deletes photos it manages', function () {
    Storage::fake('uploads');

    Storage::disk('uploads')->put('packages/managed.jpg', 'managed bytes');
    Storage::disk('uploads')->put('seeded/legacy.jpg', 'seeded bytes');

    // Seeded assets, hand-typed paths and remote URLs must survive.
    PackageImageStorage::delete('seeded/legacy.jpg');
    PackageImageStorage::delete('newassets/2026-2027 DOMESTIC/boracay.jpg');
    PackageImageStorage::delete('https://cdn.example.com/photo.jpg');
    PackageImageStorage::delete(null);

    Storage::disk('uploads')->assertExists('seeded/legacy.jpg');

    PackageImageStorage::delete('uploads/packages/managed.jpg');

    Storage::disk('uploads')->assertMissing('packages/managed.jpg');
});

test('admin can toggle package featured status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Toggle Feature Tour',
        'duration' => '3 Days',
        'price' => '$500',
        'rating' => 5,
        'image' => 'images/toggle.jpg',
        'description' => 'Test toggle',
        'category' => 'short_haul',
        'status' => 'active',
        'is_featured' => false,
    ]);

    $response = $this->actingAs($admin)->post("/admin/packages/{$package->id}/toggle-featured");

    $response->assertStatus(302);
    expect($package->fresh()->is_featured)->toBeTrue();
});

test('admin can delete a travel package', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Delete Me Tour',
        'duration' => '2 Days',
        'price' => '$300',
        'rating' => 3,
        'image' => 'images/del.jpg',
        'description' => 'ToDelete',
        'category' => 'short_haul',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/packages/{$package->id}");

    $response->assertRedirect('/admin/packages');
    $this->assertDatabaseMissing('travel_packages', ['id' => $package->id]);
});
