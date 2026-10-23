<?php

use App\Models\User;
use App\Models\VisaApplication;

test('the visa counter uses the ticketing theme with its dark mode switch', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.dashboard'))
        ->assertOk()
        ->assertSee('<body class="portal-minimal', false)
        ->assertSee('id="theme-toggle"', false)
        ->assertSee("localStorage.getItem('amega_ticketing_theme')", false);
});

test('the dashboard counts released and cancelled files apart, and lists recent files with their stage', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = fn (string $reference, string $status, string $speed = 'regular') => VisaApplication::create([
        'reference' => $reference, 'client_name' => 'Client '.$reference, 'client_phone' => '09170000000',
        'service_type' => 'visit_visa', 'destination_country' => 'Japan', 'status' => $status,
        'processing_speed' => $speed, 'created_by' => $officer->id,
    ]);
    $file('VSA-1', 'released');
    $file('VSA-2', 'cancelled');
    $file('VSA-3', 'requirements', 'rush');

    $this->actingAs($officer)->get(route('visa.dashboard'))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats) => $stats['released'] === 1 && $stats['cancelled'] === 1 && $stats['openRush'] === 1)
        ->assertSee('Released')
        ->assertSee('cancelled')
        ->assertSee('VSA-3')
        ->assertSee('Documents')
        ->assertSee('Japan')
        ->assertSee('Details');
});

test('the Services tab opens a menu of the three services, each opening its active files', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $html = $this->actingAs($officer)->get(route('visa.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('aria-haspopup="menu"')->toContain('<span>Services</span>')
        ->not->toContain('New Counter File')->not->toContain('<span>File Directory</span>');
    foreach (['visit_visa' => 'Non-Immigrant', 'e_visa' => 'E-Visa', 'passporting' => 'Passporting'] as $service => $label) {
        expect($html)->toContain('href="'.e(route('visa.services', $service)).'" role="menuitem"')->toContain($label);
    }
});

test('a file opened for one service shows only that service, without the picker', function (string $service, string $title) {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => $service]))
        ->assertOk()
        ->assertSee("New {$title} File")
        ->assertSee('<input type="hidden" name="service_type" value="'.$service.'">', false)
        ->assertDontSee('type="radio" name="service_type"', false)
        ->assertDontSee('Three services share this counter.');
})->with([
    ['e_visa', 'e-Visa'],
    ['passporting', 'Passporting'],
]);

test('a file opened without a service still offers the service picker', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    foreach ([[], ['service_type' => 'nonsense']] as $query) {
        $this->actingAs($officer)->get(route('visa.applications.create', $query))
            ->assertOk()
            ->assertSee('New Counter File')
            ->assertSee('type="radio" name="service_type"', false);
    }
});

test('a new file opens without payment fields, which stay on a saved file', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    // A new file carries the quote behind the scenes but takes no payment, insurance or e-Travel at intake.
    foreach (['visit_visa', 'e_visa', 'passporting'] as $service) {
        $html = $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => $service, 'country' => 'Canada']))->assertOk()->getContent();
        expect($html)->toContain('name="service_fee"')
            ->and($html)->not->toContain('name="amount_paid"')
            ->and($html)->not->toContain('name="payment_type"')
            ->and($html)->not->toContain('name="processing_speed"')
            ->and($html)->not->toContain('name="etravel_reference"');
    }

    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Canada']))
        ->assertSee('Initial Assessment')->assertSee('Estimated price')->assertSee('Checklist');

    $file = VisaApplication::create(['reference' => 'VSA-9', 'client_name' => 'Ana', 'service_type' => 'e_visa', 'status' => 'pending', 'created_by' => $officer->id]);
    $this->actingAs($officer)->get(route('visa.applications.edit', $file))->assertOk()
        ->assertSee('name="amount_paid"', false)->assertSee('name="payment_type"', false);

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()->assertSee('id="fees"', false);
});

test('new files start from a service, not from the dashboard search or the empty list', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.dashboard'))->assertOk()
        ->assertDontSee('Open a new counter file')
        ->assertDontSee('Open the first file')
        ->assertSee('Start one from the');
});
