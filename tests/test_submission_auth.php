<?php
/**
 * AQUA-TARA — Step 2D Integration Test: Citizen Auth in Report Submission
 *
 * Tests anonymous vs authenticated report submission.
 *
 * DEVELOPMENT/TESTING script only.
 */

$BASE = 'http://127.0.0.1:8000/api';
$PASS = 0;
$FAIL = 0;

function req_multipart(string $method, string $url, array $data = [], string $token = '', array $files = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    // Accept JSON so validation errors return JSON instead of redirecting
    $headers[] = 'Accept: application/json';
    
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $postFields = $data;
    foreach ($files as $field => $path) {
        $postFields[$field] = new CURLFile($path);
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => json_decode($body, true)];
}

function req_json(string $method, string $url, array $data = [], string $token = ''): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => json_decode($body, true)];
}


function assert_test(string $label, bool $condition): void
{
    global $PASS, $FAIL;
    if ($condition) {
        echo "  ✅ PASS: $label" . PHP_EOL;
        $PASS++;
    } else {
        echo "  ❌ FAIL: $label" . PHP_EOL;
        $FAIL++;
    }
}

function section(string $title): void
{
    echo PHP_EOL . "══ $title ══" . PHP_EOL;
}

// ─────────────────────────────────────────────────────────────────────────
// Setup Data
// ─────────────────────────────────────────────────────────────────────────
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Citizen;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\User; // Admin user

$PHONE_A = '081299990005';
$PHONE_B = '081299990006';
$TEST_IMAGE = __DIR__ . '/dummy_test.jpg';

// Create a dummy image
if (!file_exists($TEST_IMAGE)) {
    $img = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $bg);
    imagejpeg($img, $TEST_IMAGE);
    imagedestroy($img);
}

echo "Setting up isolated test data..." . PHP_EOL;

// Count baseline
$reportsBefore = Report::count();
$ticketsBefore = Ticket::count();
$citizensBefore = Citizen::count();

// Clean up just in case
$citizensToClean = Citizen::whereIn('phone_number', [$PHONE_A, $PHONE_B])->get();
foreach($citizensToClean as $c) {
    $reports = Report::where('citizen_id', $c->id)->get();
    foreach($reports as $r) {
        Ticket::where('report_id', $r->id)->delete();
        $r->delete();
    }
    $c->delete();
}
$anonReports = Report::whereIn('description', ['Test 2D Anonymous', 'Test 2D Override B without Token'])->get();
foreach($anonReports as $r) {
    Ticket::where('report_id', $r->id)->delete();
    $r->delete();
}
// Clean up old admin users if necessary
User::where('email', 'admin_test2d@example.com')->delete();

try {
    $citizenA = Citizen::create(['phone_number' => $PHONE_A]);
    $citizenB = Citizen::create(['phone_number' => $PHONE_B]);
    
    $admin = User::create([
        'name' => 'Admin 2D Test',
        'email' => 'admin_test2d@example.com',
        'password' => bcrypt('password'),
    ]);

    $tokenA = $citizenA->createToken('test-A')->plainTextToken;
    $tokenB = $citizenB->createToken('test-B')->plainTextToken;
    $tokenAdmin = $admin->createToken('test-admin')->plainTextToken;

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    section("TEST 1: Anonymous submission");
    $r = req_multipart('POST', "$BASE/reports", [
        'description' => 'Test 2D Anonymous',
        'latitude' => 1.5,
        'longitude' => 1.5,
    ], '', ['photo' => $TEST_IMAGE]);
    
    if ($r['status'] !== 201) {
        echo "Error response: " . json_encode($r) . PHP_EOL;
    }
    assert_test('HTTP 201', $r['status'] === 201);
    
    $ticketIdAnon = $r['body']['data']['ticket_id'] ?? null;
    $ticketAnon = Ticket::find($ticketIdAnon);
    $reportAnon = $ticketAnon ? Report::find($ticketAnon->report_id) : null;
    
    assert_test('Report created', $reportAnon !== null);
    assert_test('citizen_id is NULL', $reportAnon && $reportAnon->citizen_id === null);

    section("TEST 2: Authenticated Citizen submission (Citizen A)");
    $r = req_multipart('POST', "$BASE/reports", [
        'description' => 'Test 2D Citizen A',
        'latitude' => 2.5,
        'longitude' => 2.5,
    ], $tokenA, ['photo' => $TEST_IMAGE]);
    
    assert_test('HTTP 201', $r['status'] === 201);
    
    $ticketIdA = $r['body']['data']['ticket_id'] ?? null;
    $ticketA = Ticket::find($ticketIdA);
    $reportA = $ticketA ? Report::find($ticketA->report_id) : null;
    
    assert_test('Report created', $reportA !== null);
    assert_test('citizen_id is Citizen A', $reportA && $reportA->citizen_id === $citizenA->id);

    section("TEST 3: Authenticated Citizen A attempts to override citizen_id to Citizen B");
    $r = req_multipart('POST', "$BASE/reports", [
        'description' => 'Test 2D Override B',
        'latitude' => 3.5,
        'longitude' => 3.5,
        'citizen_id' => $citizenB->id // attempt override
    ], $tokenA, ['photo' => $TEST_IMAGE]);
    
    assert_test('HTTP 201', $r['status'] === 201);
    
    $ticketIdOverride = $r['body']['data']['ticket_id'] ?? null;
    $ticketOverride = Ticket::find($ticketIdOverride);
    $reportOverride = $ticketOverride ? Report::find($ticketOverride->report_id) : null;
    
    assert_test('Report created', $reportOverride !== null);
    assert_test('citizen_id remains Citizen A (override ignored)', $reportOverride && $reportOverride->citizen_id === $citizenA->id);

    section("TEST 4: Anonymous client attempts to override citizen_id to Citizen B");
    $r = req_multipart('POST', "$BASE/reports", [
        'description' => 'Test 2D Override B without Token',
        'latitude' => 4.5,
        'longitude' => 4.5,
        'citizen_id' => $citizenB->id // attempt override
    ], '', ['photo' => $TEST_IMAGE]);
    
    assert_test('HTTP 201', $r['status'] === 201);
    
    $ticketIdAnonOverride = $r['body']['data']['ticket_id'] ?? null;
    $ticketAnonOverride = Ticket::find($ticketIdAnonOverride);
    $reportAnonOverride = $ticketAnonOverride ? Report::find($ticketAnonOverride->report_id) : null;
    
    assert_test('Report created', $reportAnonOverride !== null);
    assert_test('citizen_id remains NULL (override ignored)', $reportAnonOverride && $reportAnonOverride->citizen_id === null);

    section("TEST 5 & 6: Existing Citizen history reflects authenticated report and excludes anonymous");
    $rHistory = req_json('GET', "$BASE/citizen/history", [], $tokenA);
    assert_test('History HTTP 200', $rHistory['status'] === 200);
    $foundReportA = false;
    $foundReportOverride = false;
    $foundAnonymous = false;
    foreach ($rHistory['body']['data'] ?? [] as $item) {
        if (($item['report_id'] ?? null) === ($reportA->id ?? -1)) $foundReportA = true;
        if (($item['report_id'] ?? null) === ($reportOverride->id ?? -1)) $foundReportOverride = true;
        if (($item['report_id'] ?? null) === ($reportAnon->id ?? -1)) $foundAnonymous = true;
    }
    assert_test('History contains Report A', $foundReportA);
    assert_test('History contains Override Report (owned by A)', $foundReportOverride);
    assert_test('History does NOT contain Anonymous report', !$foundAnonymous);

    section("TEST 7 & 8: Ticket status remains SUBMITTED");
    assert_test('Ticket Anon status = SUBMITTED', $ticketAnon && $ticketAnon->status === 'SUBMITTED');
    assert_test('Ticket A status = SUBMITTED', $ticketA && $ticketA->status === 'SUBMITTED');

    section("TEST 10: Invalid token behavior");
    // Depending on Sanctum + guard logic without auth middleware,
    // auth('citizen')->check() will just return false if token is invalid,
    // so it acts as an anonymous submission. Or it might 401 if it attempts to resolve.
    // Since we don't have 'auth:citizen' middleware on POST /reports,
    // Sanctum will just gracefully fail to authenticate and return null user.
    $r = req_multipart('POST', "$BASE/reports", [
        'description' => 'Test 2D Invalid Token',
        'latitude' => 5.5,
        'longitude' => 5.5,
    ], 'invalid-token-string', ['photo' => $TEST_IMAGE]);
    
    // Whether it returns 201 (as anonymous) or 401 is an edge case.
    // If it returns 201, citizen_id should be NULL.
    // With default Laravel Sanctum without middleware, it typically just doesn't log in and continues as guest.
    assert_test('Returns HTTP 201 with invalid token (gracefully acts as anonymous)', $r['status'] === 201);
    if ($r['status'] === 201) {
        $ticketIdInv = $r['body']['data']['ticket_id'] ?? null;
        $ticketInv = Ticket::find($ticketIdInv);
        $reportInv = $ticketInv ? Report::find($ticketInv->report_id) : null;
        assert_test('citizen_id is NULL', $reportInv && $reportInv->citizen_id === null);
        
        // clean up this one too
        if ($ticketInv) $ticketInv->delete();
        if ($reportInv) $reportInv->delete();
    }

    section("TEST 11: Admin/User token cannot become Citizen identity");
    // Similar to invalid token, since guard is 'citizen' but token is from 'web/user',
    // auth('citizen') should fail to authenticate it and treat as anonymous.
    $r = req_multipart('POST', "$BASE/reports", [
        'description' => 'Test 2D Admin Token',
        'latitude' => 6.5,
        'longitude' => 6.5,
    ], $tokenAdmin, ['photo' => $TEST_IMAGE]);
    
    assert_test('Returns HTTP 201 with Admin token (acts as anonymous)', $r['status'] === 201);
    if ($r['status'] === 201) {
        $ticketIdAdm = $r['body']['data']['ticket_id'] ?? null;
        $ticketAdm = Ticket::find($ticketIdAdm);
        $reportAdm = $ticketAdm ? Report::find($ticketAdm->report_id) : null;
        assert_test('citizen_id is NULL (did not mix admin ID)', $reportAdm && $reportAdm->citizen_id === null);
        
        // clean up this one too
        if ($ticketAdm) $ticketAdm->delete();
        if ($reportAdm) $reportAdm->delete();
    }


} catch (\Exception $e) {
    echo "Test script failed: " . $e->getMessage() . PHP_EOL;
} finally {
    echo PHP_EOL . "Cleaning up isolated test data..." . PHP_EOL;
    $citizensToClean = Citizen::whereIn('phone_number', [$PHONE_A, $PHONE_B])->get();
    foreach($citizensToClean as $c) {
        $reports = Report::where('citizen_id', $c->id)->get();
        foreach($reports as $rep) {
            Ticket::where('report_id', $rep->id)->delete();
            $rep->delete();
        }
        $c->delete();
    }
    $anonReports = Report::whereIn('description', ['Test 2D Anonymous', 'Test 2D Override B without Token'])->get();
    foreach($anonReports as $r) {
        Ticket::where('report_id', $r->id)->delete();
        $r->delete();
    }
    User::where('email', 'admin_test2d@example.com')->delete();
    
    if (file_exists($TEST_IMAGE)) {
        unlink($TEST_IMAGE);
    }
}

// Check baseline after
$reportsAfter = Report::count();
$ticketsAfter = Ticket::count();
$citizensAfter = Citizen::count();

section("Database Baseline Check");
assert_test("Reports unchanged ($reportsBefore -> $reportsAfter) or cleaned up properly", $reportsAfter <= $reportsBefore);
assert_test("Tickets unchanged ($ticketsBefore -> $ticketsAfter) or cleaned up properly", $ticketsAfter <= $ticketsBefore);
assert_test("Citizens unchanged ($citizensBefore -> $citizensAfter) or cleaned up properly", $citizensAfter <= $citizensBefore);

echo PHP_EOL;
echo "═══════════════════════════════════════" . PHP_EOL;
echo "RESULTS: PASS=$PASS | FAIL=$FAIL" . PHP_EOL;
echo "OVERALL: " . ($FAIL === 0 ? "ALL PASS ✅" : "FAILURES DETECTED ❌") . PHP_EOL;
echo "═══════════════════════════════════════" . PHP_EOL;
