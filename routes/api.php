<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CitizenAuthController;
use App\Http\Controllers\CitizenHistoryController;
use App\Http\Controllers\AdminTicketController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| AQUA-TARA API Routes
|
| Step 2A — Report Submission
| Step 2B — Ticket Tracking
| Step 2C — Citizen Authentication (Mock OTP + Sanctum)
|
*/

// ─────────────────────────────────────────────────────────────────────────
// Citizen Authentication — Mock OTP Flow (DEC-005, DEC-006)
// Source: API_CONTRACT.md §8 (Auth Section), Step 2C.5
// NOTE: OTP is MOCK/DEVELOPMENT only. No real SMS/WA provider.
// ─────────────────────────────────────────────────────────────────────────

// Public — no token needed
Route::prefix('auth/citizen')->group(function () {
    Route::post('/request-otp', [CitizenAuthController::class, 'requestOtp']);
    Route::post('/verify-otp',  [CitizenAuthController::class, 'verifyOtp']);
});

// Protected — requires valid Citizen Bearer token (auth:citizen guard)
Route::middleware('auth:citizen')->group(function () {
    Route::get('/auth/citizen/me', [CitizenAuthController::class, 'me']);

    // ─────────────────────────────────────────────────────────────────────
    // Citizen Report History — GET /api/citizen/history
    // Auth: Wajib (Citizen) — identity from Sanctum token, NOT from input
    // Source: API_CONTRACT.md §15, DEC-005, DEC-006
    // ─────────────────────────────────────────────────────────────────────
    Route::get('/citizen/history', [CitizenHistoryController::class, 'index']);
});

// ─────────────────────────────────────────────────────────────────────────
// Citizen Report Submission — POST /api/reports
// Auth: Optional (anonymous and authenticated citizens both supported)
// Source: API_CONTRACT.md §13, DEC-005, DEC-006
// ─────────────────────────────────────────────────────────────────────────
Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports');

// ─────────────────────────────────────────────────────────────────────────
// Ticket Tracking — GET /api/tickets/{ticket_id}
// Auth: Optional (anonymous tracking supported)
// Source: API_CONTRACT.md §14, DEC-006, DEC-009
// ─────────────────────────────────────────────────────────────────────────
Route::get('/tickets/{ticket}', [ReportController::class, 'show']);

// ─────────────────────────────────────────────────────────────────────────
// Admin Ticket Management — PUT /api/admin/tickets/{ticket_id}/status
// Auth: Wajib Admin (auth:sanctum guard — User model).
// Citizen tokens MUST NOT be accepted here.
// Source: API_CONTRACT.md §18, DEC-002, DEC-008
// ─────────────────────────────────────────────────────────────────────────
Route::middleware('supabase.jwt')->group(function () {
    Route::put('/admin/tickets/{ticket_id}/status', [AdminTicketController::class, 'updateStatus']);
    // Admin ticket management endpoints
    Route::get('/admin/dashboard', [AdminTicketController::class, 'dashboard']);
    Route::get('/admin/tickets', [AdminTicketController::class, 'index']);
    Route::get('/admin/tickets/export/csv', [AdminTicketController::class, 'exportCsv']);
    Route::get('/admin/tickets/{ticket_id}', [AdminTicketController::class, 'show']);

    // Admin IoT Nodes management endpoints
    Route::get('/admin/nodes', [AdminTicketController::class, 'iotNodesIndex']);
    Route::post('/admin/nodes', [AdminTicketController::class, 'iotNodesStore']);

    // Admin map endpoint (Sensor Heatmap, Pipe GeoJSON, Reports) — DEC-010, DEC-012
    Route::get('/admin/map', [\App\Http\Controllers\AdminMapController::class, 'index']);
});

// ─────────────────────────────────────────────────────────────────────────
// Public Map Area — GET /api/map/public (Citizen Safe Map — DEC-011)
// ─────────────────────────────────────────────────────────────────────────
Route::get('/map/public', [\App\Http\Controllers\AdminMapController::class, 'publicMap']);


// ─────────────────────────────────────────────────────────────────────────
// IoT Data Ingestion — POST /api/iot/ingest
// Auth: Custom via X-Hardware-Id header
// Source: API_CONTRACT.md §21, Step 4B Decisions
// ─────────────────────────────────────────────────────────────────────────
Route::post('/iot/ingest', [\App\Http\Controllers\IoTIngestController::class, 'store']);
