<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: reports table = Report entity.
     * Menyimpan laporan keluhan warga beserta koordinat dan foto.
     *
     * citizen_id NULLABLE: mendukung Anonymous Reporting (DEC-005, DEC-006).
     * Anonymous report = citizen_id NULL, report tetap valid dan mendapatkan Ticket.
     *
     * photo_path menyimpan referensi path/URL ke storage eksternal,
     * bukan binary image. (Physical Design §16, DEC-014)
     *
     * Koordinat menggunakan DECIMAL, bukan PostGIS (§11 Geospatial Strategy).
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 reports
     * Depends on: citizens
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();                                     // BIGINT UNSIGNED AUTO_INCREMENT PK

            // FK ke citizens — NULLABLE karena Anonymous Report diizinkan (DEC-006).
            // nullOnDelete(): jika akun citizen dihapus, laporan tetap ada (citizen_id → NULL).
            // Ini melindungi integritas record publik (Physical Design §16).
            $table->foreignId('citizen_id')
                  ->nullable()
                  ->constrained('citizens')
                  ->nullOnDelete();                           // NULLABLE FK

            // Teks deskripsi keluhan warga.
            $table->text('description');                     // NOT NULL

            // Koordinat lokasi laporan (dari browser geolocation, DEC-015).
            // DECIMAL(10,8): latitude range -90.00000000 s/d +90.00000000 (~1.1mm precision)
            // DECIMAL(11,8): longitude range -180.00000000 s/d +180.00000000 (~1.1mm precision)
            $table->decimal('latitude', 10, 8);              // NOT NULL
            $table->decimal('longitude', 11, 8);             // NOT NULL

            // Referensi path/storage key foto laporan.
            // Menyimpan path URL, bukan binary. Provider storage belum dikunci (§18 OPEN).
            $table->string('photo_path');                    // NOT NULL

            // Waktu laporan diterima sistem.
            $table->timestamp('created_at')->useCurrent();   // NOT NULL DEFAULT CURRENT_TIMESTAMP
            // Tidak ada updated_at — report tidak dimodifikasi setelah dibuat.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
