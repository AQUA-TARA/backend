<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: clusters table = Cluster / Centroid entity.
     * Menyimpan output spasial transient dari K-Means analysis (Decision C1).
     *
     * TIDAK ADA FK ke reports atau iot_measurements (Decision C1):
     *   - Tidak ada persistent Report → Cluster membership
     *   - Tidak ada persistent IoT → Cluster membership
     *   - Tidak ada cluster_membership junction table
     *
     * Cluster hanya dimiliki oleh ML Analysis (via analysis_id).
     * Admin Map hanya merender clusters dari latest analysis run.
     *
     * centroid_lat / centroid_lng: koordinat pusat dugaan masalah (K-Means output).
     * report_count: jumlah laporan yang membentuk cluster ini (metadata dari ML, nullable).
     *
     * Koordinat menggunakan DECIMAL, bukan PostGIS (§11 Geospatial Strategy).
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 clusters, §13 ML Data Model
     * Depends on: ml_analyses
     */
    public function up(): void
    {
        Schema::create('clusters', function (Blueprint $table) {
            $table->id();                                          // BIGINT UNSIGNED AUTO_INCREMENT PK

            // FK ke ml_analyses — run yang menghasilkan cluster ini.
            // restrictOnDelete: cluster tidak boleh ada tanpa analysis induknya.
            $table->foreignId('analysis_id')
                  ->constrained('ml_analyses')
                  ->restrictOnDelete();                             // NOT NULL FK

            // Koordinat sentroid pusat masalah (K-Means centroid output).
            // DECIMAL(10,8): latitude (-90 s/d +90)
            // DECIMAL(11,8): longitude (-180 s/d +180)
            $table->decimal('centroid_lat', 10, 8);               // NOT NULL
            $table->decimal('centroid_lng', 11, 8);               // NOT NULL

            // Jumlah laporan yang membentuk cluster ini.
            // NULLABLE: ML mungkin tidak selalu menyertakan metadata ini.
            $table->integer('report_count')->nullable();           // NULLABLE

            // Tidak ada timestamps — cluster adalah output analitik transient.
            // Waktu analisis sudah tercatat di ml_analyses.generated_at.

            // TIDAK ADA: FK ke reports, iot_measurements, atau membership table.
            // Cluster bersifat transient analytical output (Decision C1).
        });

        // INDEX pada analysis_id untuk query "semua Cluster dari Analysis X".
        // Justifikasi: Admin Map memanggil clusters dari latest analysis —
        // ini adalah operasi READ yang sering dilakukan saat polling map (DEC-013: 1 menit).
        \DB::statement('CREATE INDEX idx_clusters_analysis_id ON clusters (analysis_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clusters');
    }
};
