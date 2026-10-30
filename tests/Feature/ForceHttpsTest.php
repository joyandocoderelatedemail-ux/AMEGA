<?php

use App\Http\Middleware\ForceHttps;
use Illuminate\Http\Request;

/**
 * Plain http is sent to the same address over https in production, without repeating the folder the
 * app sits in (the local XAMPP copy lives under /htdocs2/amegatravelandtour/public).
 */
test('the https redirect keeps the address as it is, folder included once', function () {
    app()->detectEnvironment(fn () => 'production');
    $request = Request::create('http://localhost/htdocs2/amegatravelandtour/public/cashier?x=1', 'GET', [], [], [], [
        'SCRIPT_NAME' => '/htdocs2/amegatravelandtour/public/index.php',
        'SCRIPT_FILENAME' => base_path('public/index.php'),
    ]);

    $response = (new ForceHttps)->handle($request, fn () => response('next'));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://localhost/htdocs2/amegatravelandtour/public/cashier?x=1');
});

test('an https request, or one outside production, passes straight through', function () {
    $response = (new ForceHttps)->handle(Request::create('http://localhost/cashier'), fn () => response('next'));
    expect($response->getContent())->toBe('next');
});
