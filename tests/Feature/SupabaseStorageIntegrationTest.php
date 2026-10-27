<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Ticket;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SupabaseStorageIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('reports');
    }

    /**
     * 1. Valid upload with Supabase configured:
     * Mock Supabase Storage REST API response and verify object path saved and ticket created.
     */
    public function test_upload_valid_image_to_supabase_storage_success(): void
    {
        Config::set('services.supabase.url', 'https://mock-proj.supabase.co');
        Config::set('services.supabase.service_role_key', 'mock-service-role-key-test');
        Config::set('services.supabase.storage_bucket', 'report-images');

        Http::fake([
            'https://mock-proj.supabase.co/storage/v1/object/report-images/reports/*' => Http::response([
                'Key' => 'report-images/reports/mock-uuid.jpg',
                'Id'  => 'mock-id'
            ], 200),
        ]);

        $photo = UploadedFile::fake()->image('kebocoran.jpg', 640, 480);

        $payload = [
            'report_type' => 'air_keruh',
            'description' => 'Air keruh sekali berwarna cokelat.',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
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
        $ticket = Ticket::with('report')->find($ticketId);

        $this->assertNotNull($ticket);
        $this->assertNotNull($ticket->report);
        $this->assertStringStartsWith('reports/', $ticket->report->photo_path);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/storage/v1/object/report-images/reports/') &&
                   $request->header('Authorization')[0] === 'Bearer mock-service-role-key-test';
        });
    }

    /**
     * 2. WebP and PNG format validation.
     */
    public function test_upload_valid_png_and_webp_accepted(): void
    {
        Config::set('services.supabase.url', 'https://mock-proj.supabase.co');
        Config::set('services.supabase.service_role_key', 'mock-service-role-key-test');

        Http::fake([
            'https://mock-proj.supabase.co/storage/v1/object/report-images/reports/*' => Http::response(['Key' => 'ok'], 200),
        ]);

        // PNG
        $pngPhoto = UploadedFile::fake()->image('bukti.png', 400, 400);
        $resPng = $this->post('/api/reports', [
            'report_type' => 'air_keruh',
            'description' => 'Tes PNG upload',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo'       => $pngPhoto,
        ]);
        $resPng->assertStatus(201);

        // WebP
        $webpPhoto = UploadedFile::fake()->create('bukti.webp', 150, 'image/webp');
        $resWebp = $this->post('/api/reports', [
            'report_type' => 'air_keruh',
            'description' => 'Tes WebP upload',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo'       => $webpPhoto,
        ]);
        $resWebp->assertStatus(201);
    }

    /**
     * 3. File greater than 5 MB rejected with 422.
     */
    public function test_file_greater_than_5mb_rejected(): void
    {
        // 5121 KB > 5120 KB limit
        $largePhoto = UploadedFile::fake()->create('huge.jpg', 5121, 'image/jpeg');

        $response = $this->postJson('/api/reports', [
            'description' => 'File terlalu besar',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo'       => $largePhoto,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    /**
     * 4. Invalid file MIME type (e.g. PDF or text) rejected with 422.
     */
    public function test_invalid_mime_type_rejected(): void
    {
        $fakePdf = UploadedFile::fake()->create('document.pdf', 200, 'application/pdf');

        $response = $this->postJson('/api/reports', [
            'description' => 'Bukan gambar',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo'       => $fakePdf,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    /**
     * 5. Supabase upload failure (e.g. 500 or timeout) handled gracefully without creating orphan DB records.
     */
    public function test_supabase_error_handled_gracefully(): void
    {
        Config::set('services.supabase.url', 'https://mock-proj.supabase.co');
        Config::set('services.supabase.service_role_key', 'mock-service-role-key-test');

        Http::fake([
            'https://mock-proj.supabase.co/storage/v1/object/report-images/reports/*' => Http::response([
                'error' => 'Internal Server Error'
            ], 500),
        ]);

        $initialReportCount = Report::count();
        $initialTicketCount = Ticket::count();

        $photo = UploadedFile::fake()->image('bukti.jpg');

        $response = $this->post('/api/reports', [
            'report_type' => 'air_keruh',
            'description' => 'Test Supabase 500',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo'       => $photo,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Laporan gagal disimpan. Silakan coba lagi.',
            ]);

        // DB records must not have been created
        $this->assertEquals($initialReportCount, Report::count());
        $this->assertEquals($initialTicketCount, Ticket::count());
    }

    /**
     * 6. Cleanup called if upload succeeded but DB transaction fails.
     */
    public function test_cleanup_called_if_db_storage_fails(): void
    {
        Config::set('services.supabase.url', 'https://mock-proj.supabase.co');
        Config::set('services.supabase.service_role_key', 'mock-service-role-key-test');

        Http::fake([
            'https://mock-proj.supabase.co/storage/v1/object/report-images/reports/*' => Http::response(['Key' => 'uploaded'], 200),
            'https://mock-proj.supabase.co/storage/v1/object/report-images' => Http::response(['message' => 'deleted'], 200),
        ]);

        // Force a DB error by passing invalid latitude structure after upload
        // (Simulate database exception during transaction)
        $photo = UploadedFile::fake()->image('bukti.jpg');

        $mockStorage = $this->getMockBuilder(SupabaseStorageService::class)
            ->onlyMethods(['delete'])
            ->getMock();

        $mockStorage->expects($this->once())
            ->method('delete');

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        // Mock DB transaction throwing exception
        \Illuminate\Support\Facades\DB::shouldReceive('transaction')
            ->andThrow(new \Exception('Simulated DB failure'));

        $response = $this->post('/api/reports', [
            'report_type' => 'air_keruh',
            'description' => 'Test rollback cleanup',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo'       => $photo,
        ]);

        $response->assertStatus(500);
    }

    /**
     * 7. Signed URL generation in GET /api/tickets/{ticket_id}.
     */
    public function test_signed_url_generated_for_private_bucket_object(): void
    {
        Config::set('services.supabase.url', 'https://mock-proj.supabase.co');
        Config::set('services.supabase.service_role_key', 'mock-service-role-key-test');

        Http::fake([
            'https://mock-proj.supabase.co/storage/v1/object/sign/report-images/reports/sample-uuid.jpg' => Http::response([
                'signedURL' => '/object/sign/report-images/reports/sample-uuid.jpg?token=mock_jwt_token'
            ], 200),
        ]);

        $report = Report::create([
            'citizen_id'  => null,
            'report_type' => 'air_keruh',
            'description' => 'Air keruh test',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo_path'  => 'reports/sample-uuid.jpg',
            'created_at'  => now(),
        ]);

        $ticket = Ticket::create([
            'report_id' => $report->id,
            'status'    => 'SUBMITTED',
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'ticket_id' => $ticket->id,
                    'status'    => 'SUBMITTED',
                    'photo_url' => 'https://mock-proj.supabase.co/storage/v1/object/sign/report-images/reports/sample-uuid.jpg?token=mock_jwt_token',
                ],
            ]);
    }

    /**
     * 8. Rate limiter for public report submissions (10 requests per minute).
     */
    public function test_rate_limiting_on_report_submission(): void
    {
        Config::set('services.supabase.url', 'https://mock-proj.supabase.co');
        Config::set('services.supabase.service_role_key', 'mock-service-role-key-test');

        Http::fake([
            'https://mock-proj.supabase.co/storage/v1/object/report-images/reports/*' => Http::response(['Key' => 'uploaded'], 200),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $photo = UploadedFile::fake()->image("bukti_{$i}.jpg");
            $res = $this->post('/api/reports', [
                'report_type' => 'kebocoran',
                'description' => "Laporan ke-{$i}",
                'latitude'    => 3.325,
                'longitude'   => 117.595,
                'photo'       => $photo,
            ]);
            $res->assertStatus(201);
        }

        // 11th request should be throttled (429 Too Many Requests)
        $photo11 = UploadedFile::fake()->image("bukti_11.jpg");
        $throttledRes = $this->post('/api/reports', [
            'report_type' => 'kebocoran',
            'description' => 'Laporan ke-11',
            'latitude'    => 3.325,
            'longitude'   => 117.595,
            'photo'       => $photo11,
        ]);

        $throttledRes->assertStatus(429);
    }
}
