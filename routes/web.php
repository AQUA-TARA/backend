<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'AQUA-TARA API Backend',
        'timestamp' => now()->toIso8601String()
    ]);
});
