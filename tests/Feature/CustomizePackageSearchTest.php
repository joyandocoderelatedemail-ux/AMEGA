<?php

test('the trip search offers a customized package that lands on the contact form filled in', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('Customize My Package')->assertSee('formaction="'.route('contact').'#contact"', false);

    $this->get(route('contact', ['custom' => 1, 'search' => 'Japan', 'depart' => now()->addMonth()->format('Y-m-d'), 'pax' => 4]))
        ->assertOk()
        ->assertSee('I would like a customized travel package.')
        ->assertSee('Destination: Japan')
        ->assertSee('Travelers: 4')
        ->assertSee('<option value="Customized Package" selected>', false);
});

test('the contact form is untouched without the customize flag', function () {
    $this->get(route('contact'))->assertOk()->assertDontSee('I would like a customized travel package.');
});
