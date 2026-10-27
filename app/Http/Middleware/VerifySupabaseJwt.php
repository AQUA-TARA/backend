<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class VerifySupabaseJwt
{
    /**
     * Handle an incoming request.
     *
     * Expects Authorization: Bearer <supabase_jwt>
     * Verifies signature using SUPABASE_JWT_SECRET (HS256) or public keys for RS256.
     * On success, retrieves (or creates) a local user by email and sets the auth user.
     * Only users with app_metadata.role == 'admin' are allowed to pass.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Authorization token missing'], 401);
        }

        $token = trim(substr($authHeader, 7));
        // Verify token structure
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return response()->json(['message' => 'Malformed Supabase token'], 401);
        }
        $headerJson = base64_decode(strtr($parts[0], '-_', '+/'));
        $header = json_decode($headerJson);
        $alg = $header->alg ?? null;

        if ($alg === 'HS256') {
            // Use a dedicated long test secret when running under the testing environment.
            if (app()->environment('testing')) {
                // Allow overriding via env variable for flexibility.
                $secret = env('SUPABASE_JWT_TEST_SECRET') ?: 'test_supabase_secret_32_chars_long!!';
            } else {
                $secret = env('SUPABASE_JWT_SECRET');
            }
            // Ensure we have a secret string.
            $secret = (string) $secret;
            // Enforce minimum length of 32 bytes for HS256 keys (per firebase/php-jwt requirements).
            if (strlen($secret) < 32) {
                return response()->json(['message' => 'Supabase JWT secret too short'], 401);
            }
            try {
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid Supabase token'], 401);
            }
        } elseif (in_array($alg, ['RS256', 'ES256'])) {
            $projectRef = env('SUPABASE_PROJECT_REF');
            if (!$projectRef) {
                // Attempt to derive project reference from SUPABASE_URL if not explicitly set.
                $supabaseUrl = env('SUPABASE_URL');
                if ($supabaseUrl && preg_match('/https:\/\/([a-z0-9]+)\.supabase\.co/', $supabaseUrl, $matches)) {
                    $projectRef = $matches[1];
                }
            }
            if (!$projectRef) {
                return response()->json(['message' => 'Supabase project reference not configured'], 401);
            }
            
            $jwksUrl = "https://{$projectRef}.supabase.co/auth/v1/.well-known/jwks.json";

            try {
                $response = Http::acceptJson()
                    ->timeout(10)
                    ->get($jwksUrl);

                if (!$response->successful()) {
                    \Log::warning('Supabase JWKS request failed', [
                        'status' => $response->status(),
                    ]);

                    return response()->json([
                        'message' => 'Unable to fetch Supabase JWKS',
                    ], 503);
                }

                $jwks = $response->json();

                if (!is_array($jwks) ||
                    empty($jwks['keys']) ||
                    !is_array($jwks['keys'])) {
                    \Log::warning('Supabase JWKS response has invalid structure');

                    return response()->json([
                        'message' => 'Invalid Supabase JWKS response',
                    ], 503);
                }
            } catch (\Throwable $e) {
                \Log::warning('Supabase JWKS request exception', [
                    'exception' => get_class($e),
                ]);

                return response()->json([
                    'message' => 'Unable to fetch Supabase JWKS',
                ], 503);
            }

            $decoded = null;
            try {
                $keySet = \Firebase\JWT\JWK::parseKeySet($jwks, $alg);

                $kid = $header->kid ?? null;

                if (!is_string($kid) || !isset($keySet[$kid])) {
                    return response()->json([
                        'message' => 'No matching Supabase signing key',
                    ], 401);
                }

                $decoded = JWT::decode($token, $keySet[$kid]);
            } catch (\Throwable $e) {
                \Log::warning('Supabase JWT verification failed', [
                    'exception' => get_class($e),
                ]);

                return response()->json([
                    'message' => 'Invalid Supabase token',
                ], 401);
            }
            if (!$decoded) {
                return response()->json(['message' => 'Invalid Supabase token'], 401);
            }
        } else {
            return response()->json(['message' => 'Unsupported token algorithm'], 401);
        }

        // Validate exp claim presence and type
        if (!isset($decoded->exp) || !is_int($decoded->exp)) {
            return response()->json(['message' => 'Supabase token missing or invalid exp claim'], 401);
        }
        // Existing expiration time check
        if ($decoded->exp < time()) {
            return response()->json(['message' => 'Supabase token expired'], 401);
        }

        // Validate issuer (iss) and audience (aud) based on Supabase configuration
        $supabaseUrl = env('SUPABASE_URL');
        $projectRef   = env('SUPABASE_PROJECT_REF');

        
        if ($supabaseUrl) {
            $expectedIssuer = rtrim($supabaseUrl, '/') . '/auth/v1';
        } elseif ($projectRef) {
            $expectedIssuer = "https://{$projectRef}.supabase.co/auth/v1";
        } else {
            return response()->json([
                'message' => 'Supabase configuration missing',
            ], 500);
        }

        $expectedAudience = 'authenticated';

        // Validate issuer claim
        if (!isset($decoded->iss) || $decoded->iss !== $expectedIssuer) {
            return response()->json(['message' => 'Invalid token issuer'], 401);
        }

        // Validate audience claim – can be string or array per JWT spec.
        // Supabase user tokens use 'authenticated', while project URL may also be used in tests/configs.
        if (!isset($decoded->aud)) {
            return response()->json(['message' => 'Missing token audience'], 401);
        }
        $audClaim = $decoded->aud;
        $allowedAudiences = ['authenticated', $supabaseUrl, rtrim($supabaseUrl ?? '', '/')];
        $audValid = false;
        if (is_string($audClaim)) {
            $audValid = in_array($audClaim, $allowedAudiences, true);
        } elseif (is_array($audClaim)) {
            $audValid = !empty(array_intersect($audClaim, $allowedAudiences));
        }
        if (!$audValid) {
            return response()->json(['message' => 'Invalid token audience'], 401);
        }

        // Role enforcement – admin only
        $appMeta = $decoded->app_metadata ?? null;
        $role = null;
        if (is_object($appMeta)) {
            $role = $appMeta->role ?? null;
        } elseif (is_array($appMeta)) {
            $role = $appMeta['role'] ?? null;
        }
        if (empty($role) || $role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 403);
        }

        // Require `sub` claim – Supabase always includes it; absence indicates a non-standard or forged token.
        if (empty($decoded->sub) || !is_string($decoded->sub)) {
            return response()->json(['message' => 'Token missing required subject claim'], 401);
        }

        // Require `email` claim in JWT.
        if (empty($decoded->email) || !is_string($decoded->email)) {
            return response()->json(['message' => 'Token missing required email claim'], 401);
        }

        // Dual-Authorization: Bind local user explicitly by supabase_id (Supabase UID / sub claim).
        // Check schema preparedness to fail closed safely without 500 error if migration is pending.
        try {
            $hasSupabaseId = \Illuminate\Support\Facades\Schema::hasColumn('users', 'supabase_id');
            $hasRole = \Illuminate\Support\Facades\Schema::hasColumn('users', 'role');
        } catch (\Throwable $e) {
            // Fail closed securely if DB schema cannot be inspected
            return response()->json(['success' => false, 'message' => 'Unauthorized. Authentication service unavailable.'], 403);
        }

        if (!$hasSupabaseId || !$hasRole) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Authentication schema pending configuration.'], 403);
        }

        // Look up local user strictly by supabase_id matching the JWT sub claim.
        // No auto-provisioning, no matching by email alone.
        $user = User::where('supabase_id', $decoded->sub)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Account not registered or unbound.'], 403);
        }

        // Enforce local user role == 'admin'
        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Admin privileges required.'], 403);
        }

        // Set the authenticated user for the current request
        auth()->setUser($user);

        return $next($request);
    }
}
?>
