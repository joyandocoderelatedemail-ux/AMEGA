<?php

test('plain http requests are redirected to https in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://localhost/contact?ref=nav')
        ->assertStatus(301)
        ->assertRedirect('https://localhost/contact?ref=nav');
});

test('https requests are served normally in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('https://localhost/contact')->assertOk();
});

test('plain http is left alone outside production', function () {
    $this->get('http://localhost/contact')->assertOk();
});
