<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: pipe_networks table.
     * Menyimpan data infrastruktur peta pipa untuk Admin Map.
     * geo_data menggunakan JSONB untuk GeoJSON Feature (Linestring/MultiLinestring).
     * Pipe network hanya dapat diakses Admin (DEC-011, DEC-012).
     *
     * JSONB dipilih karena Admin Map API mengembalikan GeoJSON FeatureCollection
     * secara langsung — database berfungsi sebagai passthrough tanpa parsing spasial.
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §12 Pipe Network Storage
     */
    public function up(): void
    {
        Schema::create('pipe_networks', function (Blueprint $table) {
            $table->id();                              // BIGINT UNSIGNED AUTO_INCREMENT PK

            // Nama identifikasi segmen pipa (opsional untuk labeling admin)
            $table->string('name');                    // NOT NULL

            // GeoJSON Feature disimpan dalam JSONB.
            // PostgreSQL JSONB mendukung indexing dan partial query jika diperlukan.
            // Laravel Blueprint: ->jsonb() tersedia di PostgreSQL driver.
            $table->jsonb('geo_data');                 // NOT NULL — GeoJSON Feature

            $table->timestamp('created_at')->useCurrent(); // Waktu data pipa ditambahkan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipe_networks');
    }
};
