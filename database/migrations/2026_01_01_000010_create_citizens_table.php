<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: citizens table = Citizen entity.
     * Citizen membership is OPTIONAL for reporting (DEC-005, DEC-006).
     * Anonymous reporters do NOT have a record here — reports.citizen_id will be NULL.
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 citizens
     */
    public function up(): void
    {
        Schema::create('citizens', function (Blueprint $table) {
            $table->id();                                    // BIGINT UNSIGNED AUTO_INCREMENT PK

            // Nomor telepon/WhatsApp sebagai identifier login warga.
            // UNIQUE: satu nomor = satu akun warga.
            $table->string('phone_number')->unique();        // NOT NULL UNIQUE

            $table->timestamp('created_at')->useCurrent(); // Waktu pendaftaran warga
            // Tidak ada updated_at — citizens hanya dibuat, bukan diperbarui secara rutin.
            // Jika diperlukan di masa mendatang, dapat ditambah via migration baru.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizens');
    }
};
