<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\Citizen;
use App\Models\IoTNode;
use App\Models\IoTMeasurement;
use App\Models\MLAnalysis;
use App\Models\RiskResult;
use App\Models\Cluster;
use App\Services\MLAnalysisService;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Exception;

class MLAnalysisIntegrationTest extends TestCase
{
    use DatabaseTransactions; // Important: do not reset database or use migrate:fresh

    protected MLAnalysisService $mlService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mlService = new MLAnalysisService();
    }

    public function test_relevant_dataset_query_includes_anonymous_and_excludes_closed()
    {
        // Keep track of existing baseline counts just to be sure we don't mess them up
        $initialReportsCount = Report::count();

        // Create Anonymous Report (status SUBMITTED) -> SHOULD BE INCLUDED
        $anonReport = Report::create([
            'citizen_id' => null,
            'description' => 'Anon test',
            'latitude' => -6.1,
            'longitude' => 106.1, 'photo_path' => 'dummy.jpg',
        ]);
        Ticket::create(['report_id' => $anonReport->id, 'status' => 'SUBMITTED']);

        // Create Auth Report (status IN_PROGRESS) -> SHOULD BE INCLUDED
        $citizen = Citizen::create(['phone_number' => '08999999999']);
        $authReport = Report::create([
            'citizen_id' => $citizen->id,
            'description' => 'Auth test',
            'latitude' => -6.2,
            'longitude' => 106.2, 'photo_path' => 'dummy.jpg',
        ]);
        Ticket::create(['report_id' => $authReport->id, 'status' => 'IN_PROGRESS']);

        // Create Closed Report -> SHOULD BE EXCLUDED
        $closedReport = Report::create([
            'citizen_id' => null,
            'description' => 'Closed test',
            'latitude' => -6.3,
            'longitude' => 106.3, 'photo_path' => 'dummy.jpg',
        ]);
        Ticket::create(['report_id' => $closedReport->id, 'status' => 'CLOSED']);

        // Test the dataset generation via reflection
        $reflection = new \ReflectionClass(MLAnalysisService::class);
        $method = $reflection->getMethod('buildDataset');
        $method->setAccessible(true);
        $dataset = $method->invoke($this->mlService);

        $reportIds = array_column($dataset['citizen_reports'], 'report_id');

        $this->assertContains($anonReport->id, $reportIds, "Anonymous report should be included");
        $this->assertContains($authReport->id, $reportIds, "Authenticated report should be included");
        $this->assertNotContains($closedReport->id, $reportIds, "CLOSED report should be excluded");
    }

    public function test_relevant_dataset_query_selects_latest_active_iot()
    {
        // Node 1 (Active)
        $node1 = IoTNode::create([
            'hardware_id' => 'TEST-NODE-1',
            'latitude' => -6.1,
            'longitude' => 106.1, 'photo_path' => 'dummy.jpg',
            'is_active' => true,
        ]);
        // Node 1 measurements
        IoTMeasurement::create(['node_id' => $node1->id, 'turbidity' => 1.0, 'pressure' => 2, 'measured_at' => now()->subMinutes(10)]);
        IoTMeasurement::create(['node_id' => $node1->id, 'turbidity' => 2.0, 'pressure' => 2, 'measured_at' => now()]); // THIS ONE

        // Node 2 (Inactive) -> SHOULD BE EXCLUDED
        $node2 = IoTNode::create([
            'hardware_id' => 'TEST-NODE-2',
            'latitude' => -6.2,
            'longitude' => 106.2, 'photo_path' => 'dummy.jpg',
            'is_active' => false,
        ]);
        IoTMeasurement::create(['node_id' => $node2->id, 'turbidity' => 3.0, 'pressure' => 2, 'measured_at' => now()]);

        $reflection = new \ReflectionClass(MLAnalysisService::class);
        $method = $reflection->getMethod('buildDataset');
        $method->setAccessible(true);
        $dataset = $method->invoke($this->mlService);

        $iotData = collect($dataset['iot_data']);
        
        // Node 1 should be present with latest turbidity 2.0
        $node1Data = $iotData->firstWhere('node_id', $node1->id);
        $this->assertNotNull($node1Data);
        $this->assertEquals(2.0, $node1Data['turbidity']);

        // Node 2 should not be present
        $node2Data = $iotData->firstWhere('node_id', $node2->id);
        $this->assertNull($node2Data);
    }

    public function test_valid_ml_response_persisted_correctly()
    {
        // 1. Setup Data
        $report = Report::create([
            'citizen_id' => null,
            'description' => 'Valid response test',
            'latitude' => -6.1,
            'longitude' => 106.1, 'photo_path' => 'dummy.jpg',
        ]);
        $ticket = Ticket::create(['report_id' => $report->id, 'status' => 'SUBMITTED']);

        // 2. Mock HTTP response
        Http::fake([
            '*/internal-ml/analyze' => Http::response([
                'success' => true,
                'analysis' => ['generated_at' => now()->toIso8601String()],
                'risk_results' => [
                    ['report_id' => $report->id, 'risk_score' => 85] // Valid response
                ],
                'clusters' => [
                    ['centroid_lat' => -6.1, 'centroid_lng' => 106.1, 'report_count' => 1]
                ]
            ], 200)
        ]);

        // 3. Run Analysis
        $analysis = $this->mlService->runAnalysis();

        // 4. Verify DB State
        $this->assertDatabaseHas('ml_analyses', ['id' => $analysis->id]);
        $this->assertDatabaseHas('risk_results', [
            'analysis_id' => $analysis->id,
            'report_id' => $report->id,
            'risk_score' => 85
        ]);
        $this->assertDatabaseHas('clusters', [
            'analysis_id' => $analysis->id,
            'centroid_lat' => -6.1,
            'centroid_lng' => 106.1,
            'report_count' => 1
        ]);

        // 5. Ensure Ticket Status did NOT change
        $this->assertEquals('SUBMITTED', $ticket->fresh()->status);
    }

    public function test_flask_unavailable_controlled_error()
    {
        // Ensure at least one report exists so it doesn't throw "No eligible reports"
        $report = Report::first();
        if (!$report) {
            $report = Report::create(['citizen_id' => null, 'description' => 'Test', 'latitude' => -6.1, 'longitude' => 106.1, 'photo_path' => 'dummy.jpg']);
            Ticket::create(['report_id' => $report->id, 'status' => 'SUBMITTED']);
        }

        Http::fake([
            '*/internal-ml/analyze' => Http::response(null, 500) // Simulate failure
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/ML Service returned HTTP 500/');

        $this->mlService->runAnalysis();
    }

    public function test_invalid_ml_response_unknown_report_id_rejected()
    {
        $report = Report::create(['citizen_id' => null, 'description' => 'Test', 'latitude' => -6.1, 'longitude' => 106.1, 'photo_path' => 'dummy.jpg']);
        Ticket::create(['report_id' => $report->id, 'status' => 'SUBMITTED']);

        Http::fake([
            '*/internal-ml/analyze' => Http::response([
                'success' => true,
                'analysis' => ['generated_at' => now()->toIso8601String()],
                'risk_results' => [
                    ['report_id' => 999999, 'risk_score' => 50] // Unknown report_id
                ],
                'clusters' => []
            ], 200)
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/unknown or unsubmitted report_id/');

        $this->mlService->runAnalysis();
    }

    public function test_invalid_risk_score_bounds_rejected()
    {
        $report = Report::create(['citizen_id' => null, 'description' => 'Test', 'latitude' => -6.1, 'longitude' => 106.1, 'photo_path' => 'dummy.jpg']);
        Ticket::create(['report_id' => $report->id, 'status' => 'SUBMITTED']);

        Http::fake([
            '*/internal-ml/analyze' => Http::response([
                'success' => true,
                'analysis' => ['generated_at' => now()->toIso8601String()],
                'risk_results' => [
                    ['report_id' => $report->id, 'risk_score' => 105] // Out of bounds
                ],
                'clusters' => []
            ], 200)
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Risk score out of bounds/');

        $this->mlService->runAnalysis();
    }

    public function test_cluster_risk_score_prohibited()
    {
        $report = Report::create(['citizen_id' => null, 'description' => 'Test', 'latitude' => -6.1, 'longitude' => 106.1, 'photo_path' => 'dummy.jpg']);
        Ticket::create(['report_id' => $report->id, 'status' => 'SUBMITTED']);

        Http::fake([
            '*/internal-ml/analyze' => Http::response([
                'success' => true,
                'analysis' => ['generated_at' => now()->toIso8601String()],
                'risk_results' => [
                    ['report_id' => $report->id, 'risk_score' => 50]
                ],
                'clusters' => [
                    ['centroid_lat' => -6.1, 'centroid_lng' => 106.1, 'report_count' => 1, 'risk_score' => 50] // PROHIBITED
                ]
            ], 200)
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Cluster contains prohibited \'risk_score\'/');

        $this->mlService->runAnalysis();
    }
}
