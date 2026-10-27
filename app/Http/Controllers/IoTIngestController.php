<?php

namespace App\Http\Controllers;

use App\Models\IoTNode;
use App\Models\IoTMeasurement;
use App\Http\Requests\StoreIoTMeasurementRequest;
use Illuminate\Http\JsonResponse;

class IoTIngestController extends Controller
{
    /**
     * Store a newly created IoT measurement in storage.
     */
    public function store(StoreIoTMeasurementRequest $request): JsonResponse
    {
        $hardwareId = $request->header('X-Hardware-Id');

        if (!$hardwareId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Missing X-Hardware-Id'], 401);
        }

        $node = IoTNode::where('hardware_id', $hardwareId)->first();

        if (!$node) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Unknown hardware'], 401);
        }

        $data = $request->validated();

        $measurement = new IoTMeasurement();
        $measurement->node_id = $node->id;
        $measurement->turbidity = $data['turbidity'];

        $measurement->pressure = $data['pressure'];
        $measurement->measured_at = $data['measured_at'] ?? now();
        $measurement->save();

        return response()->json([
            'success' => true,
            'message' => 'Measurement recorded',
            'measurement_id' => $measurement->id
        ], 201);
    }
}
