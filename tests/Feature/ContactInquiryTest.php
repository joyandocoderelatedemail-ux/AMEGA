<?php

use App\Models\Inquiry;

test('a submitted inquiry returns the visitor to the form with a confirmation', function () {
    $response = $this->from(route('home'))->post(route('contact.submit'), [
        'account_category' => 'Individual',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com',
        'phone' => '+63 912 345 6789',
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
