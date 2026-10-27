<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Report;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CitizenHistoryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1. Authenticated citizen retrieves report history with linked ticket data.
     */
    public function test_authenticated_citizen_receives_history_with_ticket_data(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765460']);
        $token = $citizen->createToken('citizen-auth')->plainTextToken;

        $reportTime = Carbon::parse('2026-10-12 10:00:00');

        $report = Report::create([
            'citizen_id'  => $citizen->id,
            'description' => 'Pipa bocor di depan rumah warga.',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo_path'  => 'reports/test_history_1.jpg',
            'created_at'  => $reportTime,
        ]);

        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/citizen/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    [
                        'report_id'      => $report->id,
                        'ticket_id'      => $ticket->id,
                        'status'         => 'SUBMITTED',
                        'display_status' => 'Diterima',
                        'description'    => 'Pipa bocor di depan rumah warga.',
                    ],
                ],
                'meta'    => [
                    'total'      => 1,
                    'citizen_id' => $citizen->id,
                ],
            ]);
    }

    /**
     * 2. Multi-tenant data isolation: Citizen A cannot see Citizen B's reports.
     */
    public function test_citizen_data_isolation_between_different_citizens(): void
    {
        $citizenA = Citizen::create(['phone_number' => '081298765461']);
        $citizenB = Citizen::create(['phone_number' => '081298765462']);

        $tokenA = $citizenA->createToken('citizen-auth')->plainTextToken;

        // Report for Citizen A
        $reportA = Report::create([
            'citizen_id'  => $citizenA->id,
            'description' => 'Laporan milik Citizen A',
            'latitude'    => -6.20000000,
            'longitude'   => 106.80000000,
            'photo_path'  => 'reports/test_a.jpg',
        ]);
        $ticketA = Ticket::create(['report_id' => $reportA->id, 'status' => 'SUBMITTED']);

        // Report for Citizen B
        $reportB = Report::create([
            'citizen_id'  => $citizenB->id,
            'description' => 'Laporan milik Citizen B',
            'latitude'    => -6.21000000,
            'longitude'   => 106.81000000,
            'photo_path'  => 'reports/test_b.jpg',
        ]);
        Ticket::create(['report_id' => $reportB->id, 'status' => 'SUBMITTED']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/citizen/history');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'success' => true,
                'data'    => [
                    [
                        'report_id' => $reportA->id,
                        'ticket_id' => $ticketA->id,
                    ],
                ],
                'meta'    => [
                    'total'      => 1,
                    'citizen_id' => $citizenA->id,
                ],
            ]);

        // Explicitly assert Citizen B's report_id is not in the response
        $this->assertNotContains($reportB->id, array_column($response->json('data'), 'report_id'));
    }

    /**
     * 3. Anonymous reports (citizen_id = NULL) never appear in citizen history.
     */
    public function test_anonymous_reports_are_excluded_from_history(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765463']);
        $token = $citizen->createToken('citizen-auth')->plainTextToken;

        // Citizen's report
        $reportCitizen = Report::create([
            'citizen_id'  => $citizen->id,
            'description' => 'Laporan dari warga terdaftar',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo_path'  => 'reports/test_citizen.jpg',
        ]);
        $ticketCitizen = Ticket::create(['report_id' => $reportCitizen->id, 'status' => 'SUBMITTED']);

        // Anonymous report
        $reportAnon = Report::create([
            'citizen_id'  => null,
            'description' => 'Laporan anonim publik',
            'latitude'    => -6.20500000,
            'longitude'   => 106.84000000,
            'photo_path'  => 'reports/test_anon.jpg',
        ]);
        Ticket::create(['report_id' => $reportAnon->id, 'status' => 'SUBMITTED']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/citizen/history');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'success' => true,
                'data'    => [
                    [
                        'report_id' => $reportCitizen->id,
                        'ticket_id' => $ticketCitizen->id,
                    ],
                ],
                'meta'    => [
                    'total'      => 1,
                    'citizen_id' => $citizen->id,
                ],
            ]);

        $this->assertNotContains($reportAnon->id, array_column($response->json('data'), 'report_id'));
    }

    /**
     * 4. Empty history returns HTTP 200 with empty array data (not HTTP 404).
     */
    public function test_empty_history_returns_200_with_empty_array(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765464']);
        $token = $citizen->createToken('citizen-auth')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/citizen/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [],
                'meta'    => [
                    'total'      => 0,
                    'citizen_id' => $citizen->id,
                ],
            ]);
    }

    /**
     * 5. Security contract: Query string spoofing (e.g. ?citizen_id=...) is ignored.
     */
    public function test_query_string_citizen_id_is_ignored(): void
    {
        $actualCitizen = Citizen::create(['phone_number' => '081298765465']);
        $victimCitizen = Citizen::create(['phone_number' => '081298765466']);

        $tokenActual = $actualCitizen->createToken('citizen-auth')->plainTextToken;

        // Victim report
        $reportVictim = Report::create([
            'citizen_id'  => $victimCitizen->id,
            'description' => 'Laporan milik korban',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo_path'  => 'reports/victim.jpg',
        ]);
        Ticket::create(['report_id' => $reportVictim->id, 'status' => 'SUBMITTED']);

        // Actual citizen has no reports yet, attempts to query victim's reports via query param
        $response = $this->withHeader('Authorization', "Bearer {$tokenActual}")
            ->getJson("/api/citizen/history?citizen_id={$victimCitizen->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [],
                'meta'    => [
                    'total'      => 0,
                    'citizen_id' => $actualCitizen->id,
                ],
            ]);
    }

    /**
     * 6. Unauthenticated request without token returns HTTP 401.
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/citizen/history');

        $response->assertStatus(401);
    }

    /**
     * 7. Request with invalid or malformed Bearer token returns HTTP 401.
     */
    public function test_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-sample')
            ->getJson('/api/citizen/history');

        $response->assertStatus(401);
    }

    /**
     * 8. Reports are ordered by created_at in descending order.
     */
    public function test_history_ordered_by_created_at_descending(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765467']);
        $token = $citizen->createToken('citizen-auth')->plainTextToken;

        $timeOld = Carbon::parse('2026-10-10 08:00:00');
        $timeMid = Carbon::parse('2026-10-11 12:00:00');
        $timeNew = Carbon::parse('2026-10-12 16:00:00');

        $reportOld = Report::create([
            'citizen_id'  => $citizen->id,
            'description' => 'Laporan Terlama',
            'latitude'    => -6.20000000,
            'longitude'   => 106.80000000,
            'photo_path'  => 'reports/old.jpg',
            'created_at'  => $timeOld,
        ]);
        Ticket::create(['report_id' => $reportOld->id, 'status' => 'SUBMITTED']);

        $reportNew = Report::create([
            'citizen_id'  => $citizen->id,
            'description' => 'Laporan Terbaru',
            'latitude'    => -6.20000000,
            'longitude'   => 106.80000000,
            'photo_path'  => 'reports/new.jpg',
            'created_at'  => $timeNew,
        ]);
        Ticket::create(['report_id' => $reportNew->id, 'status' => 'SUBMITTED']);

        $reportMid = Report::create([
            'citizen_id'  => $citizen->id,
            'description' => 'Laporan Menengah',
            'latitude'    => -6.20000000,
            'longitude'   => 106.80000000,
            'photo_path'  => 'reports/mid.jpg',
            'created_at'  => $timeMid,
        ]);
        Ticket::create(['report_id' => $reportMid->id, 'status' => 'SUBMITTED']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/citizen/history');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');

        // Check descending sequence: Newest (12th) -> Mid (11th) -> Oldest (10th)
        $this->assertEquals($reportNew->id, $data[0]['report_id']);
        $this->assertEquals($reportMid->id, $data[1]['report_id']);
        $this->assertEquals($reportOld->id, $data[2]['report_id']);
    }

    /**
     * 9. Ticket status display mapping matches STATUS_MAP constant in CitizenHistoryController.
     */
    public function test_ticket_status_display_mapping_matches_status_map(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765468']);
        $token = $citizen->createToken('citizen-auth')->plainTextToken;

        $statuses = [
            'SUBMITTED'   => 'Diterima',
            'IN_REVIEW'   => 'Sedang Ditinjau',
            'CONFIRMED'   => 'Laporan Dikonfirmasi',
            'IN_PROGRESS' => 'Sedang Ditangani',
            'CLOSED'      => 'Selesai',
        ];

        foreach ($statuses as $canonicalStatus => $expectedDisplay) {
            $report = Report::create([
                'citizen_id'  => $citizen->id,
                'description' => "Laporan status {$canonicalStatus}",
                'latitude'    => -6.20880000,
                'longitude'   => 106.84560000,
                'photo_path'  => "reports/test_{$canonicalStatus}.jpg",
            ]);
            Ticket::create([
                'report_id' => $report->id,
                'status'    => $canonicalStatus,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/citizen/history');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');

        $data = $response->json('data');

        foreach ($data as $item) {
            $canonical = $item['status'];
            $this->assertArrayHasKey($canonical, $statuses);
            $this->assertEquals($statuses[$canonical], $item['display_status']);
        }
    }
}
