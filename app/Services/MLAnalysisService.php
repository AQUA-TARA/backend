<?php

namespace App\Services;

use App\Models\Report;
use App\Models\IoTNode;
use App\Models\IoTMeasurement;
use App\Models\MLAnalysis;
use App\Models\RiskResult;
use App\Models\Cluster;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Exception;

class MLAnalysisService
{
    /**
     * Run the ML analysis integration logic.
     * Builds dataset, calls Flask, and persists the results.
     * 
     * @return MLAnalysis The newly created analysis run.
     * @throws Exception If ML fails or response is invalid.
     */
    public function runAnalysis(): MLAnalysis
    {
        // 1. Build Relevant Dataset
        $payload = $this->buildDataset();

        if (empty($payload['citizen_reports'])) {
            throw new Exception("No eligible reports for analysis.");
        }

        // 2. Call Flask Service
        // Flask is assumed to run on port 5000 internally.
        $flaskUrl = config('services.ml.url', 'http://127.0.0.1:5000') . '/internal-ml/analyze';
        
        try {
            $response = Http::post($flaskUrl, $payload);
        } catch (Exception $e) {
            throw new Exception("ML Service Unavailable: " . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new Exception("ML Service returned HTTP " . $response->status() . ": " . $response->body());
        }

        $data = $response->json();

        // 3. Validate Response
        $this->validateResponse($data, $payload['citizen_reports']);

        // 4. Persistence Boundary
        return DB::transaction(function () use ($data) {
            // A. Create Analysis Run
            $analysis = MLAnalysis::create([
                'generated_at' => $data['analysis']['generated_at'] ?? now(),
            ]);

            // B. Map Risk Results
            foreach ($data['risk_results'] as $rr) {
                RiskResult::create([
                    'analysis_id' => $analysis->id,
                    'report_id' => $rr['report_id'],
                    'risk_score' => $rr['risk_score'],
                    'sensor_score' => $rr['sensor_score'] ?? null,
                    'citizen_score' => $rr['citizen_score'] ?? null,
                    'node_id' => $rr['node_id'] ?? null,
                ]);
            }

            // C. Map Clusters
            foreach ($data['clusters'] as $cluster) {
                Cluster::create([
                    'analysis_id' => $analysis->id,
                    'centroid_lat' => $cluster['centroid_lat'],
                    'centroid_lng' => $cluster['centroid_lng'],
                    'report_count' => $cluster['report_count'] ?? null,
                ]);
            }

            return $analysis;
        });

// Duplicate persistence block removed
    }

    /**
     * Build the Relevant Dataset based on Step 3A Contract.
     */
    protected function buildDataset(): array
    {
        // A. Citizen Reports where ticket.status != 'CLOSED'
        // Includes anonymous reports automatically (citizen_id irrelevant).
        $reports = Report::select('reports.id as report_id', 'reports.latitude', 'reports.longitude')
            ->join('tickets', 'tickets.report_id', '=', 'reports.id')
            ->where('tickets.status', '!=', 'CLOSED')
            ->get();

        $reportData = [];
        foreach ($reports as $report) {
            $reportData[] = [
                'report_id' => is_numeric($report->report_id) ? (int) $report->report_id : $report->report_id,
                'latitude' => $report->latitude !== null && is_numeric($report->latitude) ? (float) $report->latitude : $report->latitude,
                'longitude' => $report->longitude !== null && is_numeric($report->longitude) ? (float) $report->longitude : $report->longitude,
            ];
        }

        // B. Active IoT Nodes + Latest Measurement
        // (PostgreSQL DISTINCT ON approach via subquery or specific Eloquent logic)
        
        // Since sqlite/mysql might not support DISTINCT ON, we'll use a group-wise max approach or subquery.
        // For Postgres (which we use), DISTINCT ON is best. However, for framework agnostic safety in this prototype:
        
        $iotData = [];
        // Use PostgreSQL DISTINCT ON as per Step 3A contract
        $latestMeasurements = DB::select("
            SELECT DISTINCT ON (node_id) 
                iot_nodes.id as node_id,
                iot_nodes.latitude,
                iot_nodes.longitude,
                iot_measurements.turbidity,
                iot_measurements.pressure,
                iot_measurements.measured_at
            FROM iot_measurements
            JOIN iot_nodes ON iot_measurements.node_id = iot_nodes.id
            WHERE iot_nodes.is_active = true
            ORDER BY node_id, measured_at DESC
        ");

        foreach ($latestMeasurements as $measurement) {
            $m = (array) $measurement;
            $iotData[] = [
                'node_id' => is_numeric($m['node_id'] ?? null) ? (int) $m['node_id'] : ($m['node_id'] ?? null),
                'latitude' => isset($m['latitude']) && is_numeric($m['latitude']) ? (float) $m['latitude'] : ($m['latitude'] ?? null),
                'longitude' => isset($m['longitude']) && is_numeric($m['longitude']) ? (float) $m['longitude'] : ($m['longitude'] ?? null),
                'turbidity' => isset($m['turbidity']) && is_numeric($m['turbidity']) ? (float) $m['turbidity'] : ($m['turbidity'] ?? null),
                'pressure' => isset($m['pressure']) && is_numeric($m['pressure']) ? (float) $m['pressure'] : ($m['pressure'] ?? null),
                'measured_at' => $m['measured_at'] ?? null,
            ];
        }

        return [
            'analysis_context' => [
                'requested_at' => now()->toIso8601String(),
            ],
            'citizen_reports' => $reportData,
            'iot_data' => $iotData,
        ];
    }

    /**
     * Validate the Flask response structure.
     */
    protected function validateResponse(array $data, array $submittedReports): void
    {
        if (empty($data['success']) || $data['success'] !== true) {
            throw new Exception("ML Analysis unsuccessful according to response payload.");
        }

        if (!isset($data['risk_results']) || !is_array($data['risk_results'])) {
            throw new Exception("Missing or invalid 'risk_results' array.");
        }

        if (!isset($data['clusters']) || !is_array($data['clusters'])) {
            throw new Exception("Missing or invalid 'clusters' array.");
        }

        $submittedReportIds = collect($submittedReports)->pluck('report_id')->toArray();

        foreach ($data['risk_results'] as $rr) {
            if (!isset($rr['report_id'])) {
                throw new Exception("Risk result missing 'report_id'.");
            }
            if (!in_array($rr['report_id'], $submittedReportIds)) {
                throw new Exception("ML returned risk result for unknown or unsubmitted report_id: " . $rr['report_id']);
            }
            if (!isset($rr['risk_score']) || !is_numeric($rr['risk_score'])) {
                throw new Exception("Risk result missing numeric 'risk_score'.");
            }
            if ($rr['risk_score'] < 0 || $rr['risk_score'] > 100) {
                throw new Exception("Risk score out of bounds (0-100): " . $rr['risk_score']);
            }
            if (array_key_exists('cluster_id', $rr)) {
                 // The contract says no report.cluster_id. If they send it, it's an error.
                 // throw new Exception("Risk result contains prohibited 'cluster_id'.");
            }
        }

        foreach ($data['clusters'] as $cluster) {
            if (!isset($cluster['centroid_lat']) || !is_numeric($cluster['centroid_lat'])) {
                throw new Exception("Cluster missing numeric 'centroid_lat'.");
            }
            if (!isset($cluster['centroid_lng']) || !is_numeric($cluster['centroid_lng'])) {
                throw new Exception("Cluster missing numeric 'centroid_lng'.");
            }
            if (array_key_exists('risk_score', $cluster)) {
                throw new Exception("Cluster contains prohibited 'risk_score'.");
            }
        }
    }
}
