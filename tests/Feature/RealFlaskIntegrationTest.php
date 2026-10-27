<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\IoTNode;
use App\Models\IoTMeasurement;
use App\Models\MLAnalysis;
use App\Models\RiskResult;
use App\Models\Cluster;
use App\Services\MLAnalysisService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * STEP 4D.35D: Controlled Real Laravel-to-Flask Integration Test.
 * 
 * Verifies live HTTP request to Flask ML server at http://127.0.0.1:5000/internal-ml/analyze
 * WITHOUT using Http::fake().
 * Uses DatabaseTransactions to ensure complete database rollback.
 */
class RealFlaskIntegrationTest extends TestCase
{
    use DatabaseTransactions;
    

    protected MLAnalysisService $mlService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mlService = new MLAnalysisService();
    }

    public function test_real_flask_ml_analysis_pipeline_execution_and_persistence()
    {
        // 1. Create Synthetic Fixture Data inside database transaction
        $report1 = Report::create([
            'citizen_id' => null,
            'description' => 'Real Flask test report 1',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'photo_path' => 'dummy_test1.jpg',
        ]);
        Ticket::create([
            'report_id' => $report1->id,
            'status' => 'SUBMITTED',
        ]);

        $report2 = Report::create([
            'citizen_id' => null,
            'description' => 'Real Flask test report 2',
            'latitude' => -6.9180,
            'longitude' => 107.6200,
            'photo_path' => 'dummy_test2.jpg',
        ]);
        Ticket::create([
            'report_id' => $report2->id,
            'status' => 'IN_PROGRESS',
        ]);

        $node = IoTNode::create([
            'hardware_id' => 'REAL-FLASK-TEST-NODE',
            'latitude' => -6.9200,
            'longitude' => 107.6150,
            'is_active' => true,
        ]);
        IoTMeasurement::create([
            'node_id' => $node->id,
            'turbidity' => 18.5,
            'pressure' => 2.8,
            'measured_at' => now(),
        ]);

        // 2. Execute Real Pipeline (Http::fake() is intentionally NOT used)
        $analysis = $this->mlService->runAnalysis();

        // 3. Assert Response Instance
        $this->assertInstanceOf(MLAnalysis::class, $analysis);
        $this->assertNotNull($analysis->id);
        $this->assertNotNull($analysis->generated_at);

        // 4. Assert Database Persistence in ml_analyses, risk_results, and clusters
        $this->assertDatabaseHas('ml_analyses', [
            'id' => $analysis->id,
        ]);

        $this->assertDatabaseHas('risk_results', [
            'analysis_id' => $analysis->id,
            'report_id' => $report1->id,
        ]);
        $this->assertDatabaseHas('risk_results', [
            'analysis_id' => $analysis->id,
            'report_id' => $report2->id,
        ]);

        $this->assertDatabaseHas('clusters', [
            'analysis_id' => $analysis->id,
            'report_count' => 2,
        ]);

        // 5. Assert Risk Score Constraints (0 - 100)
        $riskResults = RiskResult::where('analysis_id', $analysis->id)->get();
        $this->assertCount(2, $riskResults);
        foreach ($riskResults as $rr) {
            $this->assertGreaterThanOrEqual(0, $rr->risk_score);
            $this->assertLessThanOrEqual(100, $rr->risk_score);
        }

        // 6. Assert Clusters Centroid
        $clusters = Cluster::where('analysis_id', $analysis->id)->get();
        $this->assertCount(1, $clusters);
        $cluster = $clusters->first();
        $this->assertEquals(-6.91775, round($cluster->centroid_lat, 5));
        $this->assertEquals(107.61955, round($cluster->centroid_lng, 5));
    }
}
