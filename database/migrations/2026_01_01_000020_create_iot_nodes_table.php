<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: iot_nodes table = IoT Node entity.
     * hardware_id = identifier fisik alat (MAC address atau serial number).
     * Koordinat menggunakan DECIMAL, bukan PostGIS (keputusan geospatial strategy §11).
     * is_active menentukan apakah node diikutkan dalam Relevant Dataset ML (Decision 2C).
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 iot_nodes
     */
    public function up(): void
    {
        Schema::create('iot_nodes', function (Blueprint $table) {
            $table->id();                                        // BIGINT UNSIGNED AUTO_INCREMENT PK

            // Identifier fisik/hardware alat sensor di lapangan.
            // UNIQUE: setiap alat hanya terdaftar sekali.
            $table->string('hardware_id')->unique();             // NOT NULL UNIQUE

            // Koordinat lokasi fisik sensor.
            // DECIMAL(10,8): latitude (-90 s/d +90) dengan presisi ~1.1mm
            // DECIMAL(11,8): longitude (-180 s/d +180) dengan presisi ~1.1mm
            $table->decimal('latitude', 10, 8);                  // NOT NULL
            $table->decimal('longitude', 11, 8);                 // NOT NULL

            // Status operasional node. Hanya active node yang menjadi Relevant Dataset ML.
            // Default TRUE: node baru diasumsikan aktif saat didaftarkan.
            $table->boolean('is_active')->default(true);         // NOT NULL DEFAULT true

            // Timestamps tidak diperlukan secara eksplisit dalam physical design,
            // namun created_at berguna untuk audit kapan node didaftarkan.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_nodes');
    }
};
