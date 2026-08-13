<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = '/home/vovoco5/repositories/amegatravelandtour/storage/framework/maintenance.php')) {
    require $maintenance;
}

require '/home/vovoco5/repositories/amegatravelandtour/vendor/autoload.php';

$app = require_once '/home/vovoco5/repositories/amegatravelandtour/bootstrap/app.php';

$app->handleRequest(Request::capture());