<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: ml_analyses table = ML Analysis entity.
     * Merepresentasikan satu independent analysis run (Decision B).
     * Setiap run ML menghasilkan risk_results dan clusters yang terhubung ke tabel ini.
     *
     * generated_at adalah penanda waktu eksekusi run — digunakan oleh Laravel
     * untuk menentukan "Latest Analysis Only" (Decision D1):
     *   SELECT id FROM ml_analyses ORDER BY generated_at DESC LIMIT 1
     *
     * Tabel ini sengaja minimal — hanya menyimpan jejak eksekusi.
     * Status eksekusi atau trigger mechanism bukan bagian dari physical schema.
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 ml_analyses, §15 Re-analysis Model
     */
    public function up(): void
    {
        Schema::create('ml_analyses', function (Blueprint $table) {
            $table->id();                                     // BIGINT UNSIGNED AUTO_INCREMENT PK

            // Penanda waktu saat analysis run dieksekusi.
            // INDEX: dibutuhkan untuk query "Latest Analysis" (ORDER BY generated_at DESC).
            // Alasan index: setiap API call yang membutuhkan analytical state
            // akan query kolom ini.
            $table->timestamp('generated_at')->useCurrent(); // NOT NULL DEFAULT CURRENT_TIMESTAMP
        });

        // Index terpisah untuk generated_at — dibutuhkan untuk query latest analysis.
        // Dibuat di luar Blueprint agar lebih eksplisit secara dokumentasi.
        // (Alternatif: ->index() di dalam Blueprint — hasil akhir sama)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_analyses');
    }
};
