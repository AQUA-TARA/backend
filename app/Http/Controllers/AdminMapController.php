<?php

namespace App\Http\Controllers;

use App\Models\IoTMeasurement;
use App\Models\IoTNode;
use App\Models\PipeNetwork;
use App\Models\Report;
use App\Models\MLAnalysis;
use App\Models\Cluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminMapController extends Controller
{
    /**
     * Normalisasi nilai kekeruhan (turbidity dalam NTU) ke Score_T (0-100).
     * Menggunakan aturan matematis persis sama dengan ML Flask production_analysis.py:
     * - t <= 5.0  (Jernih):  (t / 5.0) * 30.0
     * - 5.0 < t <= 25.0 (Waspada): 30.0 + ((t - 5.0) / 20.0) * 40.0
     * - t > 25.0 (Bahaya):  70.0 + min(30.0, ((t - 25.0) / 25.0) * 30.0)
     * - null / nan / invalid: 30.0 (fallback batas aman)
     */
    public static function normalizeTurbidity(?float $t): float
    {
        if ($t === null || is_nan($t)) {
            return 30.0;
        }

        $t = max(0.0, $t);

        if ($t <= 5.0) {
            return ($t / 5.0) * 30.0;
        } elseif ($t <= 25.0) {
            return 30.0 + (($t - 5.0) / 20.0) * 40.0;
        } else {
            return 70.0 + min(30.0, (($t - 25.0) / 25.0) * 30.0);
        }
    }

    /**
     * Hitung jarak spasial Euclidean antara dua titik koordinat.
     */
    public static function spatialDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return sqrt(pow($lat1 - $lat2, 2) + pow($lng1 - $lng2, 2));
    }

    /**
     * Hitung Skor Risiko Final (0-100) menggunakan formula standar sistem:
     * 80% Skor Turbidity Sensor (Score_T) + 20% Skor Densitas Klaster K-Means (Score_S)
     */
    public static function calculateFinalRiskScore(float $scoreT, float $scoreS): int
    {
        $finalScore = (0.6 * $scoreT) + (0.4 * $scoreS);
        return (int) max(0, min(100, round($finalScore)));
    }

    /**
     * Konversi nilai Risk Score (0-100) menjadi kategori resmi (DEC-001):
     * - 0 - 30:  JERNIH (Aman)
     * - 31 - 70: WASPADA (Peringatan)
     * - 71 - 100: BAHAYA (Kritis)
     */
    public static function getRiskLevel(int $riskScore): string
    {
        if ($riskScore <= 30) {
            return 'JERNIH';
        }
        if ($riskScore <= 70) {
            return 'WASPADA';
        }
        return 'BAHAYA';
    }

    /**
     * Konversi nilai Risk Score (0-100) menjadi nilai intensitas heatmap kontinu (0.00 - 1.00).
     */
    public static function calculateRiskIntensity(int $riskScore): float
    {
        return round(max(0.0, min(1.0, $riskScore / 100.0)), 4);
    }

    /**
     * GET /api/admin/map
     * Endpoint terpadu peta admin.
     * Heatmap MURNI berpusat di koordinat fisik iot_nodes dan menggunakan FINAL RISK SCORE (60:40).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $mode = strtolower((string) $request->query('mode', 'latest'));
            if (!in_array($mode, ['latest', 'history'], true)) {
                $mode = 'latest';
            }

            // Parameter filter jendela waktu (opsional)
            $hours = $request->query('hours');
            $hoursInt = ($hours !== null && is_numeric($hours) && (int) $hours > 0) ? (int) $hours : null;

            // 1. Ambil data klaster K-Means dari analisis ML terkini untuk komponen Score_S (40%)
            $latestAnalysis = MLAnalysis::orderByDesc('generated_at')->orderByDesc('id')->first();
            $clusters = $latestAnalysis ? $latestAnalysis->clusters : collect();

            // 2. Query Pengukuran Sensor (iot_measurements JOIN iot_nodes)
            $baseHeatmapQuery = DB::table('iot_measurements as im')
                ->join('iot_nodes as n', 'im.node_id', '=', 'n.id')
                ->where('n.is_active', true)
                ->whereNotNull('im.turbidity')
                ->where('im.turbidity', '>=', 0)
                ->whereBetween('n.latitude', [-90, 90])
                ->whereBetween('n.longitude', [-180, 180]);

            if ($hoursInt !== null) {
                $baseHeatmapQuery->where('im.measured_at', '>=', now()->subHours($hoursInt));
            }

            if ($mode === 'latest') {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    $timeFilterSql = $hoursInt !== null ? "AND im.measured_at >= '" . now()->subHours($hoursInt)->toDateTimeString() . "'" : "";
                    $rawMeasurements = DB::select("
                        SELECT DISTINCT ON (im.node_id)
                            im.id as measurement_id,
                            im.node_id,
                            n.hardware_id,
                            n.latitude,
                            n.longitude,
                            im.turbidity,
                            im.pressure,
                            im.measured_at
                        FROM iot_measurements im
                        JOIN iot_nodes n ON im.node_id = n.id
                        WHERE n.is_active = true
                          AND im.turbidity IS NOT NULL
                          AND im.turbidity >= 0
                          AND n.latitude BETWEEN -90 AND 90
                          AND n.longitude BETWEEN -180 AND 180
                          {$timeFilterSql}
                        ORDER BY im.node_id, im.measured_at DESC, im.id DESC
                    ");
                } else {
                    $subQuery = DB::table('iot_measurements as sub_m')
                        ->join('iot_nodes as sub_n', 'sub_m.node_id', '=', 'sub_n.id')
                        ->select('sub_m.node_id', DB::raw('MAX(sub_m.measured_at) as max_measured_at'))
                        ->where('sub_n.is_active', true);

                    if ($hoursInt !== null) {
                        $subQuery->where('sub_m.measured_at', '>=', now()->subHours($hoursInt));
                    }
                    $subQuery->groupBy('sub_m.node_id');

                    $rawMeasurements = DB::table('iot_measurements as im')
                        ->joinSub($subQuery, 'latest', function ($join) {
                            $join->on('im.node_id', '=', 'latest.node_id')
                                 ->on('im.measured_at', '=', 'latest.max_measured_at');
                        })
                        ->join('iot_nodes as n', 'im.node_id', '=', 'n.id')
                        ->where('n.is_active', true)
                        ->whereNotNull('im.turbidity')
                        ->where('im.turbidity', '>=', 0)
                        ->select(
                            'im.id as measurement_id',
                            'im.node_id',
                            'n.hardware_id',
                            'n.latitude',
                            'n.longitude',
                            'im.turbidity',
                            'im.pressure',
                            'im.measured_at'
                        )
                        ->orderBy('im.node_id')
                        ->orderByDesc('im.id')
                        ->get();
                }
            } else {
                $rawMeasurements = $baseHeatmapQuery
                    ->select(
                        'im.id as measurement_id',
                        'im.node_id',
                        'n.hardware_id',
                        'n.latitude',
                        'n.longitude',
                        'im.turbidity',
                        'im.pressure',
                        'im.measured_at'
                    )
                    ->orderBy('im.node_id')
                    ->orderByDesc('im.measured_at')
                    ->orderByDesc('im.id')
                    ->get();
            }

            // 3. Hitung Risk Score (60:40) untuk Setiap Titik Sensor
            $heatmap = [];
            $allScores = [];

            foreach ($rawMeasurements as $row) {
                $m = (array) $row;
                $lat = (float) $m['latitude'];
                $lng = (float) $m['longitude'];
                $turbidity = (float) $m['turbidity'];

                // Komponen 60%: Normalisasi Turbidity
                $scoreT = self::normalizeTurbidity($turbidity);

                // Komponen 40%: Kontribusi Klaster Laporan Warga K-Means Terdekat
                $scoreS = 0.0;
                if ($clusters->isNotEmpty()) {
                    $nearestCluster = null;
                    $minDist = INF;
                    foreach ($clusters as $c) {
                        $d = self::spatialDistance($lat, $lng, (float) $c->centroid_lat, (float) $c->centroid_lng);
                        if ($d < $minDist) {
                            $minDist = $d;
                            $nearestCluster = $c;
                        }
                    }

                    if ($nearestCluster) {
                        $density = (int) ($nearestCluster->report_count ?? 1);
                        $scoreS = min(100.0, $density * 15.0);
                    }
                }

                $riskScore = self::calculateFinalRiskScore($scoreT, $scoreS);
                $intensity = self::calculateRiskIntensity($riskScore);
                $riskLevel = self::getRiskLevel($riskScore);
                $allScores[] = $riskScore;

                $heatmap[] = [
                    'measurement_id' => (int) $m['measurement_id'],
                    'node_id'        => (int) $m['node_id'],
                    'hardware_id'    => (string) $m['hardware_id'],
                    'latitude'       => $lat,
                    'longitude'      => $lng,
                    'turbidity'      => $turbidity,
                    'score_t'        => round($scoreT, 2),
                    'score_s'        => round($scoreS, 2),
                    'risk_score'     => $riskScore,
                    'intensity'      => $intensity,
                    'risk_level'     => $riskLevel,
                    'category'       => $riskLevel,
                    'measured_at'    => $m['measured_at'],
                ];
            }

            // 4. Query Pin Sensor Nodes
            $nodes = IoTNode::orderBy('id', 'asc')->get();
            $nodesData = $nodes->map(function ($node) {
                $latest = $node->measurements()->orderByDesc('measured_at')->orderByDesc('id')->first();
                $turbidity = $latest ? (float) $latest->turbidity : null;

                $status = 'NORMAL';
                if ($turbidity !== null) {
                    if ($turbidity > 25.0) {
                        $status = 'CRITICAL';
                    } elseif ($turbidity > 5.0) {
                        $status = 'ANOMALY';
                    }
                }

                return [
                    'id'          => $node->id,
                    'node_id'     => (string) ($node->hardware_id ?: "NODE-{$node->id}"),
                    'hardware_id' => $node->hardware_id,
                    'latitude'    => (float) $node->latitude,
                    'longitude'   => (float) $node->longitude,
                    'is_active'   => (bool) $node->is_active,
                    'status'      => $status,
                    'turbidity'   => $turbidity,
                    'pressure'    => $latest ? (float) $latest->pressure : null,
                    'measured_at' => $latest?->measured_at,
                ];
            });

            // 5. Query Layer Laporan Warga (Terpisah dari Heatmap)
            $reports = Report::with(['ticket', 'latestRiskResult'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            $reportsData = $reports->map(function ($r) {
                $ticket = $r->ticket;
                $latestRisk = $r->latestRiskResult;
                $riskScore = $latestRisk ? (int) $latestRisk->risk_score : null;
                $riskLevel = $latestRisk ? $latestRisk->risk_level : null;

                return [
                    'id'          => $r->id,
                    'ticket_id'   => $ticket?->ticket_code ?: "AQT-{$r->id}",
                    'latitude'    => (float) $r->latitude,
                    'longitude'   => (float) $r->longitude,
                    'description' => $r->description,
                    'status'      => $ticket?->status ?? 'SUBMITTED',
                    'risk_score'  => $riskScore,
                    'risk_level'  => $riskLevel,
                    'created_at'  => $r->created_at,
                ];
            });

            // 6. Query Jaringan Pipa (GeoJSON)
            $pipes = PipeNetwork::all();
            $pipeFeatures = [];
            foreach ($pipes as $pipe) {
                if (!empty($pipe->geo_data)) {
                    $pipeFeatures[] = $pipe->geo_data;
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'heatmap'      => $heatmap,
                    'iot_nodes'    => $nodesData,
                    'reports'      => $reportsData,
                    'pipe_network' => [
                        'type'     => 'FeatureCollection',
                        'features' => $pipeFeatures,
                    ],
                    'summary'      => [
                        'total_sensor_points' => count($heatmap),
                        'max_risk_score'      => !empty($allScores) ? max($allScores) : null,
                        'avg_risk_score'      => !empty($allScores) ? round(array_sum($allScores) / count($allScores), 2) : null,
                        'query_mode'          => $mode,
                        'hours_window'        => $hoursInt,
                    ],
                ],
            ], 200);

        } catch (Throwable $e) {
            Log::error('Admin map query failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data peta spasial.',
            ], 500);
        }
    }

    /**
     * GET /api/map/public
     * Peta publik aman untuk warga (DEC-011).
     */
    public function publicMap(): JsonResponse
    {
        try {
            // Get latest K-Means clusters for Score_S component
            $latestAnalysis = MLAnalysis::orderByDesc('generated_at')->orderByDesc('id')->first();
            $clusters = $latestAnalysis ? $latestAnalysis->clusters : collect();

            $nodes = IoTNode::where('is_active', true)
                ->whereBetween('latitude', [-90, 90])
                ->whereBetween('longitude', [-180, 180])
                ->get();

            $markers = [];
            foreach ($nodes as $node) {
                $latest = $node->measurements()
                    ->whereNotNull('turbidity')
                    ->where('turbidity', '>=', 0)
                    ->orderByDesc('measured_at')
                    ->orderByDesc('id')
                    ->first();

                $turbidity = $latest ? (float) $latest->turbidity : null;
                $scoreT = self::normalizeTurbidity($turbidity);

                // Compute Score_S from nearest cluster density
                $scoreS = 0.0;
                if ($clusters->isNotEmpty()) {
                    $lat = (float) $node->latitude;
                    $lng = (float) $node->longitude;
                    $nearestCluster = null;
                    $minDist = INF;
                    foreach ($clusters as $c) {
                        $d = self::spatialDistance($lat, $lng, (float) $c->centroid_lat, (float) $c->centroid_lng);
                        if ($d < $minDist) {
                            $minDist = $d;
                            $nearestCluster = $c;
                        }
                    }
                    if ($nearestCluster) {
                        $density = (int) ($nearestCluster->report_count ?? 1);
                        $scoreS = min(100.0, $density * 15.0);
                    }
                }

                $riskScore = self::calculateFinalRiskScore($scoreT, $scoreS);
                $level = self::getRiskLevel($riskScore);

                $markers[] = [
                    'id'         => $node->id,
                    'name'       => "Area Sensor {$node->hardware_id}",
                    'lat'        => (float) $node->latitude,
                    'lng'        => (float) $node->longitude,
                    'level'      => $level,
                    'risk_score' => $riskScore,
                    'updated_at' => $latest?->measured_at ?? $node->created_at,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'markers' => $markers,
                ],
            ], 200);

        } catch (Throwable $e) {
            Log::error('Public map query failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat peta publik.',
            ], 500);
        }
    }
}
