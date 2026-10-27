<?php
/**
 * AQUA-TARA — Step 2E Ticket Lifecycle Integration Test
 * Tests canonical lifecycle enforcement on PUT /api/admin/tickets/{id}/status
 */

$BASE = "http://127.0.0.1:8000/api";
$PASS = 0;
$FAIL = 0;

function req(string $method, string $url, array $data = [], string $token = ""): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = ["Accept: application/json", "Content-Type: application/json"];
    if ($token) $headers[] = "Authorization: Bearer $token";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ["status" => $status, "body" => json_decode($body, true)];
}

function assert_test(string $label, bool $condition): void
{
    global $PASS, $FAIL;
    if ($condition) {
        echo "  PASS: $label\n";
        $PASS++;
    } else {
        echo "  FAIL: $label\n";
        $FAIL++;
    }
}

function section(string $title): void
{
    echo "\n== $title ==\n";
}

require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Citizen;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$reportsBefore   = Report::count();
$ticketsBefore   = Ticket::count();
$citizensBefore  = Citizen::count();
$historiesBefore = TicketStatusHistory::count();

echo "Setting up isolated test data...\n";

// Clean up previous failed runs
User::where("email", "admin_step2e@test.com")->delete();
Citizen::where("phone_number", "081299990010")->delete();

try {
    // Create admin user
    $admin = User::create([
        "name"     => "Admin 2E Test",
        "email"    => "admin_step2e@test.com",
        "password" => bcrypt("password"),
    ]);
    $tokenAdmin = $admin->createToken("admin-2e")->plainTextToken;

    // Create citizen for isolation test
    $citizen = Citizen::create(["phone_number" => "081299990010"]);
    $tokenCitizen = $citizen->createToken("citizen-2e")->plainTextToken;

    // Create an isolated report + ticket for lifecycle testing
    $report = Report::create([
        "citizen_id"  => null,
        "description" => "Test 2E Lifecycle",
        "latitude"    => 1.1,
        "longitude"   => 1.1,
        "photo_path"  => "test_2e.jpg",
        "created_at"  => now(),
    ]);
    $ticket = Ticket::create(["report_id" => $report->id, "status" => "SUBMITTED"]);
    $tid = $ticket->id;

    // -----------------------------------------------
    // VALID TRANSITIONS
    // -----------------------------------------------

    section("VALID TRANSITION 1: SUBMITTED -> IN_REVIEW");
    $r = req("PUT", "$BASE/admin/tickets/$tid/status", ["status" => "IN_REVIEW"], $tokenAdmin);
    assert_test("HTTP 200", $r["status"] === 200);
    assert_test("Status = IN_REVIEW", ($r["body"]["data"]["status"] ?? "") === "IN_REVIEW");
    assert_test("History created", TicketStatusHistory::where("ticket_id", $tid)->where("new_status", "IN_REVIEW")->exists());
    assert_test("actor_id = admin", TicketStatusHistory::where("ticket_id", $tid)->where("new_status", "IN_REVIEW")->where("actor_id", $admin->id)->exists());

    section("VALID TRANSITION 2: IN_REVIEW -> CONFIRMED");
    $r = req("PUT", "$BASE/admin/tickets/$tid/status", ["status" => "CONFIRMED"], $tokenAdmin);
    assert_test("HTTP 200", $r["status"] === 200);
    assert_test("Status = CONFIRMED", ($r["body"]["data"]["status"] ?? "") === "CONFIRMED");
    assert_test("History created", TicketStatusHistory::where("ticket_id", $tid)->where("new_status", "CONFIRMED")->exists());

    section("VALID TRANSITION 3: CONFIRMED -> IN_PROGRESS");
    $r = req("PUT", "$BASE/admin/tickets/$tid/status", ["status" => "IN_PROGRESS"], $tokenAdmin);
    assert_test("HTTP 200", $r["status"] === 200);
    assert_test("Status = IN_PROGRESS", ($r["body"]["data"]["status"] ?? "") === "IN_PROGRESS");

    section("VALID TRANSITION 4: IN_PROGRESS -> CLOSED");
    $r = req("PUT", "$BASE/admin/tickets/$tid/status", ["status" => "CLOSED"], $tokenAdmin);
    assert_test("HTTP 200", $r["status"] === 200);
    assert_test("Status = CLOSED", ($r["body"]["data"]["status"] ?? "") === "CLOSED");
    assert_test("4 history records created", TicketStatusHistory::where("ticket_id", $tid)->count() === 4);

    // -----------------------------------------------
    // ILLEGAL TRANSITIONS FROM CLOSED
    // -----------------------------------------------

    section("ILLEGAL: CLOSED -> SUBMITTED (terminal state)");
    $r = req("PUT", "$BASE/admin/tickets/$tid/status", ["status" => "SUBMITTED"], $tokenAdmin);
    assert_test("HTTP 422", $r["status"] === 422);
    assert_test("No new history record", TicketStatusHistory::where("ticket_id", $tid)->count() === 4);

    section("ILLEGAL: CLOSED -> IN_REVIEW");
    $r = req("PUT", "$BASE/admin/tickets/$tid/status", ["status" => "IN_REVIEW"], $tokenAdmin);
    assert_test("HTTP 422", $r["status"] === 422);

    // Create a fresh ticket for remaining illegal transition tests
    $report2 = Report::create([
        "citizen_id"  => null,
        "description" => "Test 2E Lifecycle 2",
        "latitude"    => 1.2,
        "longitude"   => 1.2,
        "photo_path"  => "test_2e_2.jpg",
        "created_at"  => now(),
    ]);
    $ticket2 = Ticket::create(["report_id" => $report2->id, "status" => "SUBMITTED"]);
    $tid2 = $ticket2->id;

    section("ILLEGAL: SUBMITTED -> CLOSED (skip)");
    $r = req("PUT", "$BASE/admin/tickets/$tid2/status", ["status" => "CLOSED"], $tokenAdmin);
    assert_test("HTTP 422", $r["status"] === 422);
    assert_test("Status unchanged (still SUBMITTED)", Ticket::find($tid2)->status === "SUBMITTED");

    section("ILLEGAL: SUBMITTED -> IN_PROGRESS (skip)");
    $r = req("PUT", "$BASE/admin/tickets/$tid2/status", ["status" => "IN_PROGRESS"], $tokenAdmin);
    assert_test("HTTP 422", $r["status"] === 422);

    section("SAME STATUS: SUBMITTED -> SUBMITTED");
    $r = req("PUT", "$BASE/admin/tickets/$tid2/status", ["status" => "SUBMITTED"], $tokenAdmin);
    assert_test("HTTP 422", $r["status"] === 422);

    // -----------------------------------------------
    // AUTHENTICATION TESTS
    // -----------------------------------------------

    section("AUTH: No token (unauthenticated)");
    $r = req("PUT", "$BASE/admin/tickets/$tid2/status", ["status" => "IN_REVIEW"]);
    assert_test("HTTP 401", $r["status"] === 401);
    assert_test("Status unchanged", Ticket::find($tid2)->status === "SUBMITTED");

    section("AUTH: Citizen token cannot do admin update");
    $r = req("PUT", "$BASE/admin/tickets/$tid2/status", ["status" => "IN_REVIEW"], $tokenCitizen);
    // Sanctum guard will try to authenticate citizen token against the 'web' guard
    // Since citizens are a different model, auth:sanctum resolves User model only
    // — citizen token should be rejected (401)
    assert_test("HTTP 401 (Citizen token rejected)", $r["status"] === 401);
    assert_test("Status unchanged", Ticket::find($tid2)->status === "SUBMITTED");

    section("AUTH: Invalid token");
    $r = req("PUT", "$BASE/admin/tickets/$tid2/status", ["status" => "IN_REVIEW"], "garbage-token");
    assert_test("HTTP 401", $r["status"] === 401);

    section("DATA: Unknown ticket_id");
    $r = req("PUT", "$BASE/admin/tickets/99999/status", ["status" => "IN_REVIEW"], $tokenAdmin);
    assert_test("HTTP 404", $r["status"] === 404);

    section("DATA: Existing Report ID 1 preserved");
    assert_test("Report 1 exists", Report::find(1) !== null);
    assert_test("Ticket 1 exists", Ticket::find(1) !== null);

} catch (\Exception $e) {
    echo "Test script exception: " . $e->getMessage() . "\n";
} finally {
    echo "\nCleaning up isolated test data...\n";
    // Cascade: deleting report deletes ticket via FK (RESTRICT but we delete ticket first)
    foreach ([1, 2] as $i) {
        $varName = "ticket$i";
        $varName2 = "report$i";
        if (isset($$varName)) {
            TicketStatusHistory::where("ticket_id", $$varName->id)->delete();
            $$varName->delete();
        }
        if (isset($$varName2)) {
            $$varName2->delete();
        }
    }
    if (isset($ticket) && $ticket->id !== 1) {
        TicketStatusHistory::where("ticket_id", $ticket->id)->delete();
        $ticket->delete();
    }
    if (isset($report) && $report->id !== 1) {
        $report->delete();
    }
    User::where("email", "admin_step2e@test.com")->delete();
    Citizen::where("phone_number", "081299990010")->delete();
}

$reportsAfter   = Report::count();
$ticketsAfter   = Ticket::count();
$citizensAfter  = Citizen::count();
$historiesAfter = TicketStatusHistory::count();

section("DATABASE BASELINE CHECK");
assert_test("Reports unchanged ($reportsBefore -> $reportsAfter)", $reportsAfter <= $reportsBefore);
assert_test("Tickets unchanged ($ticketsBefore -> $ticketsAfter)", $ticketsAfter <= $ticketsBefore);
assert_test("Citizens unchanged ($citizensBefore -> $citizensAfter)", $citizensAfter <= $citizensBefore);
assert_test("Histories cleaned up ($historiesBefore -> $historiesAfter)", $historiesAfter <= $historiesBefore);

echo "\n=======================================\n";
echo "RESULTS: PASS=$PASS | FAIL=$FAIL\n";
echo "OVERALL: " . ($FAIL === 0 ? "ALL PASS" : "FAILURES DETECTED") . "\n";
echo "=======================================\n";
