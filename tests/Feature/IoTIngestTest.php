<?php

namespace Tests\Feature;

use App\Models\IoTMeasurement;
use App\Models\IoTNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IoTIngestTest extends TestCase
{
    // Do NOT use RefreshDatabase or DatabaseTransactions if it resets the baseline.
    // The user strictly said "NO DB RESET. NO migrate:fresh. Jangan reset production/development database".
    // I will manage state by creating and then cleaning up, or just use DatabaseTransactions since it rolls back after test without dropping tables.
    // Let's use DatabaseTransactions so it doesn't leave data.
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_valid_request_creates_measurement_and_returns_201()
    {
        $node = IoTNode::create([
            'hardware_id' => 'HW-TEST-001',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'is_active' => true,
        ]);

        $initialCount = IoTMeasurement::count();

        $payload = [
            'turbidity' => 12.5,
            'pressure' => 1.2,
            'measured_at' => now()->subMinutes(5)->toIso8601String(),
        ];

        $response = $this->postJson('/api/iot/ingest', $payload, [
            'X-Hardware-Id' => 'HW-TEST-001'
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Measurement recorded'
                 ]);

        $this->assertEquals($initialCount + 1, IoTMeasurement::count());

        $this->assertDatabaseHas('iot_measurements', [
            'node_id' => $node->id,
            'turbidity' => 12.5,
            'pressure' => 1.2,
        ]);
    }

    public function test_unknown_hardware_id_returns_401()
    {
        $initialCount = IoTMeasurement::count();

        $payload = [
            'turbidity' => 12.5,
            'pressure' => 1.2,
        ];

        $response = $this->postJson('/api/iot/ingest', $payload, [
            'X-Hardware-Id' => 'UNKNOWN-HW'
        ]);

        $response->assertStatus(401);
        $this->assertEquals($initialCount, IoTMeasurement::count());
    }

    public function test_missing_hardware_id_returns_401()
    {
        $initialCount = IoTMeasurement::count();

        $payload = [
            'turbidity' => 12.5,
            'pressure' => 1.2,
        ];

        $response = $this->postJson('/api/iot/ingest', $payload);

        $response->assertStatus(401);
        $this->assertEquals($initialCount, IoTMeasurement::count());
    }

    public function test_missing_required_fields_returns_422()
    {
        $node = IoTNode::create([
            'hardware_id' => 'HW-TEST-002',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $headers = ['X-Hardware-Id' => 'HW-TEST-002'];

        $this->postJson('/api/iot/ingest', ['pressure' => 1.2], $headers)
             ->assertStatus(422)
             ->assertJsonValidationErrors('turbidity');

        $this->postJson('/api/iot/ingest', ['turbidity' => 12.5], $headers)
             ->assertStatus(422)
             ->assertJsonValidationErrors('pressure');
    }

    public function test_null_or_non_numeric_sensor_value_returns_422()
    {
        $node = IoTNode::create([
            'hardware_id' => 'HW-TEST-003',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $headers = ['X-Hardware-Id' => 'HW-TEST-003'];

        $this->postJson('/api/iot/ingest', [
            'turbidity' => null, 'pressure' => 1.2
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('turbidity');

        $this->postJson('/api/iot/ingest', [
            'turbidity' => 'abc', 'pressure' => 1.2
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('turbidity');
    }

    public function test_optional_measured_at_omitted_uses_server_timestamp()
    {
        $node = IoTNode::create([
            'hardware_id' => 'HW-TEST-004',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $payload = [
            'turbidity' => 12.5,
            'pressure' => 1.2,
        ];

        $response = $this->postJson('/api/iot/ingest', $payload, [
            'X-Hardware-Id' => 'HW-TEST-004'
        ]);

        $response->assertStatus(201);
        $measurementId = $response->json('measurement_id');
        $measurement = IoTMeasurement::find($measurementId);
        
        $this->assertNotNull($measurement->measured_at);
    }

    public function test_future_measured_at_returns_422()
    {
        $node = IoTNode::create([
            'hardware_id' => 'HW-TEST-005',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $payload = [
            'turbidity' => 12.5,
            'pressure' => 1.2,
            'measured_at' => now()->addMinutes(5)->toIso8601String(),
        ];

        $response = $this->postJson('/api/iot/ingest', $payload, [
            'X-Hardware-Id' => 'HW-TEST-005'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('measured_at');
    }

    public function test_negative_turbidity_returns_422()
    {
        $node = IoTNode::create([
            'hardware_id' => 'HW-TEST-006',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);

        $payload = [
            'turbidity' => -5.0,
            'pressure' => 1.2,
        ];

        $response = $this->postJson('/api/iot/ingest', $payload, [
            'X-Hardware-Id' => 'HW-TEST-006'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('turbidity');
    }


}
