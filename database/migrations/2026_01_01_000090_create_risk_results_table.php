<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: risk_results table = Risk Result entity.
     * Menyimpan Risk Score per Report yang dihasilkan dari satu ML Analysis run.
     *
     * HUBUNGAN: ML Analysis → Risk Result → Report (Decision A, B, Gate 1)
     *
     * TIDAK ADA UNIQUE(report_id): karena Multiple Physical Analysis Runs diizinkan (Gate 1).
     * Satu Report dapat memiliki banyak Risk Result dari analysis run yang berbeda.
     * Laravel menentukan "latest" via JOIN ke ml_analyses ORDER BY generated_at DESC (D1).
     *
     * risk_score: INTEGER range 0-100 (DEC-001).
     * risk_level TIDAK disimpan: diturunkan oleh application layer dari risk_score (§13 ML Data Model).
     *
     * INDEX pada report_id: dibutuhkan untuk query "Risk Score untuk Report X"
     * dari Triage dan Admin Map.
     * INDEX pada analysis_id: dibutuhkan untuk query "semua Risk Results dari Analysis Y"
     * saat Laravel memuat analytical state dari latest run.
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 risk_results, §9 Constraints, §13 ML Data Model
     * Depends on: ml_analyses, reports
     */
    public function up(): void
    {
        Schema::create('risk_results', function (Blueprint $table) {
            $table->id();                                         // BIGINT UNSIGNED AUTO_INCREMENT PK

            // FK ke ml_analyses — run ML yang menghasilkan skor ini.
            // restrictOnDelete: tidak boleh hapus analysis jika masih ada risk results.
            $table->foreignId('analysis_id')
                  ->constrained('ml_analyses')
                  ->restrictOnDelete();                            // NOT NULL FK

            // FK ke reports — target Report yang dievaluasi (Decision A, Gate 1).
            // restrictOnDelete: tidak boleh hapus report jika masih ada risk results.
            $table->foreignId('report_id')
                  ->constrained('reports')
                  ->restrictOnDelete();                            // NOT NULL FK

            // Nilai Risk Score 0-100 (DEC-001).
            // INTEGER: cukup untuk nilai bulat 0-100.
            // CHECK constraint ditambahkan via DB::statement di bawah.
            $table->integer('risk_score');                        // NOT NULL

            // risk_level TIDAK ada: diturunkan oleh Laravel berdasarkan risk_score:
            //   0-30  = Jernih
            //   31-70 = Waspada
            //   71-100 = Bahaya

            // Tidak ada timestamps — risk_result adalah output analitik, bukan entitas operasional.
            // Waktu analysis sudah tercatat di ml_analyses.generated_at.
        });

        // CHECK constraint untuk risk_score range 0-100 (DEC-001).
        \DB::statement('
            ALTER TABLE risk_results
            ADD CONSTRAINT chk_risk_score_range
            CHECK (risk_score >= 0 AND risk_score <= 100)
        ');

        // INDEX pada report_id untuk query "Risk Score untuk Report X" dari Triage & Map.
        // Justifikasi: setiap tampilan triage detail memanggil risk score via report_id.
        // TIDAK UNIQUE — multiple runs boleh menghasilkan multiple risk results per report.
        \DB::statement('CREATE INDEX idx_risk_results_report_id ON risk_results (report_id)');

        // INDEX pada analysis_id untuk query "semua Risk Results dari Analysis Y".
        // Justifikasi: saat Laravel mengambil latest analysis, ia memuat semua
        // risk_results untuk analysis tersebut.
        \DB::statement('CREATE INDEX idx_risk_results_analysis_id ON risk_results (analysis_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_results');
    }
};
