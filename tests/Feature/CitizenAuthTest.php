<?php

namespace Tests\Feature;

use App\Models\Citizen;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CitizenAuthTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Helper to compute OTP cache key matching CitizenAuthController.
     */
    private function otpKey(string $phone): string
    {
        return 'aquatara:otp:' . hash('sha256', $phone);
    }

    /**
     * Helper to compute Lock cache key matching CitizenAuthController.
     */
    private function lockKey(string $phone): string
    {
        return 'aquatara:otp_lock:' . hash('sha256', $phone);
    }

    /**
     * 1. Request OTP valid: status 200, response structure, Citizen record created.
     */
    public function test_request_otp_valid_creates_citizen_and_returns_200(): void
    {
        $phone = '081298765432';

        $response = $this->postJson('/api/auth/citizen/request-otp', [
            'phone_number' => $phone,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Kode OTP telah dikirim ke nomor Anda.',
            ]);

        // In testing environment (APP_ENV=testing), dev_only OTP MUST NOT be leaked
        $response->assertJsonMissing(['dev_only']);

        $this->assertDatabaseHas('citizens', [
            'phone_number' => $phone,
        ]);

        $this->assertTrue(Cache::has($this->otpKey($phone)));
    }

    /**
     * 2. Request OTP duplicate during active cooldown returns 429 with retry info.
     */
    public function test_request_otp_during_cooldown_returns_429(): void
    {
        $phone = '081298765433';

        // First request creates OTP and sets cooldown
        $this->postJson('/api/auth/citizen/request-otp', [
            'phone_number' => $phone,
        ])->assertStatus(200);

        // Immediate second request hits cooldown throttle
        $response = $this->postJson('/api/auth/citizen/request-otp', [
            'phone_number' => $phone,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'retry_after_seconds' => 60,
            ]);
    }

    /**
     * 3. Phone validation failure (missing or too short) returns 422.
     */
    public function test_request_otp_validation_fails_for_invalid_or_missing_phone(): void
    {
        // Missing phone_number
        $this->postJson('/api/auth/citizen/request-otp', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);

        // Phone number too short (< 9 chars)
        $this->postJson('/api/auth/citizen/request-otp', [
            'phone_number' => '1234567',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);
    }

    /**
     * 4. Verify OTP with correct code: returns Sanctum token and clears OTP from cache.
     */
    public function test_verify_otp_success_returns_token_and_cleans_cache(): void
    {
        $phone = '081298765434';
        Citizen::create(['phone_number' => $phone]);

        $otp = '654321';
        Cache::put($this->otpKey($phone), bcrypt($otp), now()->addMinutes(5));

        $response = $this->postJson('/api/auth/citizen/verify-otp', [
            'phone_number' => $phone,
            'otp'          => $otp,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Autentikasi berhasil.',
                'data'    => [
                    'token_type' => 'Bearer',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));

        // Cache must be cleared upon successful verification
        $this->assertFalse(Cache::has($this->otpKey($phone)));
    }

    /**
     * 5. Verify OTP with wrong code: decrements attempts and returns 401 with remaining count.
     */
    public function test_verify_otp_wrong_code_returns_401_with_remaining_attempts(): void
    {
        $phone = '081298765435';
        Citizen::create(['phone_number' => $phone]);

        $otp = '654321';
        Cache::put($this->otpKey($phone), bcrypt($otp), now()->addMinutes(5));

        $response = $this->postJson('/api/auth/citizen/verify-otp', [
            'phone_number' => $phone,
            'otp'          => '000000',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success'            => false,
                'attempts_remaining' => 2,
            ]);
    }

    /**
     * 6. Lockout triggered after 3 consecutive wrong OTP attempts.
     */
    public function test_verify_otp_locks_out_after_three_failed_attempts(): void
    {
        $phone = '081298765436';
        Citizen::create(['phone_number' => $phone]);

        $otp = '654321';
        Cache::put($this->otpKey($phone), bcrypt($otp), now()->addMinutes(5));

        // Attempt 1 -> remaining 2
        $this->postJson('/api/auth/citizen/verify-otp', [
            'phone_number' => $phone,
            'otp'          => '000001',
        ])->assertStatus(401)->assertJson(['attempts_remaining' => 2]);

        // Attempt 2 -> remaining 1
        $this->postJson('/api/auth/citizen/verify-otp', [
            'phone_number' => $phone,
            'otp'          => '000002',
        ])->assertStatus(401)->assertJson(['attempts_remaining' => 1]);

        // Attempt 3 -> Lockout 429
        $response = $this->postJson('/api/auth/citizen/verify-otp', [
            'phone_number' => $phone,
            'otp'          => '000003',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'locked'  => true,
            ]);

        $this->assertTrue(Cache::has($this->lockKey($phone)));
        $this->assertFalse(Cache::has($this->otpKey($phone)));
    }

    /**
     * 7. Verify OTP request while lockout is active immediately returns 429.
     */
    public function test_verify_otp_returns_429_when_account_locked(): void
    {
        $phone = '081298765437';
        Citizen::create(['phone_number' => $phone]);

        Cache::put($this->lockKey($phone), true, now()->addMinutes(15));

        $response = $this->postJson('/api/auth/citizen/verify-otp', [
            'phone_number' => $phone,
            'otp'          => '123456',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'locked'  => true,
            ]);
    }

    /**
     * 8. Verify OTP returns 401 when challenge is not found or expired.
     */
    public function test_verify_otp_expired_or_missing_returns_401(): void
    {
        $phone = '081298765438';
        Citizen::create(['phone_number' => $phone]);

        // No OTP put into Cache
        $response = $this->postJson('/api/auth/citizen/verify-otp', [
            'phone_number' => $phone,
            'otp'          => '123456',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta OTP baru.',
            ]);
    }

    /**
     * 9. GET /api/auth/citizen/me with valid Bearer token returns citizen identity.
     */
    public function test_get_me_with_valid_citizen_token_returns_identity(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765439']);
        $token = $citizen->createToken('citizen-auth')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/citizen/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'citizen_id'   => $citizen->id,
                    'phone_number' => '081298765439',
                ],
            ]);
    }

    /**
     * 10. GET /api/auth/citizen/me without token or with invalid token returns 401.
     */
    public function test_get_me_unauthenticated_or_invalid_token_returns_401(): void
    {
        // Unauthenticated request
        $this->getJson('/api/auth/citizen/me')
            ->assertStatus(401);

        // Invalid bearer token
        $this->withHeader('Authorization', 'Bearer invalid-token-string')
            ->getJson('/api/auth/citizen/me')
            ->assertStatus(401);
    }

    /**
     * 11. Security contract: identity is bound to authenticated principal, not query params.
     */
    public function test_get_me_ignores_query_string_citizen_id(): void
    {
        $actualCitizen = Citizen::create(['phone_number' => '081298765440']);
        $otherCitizen = Citizen::create(['phone_number' => '081298765441']);

        $token = $actualCitizen->createToken('citizen-auth')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/auth/citizen/me?citizen_id={$otherCitizen->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'citizen_id'   => $actualCitizen->id,
                    'phone_number' => '081298765440',
                ],
            ]);

        $this->assertNotEquals($otherCitizen->id, $response->json('data.citizen_id'));
    }
}
