<?php

use App\Models\Faq;
use App\Models\TravelPackage;
use App\Models\User;

/**
 * The public website's search-engine and sharing details, the FAQ and its admin page, the Privacy
 * Policy, Messenger, how to pay, and the trip search that carries its dates and travellers through.
 */
function seoPackage(array $overrides = []): TravelPackage
{
    return TravelPackage::create($overrides + [
        'title' => 'Boracay Getaway',
        'duration' => '4 Days',
        'price' => 'From ₱11,800',
        'rating' => 5,
        'image' => 'images/test.jpg',
        'description' => 'White sand and sunsets.',
        'category' => 'domestic',
        'status' => 'active',
    ]);
}

test('every page carries a title, a share preview and the tab icon; the home page has the business details', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('<title>AMEGA Travel and Tours Services | Visa, Immigration, Ticketing &amp; Tours in Angeles City, Pampanga</title>')
        ->toContain('<meta property="og:image" content="'.asset('images/og-image.jpg').'">')
        ->toContain('<link rel="icon" href="'.asset('favicon.ico').'"')
        ->toContain('"@type":"TravelAgency"')
        ->toContain('"addressLocality":"Angeles City"');
    expect(filesize(public_path('favicon.ico')))->toBeGreaterThan(0);

    $about = $this->get(route('about'))->assertOk()->getContent();
    expect($about)->toContain('<title>About AMEGA Travel and Tours')
        ->not->toContain('"@type":"TravelAgency"');
});

test('a package is shared with its own photo and description', function () {
    $package = seoPackage();

    $this->get(route('packages.show', $package))->assertOk()
        ->assertSee('<title>Boracay Getaway | AMEGA Travel and Tours</title>', false)
        ->assertSee('<meta property="og:description" content="White sand and sunsets.">', false);
});

test('the sitemap lists the public pages and every package', function () {
    $package = seoPackage();

    $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('privacy').'</loc>', false)
        ->assertSee('<loc>'.route('faq').'</loc>', false)
        ->assertSee('<loc>'.route('packages.show', $package).'</loc>', false);

    expect(file_get_contents(public_path('robots.txt')))->toContain('Sitemap: https://amegatravelandtour.com/sitemap.xml');
});

test('the privacy policy is published and linked from the footer and the forms', function () {
    $this->get(route('privacy'))->assertOk()->assertSee('Data Privacy Act of 2012')->assertSee('National Privacy Commission');

    $home = $this->get(route('home'))->assertOk()->getContent();
    expect(substr_count($home, 'href="'.route('privacy').'"'))->toBeGreaterThanOrEqual(3);
    $this->get(route('packages.show', seoPackage()))->assertSee('href="'.route('privacy').'"', false);
});

test('published questions show on the home page and the FAQ page; hidden ones do not', function () {
    Faq::query()->delete();
    Faq::create(['question' => 'Do you open on Sundays?', 'answer' => 'No, we are closed on Sundays.', 'sort_order' => 1, 'is_published' => true]);
    Faq::create(['question' => 'A draft question', 'answer' => 'Not ready.', 'sort_order' => 2, 'is_published' => false]);

    $this->get(route('home'))->assertSee('Do you open on Sundays?')->assertDontSee('A draft question');
    $this->get(route('faq'))->assertOk()->assertSee('Do you open on Sundays?')->assertSee('"@type":"FAQPage"', false);

    // No questions: the section is left out.
    Faq::query()->delete();
    $this->get(route('home'))->assertDontSee('Frequently Asked Questions');
});

test('staff with the FAQs page add, edit, hide and remove questions; others cannot', function () {
    $editor = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['faqs']]);
    $other = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['inquiries']]);

    $this->actingAs($other)->get(route('admin.faqs.index'))->assertRedirect();
    $this->actingAs($other)->post(route('admin.faqs.store'), ['question' => 'Q', 'answer' => 'A'])->assertRedirect();
    expect(Faq::where('question', 'Q')->exists())->toBeFalse();

    $this->actingAs($editor)->get(route('admin.faqs.index'))->assertOk()->assertSee('Add a question');
    $this->actingAs($editor)->post(route('admin.faqs.store'), ['question' => 'Can I pay in instalments?', 'answer' => 'Ask our staff.', 'is_published' => 1])
        ->assertRedirect(route('admin.faqs.index'));
    $faq = Faq::where('question', 'Can I pay in instalments?')->firstOrFail();
    expect($faq->is_published)->toBeTrue();

    $this->actingAs($editor)->put(route('admin.faqs.update', $faq), ['question' => 'Can I pay in instalments?', 'answer' => 'Yes, for tours.', 'sort_order' => 1])
        ->assertRedirect(route('admin.faqs.index'));
    expect($faq->fresh())->answer->toBe('Yes, for tours.')->is_published->toBeFalse();

    $this->actingAs($editor)->delete(route('admin.faqs.destroy', $faq))->assertRedirect(route('admin.faqs.index'));
    expect(Faq::find($faq->id))->toBeNull();
});

test('the contact section offers Messenger and says how to pay', function () {
    $this->get(route('contact'))->assertOk()
        ->assertSee('https://m.me/amegatravel', false)
        ->assertSee('How to Pay')
        ->assertSeeInOrder(['Cash', 'GCash', 'Bank transfer']);
});

test('the trip search carries its dates and travellers to the package booking form', function () {
    $package = seoPackage();
    $depart = now()->addDays(20)->toDateString();
    $return = now()->addDays(25)->toDateString();

    $this->get(route('home'))->assertSee('name="depart"', false)->assertSee('name="return"', false)->assertSee('name="pax"', false);

    $this->get(route('packages.index', ['depart' => $depart, 'return' => $return, 'pax' => 4]))->assertOk()
        ->assertSee('4 travellers')
        ->assertSee(e(route('packages.show', [$package, 'depart' => $depart, 'return' => $return, 'pax' => 4])), false);

    $this->get(route('packages.show', [$package, 'depart' => $depart, 'return' => $return, 'pax' => 4]))->assertOk()
        ->assertSee('name="travel_date" value="'.$depart.'"', false)
        ->assertSee('name="number_of_passengers" value="4"', false)
        ->assertSee('Return date: '.now()->addDays(25)->format('F j, Y'));

    // Nonsense is ignored: past dates, bad dates, too many travellers.
    $this->get(route('packages.show', [$package, 'depart' => '2020-01-01', 'pax' => 999]))->assertOk()
        ->assertSee('name="travel_date" value="'.now()->addDays(14)->format('Y-m-d').'"', false)
        ->assertSee('name="number_of_passengers" value="2"', false);
});

test('the trip search lists every country, the Philippines first, and finds that country\'s packages', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();
    $hero = Str::betweenFirst($html, 'name="search"', '</select>');
    expect($hero)->toContain('<option value="Philippines">Philippines</option>')
        ->toContain('<option value="Zimbabwe">Zimbabwe</option>')
        ->not->toContain('Local Destinations')
        ->not->toContain('value="boracay"')
        ->and(strpos($hero, 'Philippines'))->toBeLessThan(strpos($hero, 'Afghanistan'));

    seoPackage(['title' => 'Boracay Island Beach Getaway', 'category' => 'domestic']);
    seoPackage(['title' => 'Dubai & Abu Dhabi All-In', 'category' => 'short_haul', 'description' => 'Desert safari.']);

    $this->get(route('packages.index', ['search' => 'Philippines']))
        ->assertSee('Boracay Island Beach Getaway')->assertDontSee('Dubai &amp; Abu Dhabi All-In', false);
    $this->get(route('packages.index', ['search' => 'United Arab Emirates']))
        ->assertSee('Dubai &amp; Abu Dhabi All-In', false)->assertDontSee('Boracay Island Beach Getaway');

    // A country without a package offers a custom trip.
    $this->get(route('packages.index', ['search' => 'Iceland']))
        ->assertSee('No ready-made package for Iceland yet')->assertSee('Ask for a custom trip');
});
