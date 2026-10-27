<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

error_log('[AQUA-TARA] Entry point reached');
error_log('[AQUA-TARA] REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? 'missing'));

// Prepare writable storage directory in serverless environment (/tmp)
$storagePath = '/tmp/storage';
if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
    foreach ([
        $storagePath . '/framework/views',
        $storagePath . '/framework/cache/data',
        $storagePath . '/framework/sessions',
        $storagePath . '/logs',
        $storagePath . '/app/public',
    ] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
    $app->useStoragePath($storagePath);
}

$request = Request::capture();
$app->handleRequest($request);

