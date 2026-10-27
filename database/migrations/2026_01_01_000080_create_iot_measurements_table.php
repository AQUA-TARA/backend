<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: iot_measurements table = IoT Measurement entity.
     * Time-series pencatatan parameter metrik air dari setiap IoT Node.
     *
     * Parameter MVP (DEC-007): Turbidity, Pressure.
     * pH TIDAK wajib MVP — tidak ada kolom pH.
     *
     * Historical measurements TETAP disimpan — database tidak menghapus data lama.
     * ML hanya menggunakan measurement terbaru (Relevant Dataset 2C),
     * tetapi keputusan itu ada di lapisan aplikasi Laravel, bukan di constraint database.
     *
     * INDEX pada (node_id, measured_at): dibutuhkan untuk query
     * "Latest Measurement per Active Node" yang digunakan saat menyiapkan ML payload.
     * Justifikasi: query DISTINCT ON (node_id) ORDER BY measured_at DESC
     * adalah operasi paling kritis saat Laravel mengumpulkan Relevant Dataset.
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 iot_measurements, §10 Index Strategy, §14
     * Depends on: iot_nodes
     */
    public function up(): void
    {
        Schema::create('iot_measurements', function (Blueprint $table) {
            $table->id();                                         // BIGINT UNSIGNED AUTO_INCREMENT PK

            // FK ke iot_nodes.
            // restrictOnDelete: tidak boleh hapus node jika masih ada measurement.
            // Melindungi integritas time-series data.
            $table->foreignId('node_id')
                  ->constrained('iot_nodes')
                  ->restrictOnDelete();                            // NOT NULL FK

            // Parameter IoT MVP (DEC-007).
            // DECIMAL(8,2): cukup untuk nilai sensor fisik dengan 2 desimal.
            $table->decimal('turbidity', 8, 2);                   // NOT NULL — Kekeruhan (NTU)

            $table->decimal('pressure', 8, 2);                    // NOT NULL — Tekanan air

            // Waktu data diambil oleh sensor (bisa berbeda dari waktu ingestion ke server).
            $table->timestamp('measured_at')->useCurrent();       // NOT NULL DEFAULT CURRENT_TIMESTAMP
            // Tidak ada updated_at — measurement adalah immutable time-series record.
        });

        // Composite INDEX pada (node_id, measured_at) untuk query Latest Measurement per Node.
        // Justifikasi: SELECT DISTINCT ON (node_id) ... ORDER BY node_id, measured_at DESC
        // Tanpa index ini, query akan full table scan pada tabel yang bisa sangat besar.
        \DB::statement('CREATE INDEX idx_iot_measurements_node_time ON iot_measurements (node_id, measured_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_measurements');
    }
};
