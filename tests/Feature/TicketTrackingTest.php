<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TicketTrackingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Helper to create a Report fixture.
     */
    private function createReportFixture(?int $citizenId = null, string $description = 'Laporan uji tracking tiket.'): Report
    {
        return Report::create([
            'citizen_id'  => $citizenId,
            'description' => $description,
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo_path'  => 'reports/test_tracking_sample.jpg',
        ]);
    }

    /**
     * 1. Valid ticket returns HTTP 200 with accurate JSON structure matching actual source.
     */
    public function test_valid_ticket_returns_200_with_expected_structure(): void
    {
        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'ticket_id'      => $ticket->id,
                    'status'         => 'SUBMITTED',
                    'display_status' => 'Diterima',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.created_at'));
        $this->assertNotEmpty($response->json('data.updated_at'));
    }

    /**
     * 2. Status display mapping matches STATUS_MAP defined in ReportController for all 5 canonical statuses.
     */
    public function test_display_status_mapping_matches_source_contract(): void
    {
        $mapping = [
            'SUBMITTED'   => 'Diterima',
            'IN_REVIEW'   => 'Sedang Ditinjau',
            'CONFIRMED'   => 'Laporan Dikonfirmasi',
            'IN_PROGRESS' => 'Sedang Ditangani',
            'CLOSED'      => 'Selesai',
        ];

        foreach ($mapping as $status => $displayStatus) {
            $report = $this->createReportFixture(null, "Laporan status {$status}");
            $ticket = Ticket::create([
                'report_id' => $report->id,
                'status'    => $status,
            ]);

            $response = $this->getJson("/api/tickets/{$ticket->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data'    => [
                        'ticket_id'      => $ticket->id,
                        'status'         => $status,
                        'display_status' => $displayStatus,
                    ],
                ]);
        }
    }

    /**
     * 3. Anonymous reports (citizen_id = NULL) can be tracked publicly.
     */
    public function test_anonymous_report_ticket_can_be_tracked_publicly(): void
    {
        $report = $this->createReportFixture(null, 'Laporan keluhan anonim tanpa akun.');
        $this->assertNull($report->citizen_id);

        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'ticket_id'      => $ticket->id,
                    'status'         => 'SUBMITTED',
                    'display_status' => 'Diterima',
                ],
            ]);
    }

    /**
     * 4. Non-existent ticket ID returns HTTP 404 with expected JSON message.
     */
    public function test_non_existent_ticket_returns_404(): void
    {
        $response = $this->getJson('/api/tickets/99999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Tiket tidak ditemukan.',
            ]);
    }

    /**
     * 5. Privacy: Tracking response MUST NOT expose sensitive report, location, photo, or citizen data.
     */
    public function test_tracking_response_does_not_expose_sensitive_or_citizen_data(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765480']);
        $secretDescription = 'Teks laporan rahasia yang tidak boleh bocor ke publik.';

        $report = $this->createReportFixture($citizen->id, $secretDescription);
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200);

        // Verify sensitive fields are strictly absent from the payload
        $response->assertJsonMissing([
            'description'  => $secretDescription,
            'latitude'     => -6.20880000,
            'longitude'    => 106.84560000,
            'photo_path'   => 'reports/test_tracking_sample.jpg',
            'citizen_id'   => $citizen->id,
            'phone_number' => '081298765480',
        ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('description', $data);
        $this->assertArrayNotHasKey('latitude', $data);
        $this->assertArrayNotHasKey('longitude', $data);
        $this->assertArrayNotHasKey('photo_path', $data);
        $this->assertArrayNotHasKey('citizen_id', $data);
        $this->assertArrayNotHasKey('phone_number', $data);
    }

    /**
     * 6. Query parameter citizen_id spoofing does not affect ticket retrieved via route ID.
     */
    public function test_query_string_citizen_id_does_not_affect_ticket_retrieval(): void
    {
        $citizenA = Citizen::create(['phone_number' => '081298765481']);
        $citizenB = Citizen::create(['phone_number' => '081298765482']);

        $reportA = $this->createReportFixture($citizenA->id, 'Laporan milik Citizen A');
        $ticketA = Ticket::create([
            'report_id' => $reportA->id,
            'status'    => 'SUBMITTED',
        ]);

        $reportB = $this->createReportFixture($citizenB->id, 'Laporan milik Citizen B');
        $ticketB = Ticket::create([
            'report_id' => $reportB->id,
            'status'    => 'IN_REVIEW',
        ]);

        // Querying Ticket A while appending citizen_id parameter of Citizen B
        $response = $this->getJson("/api/tickets/{$ticketA->id}?citizen_id={$citizenB->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'ticket_id'      => $ticketA->id,
                    'status'         => 'SUBMITTED',
                    'display_status' => 'Diterima',
                ],
            ]);

        $this->assertNotEquals($ticketB->id, $response->json('data.ticket_id'));
    }

    /**
     * 7. History field is not present in response payload because the controller mapping block is commented out.
     */
    public function test_history_is_not_exposed_in_current_response(): void
    {
        $admin = User::create([
            'name'     => 'Admin Audit',
            'email'    => 'admin_audit_' . uniqid() . '@aquatara.test',
            'password' => bcrypt('password123'),
        ]);

        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'IN_REVIEW',
        ]);

        TicketStatusHistory::create([
            'ticket_id'  => $ticket->id,
            'old_status' => 'SUBMITTED',
            'new_status' => 'IN_REVIEW',
            'actor_id'   => $admin->id,
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200);

        // The history mapping is currently commented out in ReportController::show (lines 158-165).
        // Therefore, 'history' key must NOT be present in 'data'.
        $data = $response->json('data');
        $this->assertArrayNotHasKey('history', $data);
        $response->assertJsonMissing(['history']);
    }

    /**
     * 8. Public endpoint accessibility: functions equally with or without Bearer tokens.
     */
    public function test_endpoint_is_public_and_unaffected_by_tokens(): void
    {
        $report = $this->createReportFixture();
        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        // Request 1: Completely without authentication header
        $responseAnon = $this->getJson("/api/tickets/{$ticket->id}");
        $responseAnon->assertStatus(200)
            ->assertJsonPath('data.ticket_id', $ticket->id);

        // Request 2: With Citizen token
        $citizen = Citizen::create(['phone_number' => '081298765483']);
        $citizenToken = $citizen->createToken('citizen-token')->plainTextToken;

        $responseCitizen = $this->withHeader('Authorization', "Bearer {$citizenToken}")
            ->getJson("/api/tickets/{$ticket->id}");
        $responseCitizen->assertStatus(200)
            ->assertJsonPath('data.ticket_id', $ticket->id);

        // Request 3: With invalid token string
        $responseInvalid = $this->withHeader('Authorization', 'Bearer invalid-token-string')
            ->getJson("/api/tickets/{$ticket->id}");
        $responseInvalid->assertStatus(200)
            ->assertJsonPath('data.ticket_id', $ticket->id);
    }
}
