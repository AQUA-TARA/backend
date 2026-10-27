<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\Citizen;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\MLAnalysis;
use App\Models\RiskResult;

class RunMLAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Since we are mocking HTTP, we can safely create dummy data inside transactions.
        $this->seedDummyData();
    }

    protected function seedDummyData()
    {
        $citizen = Citizen::create([
            'phone_number' => '1234567890',
            'is_verified' => true,
        ]);

        $report = Report::create([
            'citizen_id' => $citizen->id,
            'description' => 'Test Report',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'photo_path' => 'dummy.jpg',
        ]);

        Ticket::create([
            'report_id' => $report->id,
            'status' => 'SUBMITTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ml_command_handles_unavailable_flask()
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $this->artisan('ml:run-analysis')
             ->expectsOutput('Starting ML Risk Analysis...')
             ->assertFailed();

        $this->assertDatabaseCount('ml_analyses', 0);
    }

    public function test_ml_command_success()
    {
        $reportId = Report::first()->id;

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'analysis' => [
                    'generated_at' => now()->toIso8601String()
                ],
                'risk_results' => [
                    [
                        'report_id' => $reportId,
                        'risk_score' => 85
                    ]
                ],
                'clusters' => [
                    [
                        'centroid_lat' => -6.2,
                        'centroid_lng' => 106.8,
                        'report_count' => 1
                    ]
                ]
            ], 200),
        ]);

        $this->artisan('ml:run-analysis')
             ->expectsOutput('Starting ML Risk Analysis...')
             ->assertSuccessful();

        $this->assertDatabaseCount('ml_analyses', 1);
        $this->assertDatabaseCount('risk_results', 1);
        $this->assertDatabaseCount('clusters', 1);
        
        $this->assertDatabaseHas('risk_results', [
            'report_id' => $reportId,
            'risk_score' => 85
        ]);

        // Assert existing entities untouched
        $this->assertDatabaseHas('tickets', [
            'report_id' => $reportId,
            'status' => 'SUBMITTED' // must NOT be changed automatically
        ]);
    }
}
