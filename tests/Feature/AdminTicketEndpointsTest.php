<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Ticket;
use Firebase\JWT\JWT;

class AdminTicketEndpointsTest extends TestCase
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

    /** Helper to create an admin user bound to a Supabase UID */
    private function createLocalAdmin(string $email = 'admin@example.com', ?string $supabaseId = null, string $role = 'admin'): User
    {
        $user = new User();
        $user->name = 'Local Admin';
        $user->email = $email;
        $user->supabase_id = $supabaseId ?? $this->defaultSub;
        $user->password = bcrypt('securepassword123');
        $user->role = $role;
        $user->save();
        return $user;
    }

    /** Helper to generate valid JWT */
    private function makeToken(array $payload, string $alg = 'HS256', ?string $secret = null): string
    {
        $defaultClaims = [
            'sub'   => $this->defaultSub,
            'iss'   => 'https://test-project.supabase.co/auth/v1',
            'aud'   => 'https://test-project.supabase.co',
            'email' => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            // Token expiration (1 hour from now) required by middleware
            'exp'   => time() + 3600,
        ];
        $payload = array_merge($defaultClaims, $payload);
        $secret = $secret ?? $this->secret;
        return JWT::encode($payload, $secret, $alg);
    }

    public function test_admin_can_list_tickets()
    {
        // Seed data
        Ticket::factory()->count(3)->create(['status' => 'SUBMITTED']);
        $this->createLocalAdmin();
        $token = $this->makeToken([]);

        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson('/api/admin/tickets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_list_without_token_returns_401()
    {
        $response = $this->getJson('/api/admin/tickets');
        $response->assertStatus(401);
    }

    public function test_admin_list_with_non_admin_role_returns_403()
    {
        Ticket::factory()->create(['status' => 'SUBMITTED']);
        $this->createLocalAdmin('user@example.com', $this->defaultSub, 'admin'); // local admin exists
        $token = $this->makeToken(['app_metadata' => ['role' => 'citizen']]);
        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson('/api/admin/tickets');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_ticket_detail()
    {
        $ticket = Ticket::factory()->create(['status' => 'SUBMITTED']);
        $this->createLocalAdmin();
        $token = $this->makeToken([]);

        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson("/api/admin/tickets/{$ticket->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['ticket_id', 'status', 'display_status', 'report', 'history']]);
    }

    public function test_admin_view_nonexistent_ticket_returns_404()
    {
        $this->createLocalAdmin();
        $token = $this->makeToken([]);
        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson('/api/admin/tickets/9999');
        $response->assertStatus(404);
    }

    public function test_admin_export_csv_successful()
    {
        Ticket::factory()->count(2)->create(['status' => 'SUBMITTED']);
        $this->createLocalAdmin();
        $token = $this->makeToken([]);

        $response = $this->withHeaders($this->authHeader($token))
                         ->get('/api/admin/tickets/export/csv');
        $response->assertStatus(200);
        $this->assertStringStartsWith(
            'text/csv', $response->headers->get('Content-Type')
        );
        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));
        $this->assertEquals('ticket_id,status,report_id,description,latitude,longitude,created_at', $lines[0]);
        $this->assertCount(3, $lines);
    }

    public function test_admin_export_route_is_not_caught_by_dynamic_route()
    {
        $this->createLocalAdmin();
        $token = $this->makeToken([]);
        $response = $this->withHeaders($this->authHeader($token))
                         ->get('/api/admin/tickets/export/csv');
        $response->assertStatus(200);
    }

    public function test_admin_dashboard_returns_metrics_and_urgent_tickets()
    {
        $this->createLocalAdmin();
        $token = $this->makeToken([]);
        $response = $this->withHeaders($this->authHeader($token))
                         ->get('/api/admin/dashboard');
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_laporan_hari_ini',
                         'rata_rata_kekeruhan',
                         'active_maintenance',
                         'system_uptime',
                         'tren_laporan' => ['labels', 'data'],
                         'urgent_tickets',
                     ],
                 ]);
    }

    public function test_admin_can_list_and_create_iot_nodes()
    {
        $this->createLocalAdmin();
        $token = $this->makeToken([]);

        // Create node
        $postResponse = $this->withHeaders($this->authHeader($token))
                             ->postJson('/api/admin/nodes', [
                                 'hardware_id' => 'ESP32-TARAKAN-TEST-01',
                                 'latitude'    => 3.3274,
                                 'longitude'   => 117.5878,
                                 'is_active'   => true,
                             ]);
        $postResponse->assertStatus(201)
                     ->assertJson([
                         'success' => true,
                         'data'    => [
                             'hardware_id' => 'ESP32-TARAKAN-TEST-01',
                             'latitude'    => 3.3274,
                             'longitude'   => 117.5878,
                             'is_active'   => true,
                         ],
                     ]);

        // List nodes
        $getResponse = $this->withHeaders($this->authHeader($token))
                            ->getJson('/api/admin/nodes');
        $getResponse->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'data' => [
                            '*' => [
                                'id',
                                'hardware_id',
                                'latitude',
                                'longitude',
                                'is_active',
                                'status',
                            ],
                        ],
                    ]);
    }
}
