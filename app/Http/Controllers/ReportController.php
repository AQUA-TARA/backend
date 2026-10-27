<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Models\Ticket;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportController extends Controller
{
    protected SupabaseStorageService $storageService;

    public function __construct(SupabaseStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Store a new citizen report.
     *
     * POST /api/reports
     *
     * Business rules (LOCKED):
     * - citizen_id is nullable: anonymous reporting is allowed (DEC-005, DEC-006).
     * - Report + Ticket created atomically in a DB transaction.
     *   If either fails, no partial record is left behind.
     * - Initial Ticket status = SUBMITTED (DEC-002).
     * - Ticket ID is returned to the citizen.
     * - No ML analysis, no RiskResult, no Cluster created here.
     * - Status is NOT auto-advanced to IN_REVIEW.
     *
     * Photo storage:
     * - Binary is NOT stored in the database.
     * - photo_path stores the object path in private bucket 'report-images'.
     * - If storage upload fails or DB transaction fails, cleanup object and return safe error.
     *
     * @param  StoreReportRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreReportRequest $request)
    {
        $uploadedPath = null;
        $isCloudStorage = false;

        try {
            // -----------------------------------------------------------------
            // 1. Store the photo file (Supabase Private Bucket or Local fallback)
            // -----------------------------------------------------------------
            $file = $request->file('photo');

            if ($this->storageService->isConfigured()) {
                $uploadedPath = $this->storageService->upload($file, 'reports');
                $isCloudStorage = true;
            } else {
                // Temporary local disk fallback if storage is unconfigured
                $uploadedPath = $file->store('reports', 'public');
            }

            // -----------------------------------------------------------------
            // 2. Atomic transaction: create Report then Ticket.
            // -----------------------------------------------------------------
            $result = DB::transaction(function () use ($request, $uploadedPath) {
                // Resolve citizen identity if authenticated
                $citizenId = auth('citizen')->check() ? auth('citizen')->id() : null;

                $report = Report::create([
                    'citizen_id'  => $citizenId,
                    'report_type' => $request->input('report_type'),
                    'description' => $request->input('description'),
                    'latitude'    => $request->input('latitude'),
                    'longitude'   => $request->input('longitude'),
                    'photo_path'  => $uploadedPath,
                ]);

                // Create the Ticket for this Report with sequential AQT-YYMMDD-NNNN code
                // Status = SUBMITTED (DEC-002 initial state).
                $ticketCode = Ticket::generateNextTicketCode();

                $ticket = Ticket::create([
                    'report_id'   => $report->id,
                    'ticket_code' => $ticketCode,
                    'status'      => 'SUBMITTED',
                ]);

                return $ticket;
            });

            // -----------------------------------------------------------------
            // 3. Return the success response.
            // -----------------------------------------------------------------
            // Determine the public ticket identifier (ticket_code) or fallback format.
            $publicTicketId = $result->ticket_code ?: "AQT-{$result->id}";
            return response()->json([
                'success' => true,
                'data'    => [
                    'id'          => $result->id,
                    'ticket_id'   => $result->id,
                    'ticket_code' => $publicTicketId,
                    'status'      => $result->status,
                ],
            ], 201);

        } catch (Throwable $e) {
            // If cloud object was created but DB transaction or later step failed, cleanup
            if ($isCloudStorage && $uploadedPath) {
                try {
                    $this->storageService->delete($uploadedPath);
                } catch (Throwable $cleanupError) {
                    Log::warning('Failed cleaning up orphan Supabase storage object', [
                        'path' => $uploadedPath,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }

            Log::error('Report submission failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Laporan gagal disimpan. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Get ticket tracking information.
     *
     * GET /api/tickets/{ticket_id}
     *
     * Business rules (LOCKED):
     * - Anonymous access allowed (DEC-006).
     * - Returns current status and history.
     * - Generates short-lived signed URL for private bucket photo without exposing arbitrary paths.
     *
     * @param  string  $ticket_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($ticket_id)
    {
        try {
            $ticketQuery = Ticket::with([
                'report',
                'statusHistories' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ]);

            // Support finding by ticket_code (e.g. AQT-260420-0012) or numeric PK
            if (is_numeric($ticket_id)) {
                $ticket = $ticketQuery->where('id', $ticket_id)->orWhere('ticket_code', (string) $ticket_id)->first();
            } else {
                $ticket = $ticketQuery->where('ticket_code', $ticket_id)->first();
            }

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiket tidak ditemukan.',
                ], 404);
            }

            // Generate signed URL if photo_path exists on the associated report
            $photoUrl = null;
            if ($ticket->report && $ticket->report->photo_path) {
                $photoUrl = $this->storageService->createSignedUrl($ticket->report->photo_path, 3600);
            }

            // Map canonical internal status to display status (DEC-002)
            $statusMapping = [
                'SUBMITTED'   => 'Diterima',
                'IN_REVIEW'   => 'Sedang Ditinjau',
                'CONFIRMED'   => 'Laporan Dikonfirmasi',
                'IN_PROGRESS' => 'Sedang Ditangani',
                'CLOSED'      => 'Selesai',
            ];

            $publicTicketId = $ticket->ticket_code ?: "AQT-{$ticket->id}";

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'             => $ticket->id,
                    'ticket_id'      => $ticket->id,
                    'ticket_code'    => $publicTicketId,
                    'cluster_code'   => $ticket->cluster_code,
                    'status'         => $ticket->status,
                    'display_status' => $statusMapping[$ticket->status] ?? $ticket->status,
                    'photo_url'      => $photoUrl,
                    'created_at'     => $ticket->created_at,
                    'updated_at'     => $ticket->updated_at,
                ],
            ], 200);

        } catch (Throwable $e) {
            Log::error('Get ticket failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'ticket_id' => $ticket_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tiket.',
            ], 500);
        }
    }
}
