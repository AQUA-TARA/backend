<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine Origin safely matching explicit CORS whitelist
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'https://aqua-tara.vercel.app,http://localhost:5173,http://127.0.0.1:5173')));

$isOriginAllowed = in_array($origin, $allowedOrigins, true);

if ($isOriginAllowed) {
    if (!headers_sent()) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-Requested-With, Application, Origin');
        header('Vary: Origin');
    }

    // Handle CORS preflight (OPTIONS) according to standard (status 204 No Content)
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit(0);
    }
} else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(403);
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

try {
    require __DIR__ . '/../vendor/autoload.php';

    $app = require_once __DIR__ . '/../bootstrap/app.php';

    if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
        $app->useStoragePath($storagePath);
        $_ENV['VIEW_COMPILED_PATH'] = $storagePath . '/framework/views';
        putenv('VIEW_COMPILED_PATH=' . $storagePath . '/framework/views');
    }

    $request = Request::capture();
    $app->handleRequest($request);
} catch (\Throwable $e) {
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    $isDebug = (bool) (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');
    if ($isDebug) {
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ], JSON_PRETTY_PRINT);
    } else {
        error_log((string) $e);
        echo json_encode([
            'error' => true,
            'message' => 'Internal Server Error'
        ]);
    }
}


