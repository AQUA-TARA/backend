<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CitizenHistoryController
 *
 * Serves GET /api/citizen/history — authenticated Citizen report history.
 *
 * Identity source: auth:citizen guard (Sanctum token).
 * citizen_id is NEVER taken from any client-supplied input.
 *
 * Source: API_CONTRACT.md §15, DEC-005, DEC-006
 */
class CitizenHistoryController extends Controller
{
    protected SupabaseStorageService $storageService;

    public function __construct(SupabaseStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Status display mapping (DEC-002).
     */
    private const STATUS_MAP = [
        'SUBMITTED'   => 'Diterima',
        'IN_REVIEW'   => 'Sedang Ditinjau',
        'CONFIRMED'   => 'Laporan Dikonfirmasi',
        'IN_PROGRESS' => 'Sedang Ditangani',
        'CLOSED'      => 'Selesai',
    ];

    /**
     * GET /api/citizen/history
     *
     * Returns the list of tickets/reports belonging to the authenticated Citizen.
     *
     * Rules:
     * - Protected by auth:citizen middleware — no unauthenticated access.
     * - citizen_id resolved from authenticated principal (never from request).
     * - Only reports WHERE citizen_id = authenticated citizen — no anonymous mixing.
     * - Citizen A cannot see Citizen B's data.
     * - Empty result → HTTP 200 with empty list (not 404).
     * - No ML/risk data, no admin data.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // ── Resolve authenticated Citizen identity from token (NEVER from input) ──
            /** @var \App\Models\Citizen $citizen */
            $citizen = $request->user('citizen');

            // ── Eager load: Report → Ticket (avoid N+1 query) ────────────────
            // Filter strictly by authenticated citizen_id.
            // Exclude anonymous reports implicitly (they have citizen_id = NULL,
            // which will never equal $citizen->id — no explicit NULL check needed).
            $reports = Report::where('citizen_id', $citizen->id)
                ->with(['ticket'])
                ->orderBy('created_at', 'desc')
                ->get();

            $history = $reports->map(function (Report $report) {
                $ticket = $report->ticket;
                $status = $ticket?->status ?? 'UNKNOWN';

                return [
                    'report_id'      => $report->id,
                    'ticket_id'      => $ticket?->id,
                    'status'         => $status,
                    'display_status' => self::STATUS_MAP[$status] ?? $status,
                    'description'    => $report->description,
                    'photo_url'      => $this->storageService->createSignedUrl($report->photo_path, 3600),
                    'created_at'     => $report->created_at,
                    'ticket_updated_at' => $ticket?->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $history,
                'meta'    => [
                    'total'      => $history->count(),
                    'citizen_id' => $citizen->id,  // echoed back for transparency
                ],
            ], 200);

        } catch (Throwable $e) {
            Log::error('Citizen history failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat laporan.',
            ], 500);
        }
    }
}
