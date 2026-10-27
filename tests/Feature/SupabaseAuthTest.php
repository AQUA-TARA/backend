<?php

namespace Tests\Feature;

use Tests\TestCase;
use Firebase\JWT\JWT;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupabaseAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test_supabase_secret_32_chars_long!!';
    private string $defaultSub = 'test-supabase-uuid-00000000-0000-0000-0000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        // Provide Supabase configuration for tests
        putenv('SUPABASE_URL=https://test-project.supabase.co');
    }

    /** Helper to build Authorization header */
    private function authHeader(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    /** Helper to seed an admin user in local database bound to a Supabase UID */
    private function createLocalAdmin(
        string $email = 'admin@example.com',
        ?string $supabaseId = null,
        string $role = 'admin'
    ): User {
        $user = new User();
        $user->name = 'Local Admin';
        $user->email = $email;
        $user->supabase_id = $supabaseId ?? $this->defaultSub;
        $user->password = bcrypt('securepassword123');
        $user->role = $role;
        $user->save();
        return $user;
    }

    /** Helper to generate valid test JWT with default claims */
    private function makeToken(array $payload, string $alg = 'HS256', ?string $secret = null): string
    {
        $defaultClaims = [
            'sub'   => $this->defaultSub,
            'iss'   => 'https://test-project.supabase.co/auth/v1',
            'aud'   => 'https://test-project.supabase.co',
            'email' => 'admin@example.com',
        ];
        $payload = array_merge($defaultClaims, $payload);
        $secret = $secret ?? $this->secret;
        return JWT::encode($payload, $secret, $alg);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. TOKEN VALIDATION (401s)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_missing_token_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $response = $this->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_malformed_token_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $response = $this->withHeaders(['Authorization' => 'Bearer malformed.token.string'])
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $badToken = $this->makeToken([
            'email' => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ], 'HS256', 'wrong_secret_very_long_key_for_testing_purposes_0123456789');
        $response = $this->withHeaders($this->authHeader($badToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_expired_token_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $expiredToken = $this->makeToken([
            'email' => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() - 10,
        ]);
        $response = $this->withHeaders($this->authHeader($expiredToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_token_without_sub_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $payload = [
            'iss'          => 'https://test-project.supabase.co/auth/v1',
            'aud'          => 'https://test-project.supabase.co',
            'email'        => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ];
        $token = JWT::encode($payload, $this->secret, 'HS256');
        $response = $this->withHeaders($this->authHeader($token))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_token_without_email_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $payload = [
            'sub'          => $this->defaultSub,
            'iss'          => 'https://test-project.supabase.co/auth/v1',
            'aud'          => 'https://test-project.supabase.co',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ];
        $token = JWT::encode($payload, $this->secret, 'HS256');
        $response = $this->withHeaders($this->authHeader($token))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_token_with_wrong_issuer_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $token = $this->makeToken([
            'iss'          => 'https://malicious-issuer.supabase.co/auth/v1',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ]);
        $response = $this->withHeaders($this->authHeader($token))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_token_with_wrong_audience_returns_401(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $token = $this->makeToken([
            'aud'          => 'https://wrong-audience.supabase.co',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ]);
        $response = $this->withHeaders($this->authHeader($token))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
    }

    public function test_invalid_token_does_not_leak_error_detail(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $badToken = $this->makeToken([
            'email' => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ], 'HS256', 'wrong_secret_very_long_key_for_testing_purposes_0123456789');
        $response = $this->withHeaders($this->authHeader($badToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);
        $response->assertStatus(401);
        $response->assertJsonMissingPath('error');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. DUAL-AUTHORIZATION & IDENTITY BINDING (403s & 200s)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_valid_admin_token_with_bound_local_admin_allows_status_update(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $admin = $this->createLocalAdmin('admin@example.com', $this->defaultSub, 'admin');

        $adminToken = $this->makeToken([
            'sub'          => $this->defaultSub,
            'email'        => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ]);

        $response = $this->withHeaders($this->authHeader($adminToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'IN_REVIEW']);
    }

    public function test_token_with_non_admin_supabase_role_is_denied(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $this->createLocalAdmin('user@example.com', $this->defaultSub, 'admin');

        $citizenToken = $this->makeToken([
            'email'        => 'user@example.com',
            'app_metadata' => ['role' => 'citizen'],
            'exp'          => time() + 3600,
        ]);

        $response = $this->withHeaders($this->authHeader($citizenToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        $response->assertStatus(403);
    }

    public function test_user_metadata_role_is_ignored_for_admin_privilege(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $this->createLocalAdmin('citizen@example.com', $this->defaultSub, 'admin');

        $spoofedToken = $this->makeToken([
            'email'         => 'citizen@example.com',
            'user_metadata' => ['role' => 'admin'],
            'app_metadata'  => ['role' => 'citizen'],
            'exp'           => time() + 3600,
        ]);

        $response = $this->withHeaders($this->authHeader($spoofedToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        $response->assertStatus(403);
    }

    public function test_unregistered_supabase_uid_returns_403_no_auto_provisioning(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        // Valid Supabase token with admin role, but sub is not in local DB
        $unboundToken = $this->makeToken([
            'sub'          => 'unregistered-supabase-uid-9999-000000000000',
            'email'        => 'unknown@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ]);

        $response = $this->withHeaders($this->authHeader($unboundToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'message' => 'Unauthorized. Account not registered or unbound.']);

        // Verify no account was automatically created
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
    }

    public function test_same_email_different_uid_is_denied(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        // Local admin has legitimate UID-A
        $this->createLocalAdmin('admin@example.com', 'legitimate-uid-aaaa-0000-000000000001', 'admin');

        // Attacker or distinct Supabase account has same email, but different UID-B
        $imposterToken = $this->makeToken([
            'sub'          => 'imposter-uid-bbbb-0000-000000000002',
            'email'        => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ]);

        $response = $this->withHeaders($this->authHeader($imposterToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'message' => 'Unauthorized. Account not registered or unbound.']);
    }

    public function test_local_user_role_not_admin_is_denied(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        // Local user exists and matches UID, but role is 'user'
        $this->createLocalAdmin('regular@example.com', $this->defaultSub, 'user');

        $token = $this->makeToken([
            'sub'          => $this->defaultSub,
            'email'        => 'regular@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ]);

        $response = $this->withHeaders($this->authHeader($token))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'message' => 'Unauthorized. Admin privileges required.']);
    }

    public function test_email_heuristics_do_not_grant_admin_access(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $this->createLocalAdmin('admin.spoofed@example.com', $this->defaultSub, 'admin');

        $heuristicsToken = $this->makeToken([
            'email'        => 'admin.spoofed@example.com',
            'app_metadata' => ['role' => 'citizen'],
            'exp'          => time() + 3600,
        ]);

        $response = $this->withHeaders($this->authHeader($heuristicsToken))
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. CROSS-BOUNDARY ENDPOINT AUTHORIZATION (CITIZEN vs ADMIN)
    // ─────────────────────────────────────────────────────────────────────────

    /** Citizen Sanctum Bearer token must be rejected by admin endpoints with 401 (not 403 or 200) */
    public function test_citizen_sanctum_token_rejected_on_admin_endpoint(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $citizen = \App\Models\Citizen::create(['phone_number' => '081299998888']);
        $citizenSanctumToken = $citizen->createToken('citizen-auth')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$citizenSanctumToken}"])
            ->putJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'IN_REVIEW']);

        // Since it's not a valid Supabase JWT, VerifySupabaseJwt must reject with 401
        $response->assertStatus(401);
    }

    /** Admin Supabase JWT must NOT be accepted on citizen-protected endpoint (GET /api/citizen/history) */
    public function test_admin_supabase_jwt_rejected_on_citizen_history_endpoint(): void
    {
        $this->createLocalAdmin('admin@example.com', $this->defaultSub, 'admin');
        $adminToken = $this->makeToken([
            'sub'          => $this->defaultSub,
            'email'        => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp'          => time() + 3600,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->getJson('/api/citizen/history');

        // auth:citizen guard expects a Sanctum token belonging to Citizen model
        $response->assertStatus(401);
    }

    /** Unauthenticated request to citizen history endpoint is rejected with 401 */
    public function test_unauthenticated_request_rejected_on_citizen_history_endpoint(): void
    {
        $response = $this->getJson('/api/citizen/history');
        $response->assertStatus(401);
    }

    /** Authenticated citizen successfully accesses their own citizen history */
    public function test_authenticated_citizen_can_access_citizen_history(): void
    {
        $citizen = \App\Models\Citizen::create(['phone_number' => '081277776666']);
        $citizenToken = $citizen->createToken('citizen-auth')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$citizenToken}"])
            ->getJson('/api/citizen/history');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'meta'    => [
                'citizen_id' => $citizen->id,
            ],
        ]);
    }
}
