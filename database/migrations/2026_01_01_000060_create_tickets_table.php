<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: tickets table = Ticket entity.
     * Setiap Report menghasilkan tepat satu Ticket (1:1).
     * UNIQUE(report_id) memastikan relasi 1:1 antara Report dan Ticket.
     *
     * status menggunakan CHECK constraint dengan nilai canonical lifecycle (DEC-002):
     *   SUBMITTED → IN_REVIEW → CONFIRMED → IN_PROGRESS → CLOSED
     *
     * INDEX pada status: dibutuhkan untuk filtering antrean Triage Admin
     * (GET /api/admin/tickets?status=...). Ini adalah query bottleneck utama.
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 tickets, §10 Index Strategy
     * Depends on: reports
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();                                     // BIGINT UNSIGNED AUTO_INCREMENT PK

            // FK ke reports — UNIQUE karena satu Report hanya memiliki satu Ticket (1:1).
            // restrictOnDelete(): mencegah penghapusan Report jika Ticket masih ada.
            // Melindungi integritas audit trail operasional.
            $table->foreignId('report_id')
                  ->unique()
                  ->constrained('reports')
                  ->restrictOnDelete();                       // NOT NULL UNIQUE FK

            // Status canonical lifecycle (DEC-002).
            // CHECK constraint diterapkan via DB::statement di bawah (PostgreSQL).
            // Default: SUBMITTED — status awal saat Ticket pertama kali dibuat.
            $table->string('status')->default('SUBMITTED');   // NOT NULL DEFAULT 'SUBMITTED'

            $table->timestamps();                             // created_at, updated_at — NOT NULL
        });

        // CHECK constraint untuk status canonical lifecycle.
        DB::statement("
            ALTER TABLE tickets
            ADD CONSTRAINT chk_ticket_status
            CHECK (status IN ('SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS', 'CLOSED'))
        ");

        // INDEX pada tickets.status untuk query filtering triage.
        // Justifikasi: GET /api/admin/tickets dengan filter status adalah operasi frekuensi tinggi.
        DB::statement('CREATE INDEX idx_tickets_status ON tickets (status)');

        // INDEX pada tickets.report_id sudah tercakup oleh UNIQUE constraint di atas.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
