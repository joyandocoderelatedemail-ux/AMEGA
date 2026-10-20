<?php

use App\Models\Service;

test('the About section names the associations AMEGA is a member of, after the accreditations', function () {
    $html = $this->get('/')->assertOk()->assertSee('Member of')
        ->assertSeeInOrder(['Fully Licensed &amp; Regulatory Compliant', 'Member of', 'Alliance of Travel and Tour Agencies of Pampanga', 'Philippine Travel Agencies Association', 'Hotels &amp; Restaurants Association in Pampanga Inc.'], false)
        ->getContent();

    foreach (['attap.png', 'ptaa.png', 'harp.jpg'] as $logo) {
        expect($html)->toContain('images/logoapproved/'.$logo)
            ->and(file_exists(public_path('images/logoapproved/'.$logo)))->toBeTrue();
    }
});

test('the general inquiry card carries the marketing email, both mobiles, the website and Facebook', function () {
    $this->get('/')->assertOk()
        ->assertSee('(+63) 991 103 2928')->assertSee('(+63) 949 990 0663')
        ->assertSee('mailto:marketing@amegatravelandtour.com', false)
        ->assertSee('https://www.amegatravelandtour.com', false)
        ->assertSee('www.facebook.com/amegatravel')
        ->assertDontSee('aurora.amega@gmail.com');
});

test('the immigration services list drops exit clearances, 9G working visa and application for correction', function () {
    $service = Service::create([
        'title' => 'Immigration Services', 'short_description' => 'BI processing', 'icon' => 'shield-check', 'order' => 3,
        'full_description' => "Visa Extensions\nExit Clearances\n9G Working Visa\nMissionary Visa\nApplication for Correction\nVisa Downgrading",
    ]);

    (require database_path('migrations/2026_10_20_100000_trim_immigration_services_list.php'))->up();

    expect($service->fresh()->full_description)->toBe("Visa Extensions\nMissionary Visa\nVisa Downgrading");
    $this->get('/')->assertOk()->assertSee('Visa Downgrading')->assertDontSee('Exit Clearances')->assertDontSee('9G Working Visa')->assertDontSee('Application for Correction');
});
