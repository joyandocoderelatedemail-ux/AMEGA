<?php

use App\Models\Inquiry;
use App\Models\Service;
use App\Models\User;

test('a submitted inquiry returns the visitor to the form with a confirmation', function () {
    $response = $this->from(route('home'))->post(route('contact.submit'), [
        'account_category' => 'Individual',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com',
        'phone' => '+63 912 345 6789',
        'number_of_pax' => 3,
        'nationality' => 'Philippines',
        'message' => 'Planning a trip to Palawan in December.',
    ]);

    $response->assertRedirect(route('home').'#contact');

    expect(Inquiry::where('email', 'juan@example.com')->exists())->toBeTrue();

    $this->get(route('home'))
        ->assertSee('Thank you for your message! Our travel agents will get back to you shortly.');
});

test('a rejected inquiry shows why and keeps what the visitor typed', function () {
    $this->from(route('contact'))->post(route('contact.submit'), [
        'first_name' => 'Juan',
        'email' => 'not-an-email',
        'message' => '',
    ])->assertRedirect(route('contact').'#contact');

    $this->get(route('contact'))
        ->assertSee('Your inquiry was not sent')
        ->assertSee('value="Juan"', false);

    expect(Inquiry::count())->toBe(0);
});

test('an inquiry keeps the service category, number of pax and nationality', function () {
    $this->post(route('contact.submit'), [
        'account_category' => 'Airfare',
        'first_name' => 'Anna',
        'last_name' => 'Smith',
        'email' => 'anna@example.com',
        'number_of_pax' => 4,
        'nationality' => 'Australia',
        'message' => 'Manila to Sydney return.',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('inquiries', [
        'email' => 'anna@example.com',
        'service_requested' => 'Airfare',
        'number_of_pax' => 4,
        'nationality' => 'Australia',
    ]);
});

test('an inquiry needs a number of pax and a listed nationality', function () {
    $this->post(route('contact.submit'), [
        'first_name' => 'Anna',
        'email' => 'anna@example.com',
        'number_of_pax' => 0,
        'nationality' => 'Atlantis',
        'message' => 'Hello',
    ])->assertSessionHasErrors(['number_of_pax', 'nationality']);

    expect(Inquiry::count())->toBe(0);
});

test('the contact form offers every active core service and airfare as a category', function () {
    Service::create(['title' => 'Visa Assistance', 'short_description' => 'x', 'icon' => 'globe', 'is_active' => true]);
    Service::create(['title' => 'Hidden Service', 'short_description' => 'x', 'icon' => 'globe', 'is_active' => false]);

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('value="Visa Assistance"', false)
        ->assertSee('value="Airfare"', false)
        ->assertDontSee('Hidden Service')
        ->assertSee('name="number_of_pax"', false)
        ->assertSee('name="nationality"', false);
});

test('the Plan Your Trip button leads to the contact page', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('contact').'"', false)
        ->assertSee('Plan Your Trip');
});

test('the admin inquiries list shows the category, pax and nationality', function () {
    Inquiry::create([
        'name' => 'Anna Smith',
        'email' => 'anna@example.com',
        'number_of_pax' => 4,
        'nationality' => 'Australia',
        'service_requested' => 'Airfare',
        'message' => 'Manila to Sydney return.',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.inquiries.index'))
        ->assertOk()
        ->assertSee('Airfare')
        ->assertSee('4 pax')
        ->assertSee('Australia');
});
