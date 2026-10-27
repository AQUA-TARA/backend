<?php

namespace App\Http\Controllers;

use App\Models\Citizen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * CitizenAuthController
 *
 * Handles Citizen authentication via phone_number + Mock OTP.
 *
 * DEVELOPMENT/TESTING ONLY:
 * This controller uses a MOCK OTP mechanism. No real SMS/WhatsApp
 * provider is used. The OTP is stored in Laravel cache and returned
 * in the response payload under a "dev_only" key (APP_ENV=local only).
 *
 * Locked Business Rules:
 * - OTP wrong 3 times → lock 15 minutes (DEC-005 OTP constraint).
 * - Resend OTP → cooldown 60 seconds (DEC-005 OTP constraint).
 * - citizen_id NEVER from request input — always from authenticated principal.
 *
 * Source: API_CONTRACT.md, DEC-005, DEC-006
 */
class CitizenAuthController extends Controller
{
    // ── OTP Configuration ─────────────────────────────────────────────────
    private const OTP_TTL_MINUTES       = 5;    // OTP expires after 5 min
    private const OTP_LOCK_MINUTES      = 15;   // Lock after max failed attempts
    private const OTP_MAX_ATTEMPTS      = 3;    // Max wrong OTP tries before lock
    private const OTP_COOLDOWN_SECONDS  = 60;   // Resend cooldown

    // ── Cache Key Helpers ─────────────────────────────────────────────────

    private function otpKey(string $phone): string
    {
        return 'aquatara:otp:' . hash('sha256', $phone);
    }

    private function attemptsKey(string $phone): string
    {
        return 'aquatara:otp_attempts:' . hash('sha256', $phone);
    }

    private function lockKey(string $phone): string
    {
        return 'aquatara:otp_lock:' . hash('sha256', $phone);
    }

    private function cooldownKey(string $phone): string
    {
        return 'aquatara:otp_cooldown:' . hash('sha256', $phone);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/auth/citizen/request-otp
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Request a (mock) OTP for the given phone_number.
     *
     * - Validates phone_number.
     * - Resolves or creates Citizen.
     * - Enforces 60-second resend cooldown.
     * - Generates and caches a 6-digit mock OTP (hashed, TTL=5 min).
     * - In APP_ENV=local, returns OTP under "dev_only" for testability.
     * - In production-like environments, dev_only is omitted.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone_number' => ['required', 'string', 'min:9', 'max:20'],
            ]);

            $phone = $validated['phone_number'];

            // ── Check cooldown (resend throttle) ──────────────────────────
            if (Cache::has($this->cooldownKey($phone))) {
                $remaining = (int) Cache::get($this->cooldownKey($phone));
                return response()->json([
                    'success' => false,
                    'message' => "OTP sudah dikirim. Tunggu {$remaining} detik sebelum meminta ulang.",
                    'retry_after_seconds' => $remaining,
                ], 429);
            }

            // ── Resolve / create Citizen (phone_number as identity) ───────
            $citizen = Citizen::firstOrCreate(
                ['phone_number' => $phone]
            );

            // ── Generate mock 6-digit OTP ─────────────────────────────────
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store hashed OTP in cache (TTL = 5 minutes)
            Cache::put(
                $this->otpKey($phone),
                bcrypt($otp),
                now()->addMinutes(self::OTP_TTL_MINUTES)
            );

            // Store cooldown timer (TTL = 60 seconds)
            // Value = timestamp for remaining-seconds calculation
            Cache::put(
                $this->cooldownKey($phone),
                self::OTP_COOLDOWN_SECONDS,
                now()->addSeconds(self::OTP_COOLDOWN_SECONDS)
            );

            // Log OTP for server-side development inspection (never in production)
            Log::channel('daily')->info('[MOCK OTP] Development OTP generated', [
                'phone' => substr($phone, 0, 4) . '****',
                'otp'   => $otp, // MOCK ONLY — remove when real provider is integrated
            ]);

            $response = [
                'success' => true,
                'message' => 'Kode OTP telah dikirim ke nomor Anda.',
            ];

            // DEVELOPMENT ONLY: expose OTP in response for testability
            if (config('app.env') === 'local') {
                $response['dev_only'] = [
                    '__note' => 'DEVELOPMENT ONLY — hapus sebelum production',
                    'otp'    => $otp,
                ];
            }

            return response()->json($response, 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Request OTP failed', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses permintaan OTP.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/auth/citizen/verify-otp
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Verify OTP and issue a Sanctum token for the Citizen.
     *
     * - Resolves Citizen from phone_number (NOT from request input citizen_id).
     * - Checks lock state (after 3 failed attempts → 15 min lock).
     * - Validates OTP against hashed value in cache.
     * - On success: resets attempt state, issues $citizen->createToken().
     * - On failure: increments attempt count, locks if threshold exceeded.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone_number' => ['required', 'string'],
                'otp'          => ['required', 'string', 'size:6'],
            ]);

            $phone = $validated['phone_number'];
            $otp   = $validated['otp'];

            // ── Check if account is locked ────────────────────────────────
            if (Cache::has($this->lockKey($phone))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun terkunci sementara karena terlalu banyak percobaan OTP yang salah. Coba lagi dalam 15 menit.',
                    'locked'  => true,
                ], 429);
            }

            // ── Resolve Citizen — identity from DB, NOT from request ──────
            $citizen = Citizen::where('phone_number', $phone)->first();

            if (!$citizen) {
                // Do not reveal whether phone exists; generic error
                return response()->json([
                    'success' => false,
                    'message' => 'OTP tidak valid.',
                ], 401);
            }

            // ── Check OTP challenge exists in cache ───────────────────────
            $cachedHash = Cache::get($this->otpKey($phone));

            if (!$cachedHash) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta OTP baru.',
                ], 401);
            }

            // ── Validate OTP ──────────────────────────────────────────────
            if (!password_verify($otp, $cachedHash)) {
                // Increment failed attempts
                $attempts = (int) Cache::get($this->attemptsKey($phone), 0) + 1;
                Cache::put($this->attemptsKey($phone), $attempts, now()->addMinutes(self::OTP_LOCK_MINUTES));

                if ($attempts >= self::OTP_MAX_ATTEMPTS) {
                    // Lock the account for 15 minutes
                    Cache::put($this->lockKey($phone), true, now()->addMinutes(self::OTP_LOCK_MINUTES));
                    Cache::forget($this->otpKey($phone));
                    Cache::forget($this->attemptsKey($phone));

                    return response()->json([
                        'success' => false,
                        'message' => 'Terlalu banyak percobaan OTP yang salah. Akun dikunci selama 15 menit.',
                        'locked'  => true,
                    ], 429);
                }

                $remaining = self::OTP_MAX_ATTEMPTS - $attempts;
                return response()->json([
                    'success'            => false,
                    'message'            => "OTP tidak valid. Sisa percobaan: {$remaining}.",
                    'attempts_remaining' => $remaining,
                ], 401);
            }

            // ── OTP correct: clean up state ───────────────────────────────
            Cache::forget($this->otpKey($phone));
            Cache::forget($this->attemptsKey($phone));
            Cache::forget($this->lockKey($phone));

            // ── Issue Sanctum token — citizen_id from authenticated model ──
            $token = $citizen->createToken('citizen-auth')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Autentikasi berhasil.',
                'data'    => [
                    'token'      => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Verify OTP failed', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memverifikasi OTP.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/auth/citizen/me  (protected by auth:citizen middleware)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Return authenticated Citizen identity — FOUNDATION VERIFICATION ONLY.
     *
     * citizen_id is resolved from the Sanctum token, not from any request input.
     * This endpoint is for verifying that auth:citizen middleware works correctly.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Citizen $citizen */
        $citizen = $request->user('citizen');

        return response()->json([
            'success' => true,
            'data'    => [
                'citizen_id'   => $citizen->id,
                'phone_number' => $citizen->phone_number,
            ],
        ], 200);
    }
}
