<?php
/**
 * AQUA-TARA — Step 2C.5 Mock OTP Authentication Integration Test
 *
 * Tests the full Citizen auth flow:
 * request-otp → verify-otp → token → /me → security isolation
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
// Setup — use two distinct test phone numbers for isolation test
// ─────────────────────────────────────────────────────────────────────────
$PHONE_A = '0811000001'; // Test Citizen A
$PHONE_B = '0811000002'; // Test Citizen B

// ─────────────────────────────────────────────────────────────────────────
// TEST 1: Request OTP for Citizen A
// ─────────────────────────────────────────────────────────────────────────
section("TEST 1: Request OTP — Citizen A");
$r = req('POST', "$BASE/auth/citizen/request-otp", ['phone_number' => $PHONE_A]);
assert_test('HTTP 200', $r['status'] === 200);
assert_test('success = true', $r['body']['success'] === true);
assert_test('dev_only.otp present (local env)', isset($r['body']['dev_only']['otp']));
$OTP_A = $r['body']['dev_only']['otp'] ?? null;
echo "  [INFO] Mock OTP A = $OTP_A" . PHP_EOL;

// ─────────────────────────────────────────────────────────────────────────
// TEST 2: Resend before cooldown (should be rejected)
// ─────────────────────────────────────────────────────────────────────────
section("TEST 2: Resend OTP before 60s cooldown — should 429");
$r = req('POST', "$BASE/auth/citizen/request-otp", ['phone_number' => $PHONE_A]);
assert_test('HTTP 429', $r['status'] === 429);
assert_test('success = false', $r['body']['success'] === false);
assert_test('retry_after_seconds present', isset($r['body']['retry_after_seconds']));

// ─────────────────────────────────────────────────────────────────────────
// TEST 3: Verify OTP — Wrong OTP (attempt 1)
// ─────────────────────────────────────────────────────────────────────────
section("TEST 3: Wrong OTP — attempt 1 of 3");
$r = req('POST', "$BASE/auth/citizen/verify-otp", ['phone_number' => $PHONE_A, 'otp' => '000000']);
assert_test('HTTP 401', $r['status'] === 401);
assert_test('success = false', $r['body']['success'] === false);
assert_test('attempts_remaining = 2', ($r['body']['attempts_remaining'] ?? null) === 2);

// ─────────────────────────────────────────────────────────────────────────
// TEST 4: Verify OTP — Wrong OTP (attempt 2)
// ─────────────────────────────────────────────────────────────────────────
section("TEST 4: Wrong OTP — attempt 2 of 3");
$r = req('POST', "$BASE/auth/citizen/verify-otp", ['phone_number' => $PHONE_A, 'otp' => '000000']);
assert_test('HTTP 401', $r['status'] === 401);
assert_test('attempts_remaining = 1', ($r['body']['attempts_remaining'] ?? null) === 1);

// ─────────────────────────────────────────────────────────────────────────
// TEST 5: Verify OTP — Wrong OTP (attempt 3 → LOCK)
// ─────────────────────────────────────────────────────────────────────────
section("TEST 5: Wrong OTP — attempt 3 → account locked");
$r = req('POST', "$BASE/auth/citizen/verify-otp", ['phone_number' => $PHONE_A, 'otp' => '000000']);
assert_test('HTTP 429', $r['status'] === 429);
assert_test('locked = true', ($r['body']['locked'] ?? false) === true);

// ─────────────────────────────────────────────────────────────────────────
// TEST 6: Attempt while locked
// ─────────────────────────────────────────────────────────────────────────
section("TEST 6: Attempt while account is locked");
$r = req('POST', "$BASE/auth/citizen/verify-otp", ['phone_number' => $PHONE_A, 'otp' => '123456']);
assert_test('HTTP 429 (locked)', $r['status'] === 429);
assert_test('locked = true', ($r['body']['locked'] ?? false) === true);

// ─────────────────────────────────────────────────────────────────────────
// TEST 7: New OTP flow for Citizen B (clean slate)
// ─────────────────────────────────────────────────────────────────────────
section("TEST 7: Request OTP — Citizen B (unaffected by A's lock)");
$r = req('POST', "$BASE/auth/citizen/request-otp", ['phone_number' => $PHONE_B]);
assert_test('HTTP 200', $r['status'] === 200);
assert_test('dev_only.otp present', isset($r['body']['dev_only']['otp']));
$OTP_B = $r['body']['dev_only']['otp'] ?? null;
echo "  [INFO] Mock OTP B = $OTP_B" . PHP_EOL;

// ─────────────────────────────────────────────────────────────────────────
// TEST 8: Verify correct OTP for Citizen B → token issued
// ─────────────────────────────────────────────────────────────────────────
section("TEST 8: Correct OTP for Citizen B → Sanctum token");
$r = req('POST', "$BASE/auth/citizen/verify-otp", ['phone_number' => $PHONE_B, 'otp' => $OTP_B]);
assert_test('HTTP 200', $r['status'] === 200);
assert_test('success = true', $r['body']['success'] === true);
assert_test('token present', !empty($r['body']['data']['token'] ?? ''));
assert_test('token_type = Bearer', ($r['body']['data']['token_type'] ?? '') === 'Bearer');
$TOKEN_B = $r['body']['data']['token'] ?? null;

// ─────────────────────────────────────────────────────────────────────────
// TEST 9: GET /api/auth/citizen/me with valid token
// ─────────────────────────────────────────────────────────────────────────
section("TEST 9: GET /me with valid Citizen B token");
$r = req('GET', "$BASE/auth/citizen/me", [], $TOKEN_B);
assert_test('HTTP 200', $r['status'] === 200);
assert_test('success = true', $r['body']['success'] === true);
assert_test('citizen_id present', !empty($r['body']['data']['citizen_id'] ?? null));
assert_test('phone_number = Citizen B', ($r['body']['data']['phone_number'] ?? '') === $PHONE_B);
$CITIZEN_B_ID = $r['body']['data']['citizen_id'] ?? null;
echo "  [INFO] Citizen B resolved ID = $CITIZEN_B_ID" . PHP_EOL;

// ─────────────────────────────────────────────────────────────────────────
// TEST 10: No token → HTTP 401
// ─────────────────────────────────────────────────────────────────────────
section("TEST 10: GET /me without token → 401");
$r = req('GET', "$BASE/auth/citizen/me");
assert_test('HTTP 401', $r['status'] === 401);

// ─────────────────────────────────────────────────────────────────────────
// TEST 11: Invalid token → HTTP 401
// ─────────────────────────────────────────────────────────────────────────
section("TEST 11: GET /me with invalid token → 401");
$r = req('GET', "$BASE/auth/citizen/me", [], 'invalid-token-xyz');
assert_test('HTTP 401', $r['status'] === 401);

// ─────────────────────────────────────────────────────────────────────────
// TEST 12: Identity isolation — Citizen B token resolves ONLY Citizen B
// ─────────────────────────────────────────────────────────────────────────
section("TEST 12: Identity isolation — token resolves correct citizen");
$r = req('GET', "$BASE/auth/citizen/me", [], $TOKEN_B);
$resolvedPhone = $r['body']['data']['phone_number'] ?? '';
assert_test('Token resolves ONLY Citizen B phone', $resolvedPhone === $PHONE_B);
assert_test('citizen_id consistent', ($r['body']['data']['citizen_id'] ?? null) === $CITIZEN_B_ID);

// ─────────────────────────────────────────────────────────────────────────
// TEST 13: citizen_id cannot be set via request body
// ─────────────────────────────────────────────────────────────────────────
section("TEST 13: citizen_id from token, not from request body");
$r = req('GET', "$BASE/auth/citizen/me?citizen_id=99999", [], $TOKEN_B);
assert_test('citizen_id still = Citizen B (not 99999)', ($r['body']['data']['citizen_id'] ?? null) !== 99999);
assert_test('citizen_id from token only', ($r['body']['data']['citizen_id'] ?? null) === $CITIZEN_B_ID);

// ─────────────────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo "═══════════════════════════════════════" . PHP_EOL;
echo "RESULTS: PASS=$PASS | FAIL=$FAIL" . PHP_EOL;
echo "OVERALL: " . ($FAIL === 0 ? "ALL PASS ✅" : "FAILURES DETECTED ❌") . PHP_EOL;
echo "═══════════════════════════════════════" . PHP_EOL;
