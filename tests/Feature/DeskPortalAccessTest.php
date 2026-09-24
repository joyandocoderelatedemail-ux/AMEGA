<?php

use App\Models\SrrvPricingTier;
use App\Models\User;
use App\Models\VisaPricingTier;
use Database\Seeders\SrrvPricingSeeder;
use Database\Seeders\VisaPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Guest access
// ---------------------------------------------------------------------------

test('guests are redirected from the visa assistance counter to login', function () {
    $this->get('/visa-assistance')->assertRedirect('/login');
});

test('guests are redirected from the SRRV desk to login', function () {
    $this->get('/srrv')->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// Dedicated desk officers
// ---------------------------------------------------------------------------

test('visa assistance officer reaches their own counter dashboard', function () {
    $officer = User::factory()->create([
        'name' => 'Ana Reyes',
        'role' => 'visa_assistance',
    ]);

    $response = $this->actingAs($officer)->get('/visa-assistance');

    $response->assertStatus(200);
    $response->assertSee('Visa Assistance Counter');
    $response->assertSee('Ana Reyes');
    $response->assertSee('Visit Visa');
    $response->assertSee('e-Visa');
    $response->assertSee('Passporting');
});

test('srrv officer reaches their own desk dashboard', function () {
    $officer = User::factory()->create([
        'name' => 'Ben Cruz',
        'role' => 'srrv',
    ]);

    $response = $this->actingAs($officer)->get('/srrv');

    $response->assertStatus(200);
    $response->assertSee('SRRV Desk');
    $response->assertSee('Ben Cruz');
    $response->assertSee('Renewal Application');
    $response->assertSee('Annual Renewal');
    $response->assertSee('Re-stamping');
});

test('visa assistance officer is redirected to their counter after login', function () {
    $officer = User::factory()->create([
        'role' => 'visa_assistance',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => $officer->email,
        'password' => 'password123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('visa.dashboard'));
    expect(Auth::user()->isVisaAssistance())->toBeTrue();
    expect(Auth::user()->isStaff())->toBeTrue();
});

test('srrv officer is redirected to their desk after login', function () {
    $officer = User::factory()->create([
        'role' => 'srrv',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => $officer->email,
        'password' => 'password123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('srrv.dashboard'));
    expect(Auth::user()->isSrrv())->toBeTrue();
    expect(Auth::user()->isStaff())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Cross-desk isolation
// ---------------------------------------------------------------------------

test('visa assistance officer cannot reach the SRRV desk', function () {
    $officer = User::factory()->create([
        'role' => 'visa_assistance',
    ]);

    $response = $this->actingAs($officer)->get('/srrv');

    $response->assertRedirect(route('visa.dashboard'));
    $response->assertSessionHas('error');
});

test('srrv officer cannot reach the visa assistance counter', function () {
    $officer = User::factory()->create([
        'role' => 'srrv',
    ]);

    $response = $this->actingAs($officer)->get('/visa-assistance');

    $response->assertRedirect(route('srrv.dashboard'));
    $response->assertSessionHas('error');
});

test('visa assistance officer cannot reach the main admin dashboard', function () {
    $officer = User::factory()->create([
        'role' => 'visa_assistance',
    ]);

    $response = $this->actingAs($officer)->get('/admin/dashboard');

    $response->assertRedirect(route('visa.dashboard'));
    $response->assertSessionHas('error');
});

test('srrv officer cannot reach the main admin dashboard', function () {
    $officer = User::factory()->create([
        'role' => 'srrv',
    ]);

    $response = $this->actingAs($officer)->get('/admin/dashboard');

    $response->assertRedirect(route('srrv.dashboard'));
    $response->assertSessionHas('error');
});

test('clients cannot access either new desk', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)->get('/visa-assistance')->assertRedirect(route('home'));
    $this->actingAs($client)->get('/srrv')->assertRedirect(route('home'));
});

// ---------------------------------------------------------------------------
// Admin access
// ---------------------------------------------------------------------------

test('admin can access both new desks', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/visa-assistance')
        ->assertStatus(200)
        ->assertSee('Visa Assistance Counter');

    $this->actingAs($admin)->get('/srrv')
        ->assertStatus(200)
        ->assertSee('SRRV Desk');
});

test('admin sees both new desk links in the admin sidebar', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee(route('visa.dashboard'));
    $response->assertSee(route('srrv.dashboard'));
});

// ---------------------------------------------------------------------------
// Agents granted the new modules
// ---------------------------------------------------------------------------

test('agent with only visa assistance permission becomes a dedicated desk agent', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['visa_assistance'],
    ]);

    expect($agent->isVisaAssistanceAgent())->toBeTrue();
    expect($agent->isVisaAssistanceStaff())->toBeTrue();
    expect($agent->hasAdminAccess())->toBeFalse();
    expect($agent->staffHomeRoute())->toBe('visa.dashboard');

    $this->actingAs($agent)->get('/visa-assistance')
        ->assertStatus(200)
        ->assertSee('Visa Assistance Counter');

    $this->actingAs($agent)->get('/admin/dashboard')
        ->assertRedirect(route('visa.dashboard'));
});

test('agent with only srrv permission becomes a dedicated desk agent', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['srrv'],
    ]);

    expect($agent->isSrrvAgent())->toBeTrue();
    expect($agent->isSrrvStaff())->toBeTrue();
    expect($agent->hasAdminAccess())->toBeFalse();
    expect($agent->staffHomeRoute())->toBe('srrv.dashboard');

    $this->actingAs($agent)->get('/srrv')
        ->assertStatus(200)
        ->assertSee('SRRV Desk');

    $this->actingAs($agent)->get('/admin/dashboard')
        ->assertRedirect(route('srrv.dashboard'));
});

test('agent granted the new modules alongside admin pages keeps admin access', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['dashboard', 'bookings', 'visa_assistance', 'srrv'],
    ]);

    expect($agent->isVisaAssistanceAgent())->toBeFalse();
    expect($agent->isSrrvAgent())->toBeFalse();
    expect($agent->hasAdminAccess())->toBeTrue();
    expect($agent->staffHomeRoute())->toBe('admin.dashboard');

    $this->actingAs($agent)->get('/admin/dashboard')->assertStatus(200);
    $this->actingAs($agent)->get('/visa-assistance')->assertStatus(200);
    $this->actingAs($agent)->get('/srrv')->assertStatus(200);
});

test('agent without the new modules cannot reach the new desks', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['dashboard', 'bookings'],
    ]);

    $this->actingAs($agent)->get('/visa-assistance')
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs($agent)->get('/srrv')
        ->assertRedirect(route('admin.dashboard'));
});

// ---------------------------------------------------------------------------
// Pricing tables
// ---------------------------------------------------------------------------

test('only the flowchart-confirmed fees are published', function () {
    $this->seed(VisaPricingSeeder::class);
    $this->seed(SrrvPricingSeeder::class);

    // The rush surcharge is the only visa figure the source flowchart gives.
    $publishedVisa = VisaPricingTier::published()->get();
    expect($publishedVisa)->toHaveCount(1);
    expect($publishedVisa->first()->label)->toContain('Rush');
    expect((float) $publishedVisa->first()->amount)->toBe(5000.0);

    // Both renewal fees are given, one per visa class.
    $publishedSrrv = SrrvPricingTier::published()->orderBy('visa_class')->get();
    expect($publishedSrrv)->toHaveCount(2);
    expect((float) $publishedSrrv->firstWhere('visa_class', 'classic')->amount)->toBe(360.0);
    expect((float) $publishedSrrv->firstWhere('visa_class', 'courtesy')->amount)->toBe(10.0);
});

test('the immigration counter opens in place like the other desks', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

    // Immigration used to carry `'external' => true`, which rendered
    // target="_blank" and sent staff off into a second browser tab while
    // Ticketing, Visa and SRRV navigated in place.
    expect($html)->toContain('Immigration Counter');

    // Isolate the immigration link and confirm it is a normal in-place link.
    preg_match('/<a[^>]*admin\/immigration"[^>]*>/', $html, $m);
    expect($m)->not->toBeEmpty();
    expect($m[0])->not->toContain('target="_blank"');

    // The only new-tab links in the admin chrome should be the public website.
    $newTabLinks = preg_match_all('/<a[^>]*target="_blank"[^>]*>/', $html, $all);
    foreach ($all[0] as $link) {
        expect($link)->not->toContain('/admin/immigration');
    }
});
