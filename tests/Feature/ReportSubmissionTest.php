<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Report;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportSubmissionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Fake public disk because ReportController uses: store('reports', 'public')
        Storage::fake('public');
    }

    /**
     * 1. Anonymous report submission: valid description, photo, coordinates.
     * Expects 201 Created, ticket_id, status SUBMITTED, and citizen_id NULL in DB.
     */
    public function test_anonymous_report_submission_creates_report_and_ticket(): void
    {
        $photo = UploadedFile::fake()->image('kebocoran_pipa.jpg', 640, 480);

        $payload = [
            'report_type' => 'kebocoran',
            'description' => 'Pipa PDAM pecah di dekat trotoar jalan utama.',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status' => 'SUBMITTED',
                ],
            ]);

        $ticketId = $response->json('data.ticket_id');
        $this->assertNotEmpty($ticketId);

        // Verify ticket in database
        $ticket = Ticket::find($ticketId);
        $this->assertNotNull($ticket);
        $this->assertEquals('SUBMITTED', $ticket->status);

        // Verify report in database: citizen_id must be NULL
        $report = Report::find($ticket->report_id);
        $this->assertNotNull($report);
        $this->assertNull($report->citizen_id);
        $this->assertEquals('kebocoran', $report->report_type);
        $this->assertEquals('Pipa PDAM pecah di dekat trotoar jalan utama.', $report->description);
        $this->assertEquals(-6.20880000, (float) $report->latitude);
        $this->assertEquals(106.84560000, (float) $report->longitude);

        // Verify file stored on public disk
        $this->assertNotNull($report->photo_path);
        Storage::disk('public')->assertExists($report->photo_path);
    }

    /**
     * 2. Authenticated citizen report submission:
     * Expects citizen_id resolved from authenticated principal (auth:citizen).
     */
    public function test_authenticated_citizen_report_submission_sets_citizen_id(): void
    {
        $citizen = Citizen::create(['phone_number' => '081298765450']);
        $token = $citizen->createToken('citizen-auth')->plainTextToken;

        $photo = UploadedFile::fake()->image('air_keruh.png', 800, 600);

        $payload = [
            'report_type' => 'air_keruh',
            'description' => 'Air keruh dan berbau karat sejak pagi hari.',
            'latitude'    => -6.17540000,
            'longitude'   => 106.82720000,
            'photo'       => $photo,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/reports', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status' => 'SUBMITTED',
                ],
            ]);

        $ticketId = $response->json('data.ticket_id');
        $ticket = Ticket::find($ticketId);
        $this->assertNotNull($ticket);

        $report = Report::find($ticket->report_id);
        $this->assertNotNull($report);
        $this->assertEquals($citizen->id, $report->citizen_id);
        $this->assertEquals('air_keruh', $report->report_type);
        $this->assertEquals('Air keruh dan berbau karat sejak pagi hari.', $report->description);

        Storage::disk('public')->assertExists($report->photo_path);
    }

    /**
     * 3. Security contract: citizen_id in client input must NEVER override authentication.
     * Anonymous request with client citizen_id input still results in citizen_id NULL.
     */
    public function test_anonymous_submission_ignores_client_provided_citizen_id(): void
    {
        $otherCitizen = Citizen::create(['phone_number' => '081298765451']);

        $photo = UploadedFile::fake()->image('genangan.jpg');

        $payload = [
            'report_type' => 'lainnya',
            'citizen_id'  => $otherCitizen->id,
            'description' => 'Genangan air bersih dari bocoran pipa.',
            'latitude'    => -6.20000000,
            'longitude'   => 106.80000000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(201);
        $ticketId = $response->json('data.ticket_id');

        $report = Ticket::find($ticketId)->report;
        $this->assertNull($report->citizen_id);
        $this->assertEquals('lainnya', $report->report_type);
        $this->assertNotEquals($otherCitizen->id, $report->citizen_id);
    }

    /**
     * 4. Security contract: Authenticated citizen cannot spoof other citizen_id via client input.
     */
    public function test_authenticated_submission_uses_token_principal_not_client_citizen_id(): void
    {
        $actualCitizen = Citizen::create(['phone_number' => '081298765452']);
        $spoofedCitizen = Citizen::create(['phone_number' => '081298765453']);

        $token = $actualCitizen->createToken('citizen-auth')->plainTextToken;
        $photo = UploadedFile::fake()->image('meteran.jpg');

        $payload = [
            'report_type' => 'kebocoran',
            'citizen_id'  => $spoofedCitizen->id,
            'description' => 'Meteran air bocor dan berputar cepat tanpa henti.',
            'latitude'    => -6.21000000,
            'longitude'   => 106.81000000,
            'photo'       => $photo,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/reports', $payload);

        $response->assertStatus(201);
        $ticketId = $response->json('data.ticket_id');

        $report = Ticket::find($ticketId)->report;
        $this->assertEquals($actualCitizen->id, $report->citizen_id);
        $this->assertEquals('kebocoran', $report->report_type);
        $this->assertNotEquals($spoofedCitizen->id, $report->citizen_id);
    }

    /**
     * 5. Missing required photo: rejected with 422 validation error.
     */
    public function test_missing_photo_returns_422(): void
    {
        $payload = [
            'description' => 'Pipa bocor di gang sempit.',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
        ];

        $response = $this->postJson('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo'])
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
            ]);
    }

    /**
     * 6. Invalid photo (non-image file): rejected with 422 validation error.
     */
    public function test_non_image_photo_returns_422(): void
    {
        $invalidFile = UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf');

        $payload = [
            'description' => 'Pipa bocor dengan lampiran dokumen pdf.',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo'       => $invalidFile,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    /**
     * 7. Missing latitude or longitude coordinates: rejected with 422 validation error.
     */
    public function test_missing_coordinates_returns_422(): void
    {
        $photo = UploadedFile::fake()->image('bocor.jpg');

        // Missing latitude
        $this->post('/api/reports', [
            'description' => 'Pipa pecah.',
            'longitude'   => 106.84560000,
            'photo'       => $photo,
        ])->assertStatus(422)->assertJsonValidationErrors(['latitude']);

        // Missing longitude
        $this->post('/api/reports', [
            'description' => 'Pipa pecah.',
            'latitude'    => -6.20880000,
            'photo'       => $photo,
        ])->assertStatus(422)->assertJsonValidationErrors(['longitude']);
    }

    /**
     * 8. Non-numeric coordinates: rejected with 422 validation error.
     */
    public function test_non_numeric_coordinates_returns_422(): void
    {
        $photo = UploadedFile::fake()->image('bocor.jpg');

        $payload = [
            'description' => 'Pipa pecah.',
            'latitude'    => 'bukan-angka',
            'longitude'   => 'invalid-coord',
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /**
     * 9. Successful creation links ticket to report.
     * Accurately verifies that upon successful creation, the ticket.report_id
     * correctly references report.id and initial status is SUBMITTED.
     */
    public function test_successful_creation_links_ticket_to_report(): void
    {
        $photo = UploadedFile::fake()->image('relasi.jpg');

        $payload = [
            'report_type' => 'kebocoran',
            'description' => 'Uji relasi report dan ticket saat pembuatan berhasil.',
            'latitude'    => -6.20500000,
            'longitude'   => 106.84000000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(201);
        $ticketId = $response->json('data.ticket_id');

        $ticket = Ticket::with('report')->find($ticketId);
        $this->assertNotNull($ticket);
        $this->assertNotNull($ticket->report);
        $this->assertEquals($ticket->report_id, $ticket->report->id);
        $this->assertEquals('SUBMITTED', $ticket->status);
    }

    /**
     * 10. Missing or null description returns 422 validation error.
     */
    public function test_missing_or_null_description_returns_422(): void
    {
        $photo = UploadedFile::fake()->image('bocor.jpg');

        $payload = [
            'description' => null,
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description'])
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
            ]);
    }

    /**
     * 11. Empty string description ("") returns 422 validation error.
     */
    public function test_empty_string_description_returns_422(): void
    {
        $photo = UploadedFile::fake()->image('bocor.jpg');

        $payload = [
            'description' => '',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description'])
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
            ]);
    }

    /**
     * 12. Whitespace-only description ("   ") returns 422 validation error.
     */
    public function test_whitespace_only_description_returns_422(): void
    {
        $photo = UploadedFile::fake()->image('bocor.jpg');

        $payload = [
            'description' => '     ',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description'])
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
            ]);
    }

    /**
     * 13. Missing report_type returns 422 validation error.
     */
    public function test_missing_report_type_returns_422(): void
    {
        $photo = UploadedFile::fake()->image('bocor.jpg');

        $payload = [
            'description' => 'Pipa bocor di simpang empat.',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['report_type'])
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
            ]);
    }

    /**
     * 14. Invalid report_type returns 422 validation error.
     */
    public function test_invalid_report_type_returns_422(): void
    {
        $photo = UploadedFile::fake()->image('bocor.jpg');

        $payload = [
            'report_type' => 'bukan_kategori_valid',
            'description' => 'Pipa bocor di simpang empat.',
            'latitude'    => -6.20880000,
            'longitude'   => 106.84560000,
            'photo'       => $photo,
        ];

        $response = $this->post('/api/reports', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['report_type']);
    }

    /**
     * 15. All three allowed report_type values are successfully accepted and stored.
     */
    public function test_all_allowed_report_types_accepted_and_stored(): void
    {
        $allowedTypes = ['kebocoran', 'air_keruh', 'lainnya'];

        foreach ($allowedTypes as $type) {
            $photo = UploadedFile::fake()->image("{$type}.jpg");

            $payload = [
                'report_type' => $type,
                'description' => "Laporan pengujian untuk tipe: {$type}",
                'latitude'    => 3.32500000,
                'longitude'   => 117.59500000,
                'photo'       => $photo,
            ];

            $response = $this->post('/api/reports', $payload);

            $response->assertStatus(201);
            $ticketId = $response->json('data.ticket_id');

            $report = Ticket::find($ticketId)->report;
            $this->assertEquals($type, $report->report_type);
        }
    }
}
