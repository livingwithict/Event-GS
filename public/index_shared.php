<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Shared hosting setup:
//   Document root = /home2/ictechco/events.ictfoundation.org.np/
//   Laravel app   = /home2/ictechco/event-app/
$appPath = dirname(__DIR__).'/event-app';

// Show errors only if vendor is missing (helps debug initial setup)
if (! file_exists($appPath.'/vendor/autoload.php')) {
    header('Content-Type: text/plain');
    echo "Laravel vendor directory not found.\n\n";
    echo "Expected at: $appPath/vendor/\n\n";
    echo "Run:\n  cd $appPath\n  composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-intl\n";
    exit(1);
}

// Maintenance mode
if (file_exists($appPath.'/storage/framework/maintenance.php')) {
    require $appPath.'/storage/framework/maintenance.php';
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// Override public_path to point to the actual document root
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
