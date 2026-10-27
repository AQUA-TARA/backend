<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

error_log('[AQUA-TARA] Entry point reached');
error_log('[AQUA-TARA] REQUEST_URI: ' . ($_SERVER['REQUEST_URI'] ?? 'missing'));

// Handle CORS preflight directly at entry point
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-Requested-With, Application');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit(0);
}

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

