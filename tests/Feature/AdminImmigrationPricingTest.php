<?php

use App\Models\ImmigrationCategory;
use App\Models\ImmigrationPricingTier;
use App\Models\ImmigrationRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view the immigration categories list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ImmigrationCategory::factory()->create(['name' => 'Tourist Visa Extension']);

    $response = $this->actingAs($admin)->get('/admin/immigration-categories');

    $response->assertStatus(200);
    $response->assertSee('Immigration Process Categories');
    $response->assertSee('Tourist Visa Extension');
});

test('guests are redirected away from the immigration admin screens', function () {
    $this->get('/admin/immigration-categories')->assertRedirect('/login');
    $this->get('/admin/immigration-pricing')->assertRedirect('/login');
});

test('clients cannot reach the immigration admin screens', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)->get('/admin/immigration-pricing')->assertRedirect('/login');
});

test('admin can create a category with requirements and process notes', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin/immigration-categories/create')->assertStatus(200);

    $response = $this->actingAs($admin)->post('/admin/immigration-categories', [
        'name' => 'Exit Clearance (ECC)',
        'description' => 'Clearance required before leaving the Philippines.',
        'icon' => 'plane-takeoff',
        'processing_time' => '1 day',
        'sort_order' => 4,
        'is_active' => '1',
        'requirements' => [
            ['label' => 'Fill up the Exit Clearance form', 'type' => 'requirement', 'needs_review' => 0],
            ['label' => 'Confirm the SSRN spelling', 'type' => 'note', 'needs_review' => 1],
            ['label' => '', 'type' => 'requirement', 'needs_review' => 0],
        ],
    ]);

    $response->assertRedirect('/admin/immigration-categories');

    $this->assertDatabaseHas('immigration_categories', [
        'slug' => 'exit-clearance-ecc',
        'name' => 'Exit Clearance (ECC)',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('immigration_requirements', [
        'label' => 'Fill up the Exit Clearance form',
        'type' => 'requirement',
        'needs_review' => false,
    ]);

    $this->assertDatabaseHas('immigration_requirements', [
        'label' => 'Confirm the SSRN spelling',
        'type' => 'note',
        'needs_review' => true,
    ]);

    // Blank rows submitted by the repeatable form are discarded
    expect(ImmigrationRequirement::count())->toBe(2);
});

test('admin can edit and update a category, replacing its requirements', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create(['name' => 'Old Category Name', 'slug' => 'old-category']);
    $stale = ImmigrationRequirement::factory()->for($category, 'category')->create(['label' => 'Stale requirement']);

    $this->actingAs($admin)->get("/admin/immigration-categories/{$category->id}/edit")->assertStatus(200);

    $response = $this->actingAs($admin)->put("/admin/immigration-categories/{$category->id}", [
        'name' => 'Re-Stamping',
        'slug' => 're-stamping',
        'icon' => 'stamp',
        'processing_time' => '7-10 working days',
        'sort_order' => 5,
        'is_active' => '1',
        'requirements' => [
            ['label' => 'Old and new passport', 'type' => 'requirement', 'needs_review' => 0],
        ],
    ]);

    $response->assertRedirect('/admin/immigration-categories');

    $this->assertDatabaseHas('immigration_categories', [
        'id' => $category->id,
        'name' => 'Re-Stamping',
        'slug' => 're-stamping',
    ]);

    $this->assertDatabaseHas('immigration_requirements', ['label' => 'Old and new passport']);
    $this->assertDatabaseMissing('immigration_requirements', ['id' => $stale->id]);
});

test('admin can toggle category status between enabled and disabled', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->post("/admin/immigration-categories/{$category->id}/toggle-status")->assertStatus(302);
    expect($category->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post("/admin/immigration-categories/{$category->id}/toggle-status");
    expect($category->fresh()->is_active)->toBeTrue();
});

test('deleting a category removes its price rows and requirements', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create();
    ImmigrationPricingTier::factory()->for($category, 'category')->create();
    ImmigrationRequirement::factory()->for($category, 'category')->create();

    $this->actingAs($admin)->delete("/admin/immigration-categories/{$category->id}")
        ->assertRedirect('/admin/immigration-categories');

    $this->assertDatabaseMissing('immigration_categories', ['id' => $category->id]);
    expect(ImmigrationPricingTier::count())->toBe(0);
    expect(ImmigrationRequirement::count())->toBe(0);
});

test('admin can view the price rows list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create(['name' => 'Tourist Visa Extension']);
    ImmigrationPricingTier::factory()->for($category, 'category')->create([
        'condition_notes' => 'Valid ACR I-Card',
        'price' => 3250,
    ]);

    $response = $this->actingAs($admin)->get('/admin/immigration-pricing');

    $response->assertStatus(200);
    $response->assertSee('Bureau of Immigration Price Rows');
    $response->assertSee('Valid ACR I-Card');
    $response->assertSee('3,250.00');
});

test('admin can create a price row', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create();

    $this->actingAs($admin)->get('/admin/immigration-pricing/create')->assertStatus(200);

    $response = $this->actingAs($admin)->post('/admin/immigration-pricing', [
        'immigration_category_id' => $category->id,
        'extension_label' => '1st Extension',
        'duration_label' => '29 days',
        'process_type' => 'regular',
        'payment_method' => 'cash',
        'condition_notes' => 'Visa waiver on arrival',
        'price' => '2930',
        'processing_time' => '7-10 working days',
        'sort_order' => 1,
        'is_active' => '1',
    ]);

    $response->assertRedirect('/admin/immigration-pricing');
    $this->assertDatabaseHas('immigration_pricing_tiers', [
        'immigration_category_id' => $category->id,
        'extension_label' => '1st Extension',
        'price' => 2930,
        'is_active' => true,
        'needs_review' => false,
    ]);
});

test('creating a price row requires a category and a numeric price', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/admin/immigration-pricing', [
            'process_type' => 'regular',
            'payment_method' => 'cash',
            'price' => 'not-a-number',
        ])
        ->assertSessionHasErrors(['immigration_category_id', 'price']);
});

test('admin can edit and update a price row', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create();
    $tier = ImmigrationPricingTier::factory()->for($category, 'category')->create(['price' => 2930]);

    $this->actingAs($admin)->get("/admin/immigration-pricing/{$tier->id}/edit")->assertStatus(200);

    $response = $this->actingAs($admin)->put("/admin/immigration-pricing/{$tier->id}", [
        'immigration_category_id' => $category->id,
        'extension_label' => '2nd Extension',
        'duration_label' => '2 months',
        'process_type' => 'express',
        'payment_method' => 'card',
        'condition_notes' => 'Valid ACR, visa expired',
        'price' => '6875.50',
        'processing_time' => '1 day',
        'is_active' => '1',
    ]);

    $response->assertRedirect('/admin/immigration-pricing');
    $this->assertDatabaseHas('immigration_pricing_tiers', [
        'id' => $tier->id,
        'extension_label' => '2nd Extension',
        'payment_method' => 'card',
        'price' => 6875.50,
    ]);
});

test('admin can toggle price row status between enabled and disabled', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tier = ImmigrationPricingTier::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->post("/admin/immigration-pricing/{$tier->id}/toggle-status")->assertStatus(302);
    expect($tier->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post("/admin/immigration-pricing/{$tier->id}/toggle-status");
    expect($tier->fresh()->is_active)->toBeTrue();
});

test('admin can confirm a flagged price row, publishing it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tier = ImmigrationPricingTier::factory()->needsReview()->create();

    $this->actingAs($admin)->post("/admin/immigration-pricing/{$tier->id}/confirm-review")->assertStatus(302);

    $tier->refresh();
    expect($tier->needs_review)->toBeFalse();
    expect($tier->is_active)->toBeTrue();
});

test('admin can filter price rows down to the ones needing review', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create();
    ImmigrationPricingTier::factory()->for($category, 'category')->create(['condition_notes' => 'Confirmed row']);
    ImmigrationPricingTier::factory()->for($category, 'category')->needsReview()->create(['condition_notes' => 'Flagged row']);

    $response = $this->actingAs($admin)->get('/admin/immigration-pricing?needs_review=1');

    $response->assertStatus(200);
    $response->assertSee('Flagged row');
    $response->assertDontSee('Confirmed row');
});

test('admin can delete a price row', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tier = ImmigrationPricingTier::factory()->create();

    $this->actingAs($admin)->delete("/admin/immigration-pricing/{$tier->id}")
        ->assertRedirect('/admin/immigration-pricing');

    $this->assertDatabaseMissing('immigration_pricing_tiers', ['id' => $tier->id]);
});

test('immigration mutations are written to the activity log', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = ImmigrationCategory::factory()->create();

    $this->actingAs($admin)->post('/admin/immigration-pricing', [
        'immigration_category_id' => $category->id,
        'process_type' => 'regular',
        'payment_method' => 'cash',
        'price' => '4000',
        'is_active' => '1',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'module' => 'Immigration Pricing',
        'action' => 'CREATE',
        'user_id' => $admin->id,
    ]);
});
