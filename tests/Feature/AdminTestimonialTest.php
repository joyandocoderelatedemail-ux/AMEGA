<?php

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('the testimonials page shows the add form', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.testimonials.index'))
        ->assertOk()
        ->assertSee('Add a testimonial')
        ->assertSee(route('admin.testimonials.store'), false);
});

test('an admin can add a testimonial', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.testimonials.store'), [
            'name' => 'Maria Santos',
            'location' => 'Makati City',
            'comment' => 'Smooth visa processing, very helpful staff.',
            'rating' => 5,
            'reviewed_on' => '2026-03-14',
        ])
        ->assertRedirect(route('admin.testimonials.index'));

    $testimonial = Testimonial::firstWhere('name', 'Maria Santos');

    expect($testimonial)->not->toBeNull()
        ->and($testimonial->rating)->toBe(5)
        ->and($testimonial->is_published)->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.testimonials.index'))
        ->assertSee('Maria Santos')
        ->assertSee('Mar 14, 2026');

    $this->get(route('testimonials'))->assertSee('March 14, 2026');
});

test('a testimonial can carry a client photo that shows on the website', function () {
    Storage::fake('uploads');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.testimonials.store'), [
            'name' => 'Jose Reyes',
            'location' => 'Cebu City',
            'comment' => 'Great tour package.',
            'rating' => 4,
            'reviewed_on' => now()->toDateString(),
            'photo' => UploadedFile::fake()->image('jose.jpg', 300, 300),
        ])
        ->assertRedirect(route('admin.testimonials.index'))
        ->assertSessionHasNoErrors();

    $testimonial = Testimonial::firstWhere('name', 'Jose Reyes');

    expect($testimonial->avatar)->toStartWith('uploads/testimonials/');
    Storage::disk('uploads')->assertExists(Str::after($testimonial->avatar, 'uploads/'));

    $this->get(route('testimonials'))->assertSee($testimonial->avatar, false);

    $this->actingAs($admin)->delete(route('admin.testimonials.destroy', $testimonial));
    Storage::disk('uploads')->assertMissing(Str::after($testimonial->avatar, 'uploads/'));
});

test('a testimonial photo must be an image', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.testimonials.store'), [
            'name' => 'Ana Cruz',
            'location' => 'Davao City',
            'comment' => 'Recommended.',
            'rating' => 5,
            'reviewed_on' => now()->toDateString(),
            'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('photo');

    expect(Testimonial::where('name', 'Ana Cruz')->exists())->toBeFalse();
});

test('a review date in the future is refused', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.testimonials.store'), [
            'name' => 'Ben Lim',
            'location' => 'Quezon City',
            'comment' => 'Fast and friendly.',
            'rating' => 5,
            'reviewed_on' => now()->addDays(3)->toDateString(),
        ])
        ->assertSessionHasErrors('reviewed_on');
});

test('only the name and the date are required', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.testimonials.store'), [])
        ->assertSessionHasErrors(['name', 'reviewed_on'])
        ->assertSessionDoesntHaveErrors(['location', 'comment', 'rating']);

    $this->actingAs($admin)
        ->post(route('admin.testimonials.store'), [
            'name' => 'Lito Garcia',
            'reviewed_on' => '2026-05-02',
            'location' => '',
            'comment' => '',
            'rating' => '',
        ])
        ->assertRedirect(route('admin.testimonials.index'))
        ->assertSessionHasNoErrors();

    $testimonial = Testimonial::firstWhere('name', 'Lito Garcia');

    expect($testimonial->location)->toBeNull()
        ->and($testimonial->comment)->toBeNull()
        ->and($testimonial->rating)->toBeNull();

    $this->actingAs($admin)->get(route('admin.testimonials.index'))->assertOk()->assertSee('Lito Garcia');
    $this->get(route('testimonials'))->assertOk()->assertSee('Lito Garcia')->assertSee('May 2, 2026');
    $this->get(route('home'))->assertOk()->assertSee('Lito Garcia');
});
