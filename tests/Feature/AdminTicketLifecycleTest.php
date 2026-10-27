<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Firebase\JWT\JWT;

class AdminTicketLifecycleTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private string $secret = 'test_supabase_secret_32_chars_long!!';

    protected function setUp(): void
    {
        parent::setUp();
        putenv('SUPABASE_URL=https://test-project.supabase.co');
    }

    /** Helper to build Authorization header */
    private function authHeader(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    /** Generate a JWT with given payload and algorithm */
    private function makeToken(array $payload, string $alg = 'HS256', ?string $secret = null): string
    {
        $defaultClaims = [
            'iss' => 'https://test-project.supabase.co/auth/v1',
            'aud' => 'https://test-project.supabase.co',
            'sub' => $payload['sub'] ?? ($this->currentAdminSub ?? 'test-supabase-admin-sub-default'),
        ];
        $payload = array_merge($defaultClaims, $payload);
        $secret = $secret ?? $this->secret;
        return JWT::encode($payload, $secret, $alg);
    }

    /**
     * Helper to create a valid Report fixture.
     */
    private function createReportFixture(?int $citizenId = null): Report
    {
        return Report::create([
            'citizen_id'  => $citizenId,
            'description' => 'Laporan uji lifecycle tiket admin.',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo_path'  => 'reports/test_admin_lifecycle.jpg',
        ]);
    }

    private ?string $currentAdminSub = null;

    /**
     * Helper to create an Admin User fixture with a unique email and matching supabase_id.
     */
    private function createAdminFixture(string $suffix = '1', ?string $supabaseId = null): User
    {
        $sub = $supabaseId ?? "supabase-admin-sub-{$suffix}-" . uniqid();
        $this->currentAdminSub = $sub;

        $user = new User();
        $user->name        = "Admin Staff {$suffix}";
        $user->email       = "admin_test_{$suffix}_" . uniqid() . "@aquatara.test";
        $user->password    = bcrypt('secretpassword123');
        $user->supabase_id = $sub;
        $user->role        = 'admin';
        $user->save();
        return $user;
    }

    /**
     * 1. Full canonical transition sequence:
     * SUBMITTED -> IN_REVIEW -> CONFIRMED -> IN_PROGRESS -> CLOSED.
     * Verifies HTTP 200, status, display_status, updated_at, and TicketStatusHistory audit trail.
     */
    public function test_admin_can_advance_ticket_through_full_valid_lifecycle(): void
    {
        $admin = $this->createAdminFixture('lifecycle');
        $adminToken = $this->makeToken([
            'email' => $admin->email,
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $transitions = [
            ['target' => 'IN_REVIEW',   'display' => 'Sedang Ditinjau',        'old' => 'SUBMITTED'],
            ['target' => 'CONFIRMED',   'display' => 'Laporan Dikonfirmasi',   'old' => 'IN_REVIEW'],
            ['target' => 'IN_PROGRESS', 'display' => 'Sedang Ditangani',       'old' => 'CONFIRMED'],
            ['target' => 'CLOSED',      'display' => 'Selesai',                'old' => 'IN_PROGRESS'],
        ];

        foreach ($transitions as $step) {
            $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
                ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                    'status' => $step['target'],
                ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Status tiket berhasil diperbarui.',
                    'data'    => [
                        'ticket_id'      => $ticket->id,
                        'status'         => $step['target'],
                        'display_status' => $step['display'],
                    ],
                ]);

            $this->assertNotEmpty($response->json('data.updated_at'));

            // Verify database update
            $this->assertEquals($step['target'], $ticket->fresh()->status);

            // Verify audit trail created in ticket_status_histories
            $this->assertDatabaseHas('ticket_status_histories', [
                'ticket_id'  => $ticket->id,
                'old_status' => $step['old'],
                'new_status' => $step['target'],
                'actor_id'   => $admin->id,
            ]);
        }

        // Verify total history records for this ticket
        $this->assertEquals(4, TicketStatusHistory::where('ticket_id', $ticket->id)->count());
    }

    /**
     * 2. Terminal state enforcement: CLOSED ticket cannot be transitioned further.
     */
    public function test_closed_ticket_is_terminal_and_cannot_be_transitioned(): void
    {
        $admin = $this->createAdminFixture('terminal');
        $adminToken = $this->makeToken([
            'email' => $admin->email,
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'CLOSED',
        ]);

        // Attempt to reopen or transition from CLOSED to any status
        $targets = ['SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS'];

        foreach ($targets as $targetStatus) {
            $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
                ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                    'status' => $targetStatus,
                ]);

            $response->assertStatus(422)
                ->assertJson([
                    'success'          => false,
                    'message'          => 'Transisi status tidak valid.',
                    'current_status'   => 'CLOSED',
                    'requested_status' => $targetStatus,
                    'allowed_next'     => null,
                ]);

            // Database state must remain CLOSED
            $this->assertEquals('CLOSED', $ticket->fresh()->status);
        }
    }

    /**
     * 3. Forward skip transition prohibited: Cannot skip stages (e.g. SUBMITTED -> CONFIRMED).
     */
    public function test_skipping_stages_is_prohibited(): void
    {
        $admin = $this->createAdminFixture('skip');
        $adminToken = $this->makeToken([
            'email' => $admin->email,
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        // Try skipping from SUBMITTED directly to CONFIRMED
        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                'status' => 'CONFIRMED',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success'          => false,
                'message'          => 'Transisi status tidak valid.',
                'current_status'   => 'SUBMITTED',
                'requested_status' => 'CONFIRMED',
                'allowed_next'     => 'IN_REVIEW',
            ]);

        $this->assertEquals('SUBMITTED', $ticket->fresh()->status);
    }

    /**
     * 4. Backward transition prohibited: Cannot revert status (e.g. IN_PROGRESS -> IN_REVIEW).
     */
    public function test_backward_transition_is_prohibited(): void
    {
        $admin = $this->createAdminFixture('backward');
        $adminToken = $this->makeToken([
            'email' => $admin->email,
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'IN_PROGRESS',
        ]);

        // Try moving backward to IN_REVIEW
        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                'status' => 'IN_REVIEW',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success'          => false,
                'message'          => 'Transisi status tidak valid.',
                'current_status'   => 'IN_PROGRESS',
                'requested_status' => 'IN_REVIEW',
                'allowed_next'     => 'CLOSED',
            ]);

        $this->assertEquals('IN_PROGRESS', $ticket->fresh()->status);
    }

    /**
     * 5. No-op transition: Requesting the current status returns 422 same_status error.
     */
    public function test_updating_to_same_status_returns_422(): void
    {
        $admin = $this->createAdminFixture('same');
        $adminToken = $this->makeToken([
            'email' => $admin->email,
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'IN_REVIEW',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                'status' => 'IN_REVIEW',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success'        => false,
                'message'        => 'Status tiket sudah sama. Tidak ada perubahan.',
                'current_status' => 'IN_REVIEW',
            ]);
    }

    /**
     * 6. Unknown / Invalid status string rejected by input validation.
     */
    public function test_unknown_status_value_rejected_by_validation(): void
    {
        $admin = $this->createAdminFixture('val');
        $adminToken = $this->makeToken([
            'email' => $admin->email,
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        // Non-enum status string
        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                'status' => 'INVALID_STATUS_CODE',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Missing status field
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/admin/tickets/{$ticket->id}/status", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * 7. Non-existent ticket ID returns HTTP 404.
     */
    public function test_non_existent_ticket_returns_404(): void
    {
        $admin = $this->createAdminFixture('notfound');
        $adminToken = $this->makeToken([
            'email' => $admin->email,
            'app_metadata' => ['role' => 'admin'],
            'exp' => time() + 3600,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson('/api/admin/tickets/99999999/status', [
                'status' => 'IN_REVIEW',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Tiket tidak ditemukan.',
            ]);
    }

    /**
     * 8. Unauthenticated request without token returns HTTP 401.
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->putJson("/api/admin/tickets/{$ticket->id}/status", [
            'status' => 'IN_REVIEW',
        ]);

        $response->assertStatus(401);
    }

    /**
     * 9. Request with invalid or malformed Bearer token returns HTTP 401.
     */
    public function test_invalid_token_returns_401(): void
    {
        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer invalid-token-sample')
            ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                'status' => 'IN_REVIEW',
            ]);

        $response->assertStatus(401);
    }

    /**
     * 10. RBAC Boundary: Citizen Sanctum token is rejected on Admin route.
     * Must return HTTP 401 with "Unauthorized. Admin access required."
     */
    public function test_citizen_token_rejected_on_admin_ticket_status_route(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765470']);
        $citizenToken = $this->makeToken([
            'email' => $citizen->email ?? 'citizen@example.com',
            'app_metadata' => ['role' => 'citizen'],
            'exp' => time() + 3600,
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$citizenToken}")
            ->putJson("/api/admin/tickets/{$ticket->id}/status", [
                'status' => 'IN_REVIEW',
            ]);

                $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ]);

        // State remains completely unchanged
        $this->assertEquals('SUBMITTED', $ticket->fresh()->status);
        $this->assertEquals(0, TicketStatusHistory::where('ticket_id', $ticket->id)->count());
    }
}
