<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminMapController;
use Tests\TestCase;

class SensorHeatmapMapTest extends TestCase
{
    /**
     * Test 1: Normalisasi Turbidity (Score_T) sesuai aturan matematis ML (0 - 100):
     * - t <= 5.0 -> (t / 5.0) * 30.0
     * - 5.0 < t <= 25.0 -> 30.0 + ((t - 5.0) / 20.0) * 40.0
     * - t > 25.0 -> 70.0 + min(30.0, ((t - 25.0) / 25.0) * 30.0)
     * - null / invalid -> 30.0
     */
    public function test_turbidity_normalization_score_t()
    {
        // Null / Fallback
        $this->assertEquals(30.0, AdminMapController::normalizeTurbidity(null));

        // Tepat 0 NTU
        $this->assertEquals(0.0, AdminMapController::normalizeTurbidity(0.0));

        // Tepat 5 NTU (Batas Jernih) -> 30.0
        $this->assertEquals(30.0, AdminMapController::normalizeTurbidity(5.0));

        // Di antara 5 dan 25 NTU (Waspada, misal 15 NTU)
        // 30.0 + ((15 - 5) / 20) * 40.0 = 30.0 + 20.0 = 50.0
        $this->assertEquals(50.0, AdminMapController::normalizeTurbidity(15.0));

        // Tepat 25 NTU (Batas Waspada) -> 70.0
        $this->assertEquals(70.0, AdminMapController::normalizeTurbidity(25.0));

        // Di atas 25 NTU (Bahaya, misal 37.5 NTU)
        // 70.0 + ((37.5 - 25) / 25) * 30.0 = 70.0 + 15.0 = 85.0
        $this->assertEquals(85.0, AdminMapController::normalizeTurbidity(37.5));

        // Nilai sangat tinggi (150 NTU) -> Clamped di 100.0
        $this->assertEquals(100.0, AdminMapController::normalizeTurbidity(150.0));
    }

    /**
     * Test 2: Formula Final Risk Score 60:40 (60% Turbidity + 40% Spatial Cluster Density)
     */
    public function test_final_risk_score_60_40_calculation()
    {
        // Kasus 1: Sensor Jernih
        // (0.6 * 10) + (0.4 * 0) = 6
        $this->assertEquals(
            6,
            AdminMapController::calculateFinalRiskScore(10.0, 0.0)
        );

        // Kasus 2: Sensor Waspada
        // (0.6 * 50) + (0.4 * 30) = 42
        $this->assertEquals(
            42,
            AdminMapController::calculateFinalRiskScore(50.0, 30.0)
        );

        // Kasus 3: Sensor Bahaya
        // (0.6 * 85) + (0.4 * 75) = 51 + 30 = 81
        $this->assertEquals(
            81,
            AdminMapController::calculateFinalRiskScore(85.0, 75.0)
        );

        // Kasus 4: Extreme Clamping [0, 100]
        $this->assertEquals(
            0,
            AdminMapController::calculateFinalRiskScore(-10.0, -10.0)
        );

        $this->assertEquals(
            100,
            AdminMapController::calculateFinalRiskScore(150.0, 150.0)
        );
    }

    /**
     * Test 3: Kategori Risk Level & Intensitas Heatmap (0.00 - 1.00)
     */
    public function test_risk_level_and_intensity_mapping()
    {
        // 0 - 30: JERNIH
        $this->assertEquals('JERNIH', AdminMapController::getRiskLevel(0));
        $this->assertEquals('JERNIH', AdminMapController::getRiskLevel(30));
        $this->assertEquals(0.0, AdminMapController::calculateRiskIntensity(0));
        $this->assertEquals(0.30, AdminMapController::calculateRiskIntensity(30));

        // 31 - 70: WASPADA
        $this->assertEquals('WASPADA', AdminMapController::getRiskLevel(31));
        $this->assertEquals('WASPADA', AdminMapController::getRiskLevel(70));
        $this->assertEquals(0.50, AdminMapController::calculateRiskIntensity(50));
        $this->assertEquals(0.70, AdminMapController::calculateRiskIntensity(70));

        // 71 - 100: BAHAYA
        $this->assertEquals('BAHAYA', AdminMapController::getRiskLevel(71));
        $this->assertEquals('BAHAYA', AdminMapController::getRiskLevel(100));
        $this->assertEquals(0.85, AdminMapController::calculateRiskIntensity(85));
        $this->assertEquals(1.0, AdminMapController::calculateRiskIntensity(100));
    }

    /**
     * Test 4: Endpoint admin /api/admin/map dilindungi middleware supabase.jwt
     */
    public function test_admin_map_endpoint_requires_auth()
    {
        $response = $this->getJson('/api/admin/map');
        $response->assertStatus(401);
    }
}

