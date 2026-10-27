<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$citizen = App\Models\Citizen::firstOrCreate(['phone_number' => '08123456789']);
$token = $citizen->createToken('test-token')->plainTextToken;
echo 'TOKEN GENERATED SUCCESSFULLY: ' . substr($token, 0, 10) . '...';
