<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Report;
use App\Models\Ticket;
use Firebase\JWT\JWT;

class TicketCodeAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test_supabase_secret_32_chars_long!!';
    private string $defaultSub = 'test-supabase-uuid-00000000-0000-0000-0000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        putenv('SUPABASE_URL=https://test-project.supabase.co');
    }

    private function authHeader(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

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

    private function makeToken(array $payload = []): string
    {
        $defaultClaims = [
            'sub'   => $this->defaultSub,
            'iss'   => 'https://test-project.supabase.co/auth/v1',
            'aud'   => 'authenticated',
            'email' => 'admin@example.com',
            'app_metadata' => ['role' => 'admin'],
            'exp'   => time() + 3600,
        ];
        $payload = array_merge($defaultClaims, $payload);
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    private function createReport(string $description = 'Air keruh berlumpur', string $lat = '3.32740000', string $lng = '117.58780000'): Report
    {
        return Report::create([
            'citizen_id'  => null,
            'description' => $description,
            'latitude'    => $lat,
            'longitude'   => $lng,
            'photo_path'  => 'reports/test.jpg',
        ]);
    }

    public function test_ticket_code_generation_follows_aqt_yymmdd_nnnn_format(): void
    {
        $date = \Carbon\Carbon::parse('2026-04-20');
        $code1 = Ticket::generateNextTicketCode($date);
        $this->assertEquals('AQT-260420-0001', $code1);

        $report1 = $this->createReport();
        Ticket::create([
            'report_id'   => $report1->id,
            'ticket_code' => $code1,
            'status'      => 'SUBMITTED',
        ]);

        $code2 = Ticket::generateNextTicketCode($date);
        $this->assertEquals('AQT-260420-0002', $code2);
    }

    public function test_admin_can_search_tickets_by_ticket_code(): void
    {
        $this->createLocalAdmin();
        $token = $this->makeToken();

        $repA = $this->createReport('Pipa bocor di Karang Anyar');
        $ticketA = Ticket::create([
            'report_id'   => $repA->id,
            'ticket_code' => 'AQT-261023-0001',
            'status'      => 'SUBMITTED',
        ]);

        $repB = $this->createReport('Air berbau belerang');
        $ticketB = Ticket::create([
            'report_id'   => $repB->id,
            'ticket_code' => 'AQT-261023-0002',
            'status'      => 'SUBMITTED',
        ]);

        // Partial and case-insensitive search by ticket_code
        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson('/api/admin/tickets?q=0001');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('AQT-261023-0001', $data[0]['ticket_code']);
    }

    public function test_admin_can_search_tickets_by_cluster_code(): void
    {
        $this->createLocalAdmin();
        $token = $this->makeToken();

        $repA = $this->createReport('Cluster issue area');
        Ticket::create([
            'report_id'    => $repA->id,
            'ticket_code'  => 'AQT-261023-0010',
            'cluster_code' => 'AQT-CLUSTER-001',
            'status'       => 'IN_REVIEW',
        ]);

        $repB = $this->createReport('Isolated issue');
        Ticket::create([
            'report_id'    => $repB->id,
            'ticket_code'  => 'AQT-261023-0011',
            'cluster_code' => null,
            'status'       => 'SUBMITTED',
        ]);

        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson('/api/admin/tickets?q=cluster-001');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('AQT-CLUSTER-001', $data[0]['cluster_code']);
    }

    public function test_admin_can_search_tickets_by_complaint_description(): void
    {
        $this->createLocalAdmin();
        $token = $this->makeToken();

        $repA = $this->createReport('Air sangat keruh di Juata Laut');
        Ticket::create([
            'report_id'   => $repA->id,
            'ticket_code' => 'AQT-261023-0021',
            'status'      => 'SUBMITTED',
        ]);

        $repB = $this->createReport('Tekanan air sangat rendah');
        Ticket::create([
            'report_id'   => $repB->id,
            'ticket_code' => 'AQT-261023-0022',
            'status'      => 'SUBMITTED',
        ]);

        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson('/api/admin/tickets?q=keruh');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('AQT-261023-0021', $data[0]['ticket_code']);
    }

    public function test_admin_view_ticket_detail_supports_ticket_code(): void
    {
        $this->createLocalAdmin();
        $token = $this->makeToken();

        $rep = $this->createReport('Detail lookup test');
        $ticket = Ticket::create([
            'report_id'   => $rep->id,
            'ticket_code' => 'AQT-261023-0099',
            'status'      => 'SUBMITTED',
        ]);

        $response = $this->withHeaders($this->authHeader($token))
                         ->getJson('/api/admin/tickets/AQT-261023-0099');

        $response->assertStatus(200);
        $this->assertEquals('AQT-261023-0099', $response->json('data.ticket_code'));
    }
}
