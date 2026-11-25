<?php

use App\Models\SrrvApplication;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Support\Str;

/**
 * The SRRV and Visa Assistance dashboards show their services as cards, like the Immigration
 * Counter: each with what is open on it, opening that service's list.
 */
test('the SRRV dashboard has a card for each service, counting its open files', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $file = fn (string $status) => SrrvApplication::withoutEvents(fn () => SrrvApplication::withoutGlobalScopes()->forceCreate([
        'reference' => 'SRRV-'.uniqid(), 'service_type' => 'renewal_application', 'visa_class' => 'courtesy', 'retiree_name' => 'Ana Cruz',
        'status' => $status, 'investment_amount' => 1500, 'created_by' => $officer->id,
    ]));
    $file('pending');
    $file('released');

    $html = $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->getContent();

    foreach (['conversion', 'termination', 'restamping'] as $key) {
        expect($html)->toContain('href="'.route('srrv.services', $key).'" class="group bg-white');
    }
    // SRRV Classic and SRRV Courtesy open a choice of New Application or their ID Renewal, each opening its own list.
    foreach (['classic', 'courtesy', 'renewal_classic', 'renewal_courtesy'] as $key) {
        expect($html)->toContain('href="'.route('srrv.services', $key).'" role="menuitem"');
    }
    expect($html)->toContain('SRRV Re-stamping')->toContain('Courtesy ID Renewal');

    // One courtesy file is open; the released one is not counted.
    preg_match('/>SRRV Courtesy<\/span>\s*<span[^>]*>\s*<span[^>]*>(\d+)<\/span> open/', $html, $match);
    expect($match[1] ?? null)->toBe('1');

    // Recent Retiree Files shows where each file stands, as the service lists do: one sent to the admin is waiting.
    $sent = $file('agreement');
    $sent->forceFill(['approval_status' => 'pending'])->save();
    $recent = Str::after($this->actingAs($officer)->get(route('srrv.dashboard'))->getContent(), 'Recent Retiree Files');
    expect($recent)->toContain('Waiting for Admin Approval');
});

test('the Visa Assistance dashboard has the service cards instead of the side list', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $html = $this->actingAs($officer)->get(route('visa.dashboard'))->assertOk()->getContent();

    foreach (['visit_visa', 'e_visa', 'passporting'] as $key) {
        expect($html)->toContain('href="'.route('visa.services', $key).'" class="group bg-white');
    }
    expect($html)->not->toContain('Open a file straight into a service.')
        ->and(VisaApplication::count())->toBe(0);
});

test('the Immigration dashboard keeps its service cards', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.immigration.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('aria-label="Services"')->toContain('class="group bg-white rounded-xl border border-slate-200 shadow-sm p-4');
});

test('the desk dashboards have no summary cards above the services', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    expect($this->actingAs($admin)->get(route('admin.immigration.dashboard'))->getContent())->not->toContain('aria-label="Totals"');
    $this->actingAs($admin)->get(route('visa.dashboard'))->assertOk()->assertDontSee('Rush Files')->assertDontSee('Open Files');
    $this->actingAs($admin)->get(route('srrv.dashboard'))->assertOk()->assertDontSee('Awaiting Oath')->assertDontSee('Renewals Open');
});

test('no desk has a Services tab, and the dashboard tab stays marked inside a service', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $marked = fn (string $html, string $label) => (bool) preg_match('/aria-current="page"[^>]*>\s*<i data-lucide="[^"]+"[^>]*><\/i>\s*<span>'.preg_quote($label, '/').'<\/span>/', $html)
        || (bool) preg_match('/border-navy-700 text-navy-700[^"]*"[^>]*>\s*<i data-lucide="[^"]+"[^>]*><\/i>\s*<span>'.preg_quote($label, '/').'<\/span>/', $html);

    foreach ([
        [route('srrv.services', 'classic'), 'Desk Dashboard'],
        [route('visa.services', 'e_visa'), 'Counter Dashboard'],
        [route('admin.client-sheets.applications', ['service' => 'ecc']), 'Counter'],
    ] as [$url, $tab]) {
        $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
        expect($html)->not->toContain('<span>Services</span>')
            ->and($marked($html, $tab))->toBeTrue("{$tab} is not marked on {$url}");
    }
});
