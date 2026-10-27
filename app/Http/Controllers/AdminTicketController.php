<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminTicketController extends Controller
{
    const VALID_TRANSITIONS = [
        'SUBMITTED'   => 'IN_REVIEW',
        'IN_REVIEW'   => 'CONFIRMED',
        'CONFIRMED'   => 'IN_PROGRESS',
        'IN_PROGRESS' => 'CLOSED',
    ];

    const STATUS_DISPLAY = [
        'SUBMITTED'   => 'Diterima',
        'IN_REVIEW'   => 'Sedang Ditinjau',
        'CONFIRMED'   => 'Laporan Dikonfirmasi',
        'IN_PROGRESS' => 'Sedang Ditangani',
        'CLOSED'      => 'Selesai',
    ];

    public function updateStatus(Request $request, $ticket_id)
    {
        if (!auth()->check() || !(auth()->user() instanceof \App\Models\User)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 401);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:SUBMITTED,IN_REVIEW,CONFIRMED,IN_PROGRESS,CLOSED'],
        ]);

        $newStatus = $validated['status'];

        try {
            $result = DB::transaction(function () use ($ticket_id, $newStatus) {
                $query = Ticket::lockForUpdate();
                if (is_numeric($ticket_id)) {
                    $ticket = $query->where('id', $ticket_id)->orWhere('ticket_code', (string) $ticket_id)->first();
                } else {
                    $ticket = $query->where('ticket_code', $ticket_id)->first();
                }

                if (!$ticket) {
                    return ['error' => 'not_found'];
                }

                $currentStatus = $ticket->status;

                if ($currentStatus === $newStatus) {
                    return ['error' => 'same_status', 'current' => $currentStatus];
                }

                $allowedNext = self::VALID_TRANSITIONS[$currentStatus] ?? null;

                if ($allowedNext !== $newStatus) {
                    return [
                        'error'     => 'invalid_transition',
                        'current'   => $currentStatus,
                        'requested' => $newStatus,
                        'allowed'   => $allowedNext,
                    ];
                }

                $ticket->status = $newStatus;
                $ticket->save();

                TicketStatusHistory::create([
                    'ticket_id'  => $ticket->id,
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'actor_id'   => auth()->id(),
                    'created_at' => now(),
                ]);

                return ['ticket' => $ticket];
            });

            if (isset($result['error'])) {
                return match ($result['error']) {
                    'not_found' => response()->json([
                        'success' => false,
                        'message' => 'Tiket tidak ditemukan.',
                    ], 404),
                    'same_status' => response()->json([
                        'success' => false,
                        'message' => 'Status tiket sudah sama. Tidak ada perubahan.',
                        'current_status' => $result['current'],
                    ], 422),
                    'invalid_transition' => response()->json([
                        'success' => false,
                        'message' => 'Transisi status tidak valid.',
                        'current_status'   => $result['current'],
                        'requested_status' => $result['requested'],
                        'allowed_next'     => $result['allowed'],
                    ], 422),
                    default => response()->json(['success' => false, 'message' => 'Unknown error.'], 500),
                };
            }

            $ticket = $result['ticket'];

            return response()->json([
                'success' => true,
                'message' => 'Status tiket berhasil diperbarui.',
                'data'    => [
                    'id'             => $ticket->id,
                    'ticket_id'      => $ticket->id,
                    'ticket_code'    => $ticket->ticket_code ?: "AQT-{$ticket->id}",
                    'cluster_code'   => $ticket->cluster_code,
                    'status'         => $ticket->status,
                    'display_status' => self::STATUS_DISPLAY[$ticket->status] ?? $ticket->status,
                    'updated_at'     => $ticket->updated_at,
                ],
            ], 200);

        } catch (Throwable $e) {
            Log::error('Admin ticket status update failed', [
                'ticket_id' => $ticket_id,
                'message'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status tiket. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * List admin tickets with optional search and filters.
     *
     * GET /api/admin/tickets
     */
    public function index(Request $request)
    {
        $query = Ticket::with(['report.latestRiskResult']);

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Multi-field search: ticket_code, cluster_code, description, coordinates, or ID
        if ($request->filled('q')) {
            $search = trim($request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('ticket_code', 'ILIKE', "%{$search}%")
                  ->orWhere('cluster_code', 'ILIKE', "%{$search}%")
                  ->orWhereHas('report', function ($rq) use ($search) {
                      $rq->where('description', 'ILIKE', "%{$search}%")
                         ->orWhere('latitude', 'ILIKE', "%{$search}%")
                         ->orWhere('longitude', 'ILIKE', "%{$search}%");
                  });

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        // Date range filters
        if ($request->filled('start_date')) {
            $query->whereHas('report', function ($q) use ($request) {
                $q->where('created_at', '>=', $request->input('start_date'));
            });
        }
        if ($request->filled('end_date')) {
            // Include the entire end date by setting time to end of day
            $endDate = $request->input('end_date') . ' 23:59:59';
            $query->whereHas('report', function ($q) use ($endDate) {
                $q->where('created_at', '<=', $endDate);
            });
        }

        $tickets = $query->orderBy('id', 'desc')->get();

        $transformed = $tickets->map(function ($ticket) {
            $ticketArray = $ticket->toArray();
            $ticketArray['ticket_id'] = $ticket->id;
            $ticketArray['ticket_code'] = $ticket->ticket_code ?: "AQT-{$ticket->id}";
            $latestRisk = $ticket->report?->latestRiskResult;
            $ticketArray['risk_score'] = $latestRisk ? $latestRisk->risk_score : null;
            $ticketArray['risk_level'] = $latestRisk ? $latestRisk->risk_level : null;
            return $ticketArray;
        });

        return response()->json([
            'success' => true,
            'data' => $transformed,
        ], 200);
    }


    /**
     * Show detailed information for a specific ticket.
     *
     * GET /api/admin/tickets/{ticket_id}
     */
    public function show($ticket_id)
    {
        $query = Ticket::with(['report.latestRiskResult', 'statusHistories']);
        if (is_numeric($ticket_id)) {
            $ticket = $query->where('id', $ticket_id)->orWhere('ticket_code', (string) $ticket_id)->first();
        } else {
            $ticket = $query->where('ticket_code', $ticket_id)->first();
        }

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        $statusMapping = [
            'SUBMITTED'   => 'Diterima',
            'IN_REVIEW'   => 'Sedang Ditinjau',
            'CONFIRMED'   => 'Laporan Dikonfirmasi',
            'IN_PROGRESS' => 'Sedang Ditangani',
            'CLOSED'      => 'Selesai',
        ];

        $publicId = $ticket->ticket_code ?: "AQT-{$ticket->id}";
        $latestRisk = $ticket->report?->latestRiskResult;

        return response()->json([
            'success' => true,
            'data' => [
                'id'             => $ticket->id,
                'ticket_id'      => $ticket->id,
                'ticket_code'    => $publicId,
                'cluster_code'   => $ticket->cluster_code,
                'status'         => $ticket->status,
                'display_status' => $statusMapping[$ticket->status] ?? $ticket->status,
                'risk_score'     => $latestRisk ? $latestRisk->risk_score : null,
                'risk_level'     => $latestRisk ? $latestRisk->risk_level : null,
                'report'         => $ticket->report,
                'history'        => $ticket->statusHistories,
            ],
        ], 200);
    }


    /**
     * Export tickets as CSV.
     *
     * GET /api/admin/tickets/export/csv
     */
    public function exportCsv(Request $request)
    {
        $query = Ticket::with('report');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $search = trim($request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('ticket_code', 'ILIKE', "%{$search}%")
                  ->orWhere('cluster_code', 'ILIKE', "%{$search}%")
                  ->orWhereHas('report', function ($rq) use ($search) {
                      $rq->where('description', 'ILIKE', "%{$search}%");
                  });

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        // Apply same date filters as index
        if ($request->filled('start_date')) {
            $query->whereHas('report', function ($q) use ($request) {
                $q->where('created_at', '>=', $request->input('start_date'));
            });
        }
        if ($request->filled('end_date')) {
            // Include the entire end date
            $endDate = $request->input('end_date') . ' 23:59:59';
            $query->whereHas('report', function ($q) use ($endDate) {
                $q->where('created_at', '<=', $endDate);
            });
        }
        $tickets = $query->orderBy('id', 'desc')->get();

        $callback = function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ticket_id', 'status', 'report_id', 'description', 'latitude', 'longitude', 'created_at']);
            foreach ($tickets as $ticket) {
                $report = $ticket->report;
                fputcsv($handle, [
                    $ticket->ticket_code ?: "AQT-{$ticket->id}",
                    $ticket->status,
                    $ticket->report_id,
                    $report ? $report->description : '',
                    $report ? $report->latitude : '',
                    $report ? $report->longitude : '',
                    $report ? $report->created_at : '',
                ]);
            }
            fclose($handle);
        };
        return response()->streamDownload($callback, 'tickets_report_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Get summary metrics and urgent tickets for the admin dashboard.
     *
     * GET /api/admin/dashboard
     */
    public function dashboard(Request $request)
    {
        $todayStart = now()->startOfDay();
        $totalReportsToday = \App\Models\Report::where('created_at', '>=', $todayStart)->count();

        // Rata-rata kekeruhan dari pengukuran sensor IoT dalam 24 jam terakhir (atau keseluruhan jika kosong)
        $avgTurbidity = \App\Models\IoTMeasurement::where('measured_at', '>=', now()->subHours(24))
            ->whereNotNull('turbidity')
            ->avg('turbidity');

        if ($avgTurbidity === null) {
            $avgTurbidity = \App\Models\IoTMeasurement::whereNotNull('turbidity')->avg('turbidity');
        }
        $avgTurbidityFormatted = $avgTurbidity !== null ? round((float) $avgTurbidity, 1) : null;

        // Rata-rata tekanan dari pengukuran sensor IoT dalam 24 jam terakhir (atau keseluruhan jika kosong)
        $avgPressure = \App\Models\IoTMeasurement::where('measured_at', '>=', now()->subHours(24))
            ->whereNotNull('pressure')
            ->avg('pressure');

        if ($avgPressure === null) {
            $avgPressure = \App\Models\IoTMeasurement::whereNotNull('pressure')->avg('pressure');
        }
        $avgPressureFormatted = $avgPressure !== null ? round((float) $avgPressure, 2) : null;

        // Active maintenance: jumlah tiket yang sedang dikerjakan
        $activeMaintenance = Ticket::whereIn('status', ['CONFIRMED', 'IN_PROGRESS'])->count();

        // Status node IoT
        $totalNode = \App\Models\IoTNode::count();
        $jumlahNodeAktif = \App\Models\IoTNode::where('is_active', true)->count();
        $jumlahNodeNonaktif = \App\Models\IoTNode::where('is_active', false)->count();
        $persentaseNodeAktif = $totalNode > 0 ? round(($jumlahNodeAktif / $totalNode) * 100, 1) : 0;

        // Tren laporan 7 hari terakhir
        $trendLabels = [];
        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayName = match ($date->dayOfWeek) {
                0 => 'Min',
                1 => 'Sen',
                2 => 'Sel',
                3 => 'Rab',
                4 => 'Kam',
                5 => 'Jum',
                6 => 'Sab',
            };
            $trendLabels[] = $dayName;
            $count = \App\Models\Report::whereDate('created_at', $date->toDateString())->count();
            $trendData[] = $count;
        }

        // Urgent tickets: ambil tiket aktif
        $urgentTicketsQuery = Ticket::with(['report.latestRiskResult'])
            ->whereIn('status', ['SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $urgentTickets = $urgentTicketsQuery->map(function ($ticket) {
            $report = $ticket->report;
            $latestRisk = $report?->latestRiskResult;
            $locationStr = 'Tarakan';
            if ($report && $report->latitude && $report->longitude) {
                $locationStr = "Tarakan ({$report->latitude}, {$report->longitude})";
            }

            return [
                'ticket_id'      => $ticket->id,
                'ticket_code'    => $ticket->ticket_code ?: "AQT-{$ticket->id}",
                'cluster_id'     => $ticket->cluster_code,
                'lokasi'         => $locationStr,
                'turbidity'      => $latestRisk && $latestRisk->sensor_score !== null ? round($latestRisk->sensor_score) : null,
                'risk_score'     => $latestRisk ? $latestRisk->risk_score : 50,
                'status'         => $ticket->status,
                'gabungan_count' => $ticket->cluster_code ? 1 : null,
            ];
        });

        // Urutkan tiket berdasarkan risk_score tertinggi
        $sortedUrgentTickets = $urgentTickets->sortByDesc('risk_score')->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'total_laporan_hari_ini' => $totalReportsToday,
                'rata_rata_kekeruhan'    => $avgTurbidityFormatted,
                'rata_rata_tekanan'      => $avgPressureFormatted,
                'active_maintenance'     => $activeMaintenance,
                'system_uptime'          => 99.9,
                'persentase_node_aktif'  => $persentaseNodeAktif,
                'jumlah_node_aktif'      => $jumlahNodeAktif,
                'jumlah_node_nonaktif'   => $jumlahNodeNonaktif,
                'total_node'             => $totalNode,
                'tren_laporan'           => [
                    'labels' => $trendLabels,
                    'data'   => $trendData,
                ],
                'urgent_tickets'         => $sortedUrgentTickets,
            ],
        ], 200);
    }

    /**
     * List IoT sensor nodes for admin management.
     *
     * GET /api/admin/nodes
     */
    public function iotNodesIndex(Request $request)
    {
        $nodes = \App\Models\IoTNode::query()
            ->with('latestMeasurement')
            ->select([
                'id',
                'hardware_id',
                'latitude',
                'longitude',
                'is_active',
                'created_at',
            ])
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($node) {
                $latest = $node->latestMeasurement;
                $turbidity = $latest !== null
                    ? (float) $latest->turbidity
                    : null;

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
                    'hardware_id' => $node->hardware_id,
                    'latitude'    => (float) $node->latitude,
                    'longitude'   => (float) $node->longitude,
                    'is_active'   => (bool) $node->is_active,
                    'turbidity'   => $turbidity,
                    'pressure'    => $latest !== null && $latest->pressure !== null
                        ? (float) $latest->pressure
                        : null,
                    'status'      => $status,
                    'measured_at' => $latest?->measured_at,
                    'created_at'  => $node->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $nodes,
        ], 200);
    }

    /**
     * Create a new IoT sensor node.
     *
     * POST /api/admin/nodes
     */
    public function iotNodesStore(Request $request)
    {
        $validated = $request->validate([
            'hardware_id' => ['required', 'string', 'max:255', 'unique:iot_nodes,hardware_id'],
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $node = \App\Models\IoTNode::create([
            'hardware_id' => trim($validated['hardware_id']),
            'latitude'    => $validated['latitude'],
            'longitude'   => $validated['longitude'],
            'is_active'   => $request->has('is_active') ? (bool) $validated['is_active'] : true,
            'created_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Node sensor IoT berhasil ditambahkan.',
            'data'    => [
                'id'          => $node->id,
                'hardware_id' => $node->hardware_id,
                'latitude'    => (float) $node->latitude,
                'longitude'   => (float) $node->longitude,
                'is_active'   => (bool) $node->is_active,
                'turbidity'   => null,
                'pressure'    => null,
                'status'      => 'NORMAL',
                'created_at'  => $node->created_at,
            ],
        ], 201);
    }
}
