<?php
/**
 * AQUA-TARA — Step 2C Final Citizen History Integration Test
 *
 * Tests the history isolation, authentication, and correct data retrieval.
 *
 * DEVELOPMENT/TESTING script only.
 */

$BASE = 'http://127.0.0.1:8000/api';
$PASS = 0;
$FAIL = 0;

function req(string $method, string $url, array $data = [], string $token = ''): array
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
use Illuminate\Support\Facades\DB;

$PHONE_A = '081299990001';
$PHONE_B = '081299990002';
$PHONE_C = '081299990003';

echo "Setting up isolated test data..." . PHP_EOL;

// Count baseline
$reportsBefore = Report::count();
$ticketsBefore = Ticket::count();
$citizensBefore = Citizen::count();

// Clean up just in case previous run failed
$citizensToClean = Citizen::whereIn('phone_number', [$PHONE_A, $PHONE_B, $PHONE_C])->get();
foreach($citizensToClean as $c) {
    // Delete tickets first via report
    $reports = Report::where('citizen_id', $c->id)->get();
    foreach($reports as $r) {
        Ticket::where('report_id', $r->id)->delete();
        $r->delete();
    }
    $c->delete();
}
$anonReports = Report::where('description', 'Test Report Anonymous')->get();
foreach($anonReports as $r) {
    Ticket::where('report_id', $r->id)->delete();
    $r->delete();
}

try {
    $citizenA = Citizen::create(['phone_number' => $PHONE_A]);
    $citizenB = Citizen::create(['phone_number' => $PHONE_B]);

    // Report A
    $reportA = Report::create([
        'citizen_id'  => $citizenA->id,
        'description' => 'Test Report A',
        'latitude'    => 1.1,
        'longitude'   => 1.1,
        'photo_path'  => 'test_a.jpg',
        'created_at'  => now(),
    ]);
    $ticketA = Ticket::create(['report_id' => $reportA->id, 'status' => 'SUBMITTED']);

    // Report B
    $reportB = Report::create([
        'citizen_id'  => $citizenB->id,
        'description' => 'Test Report B',
        'latitude'    => 2.2,
        'longitude'   => 2.2,
        'photo_path'  => 'test_b.jpg',
        'created_at'  => now(),
    ]);
    $ticketB = Ticket::create(['report_id' => $reportB->id, 'status' => 'IN_REVIEW']);

    // Anonymous Report
    $reportAnon = Report::create([
        'citizen_id'  => null,
        'description' => 'Test Report Anonymous',
        'latitude'    => 3.3,
        'longitude'   => 3.3,
        'photo_path'  => 'test_anon.jpg',
        'created_at'  => now(),
    ]);
    $ticketAnon = Ticket::create(['report_id' => $reportAnon->id, 'status' => 'SUBMITTED']);

    // Tokens
    $tokenA = $citizenA->createToken('test-A')->plainTextToken;
    $tokenB = $citizenB->createToken('test-B')->plainTextToken;

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    section("TEST 1: Citizen A history");
    $r = req('GET', "$BASE/citizen/history", [], $tokenA);
    if ($r['status'] !== 200) {
        echo "Error response: " . json_encode($r) . PHP_EOL;
    }
    assert_test('HTTP 200', $r['status'] === 200);
    assert_test('Count = 1', count($r['body']['data'] ?? []) === 1);
    assert_test('Shows Report A', ($r['body']['data'][0]['report_id'] ?? null) === $reportA->id);
    assert_test('Does NOT show Report B', ($r['body']['data'][0]['report_id'] ?? null) !== $reportB->id);
    assert_test('Does NOT show Anonymous', ($r['body']['data'][0]['report_id'] ?? null) !== $reportAnon->id);

    section("TEST 2: Citizen B history");
    $r = req('GET', "$BASE/citizen/history", [], $tokenB);
    assert_test('HTTP 200', $r['status'] === 200);
    assert_test('Count = 1', count($r['body']['data'] ?? []) === 1);
    assert_test('Shows Report B', ($r['body']['data'][0]['report_id'] ?? null) === $reportB->id);
    assert_test('Status IN_REVIEW mapped', ($r['body']['data'][0]['display_status'] ?? null) === 'Sedang Ditinjau');

    section("TEST 3: No token");
    $r = req('GET', "$BASE/citizen/history");
    assert_test('HTTP 401', $r['status'] === 401);

    section("TEST 4: Invalid token");
    $r = req('GET', "$BASE/citizen/history", [], 'invalid-token');
    assert_test('HTTP 401', $r['status'] === 401);

    section("TEST 5: Query override attempt (Citizen B trying to see Citizen A)");
    $r = req('GET', "$BASE/citizen/history?citizen_id={$citizenA->id}", [], $tokenB);
    assert_test('HTTP 200', $r['status'] === 200);
    assert_test('Still shows Report B (ignored query param)', ($r['body']['data'][0]['report_id'] ?? null) === $reportB->id);

    section("TEST 6: Empty history");
    $citizenC = Citizen::create(['phone_number' => $PHONE_C]);
    $tokenC = $citizenC->createToken('test-C')->plainTextToken;
    $r = req('GET', "$BASE/citizen/history", [], $tokenC);
    assert_test('HTTP 200', $r['status'] === 200);
    assert_test('Empty array', is_array($r['body']['data'] ?? null) && empty($r['body']['data']));

} catch (\Exception $e) {
    echo "Test script failed: " . $e->getMessage() . PHP_EOL;
} finally {
    echo PHP_EOL . "Cleaning up isolated test data..." . PHP_EOL;
    $citizensToClean = Citizen::whereIn('phone_number', [$PHONE_A, $PHONE_B, $PHONE_C])->get();
    foreach($citizensToClean as $c) {
        $reports = Report::where('citizen_id', $c->id)->get();
        foreach($reports as $rep) {
            Ticket::where('report_id', $rep->id)->delete();
            $rep->delete();
        }
        $c->delete();
    }
    $anonReports = Report::where('description', 'Test Report Anonymous')->get();
    foreach($anonReports as $r) {
        Ticket::where('report_id', $r->id)->delete();
        $r->delete();
    }
}

// Check baseline after
$reportsAfter = Report::count();
$ticketsAfter = Ticket::count();
$citizensAfter = Citizen::count();

section("Database Baseline Check");
// Because test setup and cleanup is run manually, the original numbers shouldn't change.
// Exception: if the previous run left dirt behind, the before count would have been higher.
assert_test("Reports unchanged ($reportsBefore -> $reportsAfter) or cleaned up properly", $reportsAfter <= $reportsBefore);
assert_test("Tickets unchanged ($ticketsBefore -> $ticketsAfter) or cleaned up properly", $ticketsAfter <= $ticketsBefore);
assert_test("Citizens unchanged ($citizensBefore -> $citizensAfter) or cleaned up properly", $citizensAfter <= $citizensBefore);

echo PHP_EOL;
echo "═══════════════════════════════════════" . PHP_EOL;
echo "RESULTS: PASS=$PASS | FAIL=$FAIL" . PHP_EOL;
echo "OVERALL: " . ($FAIL === 0 ? "ALL PASS ✅" : "FAILURES DETECTED ❌") . PHP_EOL;
echo "═══════════════════════════════════════" . PHP_EOL;
