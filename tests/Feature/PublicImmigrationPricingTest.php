<?php

use App\Models\ImmigrationCategory;
use App\Models\ImmigrationPricingTier;
use App\Models\ImmigrationRequirement;
use Database\Seeders\ImmigrationPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public pricing page renders active categories and their published rows', function () {
    $category = ImmigrationCategory::factory()->create([
        'name' => 'Tourist Visa Extension',
        'description' => 'Extension of stay for foreign nationals.',
    ]);

    ImmigrationPricingTier::factory()->for($category, 'category')->create([
        'extension_label' => '1st Extension',
        'duration_label' => '29 days',
        'condition_notes' => 'Visa waiver on arrival',
        'price' => 2930,
    ]);

    ImmigrationRequirement::factory()->for($category, 'category')->create([
        'label' => 'Photocopy of passport biopage',
    ]);

    $response = $this->get('/immigration-pricing');

    $response->assertStatus(200);
    $response->assertSee('Tourist Visa Extension');
    $response->assertSee('Extension of stay for foreign nationals.');
    $response->assertSee('1st Extension');
    $response->assertSee('Visa waiver on arrival');
    $response->assertSee('2,930');
    $response->assertSee('Photocopy of passport biopage');
});

test('the public pricing page hides rows flagged as needing review', function () {
    $category = ImmigrationCategory::factory()->create();

    ImmigrationPricingTier::factory()->for($category, 'category')->create(['condition_notes' => 'Confirmed row']);
    ImmigrationPricingTier::factory()->for($category, 'category')->needsReview()->create(['condition_notes' => 'Unconfirmed row']);

    $response = $this->get('/immigration-pricing');

    $response->assertSee('Confirmed row');
    $response->assertDontSee('Unconfirmed row');
});

test('the public pricing page hides disabled rows', function () {
    $category = ImmigrationCategory::factory()->create();

    ImmigrationPricingTier::factory()->for($category, 'category')->create(['condition_notes' => 'Live row']);
    ImmigrationPricingTier::factory()->for($category, 'category')->inactive()->create(['condition_notes' => 'Retired row']);

    $response = $this->get('/immigration-pricing');

    $response->assertSee('Live row');
    $response->assertDontSee('Retired row');
});

test('the public pricing page hides process notes flagged as needing review', function () {
    $category = ImmigrationCategory::factory()->create();

    ImmigrationRequirement::factory()->for($category, 'category')->note()->create(['label' => 'Published process note']);
    ImmigrationRequirement::factory()->for($category, 'category')->note()->needsReview()->create(['label' => 'Unconfirmed SSRN note']);

    $response = $this->get('/immigration-pricing');

    $response->assertSee('Published process note');
    $response->assertDontSee('Unconfirmed SSRN note');
});

test('the public pricing page hides disabled categories entirely', function () {
    ImmigrationCategory::factory()->inactive()->create(['name' => 'Hidden Category']);

    $this->get('/immigration-pricing')->assertDontSee('Hidden Category');
});

test('the services page links to the immigration pricing guide', function () {
    $this->get('/services')
        ->assertStatus(200)
        ->assertSee(route('immigration-pricing'));
});

test('the seeded price list publishes confirmed rows and withholds flagged ones', function () {
    $this->seed(ImmigrationPricingSeeder::class);

    expect(ImmigrationCategory::count())->toBe(5);

    // The four transcription-ambiguous figures from the source sheet stay unpublished
    expect(ImmigrationPricingTier::where('needs_review', true)->count())->toBe(4);
    expect(ImmigrationPricingTier::where('needs_review', true)->where('is_active', true)->count())->toBe(0);
    // Two notes remain flagged: the Indian passport surcharge and the CRTV "1410" figure.
    // The SSRN note was confirmed against the printed Client Information Sheet.
    expect(ImmigrationRequirement::where('needs_review', true)->count())->toBe(2);

    $response = $this->get('/immigration-pricing');

    $response->assertStatus(200);
    $response->assertSee('Tourist Visa Extension');
    $response->assertSee('Exit Clearance (ECC)');
    $response->assertSee('Fill up the Exit Clearance form');
    $response->assertSee('2,930');

    // The unclear Re-Stamping figure and the flagged notes never reach the public page
    $response->assertDontSee('3,800');
    $response->assertDontSee('PENDING CONFIRMATION');
});

test('the pricing seeder is idempotent', function () {
    $this->seed(ImmigrationPricingSeeder::class);
    $tierCount = ImmigrationPricingTier::count();
    $requirementCount = ImmigrationRequirement::count();

    $this->seed(ImmigrationPricingSeeder::class);

    expect(ImmigrationCategory::count())->toBe(5);
    expect(ImmigrationPricingTier::count())->toBe($tierCount);
    expect(ImmigrationRequirement::count())->toBe($requirementCount);
});
