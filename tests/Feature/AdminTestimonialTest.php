<?php

use App\Models\Testimonial;
use App\Models\User;

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
        ])
        ->assertRedirect(route('admin.testimonials.index'));

    $testimonial = Testimonial::firstWhere('name', 'Maria Santos');

    expect($testimonial)->not->toBeNull()
        ->and($testimonial->rating)->toBe(5)
        ->and($testimonial->is_published)->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.testimonials.index'))
        ->assertSee('Maria Santos');
});
