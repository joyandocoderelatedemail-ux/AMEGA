<?php

use App\Models\ImmigrationCategory;
use App\Models\ImmigrationPricingTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('guests are redirected away from the immigration admin screens', function () {
    $this->get('/admin/immigration-pricing')->assertRedirect('/login');
});

test('the public price list and the Categories page are gone; the extension rates stay for the ledger', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->get('/immigration-pricing')->assertNotFound();
    $this->actingAs($admin)->get('/admin/immigration-categories')->assertNotFound();
    expect(Route::has('immigration-pricing'))->toBeFalse()
        ->and(Route::has('admin.immigration-categories.index'))->toBeFalse();

    $this->get(route('services'))->assertOk()->assertDontSee('/immigration-pricing', false);
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('immigration-pricing');
    $this->actingAs($admin)->get(route('admin.immigration-pricing.index'))->assertOk()->assertSee('Extension Rates');
});

test('clients cannot reach the immigration admin screens', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)->get('/admin/immigration-pricing')->assertRedirect(route('admin.login'));
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
    $response->assertSee('Bureau of Immigration Rates');
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

test('the pricing rows are grouped by category tabs, then by tier', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $extension = ImmigrationCategory::create(['name' => 'Tourist Visa Extension Test', 'slug' => 'tve-test', 'sort_order' => 90, 'is_active' => true]);
    $crtv = ImmigrationCategory::create(['name' => 'CRTV Test', 'slug' => 'crtv-test', 'sort_order' => 91, 'is_active' => true]);
    $row = fn (array $data) => ImmigrationPricingTier::create($data + ['process_type' => 'regular', 'payment_method' => 'cash', 'price' => 1000, 'is_active' => true, 'needs_review' => false, 'sort_order' => 1]);
    $row(['immigration_category_id' => $extension->id, 'extension_label' => '1st Extension', 'duration_label' => '29 days']);
    $row(['immigration_category_id' => $extension->id, 'extension_label' => '2nd Extension', 'duration_label' => '2 months', 'payment_method' => 'card']);
    $row(['immigration_category_id' => $crtv->id, 'extension_label' => null, 'duration_label' => '1 year']);

    $html = $this->actingAs($admin)->get(route('admin.immigration-pricing.index'))->assertOk()
        ->assertSee('role="tablist"', false)->assertSee('Tourist Visa Extension Test')->assertSee('CRTV Test')
        ->assertSee('1st Extension')->assertSee('2nd Extension')->assertSee('Other rates')
        // A category with several tiers shows one at a time, picked from a dropdown.
        ->assertSee('aria-haspopup="listbox"', false)->assertSee('role="option"', false)
        ->getContent();
    expect($html)->not->toContain('<option value="">All Categories</option>');

    // The category in the address opens its tab; payment method still filters the rows.
    $this->actingAs($admin)->get(route('admin.immigration-pricing.index', ['category' => $crtv->id]))->assertOk()
        ->assertSee('tab: '.$crtv->id, false);
    $this->actingAs($admin)->get(route('admin.immigration-pricing.index', ['payment_method' => 'card']))->assertOk()
        ->assertSee('2nd Extension')->assertDontSee('29 days');
});
