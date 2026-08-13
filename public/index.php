<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine Laravel base directory (supports local dev, custom web roots, and cPanel production)
$basePath = file_exists(__DIR__.'/../vendor/autoload.php')
    ? __DIR__.'/..'
    : (file_exists('/home/vovoco5/repositories/amegatravelandtour/vendor/autoload.php')
        ? '/home/vovoco5/repositories/amegatravelandtour'
        : __DIR__.'/..');

// Determine if the application is under maintenance...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register The Auto Loader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
